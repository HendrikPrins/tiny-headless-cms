<?php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$db = Database::getInstance();
$uploadDir = CMS_UPLOAD_DIR;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$action = $_POST['action'] ?? '';

if ($action === 'upload_chunk') {
    $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : 0;
    $totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : 1;
    $fileIdentifier = $_POST['file_identifier'] ?? '';
    $originalFilename = $_POST['original_filename'] ?? '';

    if (empty($fileIdentifier) || empty($originalFilename)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }

    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded or upload error']);
        exit;
    }

    $tempDir = $uploadDir . '/temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $safeIdentifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileIdentifier);
    $chunkPath = $tempDir . '/' . $safeIdentifier . '_' . $chunkIndex;

    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save chunk']);
        exit;
    }

    if ($chunkIndex === $totalChunks - 1) {
        $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $finalFilename = $safeName . '_' . time() . ($ext ? '.' . $ext : '');
        $finalPath = $uploadDir . '/' . $finalFilename;

        $output = fopen($finalPath, 'wb');
        if (!$output) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create final file']);
            exit;
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $tempDir . '/' . $safeIdentifier . '_' . $i;
            if (!file_exists($chunkFile)) {
                fclose($output);
                unlink($finalPath);
                http_response_code(500);
                echo json_encode(['error' => 'Missing chunk ' . $i]);
                exit;
            }
            $chunk = fopen($chunkFile, 'rb');
            stream_copy_to_stream($chunk, $output);
            fclose($chunk);
            unlink($chunkFile);
        }
        fclose($output);

        $fileSize = filesize($finalPath);
        $mimeType = mime_content_type($finalPath);

        $assetId = $db->createAsset($originalFilename, $finalFilename, $mimeType, $fileSize);

        echo json_encode([
            'success' => true,
            'asset_id' => $assetId,
            'filename' => $finalFilename,
            'size' => $fileSize,
            'mime_type' => $mimeType
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'chunk_received' => $chunkIndex
        ]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;

