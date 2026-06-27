<?php
$title = '一般設定';

$siteTitle = $config['site_title'] ?? 'My CMS';
$timezone = $config['timezone'] ?? 'Asia/Tokyo';
$siteIcon = $config['site_icon'] ?? '';
?>

<h1>一般設定</h1>

<form action="<?= url('admin/settings/general/update') ?>" method="post">
    <div class="form-group">
        <label for="site_title">サイトのタイトル</label>
        <input type="text" id="site_title" name="site_title" value="<?= e($siteTitle) ?>">
    </div>

    <div class="form-group">
        <label for="timezone">タイムゾーン</label>
        <select id="timezone" name="timezone">
            <option value="Asia/Tokyo" <?= $timezone === 'Asia/Tokyo' ? 'selected' : '' ?>>Asia/Tokyo</option>
            <option value="UTC" <?= $timezone === 'UTC' ? 'selected' : '' ?>>UTC</option>
            <option value="America/New_York" <?= $timezone === 'America/New_York' ? 'selected' : '' ?>>America/New_York</option>
            <option value="Europe/London" <?= $timezone === 'Europe/London' ? 'selected' : '' ?>>Europe/London</option>
        </select>
    </div>

  <div class="form-group">
      <label for="site_icon">サイトアイコン</label>
      <input type="text" id="site_icon" name="site_icon" value="<?= e($siteIcon) ?>">
      <small>画像URLまたはパスを入力してください。</small>

      <?php if ($siteIcon !== ''): ?>
          <div class="site-icon-preview">
              <p>現在のサイトアイコン</p>
              <img src="<?= e($siteIcon) ?>" alt="サイトアイコン" style="width:64px;height:64px;object-fit:contain;">
          </div>
      <?php endif; ?>
  </div>

    <button type="submit">保存</button>
</form>