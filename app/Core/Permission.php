<?php

namespace app\Core;

class Permission
{
    private static ?array $roles = null;
    private static ?array $currentUser = null;
    private static bool $currentUserLoaded = false;
    /*
     * 権限定義を取得
     */
    public static function roles(): array
    {
        if (self::$roles !== null) {
            return self::$roles;
        }

        $path = BASE_PATH
            . '/app/Config/roles.php';

        if (!file_exists($path)) {
            self::$roles = [];

            return self::$roles;
        }

        $roles = require $path;

        self::$roles = is_array($roles)
            ? $roles
            : [];

        return self::$roles;
    }

    /*
     * 権限が存在するか確認
     */
    public static function can(
        string $permission,
        ?array $user = null
    ): bool {
        $user = $user ?? self::currentUser();

        if (!$user) {
            return false;
        }

        $role = $user['role'] ?? '';
        $roles = self::roles();

        $permissions =
            $roles[$role]['permissions']
            ?? [];

        return
            in_array('*', $permissions, true) ||
            in_array(
                $permission,
                $permissions,
                true
            );
    }

    /*
     * 権限名を取得
     */
    public static function roleLabel(
        string $role
    ): string {
        $roles = self::roles();

        return $roles[$role]['label']
            ?? $role;
    }

    /*
    * 権限がなければ処理を停止
    */
    public static function require(
        string $permission
    ): void {
        Auth::requireLogin();

        if (self::can($permission)) {
            return;
        }

        self::deny();
    }

    /*
    * 実行する管理画面処理の権限を確認
    */
    public static function authorizeAction(
        string $controllerName,
        string $methodName,
        array $params = []
    ): void {
        $adminPrefix =
            'app\\Controllers\\Admin\\';

        if (
            !str_starts_with(
                $controllerName,
                $adminPrefix
            )
        ) {
            return;
        }

        /*
        * ログイン処理は権限確認の対象外
        */
        if (
            $controllerName ===
            $adminPrefix . 'AuthController'
        ) {
            return;
        }

        Auth::requireLogin();

        /*
        * 投稿は所有者によって権限を分ける
        */
        if (
            $controllerName ===
            $adminPrefix . 'PostController'
        ) {
            self::authorizePostAction(
                $methodName,
                $params
            );

            return;
        }

        /*
        * 投稿編集履歴も
        * 投稿所有者によって権限を分ける
        */
        if (
            $controllerName ===
            $adminPrefix
            . 'PostRevisionController'
        ) {
            self::authorizePostRevisionAction(
                $methodName,
                $params
            );

            return;
        }


        $permissions = [
            $adminPrefix . 'DashboardController' =>
                'dashboard.view',

            $adminPrefix . 'CategoryController' =>
                'categories.manage',

            $adminPrefix . 'MediaController' =>
                'media.manage',

            $adminPrefix . 'PageController' =>
                'pages.manage',

            $adminPrefix . 'UserController' =>
                'users.manage',

            $adminPrefix . 'UpdateController' =>
                'updates.manage',

            $adminPrefix . 'ExtensionController' =>
                'extensions.manage',

            $adminPrefix . 'ThemeController' =>
                'themes.manage',

            $adminPrefix . 'SettingController' =>
                'settings.manage',
        ];

        $permission =
            $permissions[$controllerName]
            ?? 'admin.manage';

        self::require($permission);
    }

    /*
    * 投稿操作の権限を確認
    */
    private static function authorizePostAction(
        string $methodName,
        array $params
    ): void {
        if ($methodName === 'index') {
            self::require('posts.view');

            return;
        }

        if ($methodName === 'create') {
            self::require('posts.create');

            return;
        }

        if ($methodName === 'store') {
            self::require('posts.create');

            if (
                ($_POST['status'] ?? 'draft')
                === 'published'
            ) {
                self::requireAny([
                    'posts.publish',
                    'posts.publish_own',
                ]);
            }

            return;
        }

        $postId = (int) ($params[0] ?? 0);

        $post = \app\Models\Post::find(
            $postId
        );

        /*
        * 存在しない場合はController側で
        * 404として処理する
        */
        if (!$post) {
            return;
        }

        if (
            in_array(
                $methodName,
                [
                    'edit',
                    'update',
                    'preview',
                ],
                true
            )
        ) {
            self::requirePostOwnerPermission(
                $post,
                'posts.edit_any',
                'posts.edit_own'
            );

            if (
                $methodName === 'update' &&
                ($_POST['status'] ?? 'draft')
                    === 'published'
            ) {
                self::requirePostPublishPermission(
                    $post
                );
            }

            return;
        }

        if (
            in_array(
                $methodName,
                [
                    'delete',
                    'restore',
                    'destroy',
                ],
                true
            )
        ) {
            self::requirePostOwnerPermission(
                $post,
                'posts.delete_any',
                'posts.delete_own'
            );

            return;
        }

        if ($methodName === 'status') {
            self::requirePostPublishPermission(
                $post
            );

            return;
        }

        self::require('admin.manage');
    }

    /*
    * 投稿編集履歴の権限を確認
    */
    private static function authorizePostRevisionAction(
        string $methodName,
        array $params
    ): void {
        $postId = (int)(
            $params[0]
            ?? 0
        );

        $post = \app\Models\Post::find(
            $postId
        );

        /*
        * 存在しない投稿は
        * Controller側で404にする
        */
        if (!$post) {
            return;
        }

        /*
        * 履歴表示・履歴復元には
        * 投稿の編集権限が必要
        */
        if (
            in_array(
                $methodName,
                [
                    'index',
                    'restore',
                ],
                true
            )
        ) {
            self::requirePostOwnerPermission(
                $post,
                'posts.edit_any',
                'posts.edit_own'
            );
        } else {
            self::require('admin.manage');

            return;
        }

        if ($methodName === 'index') {
            return;
        }

        /*
        * 履歴復元時は
        * 復元対象の履歴を確認
        */
        $revisionId = (int)(
            $params[1]
            ?? 0
        );

        $revision =
            \app\Models\PostRevision::findForPost(
                $revisionId,
                $postId
            );

        /*
        * 存在しない履歴は
        * Controller側で404にする
        */
        if (!$revision) {
            return;
        }

        /*
        * 公開状態の履歴を復元する場合は
        * 公開権限も必要
        */
        if (
            ($revision['status'] ?? 'draft')
            === 'published'
        ) {
            self::requirePostPublishPermission(
                $post
            );
        }
    }

    /*
    * 複数権限のいずれかを確認
    */
    private static function requireAny(
        array $permissions
    ): void {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return;
            }
        }

        self::deny();
    }

    /*
    * 投稿所有者を含めて権限を確認
    */
    private static function requirePostOwnerPermission(
        array $post,
        string $anyPermission,
        string $ownPermission
    ): void {
        if (self::can($anyPermission)) {
            return;
        }

        $user = Auth::user();

        $isOwner =
            (int) ($post['user_id'] ?? 0)
            ===
            (int) ($user['id'] ?? 0);

        if (
            $isOwner &&
            self::can($ownPermission)
        ) {
            return;
        }

        self::deny();
    }

    /*
    * 投稿公開権限を確認
    */
    private static function requirePostPublishPermission(
        array $post
    ): void {
        if (self::can('posts.publish')) {
            return;
        }

        $user = Auth::user();

        $isOwner =
            (int) ($post['user_id'] ?? 0)
            ===
            (int) ($user['id'] ?? 0);

        if (
            $isOwner &&
            self::can('posts.publish_own')
        ) {
            return;
        }

        self::deny();
    }

    /*
    * 403画面を表示
    */
    private static function deny(): void
    {
        http_response_code(403);

        View::render(
            'admin/forbidden',
            [
                'title' =>
                    'アクセス権限がありません',
            ]
        );

        exit;
    }

    /*
    * DBから最新のログインユーザーを取得
    */
    private static function currentUser(): ?array
    {
        if (self::$currentUserLoaded) {
            return self::$currentUser;
        }

        self::$currentUserLoaded = true;

        $sessionUser = Auth::user();

        if (
            !$sessionUser ||
            empty($sessionUser['id'])
        ) {
            return null;
        }

        self::$currentUser =
            \app\Models\User::find(
                (int) $sessionUser['id']
            );

        return self::$currentUser;
    }
}