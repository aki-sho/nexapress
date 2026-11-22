<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\MediaZipImport;
use app\Models\Media;
use InvalidArgumentException;
use PDOException;

class MediaController extends Controller
{
    private const MAX_EDIT_DIMENSION = 4096;
    private const EDITED_IMAGE_EXTENSIONS = ['image/webp' => 'webp', 'image/png' => 'png'];
    private const IMAGE_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    private array $allowedTypes = [
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',

        'audio/mpeg' => 'audio',
        'audio/wav' => 'audio',
        'audio/x-wav' => 'audio',
        'audio/mp4' => 'audio',
        'audio/ogg' => 'audio',

        'video/mp4' => 'video',
        'video/webm' => 'video',
        'video/quicktime' => 'video',

        'application/pdf' => 'document',
        'text/plain' => 'document',
        'text/csv' => 'document',

        'application/msword' => 'document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        'application/vnd.ms-excel' => 'document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'document',
        'application/vnd.ms-powerpoint' => 'document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'document',
    ];

    private int $maxUploadSize = 104857600;

    public function index(): void
    {
        Auth::requireLogin();

        $folders = array_column(Media::folders(), 'name', 'id');
        $viewFolder = $this->folderId($_GET['folder'] ?? null);
        if ($viewFolder > 0 && !isset($folders[$viewFolder])) {
            $viewFolder = null;
        }
        $notice = $_SESSION['media_notice'] ?? null;
        unset($_SESSION['media_notice']);

        $this->view('admin/media', [
            'mediaItems' => Media::all($viewFolder),
            'folders' => $folders,
            'viewFolder' => $viewFolder,
            'notice' => $notice,
            'maxUploadSize' => $this->maxUploadSize,
            'zipImportAvailable' => MediaZipImport::available(),
            'maxEditDimension' => self::MAX_EDIT_DIMENSION,
        ]);
    }

    public function createFolder(): void
    {
        Auth::requireLogin();
        Csrf::requireValid($_POST['_csrf_token'] ?? null);

        $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
        $viewFolder = $this->folderId($_POST['view_folder'] ?? null);
        if ($name === '' || nx_text_length($name) > 100 || preg_match('/[\p{Cc}]/u', $name) !== 0) {
            $this->returnToLibrary($viewFolder, 'フォルダ名を1～100文字で入力してください。', true);
        }
        if (in_array($name, ['すべて', '未分類'], true)) {
            $this->returnToLibrary($viewFolder, '別のフォルダ名を入力してください。', true);
        }
        try {
            $id = Media::createFolder($name);
        } catch (PDOException $exception) {
            if ((int)($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }
            $this->returnToLibrary($viewFolder, '同じ名前のフォルダがあります。', true);
        }
        $this->returnToLibrary($id, 'フォルダを作成しました。');
    }

    public function deleteFolder(int $id): void
    {
        Auth::requireLogin();
        Csrf::requireValid($_POST['_csrf_token'] ?? null);

        if (!Media::deleteFolder($id)) {
            $this->returnToLibrary(null, 'フォルダが見つかりません。', true);
        }
        $this->returnToLibrary(0, 'フォルダを削除しました。中のメディアは「未分類」に戻りました。');
    }

    public function move(): void
    {
        Auth::requireLogin();
        Csrf::requireValid($_POST['_csrf_token'] ?? null);

        $viewFolder = $this->folderId($_POST['view_folder'] ?? null);
        $targetFolder = $this->folderId($_POST['folder_id'] ?? null);
        $ids = $this->selectedIds();
        if ($targetFolder === null || $ids === []) {
            $this->returnToLibrary($viewFolder, 'メディアと移動先を選んでください。', true);
        }

        $moved = Media::moveToFolder($ids, $targetFolder ?: null);
        if ($moved === null) {
            $this->returnToLibrary($viewFolder, '移動先のフォルダが見つかりません。', true);
        }
        $this->returnToLibrary($viewFolder, $moved > 0
            ? $moved . '件のメディアを移動しました。'
            : '移動するメディアはありませんでした。');
    }

    public function upload(): void
    {
        Auth::requireLogin();
        Csrf::requireValid($_POST['_csrf_token'] ?? null);

        $user = Auth::user();
        $viewFolder = $this->folderId($_POST['view_folder'] ?? null);

        if (empty($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
            $this->returnToLibrary($viewFolder, 'ファイルをアップロードできませんでした。', true);
        }

        $file = $_FILES['media_file'];

        if ($file['size'] > $this->maxUploadSize) {
            $this->returnToLibrary($viewFolder, 'ファイルのサイズは100 MBまでです。', true);
        }

        $mimeType = mime_content_type($file['tmp_name']);

        if (!isset($this->allowedTypes[$mimeType])) {
            $this->returnToLibrary($viewFolder, 'アップロードできないファイル形式です。', true);
        }

        if (!$this->storeUpload($file, $mimeType, $file['name'], (int)$user['id'], '', $viewFolder ?: null)) {
            $this->returnToLibrary($viewFolder, 'ファイルを保存できませんでした。', true);
        }
        $this->returnToLibrary($viewFolder, 'メディアをアップロードしました。');
    }

    public function importZip(): void
    {
        Auth::requireLogin();
        Csrf::requireValid($_POST['_csrf_token'] ?? null);

        $folder = $this->folderId($_POST['view_folder'] ?? null);
        $file = $_FILES['media_zip'] ?? null;
        $path = $file['tmp_name'] ?? null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
            $this->returnToLibrary($folder, 'ZIPをアップロードできませんでした。', true);
            return;
        }

        $error = null;
        try {
            $count = MediaZipImport::import($path, (int)Auth::user()['id'], $folder ?: null);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (\Throwable) {
            $error = 'ZIPの取り込みを完了できませんでした。サーバーの一時領域とメディアの保存権限を確認してください。';
        } finally {
            @unlink($path);
        }
        $this->returnToLibrary($folder, $error ?? $count . '件の画像をZIPから登録しました。', $error !== null);
    }

    public function saveEdit(int $id): void
    {
        Auth::requireLogin();
        Csrf::requireValid($_POST['_csrf_token'] ?? null);

        $original = Media::find($id);
        if (!$original || $original['file_type'] !== 'image'
            || !in_array($original['mime_type'], Media::EDITABLE_IMAGE_TYPES, true)) {
            $this->editResponse(['error' => '編集元の画像が見つかりません。'], 404);
            return;
        }

        $file = $_FILES['media_file'] ?? null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || $file['size'] < 1 || $file['size'] > $this->maxUploadSize) {
            $this->editResponse(['error' => '保存する画像の容量を確認してください。'], 422);
            return;
        }

        $mimeType = mime_content_type($file['tmp_name']);
        $dimensions = @getimagesize($file['tmp_name']);
        if (!isset(self::EDITED_IMAGE_EXTENSIONS[$mimeType]) || $dimensions === false
            || $dimensions['mime'] !== $mimeType || min($dimensions[0], $dimensions[1]) < 1
            || max($dimensions[0], $dimensions[1]) > self::MAX_EDIT_DIMENSION) {
            $this->editResponse(['error' => '保存できる画像形式または画像サイズではありません。'], 422);
            return;
        }

        $title = preg_replace('~[\x00-\x1f/\\\\]+~u', ' ', (string)$original['title']);
        preg_match('/^.{0,230}/u', trim((string)$title), $titleParts);
        $name = ($titleParts[0] ?: '画像') . '（編集済み）.' . self::EDITED_IMAGE_EXTENSIONS[$mimeType];
        try {
            $saved = $this->storeUpload(
                $file, $mimeType, $name, (int)Auth::user()['id'], $original['description'] ?? '',
                isset($original['folder_id']) ? (int)$original['folder_id'] : null
            );
        } catch (\Throwable $exception) {
            $saved = false;
        }
        if (!$saved) {
            $this->editResponse(['error' => '編集した画像を登録できませんでした。'], 500);
            return;
        }
        $this->editResponse([
            'width' => $dimensions[0], 'height' => $dimensions[1], 'bytes' => $file['size'],
            'format' => strtoupper(self::EDITED_IMAGE_EXTENSIONS[$mimeType]),
        ], 201);
    }

    public function uploadImage(): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $file = $_FILES['media_file'] ?? null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || $file['size'] < 1 || $file['size'] > $this->maxUploadSize) {
            $this->editResponse(['error' => '100 MB以下の画像を選んでください。'], 422);
            return;
        }
        $dimensions = @getimagesize($file['tmp_name']);
        $mime = mime_content_type($file['tmp_name']);
        if (!isset(self::IMAGE_EXTENSIONS[$mime]) || $dimensions === false || $dimensions['mime'] !== $mime) {
            $this->editResponse(['error' => 'JPG、PNG、GIF、WebPの画像を選んでください。'], 422);
            return;
        }
        $name = trim((string)preg_replace('~[\x00-\x1f\x7f/\\\\]+~u', ' ', (string)$file['name']));
        preg_match('/^.{1,255}/u', $name, $nameParts);
        try {
            $image = $this->storeUpload($file, $mime, $nameParts[0] ?? '画像', (int)Auth::user()['id'], '', null);
        } catch (\Throwable) {
            $image = null;
        }
        if ($image === null) {
            $this->editResponse(['error' => '画像を登録できませんでした。'], 500);
            return;
        }
        $this->editResponse(['image' => $image], 201);
    }

    private function editResponse(array $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function storeUpload(
        array $file, string $mimeType, string $originalName, int $userId, string $description, ?int $folderId
    ): ?array {
        $uploadDir = BASE_PATH . '/public/uploads/media';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return null;
        }

        $extension = self::IMAGE_EXTENSIONS[$mimeType] ?? strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeFileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $safeFileName;

        if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
            return null;
        }

        try {
            $title = pathinfo($originalName, PATHINFO_FILENAME) ?: '画像';
            $relativePath = 'uploads/media/' . $safeFileName;
            $id = Media::create([
                'title' => $title,
                'description' => $description,
                'original_name' => $originalName,
                'file_name' => $safeFileName,
                'file_path' => $relativePath,
                'mime_type' => $mimeType,
                'file_size' => $file['size'],
                'file_type' => $this->allowedTypes[$mimeType],
                'user_id' => $userId,
                'folder_id' => $folderId,
            ]);
        } catch (\Throwable $exception) {
            @unlink($targetPath);
            throw $exception;
        }
        return ['id' => $id, 'title' => $title, 'description' => $description, 'url' => public_url($relativePath)];
    }

    public function update(int $id): void
    {
        Auth::requireLogin();
        Csrf::requireValid($_POST['_csrf_token'] ?? null);

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            $title = '無題';
        }

        Media::updateMeta($id, [
            'title' => $title,
            'description' => $description,
        ]);

        $this->returnToLibrary($this->folderId($_POST['view_folder'] ?? null), 'メディア情報を保存しました。');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();
        Csrf::requireValid($_POST['_csrf_token'] ?? null);

        $this->deleteSelection([$id]);
    }

    public function bulkDelete(): void
    {
        Auth::requireLogin();
        Csrf::requireValid($_POST['_csrf_token'] ?? null);

        $this->deleteSelection($this->selectedIds());
    }

    private function deleteSelection(array $ids): void
    {
        $viewFolder = $this->folderId($_POST['view_folder'] ?? null);
        if ($ids === []) {
            $this->returnToLibrary($viewFolder, '削除するメディアを選んでください。', true);
        }

        $result = Media::deleteMany($ids);
        if ($result['remainingFiles'] > 0) {
            $this->returnToLibrary($viewFolder, $result['deleted'] . '件を一覧から削除しました。'
                . $result['remainingFiles'] . '件のファイルがサーバーに残っています。', true);
        }
        $this->returnToLibrary($viewFolder, $result['deleted'] > 0
            ? $result['deleted'] . '件のメディアを削除しました。'
            : '削除するメディアは見つかりませんでした。');
    }

    private function selectedIds(): array
    {
        $value = $_POST['media_ids'] ?? '';
        $ids = is_string($value) ? json_decode($value, false, 2) : null;
        if (!is_array($ids)) {
            return [];
        }
        foreach ($ids as $id) {
            if (!is_int($id) || $id <= 0 || $id > 2147483647) {
                return [];
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    private function folderId(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
        return $id === false ? null : $id;
    }

    private function returnToLibrary(?int $folderId, string $message, bool $error = false): void
    {
        $_SESSION['media_notice'] = ['message' => $message, 'error' => $error];
        redirect_to('admin/media' . ($folderId === null ? '' : '?folder=' . $folderId));
    }
}
