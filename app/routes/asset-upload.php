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
    $directory = $_POST['directory'] ?? '';

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

    $tempDir = $uploadDir . '/' . CMS_ASSETS_TMP_DIR;
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
        if ($safeName === '') { $safeName = 'file'; }
        $extSuffix = $ext ? '.' . $ext : '';

        // Normalize directory (same rules as assets listing)
        $safeDir = trim(str_replace(['..', '\\'], ['', '/'], $directory), '/');
        $directory = $safeDir; // normalized value for DB

        // Determine target directory on disk
        $targetDir = $uploadDir;
        if (!empty($safeDir)) {
            $targetDir = $uploadDir . '/' . $safeDir;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        }

        // Intended filename (based on original); this is what we will use for overwrite detection
        $intendedFilename = $safeName . $extSuffix;

        // Check if an asset with this directory + filename already exists
        $existingAsset = $db->getAssetByDirectoryAndFilename($directory, $intendedFilename);

        $overwriting = false;
        $finalFilename = $intendedFilename;
        $finalPath = '';
        $relativePath = '';
        $assetId = null;

        if ($existingAsset) {
            // Overwrite existing asset: keep id/path/name
            $overwriting = true;
            $assetId = (int)$existingAsset['id'];
            $finalFilename = $existingAsset['filename'];
            $relativePath = $existingAsset['path'];
            $finalPath = $uploadDir . '/' . $relativePath;

            // Ensure directory exists on disk (could have been removed manually)
            $existingDir = dirname($finalPath);
            if (!is_dir($existingDir)) {
                mkdir($existingDir, 0755, true);
            }
        } else {
            $targetDirForNew = $targetDir;
            if (file_exists($targetDirForNew . '/' . $finalFilename)) {
                $i = 2;
                while (file_exists($targetDirForNew . '/' . $safeName . '_' . $i . $extSuffix)) {
                    $i++;
                    if ($i > 100000) { break; }
                }
                $finalFilename = $safeName . '_' . $i . $extSuffix;
            }
            $finalPath = $targetDirForNew . '/' . $finalFilename;
            $relativePath = !empty($directory) ? $directory . '/' . $finalFilename : $finalFilename;
        }

        // Assemble chunks into a temporary file first
        $tempFinalPath = $finalPath . '.tmp_' . bin2hex(random_bytes(6));
        $output = fopen($tempFinalPath, 'wb');
        if (!$output) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create final file']);
            exit;
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $tempDir . '/' . $safeIdentifier . '_' . $i;
            if (!file_exists($chunkFile)) {
                fclose($output);
                @unlink($tempFinalPath);
                http_response_code(500);
                echo json_encode(['error' => 'Missing chunk ' . $i]);
                exit;
            }
            $chunk = fopen($chunkFile, 'rb');
            stream_copy_to_stream($chunk, $output);
            fclose($chunk);
            @unlink($chunkFile);
        }
        fclose($output);

        // Now move temp assembled file into place (overwrite if needed)
        $moveOk = false;
        if (@rename($tempFinalPath, $finalPath)) {
            $moveOk = true;
        } else {
            // Fallback: try unlink existing then rename
            if (file_exists($finalPath)) {
                @unlink($finalPath);
            }
            if (@rename($tempFinalPath, $finalPath)) {
                $moveOk = true;
            }
        }

        if (!$moveOk) {
            @unlink($tempFinalPath);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move final file into place']);
            exit;
        }

        $fileSize = @filesize($finalPath);
        $mimeType = @mime_content_type($finalPath);

        if ($overwriting) {
            // Update existing asset metadata only
            $db->updateAssetMetadata($assetId, $mimeType, $fileSize);
        } else {
            // Store the final (unique) filename in the DB so listing matches actual file
            $assetId = $db->createAsset($finalFilename, $relativePath, $mimeType, $fileSize, $directory);
        }

        echo json_encode([
            'success' => true,
            'asset_id' => $assetId,
            'filename' => $finalFilename,
            'size' => $fileSize,
            'mime_type' => $mimeType,
            'overwritten' => $overwriting,
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
