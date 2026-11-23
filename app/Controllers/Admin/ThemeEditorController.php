<?php

namespace app\Controllers\Admin;

use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Theme;
use app\Core\ThemeEditor;
use InvalidArgumentException;
use RuntimeException;

final class ThemeEditorController extends Controller
{
    public function index(): void
    {
        $theme = Theme::active();
        $files = ThemeEditor::files($theme);
        $choices = array_merge(...array_values($files));
        $file = $_GET['file'] ?? ($choices[0] ?? '');
        $layer = $_GET['layer'] ?? 'parent';
        $notice = $_SESSION['theme_editor_notice'] ?? '';
        unset($_SESSION['theme_editor_notice']);
        $this->show($theme, $files, $file, $layer, $notice);
    }

    public function update(): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $theme = Theme::active();
        $files = ThemeEditor::files($theme);
        $file = $_POST['file'] ?? '';
        $layer = $_POST['layer'] ?? 'parent';
        $revision = $_POST['revision'] ?? null;
        $action = $_POST['action'] ?? 'save';
        $content = $_POST['content'] ?? null;

        if (!is_string($file) || !in_array($file, array_merge(...array_values($files)), true) ||
            !in_array($layer, ['parent', 'child'], true)) {
            http_response_code(404);
            $this->show($theme, $files, $file, $layer);
            return;
        }
        if ($theme !== ($_POST['theme'] ?? null)) {
            http_response_code(409);
            $this->show($theme, $files, $file, $layer, '', 'テーマが切り替わりました。編集画面を開き直してください。');
            return;
        }
        if (!in_array($action, ['save', 'restore', 'create_child', 'use'], true) ||
            ($layer === 'child' && in_array($action, ['restore', 'create_child'], true))) {
            http_response_code(422);
            $this->show($theme, $files, $file, $layer, '', 'ファイルの操作を確認してください。');
            return;
        }
        if ($action === 'save' && (!is_string($content) || strlen($content) > 2 * ThemeEditor::MAX_BYTES)) {
            http_response_code(422);
            $this->show($theme, $files, $file, $layer, '', 'UTF-8のテキストを256 KB以内で入力してください。');
            return;
        }

        try {
            $changed = ThemeEditor::change($theme, $file, $layer, $action,
                is_string($revision) ? $revision : null, $action === 'save' ? $content : null);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            http_response_code($exception instanceof InvalidArgumentException ? $exception->getCode() : 500);
            $this->show($theme, $files, $file, $layer, '', $exception->getMessage(),
                $action === 'save' ? $content : null, is_string($revision) ? $revision : null);
            return;
        }

        if ($changed && $action !== 'create_child') {
            do_action('design_published', $theme);
        }
        $_SESSION['theme_editor_notice'] = match ($action) {
            'restore' => 'テーマの元の内容に戻しました。',
            'create_child' => $changed ? '子ファイルを作成しました。' : '作成済みの子ファイルを開きました。',
            'use' => $layer === 'child' ? '公開サイトで子ファイルを使います。' : '公開サイトで親ファイルを使います。',
            default => 'ファイルを保存しました。',
        };
        $this->redirect('admin/themes/editor?file=' . rawurlencode($file) .
            ($action === 'create_child' || $layer === 'child' ? '&layer=child' : ''));
    }

    private function show(
        string $theme, array $files, mixed $file, mixed $layer, string $notice = '', string $error = '',
        ?string $draft = null, ?string $revision = null
    ): void {
        $document = null;
        if (is_string($file) && in_array($file, array_merge(...array_values($files)), true) &&
            in_array($layer, ['parent', 'child'], true)) {
            try {
                $document = ThemeEditor::read($theme, $file, $layer);
            } catch (InvalidArgumentException | RuntimeException $exception) {
                http_response_code($exception instanceof InvalidArgumentException ? $exception->getCode() : 500);
                $error = $exception->getMessage();
            }
        } elseif ($file !== '' || array_filter($files)) {
            http_response_code(404);
            $error = 'テーマファイルが見つかりませんでした。';
        }
        $active = array_values(array_filter(Theme::all(), static fn(array $item): bool => $item['id'] === $theme));
        $this->view('admin/theme-editor', [
            'title' => 'テーマエディタ',
            'theme' => $theme,
            'themeName' => $active[0]['name'] ?? $theme,
            'files' => $files,
            'children' => ThemeEditor::childFiles($theme, $files),
            'file' => $file,
            'layer' => $layer,
            'document' => $document,
            'notice' => $notice,
            'error' => $error,
            'draft' => $draft,
            'revision' => $revision,
            'csrf' => Csrf::token(),
        ]);
    }
}
