<form id="content-bulk" class="content-bulk-actions" data-content-bulk method="post" action="<?= e($bulkActionUrl) ?>">
    <input type="hidden" name="_csrf_token" value="<?= e(\app\Core\Csrf::token()) ?>">
    <select name="bulk_action" aria-label="一括操作" required>
        <option value="">一括操作</option>
        <?php foreach ($bulkActions as $value => $label): ?>
            <option value="<?= e($value) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="button small">適用</button>
    <span data-content-bulk-count role="status" hidden>0件選択</span>
</form>
<script src="<?= e(nx_asset_url('assets/js/content-bulk.js')) ?>" defer></script>
