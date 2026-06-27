<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Models\Media;

class MediaController extends Controller
{
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

        $uploadDir = BASE_PATH . '/public/uploads/media';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeFileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $safeFileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            redirect_to('admin/media');
        }

        Media::create([
            'title' => pathinfo($originalName, PATHINFO_FILENAME),
            'description' => '',
            'original_name' => $originalName,
            'file_name' => $safeFileName,
            'file_path' => 'uploads/media/' . $safeFileName,
            'mime_type' => $mimeType,
            'file_size' => $file['size'],
            'file_type' => $this->allowedTypes[$mimeType],
            'user_id' => $user['id'],
        ]);

        redirect_to('admin/media');
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