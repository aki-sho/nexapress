<?php

use app\Core\Csrf;

$post = is_array($post ?? null)
    ? $post
    : [];

$errors = is_array($errors ?? null)
    ? $errors
    : [];

$postId = (int)(
    $post['id']
    ?? 0
);

$isEdit = $postId > 0;

$title = $isEdit
    ? '投稿編集'
    : '新規投稿';

$postTitle = (string)(
    $post['title']
    ?? ''
);

$slug = (string)(
    $post['slug']
    ?? ''
);

$excerpt = (string)(
    $post['excerpt']
    ?? ''
);

$content = (string)(
    $post['content']
    ?? ''
);

$status = (string)(
    $post['status']
    ?? 'draft'
);

$categoryId = $post['category_id']
    ?? '';

$featuredMediaId =
    $post['featured_media_id']
    ?? '';

$csrfToken = Csrf::token();

/*
 * 指定項目にエラーがあるか確認
 */
$hasError = static function (
    string $field
) use ($errors): bool {
    return isset($errors[$field])
        && trim(
            (string)$errors[$field]
        ) !== '';
};

/*
 * 項目ごとのエラーを表示
 */
$renderError = static function (
    string $field
) use ($errors): void {
    if (
        !isset($errors[$field]) ||
        trim(
            (string)$errors[$field]
        ) === ''
    ) {
        return;
    }

    ?>
    <p class="post-field-error">
        <?= e(
            (string)$errors[$field]
        ) ?>
    </p>
    <?php
};

/*
 * 選択中のアイキャッチ画像を取得
 */
$currentFeaturedMedia = null;

foreach (
    $featuredMediaItems ?? []
    as $mediaItem
) {
    if (
        (string)($mediaItem['id'] ?? '')
        ===
        (string)$featuredMediaId
    ) {
        $currentFeaturedMedia =
            $mediaItem;

        break;
    }
}
?>

<section class="admin-page post-form-page">
    <!-- ページ上部 -->
    <div class="admin-page-header post-form-header">
        <div>
            <h1><?= e($title) ?></h1>

            <p>
                <?= $isEdit
                    ? '投稿内容を編集して保存します。'
                    : '新しい投稿を作成します。' ?>
            </p>
        </div>

        <div class="post-form-header-actions">
            <?php if ($isEdit): ?>
                <a
                    class="button secondary"
                    href="<?= url(
                        'admin/posts/preview/'
                        . $postId
                    ) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    保存済み内容をプレビュー
                </a>

                <a
                    class="button secondary"
                    href="<?= url(
                        'admin/posts/revisions/'
                        . $postId
                    ) ?>"
                >
                    編集履歴
                </a>
            <?php endif; ?>

            <a
                class="button secondary"
                href="<?= url('admin/posts') ?>"
            >
                投稿一覧へ戻る
            </a>
        </div>
    </div>

    <!-- 入力エラー一覧 -->
    <?php if ($errors !== []): ?>
        <div
            class="post-error-summary"
            role="alert"
        >
            <strong>
                入力内容を確認してください。
            </strong>

            <ul>
                <?php foreach (
                    $errors
                    as $errorMessage
                ): ?>
                    <?php if (
                        trim(
                            (string)$errorMessage
                        ) !== ''
                    ): ?>
                        <li>
                            <?= e(
                                (string)$errorMessage
                            ) ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        action="<?= e($action) ?>"
        method="post"
        class="post-editor-form"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= e($csrfToken) ?>"
        >

        <div class="post-form-layout">
            <!-- 投稿本文側 -->
            <div class="post-form-main">
                <section class="admin-card post-form-card">
                    <div class="post-form-card-header">
                        <h2>基本情報</h2>

                        <p>
                            投稿のタイトルとURLを設定します。
                        </p>
                    </div>

                    <!-- タイトル -->
                    <div class="form-group">
                        <label for="title">
                            タイトル
                            <span class="post-required">
                                必須
                            </span>
                        </label>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            value="<?= e($postTitle) ?>"
                            maxlength="255"
                            required
                            <?= $hasError('title')
                                ? 'aria-invalid="true"'
                                : '' ?>
                        >

                        <?php $renderError('title'); ?>
                    </div>

                    <!-- スラッグ -->
                    <div class="form-group">
                        <label for="slug">
                            スラッグ
                        </label>

                        <input
                            type="text"
                            id="slug"
                            name="slug"
                            value="<?= e($slug) ?>"
                            maxlength="255"
                            placeholder="example-post"
                            <?= $hasError('slug')
                                ? 'aria-invalid="true"'
                                : '' ?>
                        >

                        <p class="form-help">
                            投稿URLに使用します。
                            空欄の場合はタイトルから
                            自動生成されます。
                        </p>

                        <?php $renderError('slug'); ?>
                    </div>

                    <!-- カテゴリ -->
                    <div class="form-group">
                        <label for="category_id">
                            カテゴリ
                        </label>

                        <select
                            id="category_id"
                            name="category_id"
                            <?= $hasError('category_id')
                                ? 'aria-invalid="true"'
                                : '' ?>
                        >
                            <option value="">
                                未分類
                            </option>

                            <?php foreach (
                                $categories ?? []
                                as $category
                            ): ?>
                                <?php
                                $currentCategoryId =
                                    (string)(
                                        $category['id']
                                        ?? ''
                                    );
                                ?>

                                <option
                                    value="<?= e(
                                        $currentCategoryId
                                    ) ?>"
                                    <?= (string)$categoryId
                                        === $currentCategoryId
                                            ? 'selected'
                                            : '' ?>
                                >
                                    <?= e(
                                        (string)(
                                            $category['name']
                                            ?? ''
                                        )
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php
                        $renderError(
                            'category_id'
                        );
                        ?>
                    </div>
                </section>

                <!-- 本文 -->
                <section class="admin-card post-form-card">
                    <div class="post-form-card-header">
                        <h2>本文</h2>

                        <p>
                            投稿ページへ表示する内容です。
                        </p>
                    </div>

                    <div
                        class="
                            form-group
                            post-editor-field
                        "
                    >
                        <label for="content">
                            本文
                            <span class="post-required">
                                必須
                            </span>
                        </label>

                        <textarea
                            id="content"
                            name="content"
                            rows="20"
                            required
                            <?= $hasError('content')
                                ? 'aria-invalid="true"'
                                : '' ?>
                        ><?= e($content) ?></textarea>

                        <p class="form-help">
                            危険なスクリプトや属性は、
                            保存時に自動的に除去されます。
                        </p>

                        <?php $renderError('content'); ?>
                    </div>
                </section>

                <!-- 抜粋 -->
                <section class="admin-card post-form-card">
                    <div class="post-form-card-header">
                        <h2>抜粋</h2>

                        <p>
                            投稿一覧などに表示する
                            短い説明文です。
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="excerpt">
                            抜粋
                        </label>

                        <textarea
                            id="excerpt"
                            name="excerpt"
                            rows="5"
                            maxlength="1000"
                            placeholder="この記事の概要を入力します。"
                            <?= $hasError('excerpt')
                                ? 'aria-invalid="true"'
                                : '' ?>
                        ><?= e($excerpt) ?></textarea>

                        <p class="form-help">
                            空欄の場合は、本文から
                            自動的に抜粋を作成します。
                            最大1,000文字です。
                        </p>

                        <?php $renderError('excerpt'); ?>
                    </div>
                </section>
            </div>

            <!-- 投稿設定側 -->
            <aside class="post-form-sidebar">
                <!-- 公開設定 -->
                <section class="admin-card post-form-card">
                    <div class="post-form-card-header">
                        <h2>公開設定</h2>
                    </div>

                    <div class="form-group">
                        <label for="status">
                            公開状態
                        </label>

                        <select
                            id="status"
                            name="status"
                            <?= $hasError('status')
                                ? 'aria-invalid="true"'
                                : '' ?>
                        >
                            <option
                                value="draft"
                                <?= $status === 'draft'
                                    ? 'selected'
                                    : '' ?>
                            >
                                下書き
                            </option>

                            <option
                                value="published"
                                <?= $status === 'published'
                                    ? 'selected'
                                    : '' ?>
                            >
                                公開
                            </option>
                        </select>

                        <?php $renderError('status'); ?>
                    </div>

                    <?php if ($isEdit): ?>
                        <dl class="post-form-meta">
                            <div>
                                <dt>投稿ID</dt>
                                <dd><?= $postId ?></dd>
                            </div>

                            <div>
                                <dt>作成日時</dt>
                                <dd>
                                    <?= e(
                                        (string)(
                                            $post['created_at']
                                            ?? '—'
                                        )
                                    ) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>更新日時</dt>
                                <dd>
                                    <?= e(
                                        (string)(
                                            $post['updated_at']
                                            ?? '—'
                                        )
                                    ) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>公開日時</dt>
                                <dd>
                                    <?= e(
                                        (string)(
                                            $post['published_at']
                                            ?? '—'
                                        )
                                    ) ?>
                                </dd>
                            </div>
                        </dl>
                    <?php endif; ?>

                    <?php if ($isEdit): ?>
                        <p class="post-preview-help">
                            プレビューには、現在
                            データベースへ保存されている
                            内容が表示されます。
                        </p>
                    <?php endif; ?>
                </section>

                <!-- アイキャッチ -->
                <section class="admin-card post-form-card">
                    <div class="post-form-card-header">
                        <h2>アイキャッチ画像</h2>

                        <p>
                            投稿一覧と投稿ページで
                            使用する画像です。
                        </p>
                    </div>

                    <?php if (
                        $currentFeaturedMedia !== null
                    ): ?>
                        <div class="post-current-featured">
                            <img
                                src="<?= e(
                                    public_url(
                                        (string)(
                                            $currentFeaturedMedia[
                                                'file_path'
                                            ] ?? ''
                                        )
                                    )
                                ) ?>"
                                alt="<?= e(
                                    (string)(
                                        $currentFeaturedMedia[
                                            'title'
                                        ] ?? ''
                                    )
                                ) ?>"
                            >

                            <span>
                                現在のアイキャッチ
                            </span>
                        </div>
                    <?php endif; ?>

                    <div
                        class="post-media-selector"
                        <?= $hasError(
                            'featured_media_id'
                        )
                            ? 'aria-invalid="true"'
                            : '' ?>
                    >
                        <!-- アイキャッチなし -->
                        <label class="post-media-option">
                            <input
                                type="radio"
                                name="featured_media_id"
                                value=""
                                <?= (string)$featuredMediaId
                                    === ''
                                        ? 'checked'
                                        : '' ?>
                            >

                            <span
                                class="
                                    post-media-option-body
                                    post-media-option-none
                                "
                            >
                                <span
                                    class="
                                        post-media-empty-icon
                                    "
                                >
                                    ×
                                </span>

                                <strong>
                                    設定しない
                                </strong>
                            </span>
                        </label>

                        <?php foreach (
                            $featuredMediaItems ?? []
                            as $mediaItem
                        ): ?>
                            <?php
                            $mediaId =
                                (string)(
                                    $mediaItem['id']
                                    ?? ''
                                );

                            $mediaTitle =
                                (string)(
                                    $mediaItem['title']
                                    ??
                                    $mediaItem[
                                        'original_name'
                                    ]
                                    ?? '画像'
                                );

                            $mediaUrl =
                                public_url(
                                    (string)(
                                        $mediaItem[
                                            'file_path'
                                        ] ?? ''
                                    )
                                );
                            ?>

                            <label
                                class="post-media-option"
                            >
                                <input
                                    type="radio"
                                    name="featured_media_id"
                                    value="<?= e($mediaId) ?>"
                                    <?= (string)$featuredMediaId
                                        === $mediaId
                                            ? 'checked'
                                            : '' ?>
                                >

                                <span
                                    class="
                                        post-media-option-body
                                    "
                                >
                                    <img
                                        src="<?= e(
                                            $mediaUrl
                                        ) ?>"
                                        alt="<?= e(
                                            $mediaTitle
                                        ) ?>"
                                        loading="lazy"
                                    >

                                    <strong>
                                        <?= e(
                                            $mediaTitle
                                        ) ?>
                                    </strong>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if (
                        empty($featuredMediaItems)
                    ): ?>
                        <p class="post-media-empty-message">
                            選択できる画像がありません。
                            先にメディア管理から画像を
                            アップロードしてください。
                        </p>
                    <?php endif; ?>

                    <?php
                    $renderError(
                        'featured_media_id'
                    );
                    ?>
                </section>
            </aside>
        </div>

        <!-- 保存ボタン -->
        <div class="post-form-submit-bar">
            <a
                class="button secondary"
                href="<?= url('admin/posts') ?>"
            >
                キャンセル
            </a>

            <button
                type="submit"
                class="button post-save-button"
            >
                <?= $isEdit
                    ? '変更を保存'
                    : '投稿を保存' ?>
            </button>
        </div>
    </form>
</section>