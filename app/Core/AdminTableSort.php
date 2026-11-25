<?php

namespace app\Core;

final class AdminTableSort
{
    private string $key;
    private string $direction;
    private array $parameters;

    public function __construct(private array $columns, array $query, string $defaultKey = '')
    {
        $key = $query['sort'] ?? null;
        $direction = $query['order'] ?? null;
        $validKey = is_string($key) && isset($columns[$key]);
        $this->key = $validKey ? $key : $defaultKey;
        $this->direction = $validKey && $direction !== 'desc' ? 'asc' : 'desc';
        $this->parameters = $validKey ? ['sort' => $this->key, 'order' => $this->direction] : [];
    }

    public function orderBy(string $fallback): string
    {
        $column = $this->columns[$this->key] ?? null;
        return $column === null ? $fallback : $column . ' ' . strtoupper($this->direction);
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function heading(string $key, string $label, string $route, array $filters = []): void
    {
        $active = $this->key === $key;
        $descending = $active && $this->direction === 'desc';
        $next = $active && !$descending ? 'desc' : 'asc';
        $parameters = ['sort' => $key, 'order' => $next] + $filters;
        $href = url($route) . '?' . http_build_query($parameters);
        ?>
        <th scope="col"<?= $active ? ' aria-sort="' . ($descending ? 'descending' : 'ascending') . '"' : '' ?>>
            <a class="admin-sort-link" href="<?= e($href) ?>"
               aria-label="<?= e($label . 'を' . ($next === 'asc' ? '昇順' : '降順') . 'で並べ替え') ?>">
                <?= e($label) ?> <span aria-hidden="true"><?= $active ? ($descending ? '↓' : '↑') : '↕' ?></span>
            </a>
        </th>
        <?php
    }
}
