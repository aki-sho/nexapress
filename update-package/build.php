<?php

if (PHP_SAPI !== 'cli') {
    exit('このファイルはコマンドライン専用です。');
}

$basePath = dirname(__DIR__);

$versionConfig = require $basePath
    . '/config/version.php';

$updateConfig = require $basePath
    . '/config/update.php';

$manifestPath = __DIR__
    . '/update-manifest.json';

if (!is_file($manifestPath)) {
    exit('update-manifest.jsonがありません。');
}

$manifest = json_decode(
    (string)file_get_contents($manifestPath),
    true
);

if (!is_array($manifest)) {
    exit('update-manifest.jsonが正しくありません。');
}

$version = (string)(
    $versionConfig['version'] ?? ''
);

if (
    $version === '' ||
    $version !== ($manifest['version'] ?? '')
) {
    exit(
        'version.phpとupdate-manifest.jsonの'
        . 'バージョンが一致しません。'
    );
}

if (!class_exists(ZipArchive::class)) {
    exit('PHPのZipArchive拡張機能が必要です。');
}

$buildDirectory = __DIR__ . '/build';
$packageDirectory = $buildDirectory . '/package';

removeDirectory($buildDirectory);

if (!mkdir($packageDirectory, 0755, true)) {
    exit('作業フォルダを作成できません。');
}

copy(
    $manifestPath,
    $buildDirectory . '/update-manifest.json'
);

foreach (
    $updateConfig['allowed_update_paths'] ?? []
    as $relativePath
) {
    $relativePath = trim(
        str_replace('\\', '/', $relativePath),
        '/'
    );

    $source = $basePath . '/' . $relativePath;
    $destination = $packageDirectory
        . '/'
        . $relativePath;

    if (!file_exists($source)) {
        removeDirectory($buildDirectory);

        exit(
            '更新対象が見つかりません：'
            . $relativePath
        );
    }

    copyPath($source, $destination);
}

$outputPath = __DIR__
    . '/nexapress-update-'
    . $version
    . '.zip';

if (file_exists($outputPath)) {
    unlink($outputPath);
}

$zip = new ZipArchive();

if (
    $zip->open(
        $outputPath,
        ZipArchive::CREATE |
        ZipArchive::OVERWRITE
    ) !== true
) {
    removeDirectory($buildDirectory);

    exit('更新ZIPを作成できません。');
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $buildDirectory,
        RecursiveDirectoryIterator::SKIP_DOTS
    )
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $filePath = $file->getPathname();

    $zipPath = substr(
        $filePath,
        strlen($buildDirectory) + 1
    );

    $zipPath = str_replace('\\', '/', $zipPath);

    if (!$zip->addFile($filePath, $zipPath)) {
        $zip->close();
        removeDirectory($buildDirectory);

        exit(
            'ZIPへ追加できません：'
            . $zipPath
        );
    }
}

if (!$zip->close()) {
    removeDirectory($buildDirectory);

    exit('更新ZIPを完了できません。');
}

removeDirectory($buildDirectory);

echo '作成完了：' . $outputPath . PHP_EOL;

function copyPath(
    string $source,
    string $destination
): void {
    if (is_file($source)) {
        $parentDirectory = dirname($destination);

        if (!is_dir($parentDirectory)) {
            mkdir($parentDirectory, 0755, true);
        }

        if (!copy($source, $destination)) {
            throw new RuntimeException(
                'ファイルをコピーできません：'
                . $source
            );
        }

        return;
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $items = scandir($source);

    if ($items === false) {
        throw new RuntimeException(
            'フォルダを読み込めません：'
            . $source
        );
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        copyPath(
            $source . '/' . $item,
            $destination . '/' . $item
        );
    }
}

function removeDirectory(
    string $directory
): void {
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . '/' . $item;

        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }

    rmdir($directory);
}