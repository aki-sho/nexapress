<?php

$featuredImage =
    nx_post_featured_image_url(
        $post
    );

$featuredAlt =
    nx_post_featured_image_alt(
        $post
    );

$excerpt =
    nx_post_excerpt(
        $post,
        160
    );

$postDate =
    nx_post_date($post);

$postUrl =
    post_url($post);

$publishedDateTime =
    (string)(
        $post['published_at']
        ??
        $post['created_at']
        ?? ''
    );
?>

<article class="post-card">
    <?php if (
        $featuredImage !== ''
    ): ?>
        <a
            class="post-card-image"
            href="<?= e($postUrl) ?>"
            aria-label="<?= e(
                (string)(
                    $post['title']
                    ?? ''
                )
            ) ?>"
        >
            <img
                src="<?= e(
                    $featuredImage
                ) ?>"
                alt="<?= e(
                    $featuredAlt
                ) ?>"
                loading="lazy"
            >
        </a>
    <?php endif; ?>

    <div class="post-card-body">
        <div class="post-card-meta">
            <?php if (
                $postDate !== ''
            ): ?>
                <time
                    datetime="<?= e(
                        $publishedDateTime
                    ) ?>"
                >
                    <?= e($postDate) ?>
                </time>
            <?php endif; ?>

            <?php if (
                !empty(
                    $post['category_name']
                )
            ): ?>
                <span class="post-card-category">
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

        <h2>
            <a href="<?= e($postUrl) ?>">
                <?= e(
                    (string)(
                        $post['title']
                        ?? ''
                    )
                ) ?>
            </a>
        </h2>

        <?php if ($excerpt !== ''): ?>
            <p class="post-excerpt">
                <?= e($excerpt) ?>
            </p>
        <?php endif; ?>

        <a
            class="post-card-readmore"
            href="<?= e($postUrl) ?>"
        >
            記事を読む
        </a>
    </div>
</article>