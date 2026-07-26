<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Database;
use app\Core\PostValidator;
use app\Models\Category;
use app\Models\Media;
use app\Models\Post;
use app\Models\PostRevision;
use app\Models\User;
use Throwable;

class PostController extends Controller
{
    /*
     * 投稿一覧
     *
     * 検索・絞り込み・ゴミ箱・
     * ページ分割へ対応
     */
    public function index(): void
    {
        Auth::requireLogin();

        $filters = $this->adminFilters();

        $perPage = 20;

        $totalPosts =
            Post::countAdmin($filters);

        $totalPages = max(
            1,
            (int)ceil(
                $totalPosts / $perPage
            )
        );

        $currentPage = max(
            1,
            (int)($_GET['page'] ?? 1)
        );

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $posts = Post::adminPage(
            $filters,
            $currentPage,
            $perPage
        );

        $this->view('admin/posts', [
            'posts' => $posts,

            'categories' =>
                Category::all(),

            'authors' =>
                User::all(),

            'filters' =>
                $filters,

            'pagination' => [
                'current_page' =>
                    $currentPage,

                'total_pages' =>
                    $totalPages,

                'total_items' =>
                    $totalPosts,

                'per_page' =>
                    $perPage,
            ],

            'notice' =>
                $this->pullNotice(),
        ]);
    }

    /*
     * 新規投稿画面
     */
    public function create(): void
    {
        Auth::requireLogin();

        $this->renderForm(
            null,
            url('admin/posts/store')
        );
    }

    /*
     * 投稿を新規保存
     */
    public function store(): void
    {
        Auth::requireLogin();

        $this->requireCsrf();

        $validation =
            PostValidator::validate(
                $_POST
            );

        if (!$validation['valid']) {
            $this->renderForm(
                $validation['data'],
                url('admin/posts/store'),
                $validation['errors']
            );

            return;
        }

        $user = Auth::user();

        $userId =
            (int)($user['id'] ?? 0);

        if ($userId <= 0) {
            Auth::logout();
            redirect_to('admin/login');
        }

        try {
            $data =
                $validation['data'];

            $data['user_id'] =
                $userId;

            Post::create($data);

            $this->setNotice(
                '投稿を保存しました。'
            );

            $this->redirectToList();
        } catch (Throwable $exception) {
            $this->renderForm(
                $validation['data'],
                url('admin/posts/store'),
                [
                    'general' =>
                        '投稿を保存できませんでした。'
                        . '入力内容を確認して'
                        . 'もう一度お試しください。',
                ]
            );
        }
    }

    /*
     * 投稿編集画面
     */
    public function edit(
        int $id
    ): void {
        Auth::requireLogin();

        $post = Post::find($id);

        if (
            !$post ||
            !empty($post['deleted_at'])
        ) {
            $this->notFound();

            return;
        }

        $this->renderForm(
            $post,
            url(
                'admin/posts/update/'
                . $id
            )
        );
    }

    /*
     * 投稿を更新
     */
    public function update(
        int $id
    ): void {
        Auth::requireLogin();

        $this->requireCsrf();

        $post = Post::find($id);

        if (
            !$post ||
            !empty($post['deleted_at'])
        ) {
            $this->notFound();

            return;
        }

        $validation =
            PostValidator::validate(
                $_POST,
                $id
            );

        if (!$validation['valid']) {
            $formPost = array_merge(
                $validation['data'],
                [
                    'id' => $id,
                    'created_at' =>
                        $post['created_at']
                        ?? null,
                    'updated_at' =>
                        $post['updated_at']
                        ?? null,
                    'published_at' =>
                        $post['published_at']
                        ?? null,
                ]
            );

            $this->renderForm(
                $formPost,
                url(
                    'admin/posts/update/'
                    . $id
                ),
                $validation['errors']
            );

            return;
        }

        /*
         * 内容に変更がない場合は
         * 履歴を増やさない
         */
        if (
            !$this->hasChanges(
                $post,
                $validation['data']
            )
        ) {
            $this->setNotice(
                '変更内容はありませんでした。'
            );

            $this->redirectToList();
        }

        $user = Auth::user();

        $userId =
            (int)($user['id'] ?? 0);

        if ($userId <= 0) {
            Auth::logout();
            redirect_to('admin/login');
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            /*
             * 更新前の内容を編集履歴へ保存
             */
            PostRevision::createFromPost(
                $post,
                $userId
            );

            /*
             * 新しい内容を投稿へ反映
             */
            Post::update(
                $id,
                $validation['data']
            );

            $pdo->commit();

            $this->setNotice(
                '投稿を更新しました。'
            );

            $this->redirectToList();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $formPost = array_merge(
                $validation['data'],
                [
                    'id' => $id,
                    'created_at' =>
                        $post['created_at']
                        ?? null,
                    'updated_at' =>
                        $post['updated_at']
                        ?? null,
                    'published_at' =>
                        $post['published_at']
                        ?? null,
                ]
            );

            $this->renderForm(
                $formPost,
                url(
                    'admin/posts/update/'
                    . $id
                ),
                [
                    'general' =>
                        '投稿を更新できませんでした。'
                        . '時間を空けて'
                        . 'もう一度お試しください。',
                ]
            );
        }
    }

    /*
     * 下書き・公開前プレビュー
     */
    public function preview(
        int $id
    ): void {
        Auth::requireLogin();

        $post =
            Post::findPreview($id);

        if (!$post) {
            $this->notFound();

            return;
        }

        /*
         * プレビューをブラウザや
         * プロキシへ保存させない
         */
        header(
            'Cache-Control: no-store, no-cache, '
            . 'must-revalidate, max-age=0'
        );

        header('Pragma: no-cache');

        header(
            'X-Robots-Tag: noindex, nofollow'
        );

        $post['is_preview'] = true;

        $this->view(
            'post-detail',
            [
                'post' => $post,
                'isPreview' => true,
            ]
        );
    }

    /*
     * 投稿をゴミ箱へ移動
     */
    public function delete(
        int $id
    ): void {
        Auth::requireLogin();

        $this->requireCsrf();

        $post = Post::find($id);

        if (
            !$post ||
            !empty($post['deleted_at'])
        ) {
            $this->notFound();

            return;
        }

        Post::delete($id);

        $this->setNotice(
            '投稿をゴミ箱へ移動しました。'
        );

        $this->redirectToList();
    }

    /*
     * ゴミ箱から投稿を復元
     */
    public function restore(
        int $id
    ): void {
        Auth::requireLogin();

        $this->requireCsrf();

        $post = Post::find($id);

        if (
            !$post ||
            empty($post['deleted_at'])
        ) {
            $this->notFound();

            return;
        }

        Post::restore($id);

        $this->setNotice(
            '投稿を復元しました。'
        );

        $this->redirectToList(true);
    }

    /*
     * ゴミ箱内の投稿を完全削除
     */
    public function destroy(
        int $id
    ): void {
        Auth::requireLogin();

        $this->requireCsrf();

        $post = Post::find($id);

        if (
            !$post ||
            empty($post['deleted_at'])
        ) {
            $this->notFound();

            return;
        }

        Post::destroy($id);

        $this->setNotice(
            '投稿を完全に削除しました。'
        );

        $this->redirectToList(true);
    }

    /*
     * 公開・下書きを切り替え
     */
    public function status(
        int $id
    ): void {
        Auth::requireLogin();

        $this->requireCsrf();

        $post = Post::find($id);

        if (
            !$post ||
            !empty($post['deleted_at'])
        ) {
            $this->notFound();

            return;
        }

        Post::toggleStatus($id);

        $message =
            ($post['status'] ?? '')
            === 'published'
                ? '投稿を下書きに変更しました。'
                : '投稿を公開しました。';

        $this->setNotice($message);

        $this->redirectToList();
    }

    /*
     * 投稿フォームを表示
     */
    private function renderForm(
        ?array $post,
        string $action,
        array $errors = []
    ): void {
        $this->view(
            'admin/post-form',
            [
                'post' => $post,

                'categories' =>
                    Category::all(),

                'featuredMediaItems' =>
                    Media::images(),

                'action' =>
                    $action,

                'errors' =>
                    $errors,
            ]
        );
    }

    /*
     * 投稿一覧の検索条件を整理
     */
    private function adminFilters(): array
    {
        $status = strtolower(
            trim(
                (string)(
                    $_GET['status']
                    ?? ''
                )
            )
        );

        if (
            !in_array(
                $status,
                [
                    '',
                    'draft',
                    'published',
                ],
                true
            )
        ) {
            $status = '';
        }

        return [
            'keyword' =>
                trim(
                    (string)(
                        $_GET['keyword']
                        ?? ''
                    )
                ),

            'status' =>
                $status,

            'category_id' =>
                max(
                    0,
                    (int)(
                        $_GET['category_id']
                        ?? 0
                    )
                ),

            'author_id' =>
                max(
                    0,
                    (int)(
                        $_GET['author_id']
                        ?? 0
                    )
                ),

            'trash' =>
                (
                    $_GET['view']
                    ?? ''
                ) === 'trash',
        ];
    }

    /*
     * CSRFトークンを検証
     */
    private function requireCsrf(): void
    {
        Csrf::requireValid(
            $_POST['_csrf_token']
            ?? null
        );
    }

    /*
     * 投稿に変更があるか確認
     */
    private function hasChanges(
        array $post,
        array $data
    ): bool {
        $textFields = [
            'title',
            'slug',
            'excerpt',
            'content',
            'status',
        ];

        foreach ($textFields as $field) {
            if (
                (string)($post[$field] ?? '')
                !==
                (string)($data[$field] ?? '')
            ) {
                return true;
            }
        }

        $postCategoryId =
            !empty($post['category_id'])
                ? (int)$post['category_id']
                : null;

        $newCategoryId =
            !empty($data['category_id'])
                ? (int)$data['category_id']
                : null;

        if (
            $postCategoryId
            !== $newCategoryId
        ) {
            return true;
        }

        $postFeaturedMediaId =
            !empty(
                $post['featured_media_id']
            )
                ? (int)$post[
                    'featured_media_id'
                ]
                : null;

        $newFeaturedMediaId =
            !empty(
                $data['featured_media_id']
            )
                ? (int)$data[
                    'featured_media_id'
                ]
                : null;

        return
            $postFeaturedMediaId
            !== $newFeaturedMediaId;
    }

    /*
     * 投稿一覧へ通知を保存
     */
    private function setNotice(
        string $message,
        string $type = 'success'
    ): void {
        $_SESSION['post_notice'] = [
            'message' => $message,
            'type' => $type,
        ];
    }

    /*
     * 投稿一覧の通知を取得して削除
     */
    private function pullNotice(): ?array
    {
        $notice =
            $_SESSION['post_notice']
            ?? null;

        unset($_SESSION['post_notice']);

        return is_array($notice)
            ? $notice
            : null;
    }

    /*
     * 投稿一覧へ戻る
     */
    private function redirectToList(
        bool $trash = false
    ): void {
        redirect_to(
            $trash
                ? 'admin/posts?view=trash'
                : 'admin/posts'
        );
    }

    /*
     * 投稿が存在しない場合
     */
    private function notFound(): void
    {
        http_response_code(404);

        echo '投稿が見つかりません。';
    }
}