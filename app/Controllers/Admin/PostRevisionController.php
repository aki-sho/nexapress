<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Database;
use app\Core\PostValidator;
use app\Models\Post;
use app\Models\PostRevision;
use Throwable;

/*
 * 投稿編集履歴の表示・復元
 */
class PostRevisionController extends Controller
{
    /*
     * 投稿の編集履歴一覧
     */
    public function index(
        int $postId
    ): void {
        Auth::requireLogin();

        $post = Post::find($postId);

        if (
            !$post ||
            !empty($post['deleted_at'])
        ) {
            http_response_code(404);
            echo '投稿が見つかりません。';

            return;
        }

        $revisions =
            PostRevision::allForPost(
                $postId
            );

        $notice =
            $_SESSION[
                'post_revision_notice'
            ] ?? null;

        unset(
            $_SESSION[
                'post_revision_notice'
            ]
        );

        $this->view(
            'admin/post-revisions',
            [
                'post' => $post,
                'revisions' => $revisions,
                'notice' => $notice,
            ]
        );
    }

    /*
     * 選択した編集履歴を復元
     */
    public function restore(
        int $postId,
        int $revisionId
    ): void {
        Auth::requireLogin();

        Csrf::requireValid(
            $_POST['_csrf_token']
            ?? null
        );

        $post = Post::find($postId);

        if (
            !$post ||
            !empty($post['deleted_at'])
        ) {
            http_response_code(404);
            echo '投稿が見つかりません。';

            return;
        }

        $revision =
            PostRevision::findForPost(
                $revisionId,
                $postId
            );

        if (!$revision) {
            http_response_code(404);
            echo '編集履歴が見つかりません。';

            return;
        }

        /*
         * 復元する履歴も現在の投稿と同じ
         * 入力検証を通す
         */
        $validation =
            PostValidator::validate(
                [
                    'title' =>
                        $revision['title']
                        ?? '',

                    'slug' =>
                        $revision['slug']
                        ?? '',

                    'excerpt' =>
                        $revision['excerpt']
                        ?? '',

                    'content' =>
                        $revision['content']
                        ?? '',

                    'status' =>
                        $revision['status']
                        ?? 'draft',

                    'category_id' =>
                        $revision['category_id']
                        ?? null,

                    'featured_media_id' =>
                        $revision[
                            'featured_media_id'
                        ] ?? null,
                ],
                $postId
            );

        if (!$validation['valid']) {
            $errorMessages =
                array_values(
                    $validation['errors']
                );

            $this->setNotice(
                '編集履歴を復元できませんでした。'
                . ' '
                . implode(
                    ' ',
                    $errorMessages
                ),
                'error'
            );

            $this->redirectToHistory(
                $postId
            );
        }

        $user = Auth::user();

        $editorUserId =
            (int)($user['id'] ?? 0);

        if ($editorUserId <= 0) {
            Auth::logout();
            redirect_to('admin/login');
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            /*
             * 復元前の現在内容も履歴へ残す
             */
            PostRevision::createFromPost(
                $post,
                $editorUserId
            );

            /*
             * 選択した履歴の内容を投稿へ反映
             */
            Post::update(
                $postId,
                $validation['data']
            );

            $pdo->commit();

            $this->setNotice(
                '選択した編集履歴を復元しました。',
                'success'
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->setNotice(
                '編集履歴の復元中に'
                . 'エラーが発生しました。',
                'error'
            );
        }

        $this->redirectToHistory(
            $postId
        );
    }

    /*
     * 履歴画面へ表示するメッセージを保存
     */
    private function setNotice(
        string $message,
        string $type
    ): void {
        $_SESSION[
            'post_revision_notice'
        ] = [
            'message' => $message,
            'type' => $type,
        ];
    }

    /*
     * 投稿編集履歴へ戻る
     */
    private function redirectToHistory(
        int $postId
    ): void {
        redirect_to(
            'admin/posts/revisions/'
            . $postId
        );
    }
}