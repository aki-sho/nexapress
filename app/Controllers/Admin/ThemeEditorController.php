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
        $notice = $_SESSION['theme_editor_notice'] ?? '';
        unset($_SESSION['theme_editor_notice']);
        $this->show($theme, $files, $file, $notice);
    }

    public function update(): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $theme = Theme::active();
        $files = ThemeEditor::files($theme);
        $file = $_POST['file'] ?? '';
        $revision = $_POST['revision'] ?? null;
        $restore = ($_POST['action'] ?? '') === 'restore';
        $content = $_POST['content'] ?? null;

        if (!is_string($file) || !in_array($file, array_merge(...array_values($files)), true)) {
            http_response_code(404);
            $this->show($theme, $files, $file);
            return;
        }
        if ($theme !== ($_POST['theme'] ?? null) || !is_string($revision)) {
            http_response_code(409);
            $this->show($theme, $files, $file, '', 'テーマが切り替わりました。編集画面を開き直してください。');
            return;
        }
        if (!$restore && (!is_string($content) || strlen($content) > 2 * ThemeEditor::MAX_BYTES)) {
            http_response_code(422);
            $this->show($theme, $files, $file, '', 'UTF-8のテキストを256 KB以内で入力してください。');
            return;
        }

        try {
            $changed = ThemeEditor::save($theme, $file, $revision, $restore ? null : $content);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            http_response_code($exception instanceof InvalidArgumentException ? $exception->getCode() : 500);
            $this->show($theme, $files, $file, '', $exception->getMessage(), $restore ? null : $content, $revision);
            return;
        }

        if ($changed) {
            do_action('design_published', $theme);
        }
        $_SESSION['theme_editor_notice'] = $restore ? 'テーマの元の内容に戻しました。' : 'ファイルを保存しました。';
        $this->redirect('admin/themes/editor?file=' . rawurlencode($file));
    }

    private function show(
        string $theme,
        array $files,
        mixed $file,
        string $notice = '',
        string $error = '',
        ?string $draft = null,
        ?string $revision = null
    ): void {
        $document = null;
        if (is_string($file) && in_array($file, array_merge(...array_values($files)), true)) {
            try {
                $document = ThemeEditor::read($theme, $file);
            } catch (RuntimeException $exception) {
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
            'file' => $file,
            'document' => $document,
            'notice' => $notice,
            'error' => $error,
            'draft' => $draft,
            'revision' => $revision,
            'csrf' => Csrf::token(),
        ]);
    }
}
