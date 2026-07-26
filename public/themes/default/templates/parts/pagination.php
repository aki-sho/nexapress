<?php

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
        class="public-pagination"
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

        <?php if ($startPage > 1): ?>
            <a
                href="<?= e(
                    nx_home_page_url(1)
                ) ?>"
            >
                1
            </a>

            <?php if (
                $startPage > 2
            ): ?>
                <span
                    class="public-pagination-ellipsis"
                    aria-hidden="true"
                >
                    …
                </span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for (
            $pageNumber = $startPage;
            $pageNumber <= $endPage;
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
            $endPage < $totalPages
        ): ?>
            <?php if (
                $endPage
                < $totalPages - 1
            ): ?>
                <span
                    class="public-pagination-ellipsis"
                    aria-hidden="true"
                >
                    …
                </span>
            <?php endif; ?>

            <a
                href="<?= e(
                    nx_home_page_url(
                        $totalPages
                    )
                ) ?>"
            >
                <?= $totalPages ?>
            </a>
        <?php endif; ?>

        <?php if (
            $currentPage < $totalPages
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