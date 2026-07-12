<?php

namespace app\Core;

/*
 * 拡張機能から処理を追加するためのフック管理
 */
class Hook
{
    private static array $actions = [];

    /*
     * 指定されたフックへ処理を登録
     */
    public static function addAction(
        string $hookName,
        callable $callback,
        int $priority = 10
    ): void {
        self::$actions[$hookName][$priority][] = $callback;
    }

    /*
     * 指定されたフックに登録された処理を実行
     */
    public static function doAction(
        string $hookName,
        mixed ...$arguments
    ): void {
        if (empty(self::$actions[$hookName])) {
            return;
        }

        /*
         * 数値が小さい優先度から実行
         */
        ksort(self::$actions[$hookName]);

        foreach (
            self::$actions[$hookName]
            as $callbacks
        ) {
            foreach ($callbacks as $callback) {
                $callback(...$arguments);
            }
        }
    }
}