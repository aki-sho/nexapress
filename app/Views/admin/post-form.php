<?php require BASE_PATH . '/app/Views/admin/layout.php'; ?>

<h1><?php echo empty($post['id']) ? '投稿作成' : '投稿編集'; ?></h1>

<?php if (!empty($error)): ?>
    <p style="color: red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<form method="post" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="form-group">
        <label>タイトル</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="form-group">
        <label>スラッグ</label>
        <input type="text" name="slug" value="<?php echo htmlspecialchars($post['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="form-group">
        <label>本文</label>
        <textarea name="content"><?php echo htmlspecialchars($post['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="form-group">
        <label>状態</label>
        <select name="status">
            <option value="draft" <?php echo (($post['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>下書き</option>
            <option value="published" <?php echo (($post['status'] ?? '') === 'published') ? 'selected' : ''; ?>>公開</option>
        </select>
    </div>

    <button type="submit">保存</button>
</form>

</main>
</div>
</body>
</html>