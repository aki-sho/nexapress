<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\AdminTableSort;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Database;
use app\Core\PageLayout;
use app\Core\PostValidator;
use InvalidArgumentException;
use app\Core\RequestSize;
use app\Models\Category;
use app\Models\ContentBulk;
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
        $sort = Post::adminSort($_GET, $filters['trash']);

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
            $perPage,
            $sort
        );

        $this->view('admin/posts', [
            'posts' => $posts,

            'categories' =>
                Category::all(),

            'authors' =>
                User::all(),

            'filters' =>
                $filters,

            'sort' => $sort,
            'listParameters' => $this->listParameters($filters, $sort),

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

    public function bulk(): void
    {
        $filters = $this->adminFilters();
        $result = ContentBulk::run('posts', $filters['trash'], $_POST);
        $this->setNotice($result['message'], $result['type']);
        $this->redirectToList($filters['trash']);
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

        RequestSize::requirePostWithinLimit();
        $this->requireCsrf();

        $validation =
            PostValidator::validate(
                $_POST
            );
        $display = $this->validateDisplay($validation);

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

        $pdo = Database::connect();
        try {
            $data = $validation['data'] + ['user_id' => $userId];
            $pdo->beginTransaction();
            $id = Post::create($data);
            PageLayout::saveEditorDisplay($id, 'post', $display);
            $pdo->commit();

            nx_editor_saved();

            $this->setNotice(
                '投稿を保存しました。'
            );

            $this->redirectToList();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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

        RequestSize::requirePostWithinLimit();
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
        $display = $this->validateDisplay($validation);

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

        $contentChanged = $this->hasChanges($post, $validation['data']);

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

            if ($contentChanged) {
                PostRevision::createFromPost($post, $userId);
                Post::update($id, $validation['data']);
            }
            $displayChanged = PageLayout::saveEditorDisplay($id, 'post', $display);
            $pdo->commit();

            nx_editor_saved();

            $this->setNotice($contentChanged || $displayChanged
                ? '投稿を更新しました。' : '変更内容はありませんでした。');

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

    public function editorPreview(int $id = 0): void
    {
        RequestSize::requirePostWithinLimit();
        $this->requireCsrf();
        $post = $id === 0 ? ['id' => 0, 'status' => 'draft'] : Post::findPreview($id);
        if ($post === null) {
            $this->notFound();
            return;
        }
        \app\Core\EditorPreview::render('post', $post, $_POST);
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

    private function validateDisplay(array &$validation): array
    {
        try {
            return PageLayout::editorChanges($_POST['page_display'] ?? null, 'post');
        } catch (InvalidArgumentException $exception) {
            $validation['valid'] = false;
            $validation['errors']['general'] = $exception->getMessage();
            return [];
        }
    }

    /*
     * 投稿一覧の検索条件を整理
     */
    private function adminFilters(): array
    {
        $statusInput = $_GET['status'] ?? '';
        $status = is_string($statusInput) ? strtolower(trim($statusInput)) : '';
        $keyword = $_GET['keyword'] ?? '';

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
                is_string($keyword) ? trim($keyword) : '',

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
        $filters = $this->adminFilters();
        $filters['trash'] = $trash;
        $parameters = $this->listParameters($filters, Post::adminSort($_GET, $trash));
        $page = filter_var($_GET['page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);
        if ($page !== false) {
            $parameters['page'] = $page;
        }
        redirect_to('admin/posts' . ($parameters ? '?' . http_build_query($parameters) : ''));
    }

    private function listParameters(array $filters, AdminTableSort $sort): array
    {
        return array_filter([
            'keyword' => $filters['keyword'],
            'status' => $filters['status'],
            'category_id' => $filters['category_id'],
            'author_id' => $filters['author_id'],
            'view' => $filters['trash'] ? 'trash' : '',
        ], static fn($value) => $value !== '' && $value !== 0) + $sort->parameters();
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
