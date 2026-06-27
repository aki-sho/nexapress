<?php
$title = 'URL設定';

$siteUrlMode = $config['site_url_mode'] ?? 'public';
$postUrlType = $config['post_url_type'] ?? 'post_slug';
$pageUrlType = $config['page_url_type'] ?? 'page_slug';
?>

<h1>URL設定</h1>

<form action="<?= url('admin/settings/url/update') ?>" method="post">
    <div class="form-group">
        <label for="site_url_mode">サイトURL形式</label>
        <select id="site_url_mode" name="site_url_mode">
            <option value="public" <?= $siteUrlMode === 'public' ? 'selected' : '' ?>>
                /public あり
            </option>
            <option value="root" <?= $siteUrlMode === 'root' ? 'selected' : '' ?>>
                /public なし
            </option>
        </select>
    </div>

    <div class="form-group">
        <label for="post_url_type">投稿URL形式</label>
        <select id="post_url_type" name="post_url_type">
            <option value="post_slug" <?= $postUrlType === 'post_slug' ? 'selected' : '' ?>>
                /post/{slug}
            </option>
            <option value="slug" <?= $postUrlType === 'slug' ? 'selected' : '' ?>>
                /{slug}
            </option>
            <option value="category_slug" <?= $postUrlType === 'category_slug' ? 'selected' : '' ?>>
                /{category}/{slug}
            </option>
        </select>
    </div>

    <div class="form-group">
        <label for="page_url_type">固定ページURL形式</label>
        <select id="page_url_type" name="page_url_type">
            <option value="page_slug" <?= $pageUrlType === 'page_slug' ? 'selected' : '' ?>>
                /page/{slug}
            </option>
            <option value="slug" <?= $pageUrlType === 'slug' ? 'selected' : '' ?>>
                /{slug}
            </option>
        </select>
    </div>

    <button type="submit">保存</button>
</form>