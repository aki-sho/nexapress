<?php

namespace app\Models;

use app\Core\Auth;
use app\Core\Csrf;
use app\Core\Database;
use app\Core\Permission;
use app\Core\RequestSize;
use InvalidArgumentException;
use Throwable;

final class ContentBulk
{
    public static function actions(string $list, bool $trash): array
    {
        $isPage = $list === 'pages';
        $canDelete = $isPage || Permission::can('posts.delete_any') || Permission::can('posts.delete_own');
        if ($trash) {
            return $canDelete ? ['restore' => $isPage ? '下書きに復元' : '復元する'] : [];
        }

        $canPublish = $isPage || Permission::can('posts.publish') || Permission::can('posts.publish_own');
        return ($canPublish ? ['published' => '公開する', 'draft' => '下書きにする'] : [])
            + ($canDelete ? ['trash' => 'ゴミ箱へ移動'] : []);
    }

    public static function run(string $list, bool $trash, array $input): array
    {
        RequestSize::requirePostWithinLimit();
        Csrf::requireValid($input['_csrf_token'] ?? null);
        $action = $input['bulk_action'] ?? '';
        try {
            if (!is_string($action) || !array_key_exists($action, self::actions($list, $trash))) {
                throw new InvalidArgumentException('この一覧で使える一括操作を選んでください。');
            }
            if (($input['selection_complete'] ?? '') !== '1') {
                throw new InvalidArgumentException('選択内容を最後まで受信できませんでした。選択する件数を減らして、もう一度適用してください。');
            }
            $ids = self::selectedIds($input['selected_ids'] ?? null);
            $changed = self::change($list, $trash, $action, $ids);
        } catch (InvalidArgumentException $exception) {
            return ['message' => $exception->getMessage(), 'type' => 'error'];
        } catch (Throwable $exception) {
            error_log('Content bulk operation failed (' . get_class($exception) . ')');
            return ['message' => '一括操作に失敗しました。一覧を更新してからもう一度お試しください。', 'type' => 'error'];
        }

        if (!$changed) {
            return ['message' => '選択した項目はすでに指定した状態です。', 'type' => 'success'];
        }

        $isPage = $list === 'pages';
        $message = count($changed) . '件を' . match ($action) {
            'published' => '公開しました。',
            'draft' => '下書きにしました。',
            'trash' => 'ゴミ箱へ移動しました。',
            'restore' => $isPage ? '下書きとして復元しました。' : '復元しました。',
        };
        $event = ($isPage ? 'page_' : 'post_') . match ($action) {
            'published', 'draft' => 'saved',
            'trash' => 'trashed',
            'restore' => 'restored',
        };
        $notified = true;
        foreach ($changed as $id) {
            try {
                do_action($event, $id);
            } catch (Throwable $exception) {
                $notified = false;
                error_log('Content bulk notification failed for ' . $event . ' (' . get_class($exception) . ')');
            }
        }

        return $notified ? ['message' => $message, 'type' => 'success'] : [
            'message' => $message . '公開画面への反映処理で問題が発生しました。',
            'type' => 'error',
        ];
    }

    private static function selectedIds(mixed $input): array
    {
        if (!is_array($input) || !$input) {
            throw new InvalidArgumentException('一括操作する項目を選んでください。');
        }
        $ids = [];
        foreach ($input as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new InvalidArgumentException('選択した項目を一覧で確認してください。');
            }
            $ids[$id] = $id;
        }
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    private static function change(string $list, bool $trash, string $action, array $ids): array
    {
        $pdo = Database::connect();
        $isPage = $list === 'pages';
        $table = Database::table($list);
        $inTrash = $trash ? 'IS NOT NULL' : 'IS NULL';
        $setsStatus = $action === 'published' || $action === 'draft';
        $publishedAt = $isPage ? 'COALESCE(published_at, ?)' : '?';
        [$update, $parameters] = match ($action) {
            'published' => ["status = 'published', published_at = {$publishedAt}", [date('Y-m-d H:i:s')]],
            'draft' => ["status = 'draft', published_at = NULL", []],
            'trash' => ['deleted_at = NOW()', []],
            'restore' => [$isPage ? "deleted_at = NULL, status = 'draft', published_at = NULL" : 'deleted_at = NULL', []],
        };

        $changed = [];
        $pdo->beginTransaction();
        try {
            foreach (array_chunk($ids, 250) as $batch) {
                $in = implode(', ', array_fill(0, count($batch), '?'));
                $select = $pdo->prepare("SELECT id, user_id, status FROM {$table}
                    WHERE id IN ({$in}) AND deleted_at {$inTrash} ORDER BY id FOR UPDATE");
                $select->execute($batch);
                $items = $select->fetchAll();
                if (count($items) !== count($batch)) {
                    throw new InvalidArgumentException('選択した項目の状態が変わりました。一覧を更新して選び直してください。');
                }

                $updates = [];
                foreach ($items as $item) {
                    if (!$isPage && !($setsStatus
                        ? Permission::canPublishPost($item) : Permission::canDeletePost($item))) {
                        throw new InvalidArgumentException('選択した投稿を操作する権限がありません。');
                    }
                    if (!$setsStatus || $item['status'] !== $action) {
                        $updates[] = (int)$item['id'];
                    }
                }
                if (!$updates) {
                    continue;
                }
                if ($isPage && $setsStatus) {
                    PageRevision::captureCurrent($updates, (int)(Auth::user()['id'] ?? 0));
                }

                $in = implode(', ', array_fill(0, count($updates), '?'));
                $write = $pdo->prepare("UPDATE {$table} SET {$update}, updated_at = NOW() WHERE id IN ({$in})");
                $write->execute(array_merge($parameters, $updates));
                array_push($changed, ...$updates);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return $changed;
    }
}
