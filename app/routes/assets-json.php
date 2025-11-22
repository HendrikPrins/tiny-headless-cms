<?php
// Lightweight JSON endpoint for listing/searching assets for editor image picker
header('Content-Type: application/json');

// Basic auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    return;
}

$q = $_GET['q'] ?? '';
$dir = $_GET['dir'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, images, other
// normalize dir
$dir = trim(str_replace(['..','\\'],['','/'],$dir),'/');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
if ($limit < 1) $limit = 1; if ($limit > 200) $limit = 200; if ($offset < 0) $offset = 0;
$db = Database::getInstance();

// Collect subdirectories of current dir
$basePath = CMS_UPLOAD_DIR . ($dir !== '' ? '/' . $dir : '');
$subDirs = [];
if (is_dir($basePath)) {
    $items = @scandir($basePath) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $basePath . '/' . $item;
        if (is_dir($full)) {
            if (strtolower($item) === strtolower(CMS_ASSETS_TMP_DIR)) continue;
            $subDirs[] = $dir !== '' ? $dir . '/' . $item : $item;
        }
    }
    sort($subDirs, SORT_NATURAL|SORT_FLAG_CASE);
}

$resultAssets = [];
$total = 0; $mode = '';
if ($q !== '') {
    // search across all directories with filter
    $search = $db->searchAssets($q, $limit, $offset, $filter);
    $resultAssets = $search['items'];
    $total = $search['total'];
    $mode = 'search';
} else {
    // paged assets for current dir with filter
    $paged = $db->getAssetsPaged($dir, $limit, $offset, $filter);
    $resultAssets = $paged['items'];
    $total = $paged['total'];
    $mode = 'directory';
}

$items = [];
foreach ($resultAssets as $row) {
    $items[] = [
        'id' => (int)$row['id'],
        'filename' => $row['filename'],
        'path' => $row['path'],
        'directory' => $row['directory'],
        'mime' => $row['mime_type'],
        'size' => (int)$row['size'],
        'created_at' => $row['created_at'],
        'url' => '/uploads/' . $row['path'],
    ];
}

echo json_encode([
    'mode' => $mode,
    'searchQuery' => $q,
    'directory' => $dir,
    'subDirectories' => $subDirs,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'items' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
