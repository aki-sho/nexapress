<?php

namespace app\Core;

/*
 * 一覧画面のページ分割情報を作成
 */
class Paginator
{
    public static function make(
        int $totalItems,
        int $currentPage,
        int $perPage
    ): array {
        $totalItems = max(
            0,
            $totalItems
        );

        $perPage = max(
            1,
            min($perPage, 100)
        );

        $totalPages = max(
            1,
            (int)ceil(
                $totalItems / $perPage
            )
        );

        $currentPage = max(
            1,
            min(
                $currentPage,
                $totalPages
            )
        );

        return [
            'current_page' =>
                $currentPage,

            'total_pages' =>
                $totalPages,

            'total_items' =>
                $totalItems,

            'per_page' =>
                $perPage,

            'offset' =>
                ($currentPage - 1)
                * $perPage,

            'has_previous' =>
                $currentPage > 1,

            'has_next' =>
                $currentPage
                < $totalPages,

            'previous_page' =>
                $currentPage > 1
                    ? $currentPage - 1
                    : null,

            'next_page' =>
                $currentPage
                < $totalPages
                    ? $currentPage + 1
                    : null,
        ];
    }
}