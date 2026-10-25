<?php

$title = '一般設定';

$siteTitle =
    $config['site_title'] ?? 'My CMS';

$timezone =
    $config['timezone'] ?? 'Asia/Tokyo';

$siteIcon =
    $config['site_icon'] ?? '';

$discourageSearchEngines = (bool) (
    $config['discourage_search_engines']
    ?? false
);

?>

<h1>一般設定</h1>
<?php if (!empty($error)): ?><p class="error-message" role="alert"><?= e($error) ?></p><?php endif; ?>

<form
    action="<?= url(
        'admin/settings/general/update'
    ) ?>"
    method="post"
>
    <input type="hidden" name="_csrf_token" value="<?= e(\app\Core\Csrf::token()) ?>">
    <fieldset class="admin-card">
        <legend>トップページの表示</legend>
        <div class="form-group"><label for="homepage-mode">表示内容</label>
            <select id="homepage-mode" name="homepage_mode">
                <option value="posts" <?= ($config['homepage_mode'] ?? 'posts') === 'posts' ? 'selected' : '' ?>>最新の記事一覧</option>
                <option value="page" <?= ($config['homepage_mode'] ?? '') === 'page' ? 'selected' : '' ?>>固定ページ</option>
            </select></div>
        <div class="form-group"><label for="homepage-page">トップに表示する固定ページ</label>
            <select id="homepage-page" name="homepage_page_id"><option value="0">選択してください</option>
                <?php foreach ($pages ?? [] as $homepage): ?><option value="<?= (int)$homepage['id'] ?>" <?= (int)($config['homepage_page_id'] ?? 0) === (int)$homepage['id'] ? 'selected' : '' ?>><?= e($homepage['title']) ?></option><?php endforeach; ?>
            </select></div>
        <p>公開中の固定ページを選べます。固定ページをトップにした後も <a href="<?= e(url(nx_public_archive_path('posts'))) ?>">記事一覧</a> を表示できます。</p>
    </fieldset>
    <div class="form-group">
        <label for="site_title">
            サイトのタイトル
        </label>

        <input
            type="text"
            id="site_title"
            name="site_title"
            value="<?= e($siteTitle) ?>"
            required
        >
    </div>

    <div class="form-group">
        <label for="timezone">
            タイムゾーン
        </label>

        <select
            id="timezone"
            name="timezone"
        >
            <option
                value="Asia/Tokyo"
                <?= $timezone === 'Asia/Tokyo'
                    ? 'selected'
                    : '' ?>
            >
                Asia/Tokyo
            </option>

            <option
                value="UTC"
                <?= $timezone === 'UTC'
                    ? 'selected'
                    : '' ?>
            >
                UTC
            </option>

            <option
                value="America/New_York"
                <?= $timezone === 'America/New_York'
                    ? 'selected'
                    : '' ?>
            >
                America/New_York
            </option>

            <option
                value="Europe/London"
                <?= $timezone === 'Europe/London'
                    ? 'selected'
                    : '' ?>
            >
                Europe/London
            </option>
        </select>
    </div>

    <div class="form-group">
        <label for="site_icon">
            サイトアイコン
        </label>

        <input
            type="text"
            id="site_icon"
            name="site_icon"
            value="<?= e($siteIcon) ?>"
        >

        <small>
            画像URLまたはパスを入力してください。
        </small>

        <?php if ($siteIcon !== ''): ?>
            <div class="site-icon-preview">
                <p>現在のサイトアイコン</p>

                <img
                    src="<?= e($siteIcon) ?>"
                    alt="サイトアイコン"
                    style="
                        width: 64px;
                        height: 64px;
                        object-fit: contain;
                    "
                >
            </div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <h2>検索エンジンでの表示</h2>

        <label>
            <input
                type="checkbox"
                name="discourage_search_engines"
                value="1"
                <?= $discourageSearchEngines
                    ? 'checked'
                    : '' ?>
            >

            検索エンジンがサイトを
            インデックスしないようにする
        </label>

        <small>
            この設定は検索エンジンへのお願いであり、
            完全な非公開を保証するものではありません。
        </small>
    </div>

    <button type="submit">
        保存
    </button>
</form>
