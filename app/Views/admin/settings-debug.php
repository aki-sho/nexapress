<?php
$title = 'デバッグ設定';

$enabled = !empty($config['enabled']);
?>

<h1>デバッグ設定</h1>

<form action="<?= url('admin/settings/debug/update') ?>" method="post">
    <div class="form-group">
        <label for="enabled">デバッグログ</label>

        <select id="enabled" name="enabled">
            <option value="0" <?= !$enabled ? 'selected' : '' ?>>OFF</option>
            <option value="1" <?= $enabled ? 'selected' : '' ?>>ON</option>
        </select>
    </div>

    <button type="submit">保存</button>
</form>