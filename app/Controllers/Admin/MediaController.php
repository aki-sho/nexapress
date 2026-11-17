<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
use app\Models\Media;

class MediaController extends Controller
{
    private const MAX_EDIT_DIMENSION = 4096;
    private const EDITED_IMAGE_EXTENSIONS = ['image/webp' => 'webp', 'image/png' => 'png'];

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

        $mediaItems = Media::all();

        $this->view('admin/media', [
            'mediaItems' => $mediaItems,
            'maxUploadSize' => $this->maxUploadSize,
            'maxEditDimension' => self::MAX_EDIT_DIMENSION,
        ]);
    }

    public function upload(): void
    {
        Auth::requireLogin();

        $user = Auth::user();

        if (empty($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
            redirect_to('admin/media');
        }

        $file = $_FILES['media_file'];

        if ($file['size'] > $this->maxUploadSize) {
            redirect_to('admin/media');
        }

        $mimeType = mime_content_type($file['tmp_name']);

        if (!isset($this->allowedTypes[$mimeType])) {
            redirect_to('admin/media');
        }

        $this->storeUpload($file, $mimeType, $file['name'], (int)$user['id']);
        redirect_to('admin/media');
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
            $saved = $this->storeUpload($file, $mimeType, $name, (int)Auth::user()['id'], $original['description'] ?? '');
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

    private function editResponse(array $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function storeUpload(array $file, string $mimeType, string $originalName, int $userId, string $description = ''): bool
    {
        $uploadDir = BASE_PATH . '/public/uploads/media';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return false;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeFileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $safeFileName;

        if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
            return false;
        }

        try {
            Media::create([
                'title' => pathinfo($originalName, PATHINFO_FILENAME),
                'description' => $description,
                'original_name' => $originalName,
                'file_name' => $safeFileName,
                'file_path' => 'uploads/media/' . $safeFileName,
                'mime_type' => $mimeType,
                'file_size' => $file['size'],
                'file_type' => $this->allowedTypes[$mimeType],
                'user_id' => $userId,
            ]);
        } catch (\Throwable $exception) {
            @unlink($targetPath);
            throw $exception;
        }
        return true;
    }

    public function update(int $id): void
    {
        Auth::requireLogin();

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            $title = '無題';
        }

        Media::updateMeta($id, [
            'title' => $title,
            'description' => $description,
        ]);

        redirect_to('admin/media');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();

        Media::delete($id);

        redirect_to('admin/media');
    }
}
