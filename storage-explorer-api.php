<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

class StorageExplorer {
    private $basePath;

    public function __construct() {
        $this->basePath = __DIR__ . '/public/storage';
    }

    public function getFolderStructure($path = '') {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');

        if (!is_dir($fullPath)) {
            return ['error' => 'المجلد غير موجود'];
        }

        $result = [
            'path' => $path,
            'folders' => [],
            'files' => [],
            'stats' => [
                'totalFolders' => 0,
                'totalFiles' => 0,
                'totalSize' => 0,
                'fileTypes' => []
            ]
        ];

        try {
            $items = scandir($fullPath);

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;

                $itemPath = $fullPath . '/' . $item;

                if (is_dir($itemPath)) {
                    $folderInfo = $this->getFolderInfo($itemPath, $path . '/' . $item);
                    $result['folders'][] = $folderInfo;
                    $result['stats']['totalFolders']++;
                } else {
                    $fileInfo = $this->getFileInfo($itemPath, $path . '/' . $item);
                    $result['files'][] = $fileInfo;
                    $result['stats']['totalFiles']++;
                    $result['stats']['totalSize'] += $fileInfo['size'];

                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (!in_array($ext, $result['stats']['fileTypes'])) {
                        $result['stats']['fileTypes'][] = $ext;
                    }
                }
            }

        } catch (Exception $e) {
            return ['error' => 'خطأ في قراءة المجلد: ' . $e->getMessage()];
        }

        return $result;
    }

    private function getFolderInfo($folderPath, $relativePath) {
        $name = basename($folderPath);
        $fileCount = 0;
        $folderSize = 0;

        try {
            $items = scandir($folderPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;

                $itemPath = $folderPath . '/' . $item;
                if (is_file($itemPath)) {
                    $fileCount++;
                    $folderSize += filesize($itemPath);
                }
            }
        } catch (Exception $e) {
            // Handle permission errors silently
        }

        return [
            'name' => $name,
            'path' => $relativePath,
            'fileCount' => $fileCount,
            'size' => $folderSize,
            'url' => 'http://127.0.0.1:8000/storage' . $relativePath,
            'created' => filemtime($folderPath),
            'type' => 'folder'
        ];
    }

    private function getFileInfo($filePath, $relativePath) {
        $name = basename($filePath);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = filesize($filePath);
        $mimeType = $this->getMimeType($filePath);

        return [
            'name' => $name,
            'path' => $relativePath,
            'extension' => $ext,
            'size' => $size,
            'mimeType' => $mimeType,
            'url' => 'http://127.0.0.1:8000/storage' . $relativePath,
            'created' => filemtime($filePath),
            'modified' => filemtime($filePath),
            'type' => $this->getFileType($ext, $mimeType),
            'canPreview' => $this->canPreview($ext, $mimeType)
        ];
    }

    private function getMimeType($filePath) {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        } else if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            return finfo_file($finfo, $filePath);
        } else {
            return 'application/octet-stream';
        }
    }

    private function getFileType($ext, $mimeType) {
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        $documentExts = ['pdf', 'doc', 'docx', 'txt', 'rtf'];
        $spreadsheetExts = ['xls', 'xlsx', 'csv'];
        $archiveExts = ['zip', 'rar', '7z', 'tar', 'gz'];

        if (in_array($ext, $imageExts) || strpos($mimeType, 'image/') === 0) {
            return 'image';
        } else if (in_array($ext, $documentExts)) {
            return 'document';
        } else if (in_array($ext, $spreadsheetExts)) {
            return 'spreadsheet';
        } else if (in_array($ext, $archiveExts)) {
            return 'archive';
        } else {
            return 'other';
        }
    }

    private function canPreview($ext, $mimeType) {
        $previewableExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt'];
        $previewableMimes = ['image/', 'text/', 'application/pdf'];

        if (in_array($ext, $previewableExts)) {
            return true;
        }

        foreach ($previewableMimes as $mime) {
            if (strpos($mimeType, $mime) === 0) {
                return true;
            }
        }

        return false;
    }

    public function getStorageStats() {
        $paths = [
            'uploads' => '/uploads',
            'images' => '/images',
            'documents' => '/documents',
            'attachments' => '/attachments'
        ];

        $stats = [];

        foreach ($paths as $key => $path) {
            $data = $this->getFolderStructure($path);
            if (!isset($data['error'])) {
                $stats[$key] = [
                    'folders' => count($data['folders']),
                    'files' => $data['stats']['totalFiles'],
                    'size' => $data['stats']['totalSize'],
                    'types' => count($data['stats']['fileTypes'])
                ];
            } else {
                $stats[$key] = [
                    'folders' => 0,
                    'files' => 0,
                    'size' => 0,
                    'types' => 0,
                    'error' => $data['error']
                ];
            }
        }

        return $stats;
    }

    public function searchFiles($query, $path = '') {
        $results = [];
        $fullPath = $this->basePath . '/' . ltrim($path, '/');

        if (!is_dir($fullPath)) {
            return ['error' => 'المجلد غير موجود'];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $fileName = $file->getFilename();
                if (stripos($fileName, $query) !== false) {
                    $relativePath = str_replace($this->basePath, '', $file->getPathname());
                    $results[] = $this->getFileInfo($file->getPathname(), $relativePath);
                }
            }
        }

        return $results;
    }
}

// Handle API requests
$action = $_GET['action'] ?? 'list';
$path = $_GET['path'] ?? '';
$query = $_GET['query'] ?? '';

$explorer = new StorageExplorer();

try {
    switch ($action) {
        case 'list':
            $result = $explorer->getFolderStructure($path);
            break;

        case 'stats':
            $result = $explorer->getStorageStats();
            break;

        case 'search':
            $result = $explorer->searchFiles($query, $path);
            break;

        default:
            $result = ['error' => 'إجراء غير صالح'];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'خطأ في الخادم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
