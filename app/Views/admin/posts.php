<?php

use app\Core\Csrf;

$title = '投稿一覧';

$filters = is_array($filters ?? null)
    ? $filters
    : [];

$pagination = is_array(
    $pagination ?? null
)
    ? $pagination
    : [];

$notice = is_array($notice ?? null)
    ? $notice
    : null;

$isTrash = !empty(
    $filters['trash']
);

$currentPage = max(
    1,
    (int)(
        $pagination['current_page']
        ?? 1
    )
);

$totalPages = max(
    1,
    (int)(
        $pagination['total_pages']
        ?? 1
    )
);

$totalItems = max(
    0,
    (int)(
        $pagination['total_items']
        ?? 0
    )
);

$csrfToken = Csrf::token();

$statusLabels = [
    'draft' => '下書き',
    'published' => '公開',
];

/*
 * 検索条件を維持した
 * 投稿一覧URLを作成
 */
$buildListUrl = static function (
    array $overrides = [],
    array $remove = []
) use (
    $filters,
    $isTrash
): string {
    $params = [];

    $keyword = trim(
        (string)(
            $filters['keyword']
            ?? ''
        )
    );

    if ($keyword !== '') {
        $params['keyword'] =
            $keyword;
    }

    $status = trim(
        (string)(
            $filters['status']
            ?? ''
        )
    );

    if ($status !== '') {
        $params['status'] =
            $status;
    }

    $categoryId = (int)(
        $filters['category_id']
        ?? 0
    );

    if ($categoryId > 0) {
        $params['category_id'] =
            $categoryId;
    }

    $authorId = (int)(
        $filters['author_id']
        ?? 0
    );

    if ($authorId > 0) {
        $params['author_id'] =
            $authorId;
    }

    if ($isTrash) {
        $params['view'] = 'trash';
    }

    foreach (
        $overrides
        as $key => $value
    ) {
        if (
            $value === null ||
            $value === ''
        ) {
            unset($params[$key]);

            continue;
        }

        $params[$key] = $value;
    }

    foreach ($remove as $key) {
        unset($params[$key]);
    }

    $query = http_build_query(
        $params
    );

    $baseUrl = url('admin/posts');

    return $query !== ''
        ? $baseUrl . '?' . $query
        : $baseUrl;
};

/*
 * 日時表示
 */
$formatDate = static function (
    mixed $value
): string {
    $value = trim(
        (string)$value
    );

    if ($value === '') {
        return '—';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date(
        'Y/m/d H:i',
        $timestamp
    );
};

/*
 * 投稿一覧用の抜粋を取得
 */
$postExcerpt = static function (
    array $post
): string {
    $excerpt = trim(
        (string)(
            $post['excerpt']
            ?? ''
        )
    );

    if ($excerpt === '') {
        $excerpt = html_entity_decode(
            strip_tags(
                (string)(
                    $post['content']
                    ?? ''
                )
            ),
            ENT_QUOTES |
            ENT_HTML5,
            'UTF-8'
        );

        $excerpt = preg_replace(
            '/\s+/u',
            ' ',
            $excerpt
        ) ?? '';

        $excerpt = trim($excerpt);
    }

    if ($excerpt === '') {
        return '抜粋はありません。';
    }

    if (function_exists('mb_substr')) {
        $shortText = mb_substr(
            $excerpt,
            0,
            100,
            'UTF-8'
        );

        if (
            mb_strlen(
                $excerpt,
                'UTF-8'
            ) > 100
        ) {
            $shortText .= '…';
        }

        return $shortText;
    }

    $shortText = substr(
        $excerpt,
        0,
        100
    );

    if (strlen($excerpt) > 100) {
        $shortText .= '…';
    }

    return $shortText;
};
?>

<section class="admin-page posts-page">
    <!-- ページ上部 -->
    <div class="admin-page-header posts-page-header">
        <div>
            <h1>
                <?= $isTrash
                    ? '投稿ゴミ箱'
                    : '投稿一覧' ?>
            </h1>

            <p>
                <?= $isTrash
                    ? '削除した投稿の復元や完全削除を行います。'
                    : '投稿の検索、編集、公開状態の管理を行います。' ?>
            </p>
        </div>

        <?php if (!$isTrash): ?>
            <a
                class="button"
                href="<?= url(
                    'admin/posts/create'
                ) ?>"
            >
                新規投稿を追加
            </a>
        <?php endif; ?>
    </div>

    <!-- 処理結果 -->
    <?php if ($notice !== null): ?>
        <?php
        $noticeType =
            ($notice['type'] ?? '')
            === 'error'
                ? 'error'
                : 'success';
        ?>

        <div
            class="
                post-notice
                post-notice--<?= e(
                    $noticeType
                ) ?>
            "
            role="status"
        >
            <?= e(
                (string)(
                    $notice['message']
                    ?? ''
                )
            ) ?>
        </div>
    <?php endif; ?>

    <!-- 通常一覧・ゴミ箱切り替え -->
    <nav
        class="post-list-tabs"
        aria-label="投稿表示切り替え"
    >
        <a
            href="<?= url('admin/posts') ?>"
            class="<?= !$isTrash
                ? 'is-active'
                : '' ?>"
        >
            投稿一覧
        </a>

        <a
            href="<?= url(
                'admin/posts'
            ) ?>?view=trash"
            class="<?= $isTrash
                ? 'is-active'
                : '' ?>"
        >
            ゴミ箱
        </a>
    </nav>

    <!-- 検索・絞り込み -->
    <form
        method="get"
        action="<?= url('admin/posts') ?>"
        class="post-filter-form admin-card"
    >
        <?php if ($isTrash): ?>
            <input
                type="hidden"
                name="view"
                value="trash"
            >
        <?php endif; ?>

        <div class="post-filter-grid">
            <!-- キーワード -->
            <div class="form-group">
                <label for="keyword">
                    キーワード
                </label>

                <input
                    type="search"
                    id="keyword"
                    name="keyword"
                    value="<?= e(
                        (string)(
                            $filters['keyword']
                            ?? ''
                        )
                    ) ?>"
                    placeholder="タイトル・本文・スラッグ"
                >
            </div>

            <!-- 公開状態 -->
            <div class="form-group">
                <label for="status">
                    公開状態
                </label>

                <select
                    id="status"
                    name="status"
                >
                    <option value="">
                        すべて
                    </option>

                    <option
                        value="draft"
                        <?= (
                            $filters['status']
                            ?? ''
                        ) === 'draft'
                            ? 'selected'
                            : '' ?>
                    >
                        下書き
                    </option>

                    <option
                        value="published"
                        <?= (
                            $filters['status']
                            ?? ''
                        ) === 'published'
                            ? 'selected'
                            : '' ?>
                    >
                        公開
                    </option>
                </select>
            </div>

            <!-- カテゴリ -->
            <div class="form-group">
                <label for="category_id">
                    カテゴリ
                </label>

                <select
                    id="category_id"
                    name="category_id"
                >
                    <option value="">
                        すべて
                    </option>

                    <?php foreach (
                        $categories ?? []
                        as $category
                    ): ?>
                        <?php
                        $categoryValue =
                            (string)(
                                $category['id']
                                ?? ''
                            );
                        ?>

                        <option
                            value="<?= e(
                                $categoryValue
                            ) ?>"
                            <?= (string)(
                                $filters[
                                    'category_id'
                                ] ?? ''
                            ) === $categoryValue
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e(
                                (string)(
                                    $category['name']
                                    ?? ''
                                )
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 投稿者 -->
            <div class="form-group">
                <label for="author_id">
                    投稿者
                </label>

                <select
                    id="author_id"
                    name="author_id"
                >
                    <option value="">
                        すべて
                    </option>

                    <?php foreach (
                        $authors ?? []
                        as $author
                    ): ?>
                        <?php
                        $authorValue =
                            (string)(
                                $author['id']
                                ?? ''
                            );
                        ?>

                        <option
                            value="<?= e(
                                $authorValue
                            ) ?>"
                            <?= (string)(
                                $filters[
                                    'author_id'
                                ] ?? ''
                            ) === $authorValue
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e(
                                (string)(
                                    $author['name']
                                    ??
                                    $author['username']
                                    ?? ''
                                )
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="post-filter-actions">
            <button
                type="submit"
                class="button"
            >
                絞り込む
            </button>

            <a
                class="button secondary"
                href="<?= $isTrash
                    ? url(
                        'admin/posts'
                    ) . '?view=trash'
                    : url('admin/posts') ?>"
            >
                条件をリセット
            </a>
        </div>
    </form>

    <!-- 件数表示 -->
    <div class="post-list-summary">
        <p>
            全
            <strong>
                <?= $totalItems ?>
            </strong>
            件
        </p>

        <?php if (
            $totalItems > 0
        ): ?>
            <p>
                <?= $currentPage ?>
                /
                <?= $totalPages ?>
                ページ
            </p>
        <?php endif; ?>
    </div>

    <!-- 投稿がない場合 -->
    <?php if (empty($posts)): ?>
        <div class="admin-card post-empty-card">
            <h2>
                <?= $isTrash
                    ? 'ゴミ箱は空です'
                    : '投稿が見つかりません' ?>
            </h2>

            <p>
                <?= $isTrash
                    ? '削除された投稿はありません。'
                    : '検索条件を変更するか、新しい投稿を追加してください。' ?>
            </p>
        </div>
    <?php else: ?>
        <!-- 投稿一覧 -->
        <div class="admin-table-wrap">
            <table class="admin-table post-admin-table">
                <thead>
                    <tr>
                        <th class="post-image-column">
                            画像
                        </th>

                        <th>
                            投稿
                        </th>

                        <th>
                            状態
                        </th>

                        <th>
                            カテゴリ
                        </th>

                        <th>
                            投稿者
                        </th>

                        <th>
                            <?= $isTrash
                                ? '削除日時'
                                : '作成日時' ?>
                        </th>

                        <th class="post-actions-column">
                            操作
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        $posts
                        as $post
                    ): ?>
                        <?php
                        $postId = (int)(
                            $post['id']
                            ?? 0
                        );

                        $postStatus =
                            (string)(
                                $post['status']
                                ?? 'draft'
                            );

                        $featuredPath =
                            trim(
                                (string)(
                                    $post[
                                        'featured_media_path'
                                    ] ?? ''
                                )
                            );

                        $displayDate =
                            $isTrash
                                ? (
                                    $post['deleted_at']
                                    ?? ''
                                )
                                : (
                                    $post['created_at']
                                    ?? ''
                                );
                        ?>

                        <tr>
                            <!-- アイキャッチ -->
                            <td>
                                <?php if (
                                    $featuredPath !== ''
                                ): ?>
                                    <img
                                        class="post-list-thumbnail"
                                        src="<?= e(
                                            public_url(
                                                $featuredPath
                                            )
                                        ) ?>"
                                        alt="<?= e(
                                            (string)(
                                                $post[
                                                    'featured_media_title'
                                                ]
                                                ??
                                                $post['title']
                                                ?? ''
                                            )
                                        ) ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div
                                        class="
                                            post-list-thumbnail
                                            post-list-thumbnail--empty
                                        "
                                        aria-label="画像なし"
                                    >
                                        画像なし
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- 投稿情報 -->
                            <td class="post-list-title-cell">
                                <strong>
                                    <?= e(
                                        (string)(
                                            $post['title']
                                            ?? ''
                                        )
                                    ) ?>
                                </strong>

                                <div class="post-list-slug">
                                    <code>
                                        <?= e(
                                            (string)(
                                                $post['slug']
                                                ?? ''
                                            )
                                        ) ?>
                                    </code>
                                </div>

                                <p class="post-list-excerpt">
                                    <?= e(
                                        $postExcerpt(
                                            $post
                                        )
                                    ) ?>
                                </p>

                                <?php if (
                                    !$isTrash &&
                                    $postStatus
                                    === 'published'
                                ): ?>
                                    <a
                                        class="post-public-link"
                                        href="<?= e(
                                            post_url(
                                                $post
                                            )
                                        ) ?>"
                                        target="_blank"
                                        rel="
                                            noopener
                                            noreferrer
                                        "
                                    >
                                        公開ページを開く
                                    </a>
                                <?php endif; ?>
                            </td>

                            <!-- 公開状態 -->
                            <td>
                                <span
                                    class="
                                        post-status-badge
                                        post-status-badge--<?= e(
                                            $postStatus
                                        ) ?>
                                    "
                                >
                                    <?= e(
                                        $statusLabels[
                                            $postStatus
                                        ]
                                        ?? $postStatus
                                    ) ?>
                                </span>
                            </td>

                            <!-- カテゴリ -->
                            <td>
                                <?= e(
                                    (string)(
                                        $post[
                                            'category_name'
                                        ] ?? '未分類'
                                    )
                                ) ?>
                            </td>

                            <!-- 投稿者 -->
                            <td>
                                <?= e(
                                    (string)(
                                        $post[
                                            'author_name'
                                        ]
                                        ?? '不明'
                                    )
                                ) ?>
                            </td>

                            <!-- 日時 -->
                            <td>
                                <?= e(
                                    $formatDate(
                                        $displayDate
                                    )
                                ) ?>
                            </td>

                            <!-- 操作 -->
                            <td>
                                <?php if ($isTrash): ?>
                                    <div class="post-row-actions">
                                        <!-- 復元 -->
                                        <form
                                            method="post"
                                            action="<?= url(
                                                'admin/posts/'
                                                . 'restore/'
                                                . $postId
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
                                                class="
                                                    button
                                                    small
                                                "
                                            >
                                                復元
                                            </button>
                                        </form>

                                        <!-- 完全削除 -->
                                        <form
                                            method="post"
                                            action="<?= url(
                                                'admin/posts/'
                                                . 'destroy/'
                                                . $postId
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
                                                class="
                                                    button
                                                    danger
                                                    small
                                                "
                                                onclick="
                                                    return confirm(
                                                        'この投稿を'
                                                        + '完全に削除しますか？'
                                                        + '\n'
                                                        + 'この操作は'
                                                        + '取り消せません。'
                                                    );
                                                "
                                            >
                                                完全削除
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="post-row-actions">
                                        <!-- 編集 -->
                                        <a
                                            class="
                                                button
                                                secondary
                                                small
                                            "
                                            href="<?= url(
                                                'admin/posts/'
                                                . 'edit/'
                                                . $postId
                                            ) ?>"
                                        >
                                            編集
                                        </a>

                                        <!-- プレビュー -->
                                        <a
                                            class="
                                                button
                                                secondary
                                                small
                                            "
                                            href="<?= url(
                                                'admin/posts/'
                                                . 'preview/'
                                                . $postId
                                            ) ?>"
                                            target="_blank"
                                            rel="
                                                noopener
                                                noreferrer
                                            "
                                        >
                                            プレビュー
                                        </a>

                                        <!-- 履歴 -->
                                        <a
                                            class="
                                                button
                                                secondary
                                                small
                                            "
                                            href="<?= url(
                                                'admin/posts/'
                                                . 'revisions/'
                                                . $postId
                                            ) ?>"
                                        >
                                            履歴
                                        </a>

                                        <!-- 公開状態変更 -->
                                        <form
                                            method="post"
                                            action="<?= url(
                                                'admin/posts/'
                                                . 'status/'
                                                . $postId
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
                                                class="
                                                    button
                                                    small
                                                "
                                            >
                                                <?= $postStatus
                                                    === 'published'
                                                        ? '下書きにする'
                                                        : '公開する' ?>
                                            </button>
                                        </form>

                                        <!-- ゴミ箱へ移動 -->
                                        <form
                                            method="post"
                                            action="<?= url(
                                                'admin/posts/'
                                                . 'delete/'
                                                . $postId
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
                                                class="
                                                    button
                                                    danger
                                                    small
                                                "
                                                onclick="
                                                    return confirm(
                                                        'この投稿を'
                                                        + 'ゴミ箱へ'
                                                        + '移動しますか？'
                                                    );
                                                "
                                            >
                                                ゴミ箱へ移動
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ページ分割 -->
        <?php if ($totalPages > 1): ?>
            <?php
            $startPage = max(
                1,
                $currentPage - 2
            );

            $endPage = min(
                $totalPages,
                $currentPage + 2
            );
            ?>

            <nav
                class="post-pagination"
                aria-label="投稿一覧のページ送り"
            >
                <!-- 最初・前 -->
                <?php if (
                    $currentPage > 1
                ): ?>
                    <a
                        href="<?= e(
                            $buildListUrl(
                                [
                                    'page' => 1,
                                ]
                            )
                        ) ?>"
                    >
                        最初
                    </a>

                    <a
                        href="<?= e(
                            $buildListUrl(
                                [
                                    'page' =>
                                        $currentPage
                                        - 1,
                                ]
                            )
                        ) ?>"
                    >
                        前へ
                    </a>
                <?php endif; ?>

                <!-- ページ番号 -->
                <?php for (
                    $pageNumber =
                        $startPage;
                    $pageNumber <=
                        $endPage;
                    $pageNumber++
                ): ?>
                    <?php if (
                        $pageNumber
                        === $currentPage
                    ): ?>
                        <span
                            class="is-current"
                            aria-current="page"
                        >
                            <?= $pageNumber ?>
                        </span>
                    <?php else: ?>
                        <a
                            href="<?= e(
                                $buildListUrl(
                                    [
                                        'page' =>
                                            $pageNumber,
                                    ]
                                )
                            ) ?>"
                        >
                            <?= $pageNumber ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <!-- 次・最後 -->
                <?php if (
                    $currentPage <
                    $totalPages
                ): ?>
                    <a
                        href="<?= e(
                            $buildListUrl(
                                [
                                    'page' =>
                                        $currentPage
                                        + 1,
                                ]
                            )
                        ) ?>"
                    >
                        次へ
                    </a>

                    <a
                        href="<?= e(
                            $buildListUrl(
                                [
                                    'page' =>
                                        $totalPages,
                                ]
                            )
                        ) ?>"
                    >
                        最後
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>