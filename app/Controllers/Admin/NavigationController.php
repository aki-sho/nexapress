<?php

namespace app\Controllers\Admin;

use app\Core\Controller;
use app\Core\Csrf;
use app\Models\Category;
use app\Models\Navigation;
use app\Models\Page;
use Throwable;

class NavigationController extends Controller
{
    public function index(): void
    {
        $this->show();
    }

    public function save(): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $value = static fn(string $key): string => is_scalar($_POST[$key] ?? '') ? trim((string)($_POST[$key] ?? '')) : '';
        $label = $value('label');
        $type = $value('type');
        $reference = (int)$value('reference_id');
        $url = $value('url');
        $id = (int)$value('id') ?: null;
        $error = null;
        if ($label === '' || nx_text_length($label) > 255) {
            $error = '表示名を1〜255文字で入力してください。';
        } elseif (!in_array($type, ['page', 'category', 'custom'], true)) {
            $error = 'リンクの種類が正しくありません。';
        } elseif ($type === 'page' && !(($page = Page::find($reference)) && $page['deleted_at'] === null)) {
            $error = 'ゴミ箱以外の固定ページを選択してください。';
        } elseif ($type === 'category' && !Category::find($reference)) {
            $error = 'カテゴリを選択してください。';
        } elseif ($type === 'custom' && !Navigation::safeUrl($url)) {
            $error = 'URLは / から始まるサイト内パス、または http(s) URLを入力してください。';
        } elseif ($id === null && count(Navigation::all()) >= 100) {
            $error = 'メニュー項目は100件以内にしてください。';
        }
        if ($error) {
            $this->show($error);
            return;
        }
        try {
            Navigation::save(['label' => $label, 'type' => $type,
                'reference_id' => $type === 'custom' ? null : $reference, 'url' => $type === 'custom' ? $url : ''], $id);
            $_SESSION['navigation_notice'] = 'メニューを保存しました。';
            redirect_to('admin/navigation');
        } catch (Throwable $exception) {
            $this->show('メニューを保存できませんでした。');
        }
    }

    public function move(int $id): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        Navigation::move($id, ($_POST['direction'] ?? '') === 'up' ? -1 : 1);
        redirect_to('admin/navigation');
    }

    public function delete(int $id): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        Navigation::delete($id);
        redirect_to('admin/navigation');
    }

    private function show(?string $error = null): void
    {
        $notice = $_SESSION['navigation_notice'] ?? null;
        unset($_SESSION['navigation_notice']);
        $this->view('admin/navigation', [
            'items' => Navigation::all(), 'pages' => Page::all(), 'categories' => Category::all(),
            'error' => $error, 'notice' => $notice,
        ]);
    }
}
