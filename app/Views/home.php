<?php

$title = site_title();

$pagination = is_array(
    $pagination ?? null
)
    ? $pagination
    : [];

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
?>

<section class="post-archive">
    <h1 class="page-title">
        記事一覧
    </h1>

    <?php if (empty($posts)): ?>
        <div class="empty-message">
            <p>
                公開中の記事はありません。
            </p>
        </div>
    <?php else: ?>
        <div class="post-list">
            <?php foreach (
                $posts
                as $post
            ): ?>
                <?php
                $featuredImage =
                    nx_post_featured_image_url(
                        $post
                    );

                $excerpt =
                    nx_post_excerpt(
                        $post,
                        160
                    );

                $postDate =
                    nx_post_date($post);
                ?>

                <article class="post-card">
                    <?php if (
                        $featuredImage !== ''
                    ): ?>
                        <a
                            class="post-card-image"
                            href="<?= e(
                                post_url($post)
                            ) ?>"
                        >
                            <img
                                src="<?= e(
                                    $featuredImage
                                ) ?>"
                                alt="<?= e(
                                    nx_post_featured_image_alt(
                                        $post
                                    )
                                ) ?>"
                                loading="lazy"
                            >
                        </a>
                    <?php endif; ?>

                    <div class="post-card-body">
                        <h2>
                            <a
                                href="<?= e(
                                    post_url($post)
                                ) ?>"
                            >
                                <?= e(
                                    (string)(
                                        $post['title']
                                        ?? ''
                                    )
                                ) ?>
                            </a>
                        </h2>

                        <?php if (
                            $postDate !== ''
                        ): ?>
                            <p class="post-date">
                                <?= e($postDate) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (
                            !empty(
                                $post[
                                    'category_name'
                                ]
                            )
                        ): ?>
                            <p class="post-category">
                                <?= e(
                                    (string)(
                                        $post[
                                            'category_name'
                                        ]
                                    )
                                ) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (
                            $excerpt !== ''
                        ): ?>
                            <p class="post-excerpt">
                                <?= e($excerpt) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

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
                aria-label="記事一覧のページ送り"
            >
                <?php if (
                    $currentPage > 1
                ): ?>
                    <a
                        href="<?= e(
                            nx_home_page_url(
                                $currentPage - 1
                            )
                        ) ?>"
                        rel="prev"
                    >
                        前へ
                    </a>
                <?php endif; ?>

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
                                nx_home_page_url(
                                    $pageNumber
                                )
                            ) ?>"
                        >
                            <?= $pageNumber ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if (
                    $currentPage
                    < $totalPages
                ): ?>
                    <a
                        href="<?= e(
                            nx_home_page_url(
                                $currentPage + 1
                            )
                        ) ?>"
                        rel="next"
                    >
                        次へ
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>