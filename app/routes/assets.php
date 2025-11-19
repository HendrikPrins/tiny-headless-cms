<?php
$title = 'Assets';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        if ($assetId > 0) {
            $asset = $db->getAssetById($assetId);
            if ($asset) {
                $filePath = CMS_UPLOAD_DIR . '/' . $asset['path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $db->deleteAsset($assetId);
                header('Location: admin.php?page=assets', true, 303);
                exit;
            }
        }
    }
}

$assets = $db->getAssets();
$chunkSize = 1024 * 1024; // Default 1MB
?>

<div class="content-header">
    <h1>Assets</h1>
</div>

<div id="upload-section" style="margin-bottom: 32px; padding: 24px; background: #f5f5f5; border-radius: 8px;">
    <h2 style="margin-top: 0;">Upload Files</h2>
    <div id="drop-zone" style="border: 2px dashed #ccc; padding: 40px; text-align: center; border-radius: 8px; background: white; cursor: pointer; transition: all 0.3s;">
        <p style="margin: 0; font-size: 1.1em;">Drag and drop files here or click to select</p>
        <p style="margin: 8px 0 0 0; font-size: 0.9em; color: #666;">Supports images, PDFs, ZIP files, and more</p>
    </div>
    <input type="file" id="file-input" multiple style="display: none;">

    <div id="upload-progress" style="margin-top: 20px; display: none;">
        <h3 style="margin-bottom: 12px;">Uploading...</h3>
        <div id="progress-list"></div>
    </div>
</div>

<?php if (empty($assets)): ?>
    <p>No assets uploaded yet.</p>
<?php else: ?>
    <div class="table-wrapper">
        <table class="striped bordered">
            <thead>
                <tr>
                    <th>Preview</th>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td style="width: 80px;">
                            <?php if (strpos($asset['mime_type'], 'image/') === 0): ?>
                                <img src="/uploads/<?= htmlspecialchars($asset['path'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($asset['filename'], ENT_QUOTES, 'UTF-8') ?>"
                                     style="max-width: 60px; max-height: 60px; display: block;">
                            <?php else: ?>
                                <span style="font-size: 2em;">📄</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($asset['filename'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($asset['mime_type'] ?? 'unknown', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= formatFileSize($asset['size']) ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($asset['created_at'])) ?></td>
                        <td style="white-space: nowrap;">
                            <a href="/uploads/<?= htmlspecialchars($asset['path'], ENT_QUOTES, 'UTF-8') ?>"
                               target="_blank"
                               class="btn btn-icon btn-primary"
                               title="View/Download">👁️</a>
                            <button type="button"
                                    class="btn btn-icon btn-secondary"
                                    onclick="copyToClipboard('/uploads/<?= htmlspecialchars($asset['path'], ENT_QUOTES, 'UTF-8') ?>')"
                                    title="Copy URL">📋</button>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this asset?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>">
                                <button type="submit" class="btn-icon btn-danger" title="Delete">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
(function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const uploadProgress = document.getElementById('upload-progress');
    const progressList = document.getElementById('progress-list');
    const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>';
    const CHUNK_SIZE = <?= CMS_MAX_UPLOAD_SIZE ?>;

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#007bff';
        dropZone.style.backgroundColor = '#f0f8ff';
    });

    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#ccc';
        dropZone.style.backgroundColor = 'white';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#ccc';
        dropZone.style.backgroundColor = 'white';
        const files = Array.from(e.dataTransfer.files);
        if (files.length > 0) {
            handleFiles(files);
        }
    });

    fileInput.addEventListener('change', (e) => {
        const files = Array.from(e.target.files);
        if (files.length > 0) {
            handleFiles(files);
        }
        fileInput.value = '';
    });

    async function handleFiles(files) {
        uploadProgress.style.display = 'block';
        progressList.innerHTML = '';

        for (const file of files) {
            await uploadFile(file);
        }

        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }

    async function uploadFile(file) {
        const fileId = 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

        const progressItem = document.createElement('div');
        progressItem.style.cssText = 'margin-bottom: 12px; padding: 12px; background: white; border-radius: 4px;';
        progressItem.innerHTML = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-weight: 500;">${escapeHtml(file.name)}</span>
                <span id="progress-${fileId}">0%</span>
            </div>
            <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                <div id="bar-${fileId}" style="height: 100%; background: #007bff; width: 0%; transition: width 0.3s;"></div>
            </div>
        `;
        progressList.appendChild(progressItem);

        const progressText = document.getElementById('progress-' + fileId);
        const progressBar = document.getElementById('bar-' + fileId);

        try {
            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                console.log(chunkIndex);
                const start = chunkIndex * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('action', 'upload_chunk');
                formData.append('chunk', chunk);
                formData.append('chunk_index', chunkIndex);
                formData.append('total_chunks', totalChunks);
                formData.append('file_identifier', fileId);
                formData.append('original_filename', file.name);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('admin.php?page=asset-upload', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Upload failed');
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Upload failed');
                }

                const progress = Math.round(((chunkIndex + 1) / totalChunks) * 100);
                progressText.textContent = progress + '%';
                progressBar.style.width = progress + '%';
            }

            progressBar.style.background = '#28a745';
            progressText.textContent = '✓ Complete';
        } catch (error) {
            progressBar.style.background = '#dc3545';
            progressText.textContent = '✗ Failed: ' + error.message;
            console.error('Upload error:', error);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();

function copyToClipboard(text) {
    const fullUrl = window.location.origin + text;
    navigator.clipboard.writeText(fullUrl).then(() => {
        alert('URL copied to clipboard: ' + fullUrl);
    }).catch(err => {
        console.error('Failed to copy:', err);
        prompt('Copy this URL:', fullUrl);
    });
}
</script>

<?php
function formatFileSize($bytes) {
    if ($bytes === null) return 'Unknown';
    $bytes = (int)$bytes;
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}
?>
