<?php

use app\Core\Csrf;

$title = '投稿編集履歴';

$postId =
    (int)($post['id'] ?? 0);

$postTitle =
    (string)($post['title'] ?? '');

$csrfToken = Csrf::token();

$statusLabels = [
    'draft' => '下書き',
    'published' => '公開',
];

/*
 * 日時の表示形式
 */
$formatDate = static function (
    mixed $date
): string {
    $date = trim((string)$date);

    if ($date === '') {
        return '—';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date(
        'Y/m/d H:i:s',
        $timestamp
    );
};

/*
 * 本文の短いプレビュー
 */
$contentPreview = static function (
    mixed $content
): string {
    $text = html_entity_decode(
        strip_tags((string)$content),
        ENT_QUOTES |
        ENT_HTML5,
        'UTF-8'
    );

    $text = preg_replace(
        '/\s+/u',
        ' ',
        $text
    ) ?? '';

    $text = trim($text);

    if ($text === '') {
        return '本文なし';
    }

    if (function_exists('mb_substr')) {
        $preview = mb_substr(
            $text,
            0,
            180,
            'UTF-8'
        );

        if (
            mb_strlen(
                $text,
                'UTF-8'
            ) > 180
        ) {
            $preview .= '…';
        }

        return $preview;
    }

    $preview = substr(
        $text,
        0,
        180
    );

    if (strlen($text) > 180) {
        $preview .= '…';
    }

    return $preview;
};
?>

<section class="admin-page post-revisions-page">
    <!-- ページ上部 -->
    <div class="admin-page-header">
        <div>
            <h1>投稿編集履歴</h1>

            <p>
                「<?= e($postTitle) ?>」の
                保存済み履歴です。
            </p>
        </div>

        <div class="table-actions">
            <a
                class="button secondary"
                href="<?= url(
                    'admin/posts/edit/'
                    . $postId
                ) ?>"
            >
                投稿編集へ戻る
            </a>

            <a
                class="button secondary"
                href="<?= url('admin/posts') ?>"
            >
                投稿一覧へ戻る
            </a>
        </div>
    </div>

    <!-- 復元結果 -->
    <?php if (is_array($notice ?? null)): ?>
        <?php
        $noticeType =
            ($notice['type'] ?? '') === 'error'
                ? 'error'
                : 'success';
        ?>

        <div
            class="<?= $noticeType === 'error'
                ? 'error-message'
                : 'admin-card' ?>"
        >
            <?= e(
                (string)(
                    $notice['message']
                    ?? ''
                )
            ) ?>
        </div>
    <?php endif; ?>

    <!-- 現在の記事情報 -->
    <div class="admin-card post-current-summary">
        <h2>現在の記事</h2>

        <dl>
            <div>
                <dt>タイトル</dt>
                <dd><?= e($postTitle) ?></dd>
            </div>

            <div>
                <dt>スラッグ</dt>
                <dd>
                    <code>
                        <?= e(
                            (string)(
                                $post['slug']
                                ?? ''
                            )
                        ) ?>
                    </code>
                </dd>
            </div>

            <div>
                <dt>公開状態</dt>
                <dd>
                    <?= e(
                        $statusLabels[
                            $post['status']
                            ?? ''
                        ] ?? (
                            $post['status']
                            ?? '不明'
                        )
                    ) ?>
                </dd>
            </div>

            <div>
                <dt>最終更新</dt>
                <dd>
                    <?= e(
                        $formatDate(
                            $post['updated_at']
                            ?? $post['created_at']
                            ?? ''
                        )
                    ) ?>
                </dd>
            </div>
        </dl>
    </div>

    <!-- 履歴一覧 -->
    <?php if (empty($revisions)): ?>
        <div class="admin-card">
            <p>
                編集履歴はまだありません。
            </p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>保存日時</th>
                        <th>編集者</th>
                        <th>タイトル</th>
                        <th>状態</th>
                        <th>カテゴリ</th>
                        <th>内容</th>
                        <th>操作</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        $revisions
                        as $revision
                    ): ?>
                        <?php
                        $revisionId =
                            (int)(
                                $revision['id']
                                ?? 0
                            );

                        $revisionStatus =
                            (string)(
                                $revision['status']
                                ?? 'draft'
                            );
                        ?>

                        <tr>
                            <td>
                                <?= e(
                                    $formatDate(
                                        $revision[
                                            'created_at'
                                        ] ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    (string)(
                                        $revision[
                                            'editor_name'
                                        ] ?? '不明'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= e(
                                        (string)(
                                            $revision[
                                                'title'
                                            ] ?? ''
                                        )
                                    ) ?>
                                </strong>

                                <br>

                                <small>
                                    <?= e(
                                        (string)(
                                            $revision[
                                                'slug'
                                            ] ?? ''
                                        )
                                    ) ?>
                                </small>
                            </td>

                            <td>
                                <?= e(
                                    $statusLabels[
                                        $revisionStatus
                                    ] ?? $revisionStatus
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    (string)(
                                        $revision[
                                            'category_name'
                                        ] ?? '未分類'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <details>
                                    <summary>
                                        内容を確認
                                    </summary>

                                    <?php if (
                                        !empty(
                                            $revision[
                                                'excerpt'
                                            ]
                                        )
                                    ): ?>
                                        <p>
                                            <strong>
                                                抜粋
                                            </strong>
                                        </p>

                                        <p>
                                            <?= e(
                                                (string)(
                                                    $revision[
                                                        'excerpt'
                                                    ]
                                                )
                                            ) ?>
                                        </p>
                                    <?php endif; ?>

                                    <p>
                                        <strong>
                                            本文
                                        </strong>
                                    </p>

                                    <p>
                                        <?= e(
                                            $contentPreview(
                                                $revision[
                                                    'content'
                                                ] ?? ''
                                            )
                                        ) ?>
                                    </p>

                                    <?php if (
                                        !empty(
                                            $revision[
                                                'featured_media_title'
                                            ]
                                        )
                                    ): ?>
                                        <p>
                                            <strong>
                                                アイキャッチ：
                                            </strong>

                                            <?= e(
                                                (string)(
                                                    $revision[
                                                        'featured_media_title'
                                                    ]
                                                )
                                            ) ?>
                                        </p>
                                    <?php endif; ?>
                                </details>
                            </td>

                            <td>
                                <form
                                    method="post"
                                    action="<?= url(
                                        'admin/posts/'
                                        . 'revisions/'
                                        . $postId
                                        . '/restore/'
                                        . $revisionId
                                    ) ?>"
                                >
                                    <input
                                        type="hidden"
                                        name="_csrf_token"
                                        value="<?= e(
                                            $csrfToken
                                        ) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="button small"
                                        onclick="
                                            return confirm(
                                                'この編集履歴を'
                                                + '復元しますか？'
                                            );
                                        "
                                    >
                                        この履歴を復元
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>