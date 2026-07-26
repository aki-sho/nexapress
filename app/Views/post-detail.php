<?php

$title =
    $post['title']
    ?? '記事詳細';

$isPreview = !empty(
    $isPreview
    ??
    $post['is_preview']
    ?? false
);

$featuredImage =
    nx_post_featured_image_url(
        $post ?? []
    );

$postDate =
    nx_post_date(
        $post ?? []
    );

$updatedDate = trim(
    (string)(
        $post['updated_at']
        ?? ''
    )
);

if ($updatedDate !== '') {
    $updatedTimestamp =
        strtotime($updatedDate);

    if ($updatedTimestamp !== false) {
        $updatedDate = date(
            'Y/m/d',
            $updatedTimestamp
        );
    }
}
?>

<?php if (empty($post)): ?>
    <div class="empty-message">
        <h1>
            記事が見つかりません
        </h1>

        <p>
            指定された記事は存在しないか、
            公開されていません。
        </p>

        <p>
            <a href="<?= url('') ?>">
                トップへ戻る
            </a>
        </p>
    </div>
<?php else: ?>
    <article class="post-detail">
        <?php if ($isPreview): ?>
            <div class="post-preview-notice">
                <strong>
                    下書きプレビュー
                </strong>

                <span>
                    このページはログイン中の
                    管理者だけが閲覧できます。
                </span>
            </div>
        <?php endif; ?>

        <?php if (
            $featuredImage !== ''
        ): ?>
            <figure class="post-featured-image">
                <img
                    src="<?= e(
                        $featuredImage
                    ) ?>"
                    alt="<?= e(
                        nx_post_featured_image_alt(
                            $post
                        )
                    ) ?>"
                >
            </figure>
        <?php endif; ?>

        <header class="post-detail-header">
            <h1>
                <?= e(
                    (string)(
                        $post['title']
                        ?? ''
                    )
                ) ?>
            </h1>

            <div class="post-detail-meta">
                <?php if (
                    $postDate !== ''
                ): ?>
                    <time
                        class="post-date"
                        datetime="<?= e(
                            (string)(
                                $post[
                                    'published_at'
                                ]
                                ??
                                $post[
                                    'created_at'
                                ]
                                ?? ''
                            )
                        ) ?>"
                    >
                        公開日：
                        <?= e($postDate) ?>
                    </time>
                <?php endif; ?>

                <?php if (
                    $updatedDate !== ''
                ): ?>
                    <time
                        class="post-updated-date"
                        datetime="<?= e(
                            (string)(
                                $post[
                                    'updated_at'
                                ]
                            )
                        ) ?>"
                    >
                        更新日：
                        <?= e($updatedDate) ?>
                    </time>
                <?php endif; ?>

                <?php if (
                    !empty(
                        $post[
                            'category_name'
                        ]
                    )
                ): ?>
                    <span class="post-category">
                        カテゴリ：
                        <?= e(
                            (string)(
                                $post[
                                    'category_name'
                                ]
                            )
                        ) ?>
                    </span>
                <?php endif; ?>
            </div>
        </header>

        <div class="post-content">
            <?= nx_post_content($post) ?>
        </div>

        <footer class="post-detail-footer">
            <a href="<?= url('') ?>">
                記事一覧へ戻る
            </a>
        </footer>
    </article>
<?php endif; ?>