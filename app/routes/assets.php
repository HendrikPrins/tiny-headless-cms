<?php
$title = 'Assets';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $assetId = (int)($_POST['asset_id'] ?? 0);
            if ($assetId > 0) {
                $asset = $db->getAssetById($assetId);
                if ($asset) {
                    $filePath = CMS_UPLOAD_DIR . '/' . $asset['path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $db->deleteAsset($assetId);
                    header('Location: admin.php?page=assets' . (isset($_GET['dir']) ? '&dir=' . urlencode($_GET['dir']) : ''), true, 303);
                    exit;
                }
            }
        } elseif ($action === 'move') {
            $assetId = (int)($_POST['asset_id'] ?? 0);
            $newDirectory = $_POST['new_directory'] ?? '';
            $newDirectory = trim(str_replace(['..', '\\'], ['', '/'], $newDirectory), '/');

            if ($assetId > 0) {
                $asset = $db->getAssetById($assetId);
                if ($asset) {
                    $oldPath = CMS_UPLOAD_DIR . '/' . $asset['path'];
                    $filename = basename($asset['path']);

                    $targetDir = CMS_UPLOAD_DIR;
                    if (!empty($newDirectory)) {
                        $targetDir = CMS_UPLOAD_DIR . '/' . $newDirectory;
                        if (!is_dir($targetDir)) {
                            mkdir($targetDir, 0755, true);
                        }
                    }

                    $newPath = $targetDir . '/' . $filename;
                    $relativePath = !empty($newDirectory) ? $newDirectory . '/' . $filename : $filename;

                    if (file_exists($oldPath) && rename($oldPath, $newPath)) {
                        $db->updateAssetPath($assetId, $relativePath, $newDirectory);

                        header('Location: admin.php?page=assets' . (!empty($newDirectory) ? '&dir=' . urlencode($newDirectory) : ''), true, 303);
                        exit;
                    }
                }
            }
        } elseif ($action === 'create_directory') {
            $parent = $_POST['parent'] ?? '';
            $parent = trim(str_replace(['..', '\\'], ['', '/'], $parent), '/');
            $dirname = $_POST['dirname'] ?? '';
            // Disallow temp dir name
            if (strtolower($dirname) === strtolower(CMS_ASSETS_TMP_DIR)) {
                echo '<div class="alert alert-danger">This directory name is reserved.</div>';
            } elseif (preg_match('/^[a-zA-Z0-9_-]+$/', $dirname)) {
                $fullPathRel = ($parent !== '' ? $parent . '/' : '') . $dirname;
                $fullPathAbs = CMS_UPLOAD_DIR . '/' . $fullPathRel;
                if (is_dir($fullPathAbs)) {
                    echo '<div class="alert alert-warning">Directory already exists.</div>';
                } else {
                    if (!@mkdir($fullPathAbs, 0755, true)) {
                        echo '<div class="alert alert-danger">Failed to create directory.</div>';
                    } else {
                        header('Location: admin.php?page=assets&dir=' . urlencode($fullPathRel), true, 303);
                        exit;
                    }
                }
            } else {
                echo '<div class="alert alert-danger">Invalid directory name.</div>';
            }
        } elseif ($action === 'rename_directory') {
            $current = $_POST['current_dir'] ?? '';
            $current = trim(str_replace(['..','\\'],['','/'],$current),'/');
            $newName = $_POST['new_dir_name'] ?? '';
            // Disallow temp dir name
            if (strtolower($newName) === strtolower(CMS_ASSETS_TMP_DIR)) {
                echo '<div class="alert alert-danger">This directory name is reserved.</div>';
            } elseif ($current !== '' && preg_match('/^[a-zA-Z0-9_-]+$/',$newName)) {
                $parent = dirname($current);
                if ($parent === '.' || $parent === '/') $parent='';
                $newFull = ($parent !== '' ? $parent.'/' : '').$newName;
                $oldAbs = CMS_UPLOAD_DIR . '/' . $current;
                $newAbs = CMS_UPLOAD_DIR . '/' . $newFull;
                if (!is_dir($oldAbs)) {
                    echo '<div class="alert alert-danger">Original directory not found.</div>';
                } elseif (is_dir($newAbs)) {
                    echo '<div class="alert alert-warning">Target name already exists.</div>';
                } else {
                    if (@rename($oldAbs,$newAbs)) {
                        try { $db->renameAssetDirectory($current,$newFull); } catch (Exception $e) { echo '<div class="alert alert-danger">DB update failed: '.htmlspecialchars($e->getMessage(),ENT_QUOTES).'</div>'; }
                        header('Location: admin.php?page=assets&dir=' . urlencode($newFull), true, 303);
                        exit;
                    } else {
                        echo '<div class="alert alert-danger">Failed to rename directory.</div>';
                    }
                }
            } else {
                echo '<div class="alert alert-danger">Invalid new directory name.</div>';
            }
        }
    }
}

$currentDir = $_GET['dir'] ?? '';
$currentDir = trim(str_replace(['..', '\\'], ['', '/'], $currentDir), '/');

// Helper to list immediate subdirectories (even if empty), filtering out temp dir
function listImmediateSubdirectories(string $rootUploadDir, string $relativeDir): array {
    $basePath = rtrim($rootUploadDir, '/');
    if ($relativeDir !== '') {
        $basePath .= '/' . $relativeDir;
    }
    if (!is_dir($basePath)) { return []; }
    $items = @scandir($basePath) ?: [];
    $dirs = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (strtolower($item) === strtolower(CMS_ASSETS_TMP_DIR)) continue;
        $full = $basePath . '/' . $item;
        if (is_dir($full)) {
            // Build relative path
            $rel = ($relativeDir !== '' ? $relativeDir . '/' : '') . $item;
            $dirs[] = $rel;
        }
    }
    sort($dirs, SORT_NATURAL|SORT_FLAG_CASE);
    return $dirs;
}

// Get all known directories from assets table (for move dropdown completeness)
$assetKnownDirs = array_filter($db->getAssetDirectories(), function($d){ return $d !== null; });
// Merge with physical scan of all directories recursively for move dialog
function collectAllDirectories(string $rootUploadDir, string $relativeDir = ''): array {
    $list = [];
    $immediate = listImmediateSubdirectories($rootUploadDir, $relativeDir);
    foreach ($immediate as $dir) {
        // Filter out any path segment that is temp
        $parts = explode('/', $dir);
        if (in_array(strtolower(CMS_ASSETS_TMP_DIR), array_map('strtolower', $parts), true)) continue;
        $list[] = $dir;
        // Recurse
        $list = array_merge($list, collectAllDirectories($rootUploadDir, $dir));
    }
    return $list;
}
$physicalDirsAll = collectAllDirectories(CMS_UPLOAD_DIR, '');
$allDirectories = array_values(array_unique(array_merge($assetKnownDirs, $physicalDirsAll)));
sort($allDirectories, SORT_NATURAL|SORT_FLAG_CASE);

// Immediate subdirectories of current directory for display grid
$subDirs = listImmediateSubdirectories(CMS_UPLOAD_DIR, $currentDir);

// Fetch assets only for current directory
$assets = $db->getAssets($currentDir);
?>

<div class="content-header">
    <h1>Assets</h1>
    <nav class="breadcrumb" aria-label="breadcrumb">
        <ol>
            <li><a href="?page=assets">Assets</a></li>
            <?php if (!empty($currentDir)):
                $parts = explode('/', $currentDir);
                $breadcrumbPath = '';
                foreach ($parts as $part):
                    $breadcrumbPath .= ($breadcrumbPath ? '/' : '') . $part;
            ?>
                <li><a href="?page=assets&dir=<?= urlencode($breadcrumbPath) ?>"><?= htmlspecialchars($part, ENT_QUOTES, 'UTF-8') ?></a></li>
            <?php endforeach; endif; ?>
        </ol>
    </nav>
</div>

<div class="buttons">
    <button type="button" onclick="showCreateDirectoryDialog()" class="btn-primary"><?=ICON_FOLDER_PLUS?> New Directory</button>
    <?php if (!empty($currentDir)): ?>
        <button type="button" onclick="showRenameDirectoryDialog()" class="btn-secondary"><?=ICON_PENCIL?> Rename Directory</button>
        <a href="?page=assets&dir=<?= urlencode(dirname($currentDir) === '.' ? '' : dirname($currentDir)) ?>" class="btn-secondary">⬆️ Up</a>
    <?php endif; ?>
</div>

<dialog id="create-directory-dialog" class="dialog">
    <div>
        <h2>Create New Directory</h2>
        <form method="post" action="?page=assets<?= $currentDir !== '' ? '&dir=' . urlencode($currentDir) : '' ?>" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create_directory">
            <input type="hidden" name="parent" value="<?= htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') ?>">
            <label>
                <span>Directory Name:</span>
                <input type="text" name="dirname" required pattern="[a-zA-Z0-9_-]+" title="Only letters, numbers, hyphens and underscores allowed" style="width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </label>
            <div class="dialog-footer">
                <button type="submit" class="btn-primary">Create</button>
                <button type="button" onclick="document.getElementById('create-directory-dialog').close()" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</dialog>

<dialog id="rename-directory-dialog" class="dialog">
  <div>
    <h2>Rename Directory</h2>
    <form method="post" action="?page=assets&dir=<?= urlencode($currentDir) ?>" style="margin:0; display:flex; flex-direction:column; gap:12px;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="rename_directory">
      <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir, ENT_QUOTES) ?>">
      <label>
        <span>New Name</span>
        <input type="text" name="new_dir_name" required pattern="[a-zA-Z0-9_-]+" title="Letters, numbers, hyphens, underscores" style="padding:8px; border:1px solid #ccc; border-radius:4px;">
      </label>
      <div class="dialog-footer">
        <button type="submit" class="btn-primary">Rename</button>
        <button type="button" onclick="document.getElementById('rename-directory-dialog').close()" class="btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<dialog id="move-asset-dialog" style="border: none; border-radius: 8px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
    <div style="padding: 20px;">
        <h2 style="margin-top: 0;">Move Asset</h2>
        <form method="post" id="move-asset-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="asset_id" id="move-asset-id">
            <label style="display: block; margin-bottom: 8px;">
                <span style="display: block; margin-bottom: 4px;">Move to directory:</span>
                <select name="new_directory" id="move-directory-select" style="width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">Root</option>
                    <?php foreach ($allDirectories as $dir): if ($dir === '') continue; ?>
                        <option value="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"><?= htmlspecialchars($dir, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div style="display: flex; gap: 8px; margin-top: 16px;">
                <button type="submit" class="btn-primary">Move</button>
                <button type="button" onclick="document.getElementById('move-asset-dialog').close()" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</dialog>

<?php
$dirGroups = [];
foreach ($assets as $asset) {
    $dirGroups[] = $asset;
}

// Remove duplicate $subDirs assignment; ensure we only use physical scan result
$subDirs = listImmediateSubdirectories(CMS_UPLOAD_DIR, $currentDir);
?>

<?php if (!empty($subDirs)): ?>
<h2>Directories</h2>
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 16px; margin-bottom: 32px;">
    <?php foreach ($subDirs as $subDir):
        $dirName = empty($currentDir) ? $subDir : substr($subDir, strlen($currentDir) + 1);
        $fullPath = $subDir;
    ?>
        <a href="?page=assets&dir=<?= urlencode($fullPath) ?>"
           style="display: flex; flex-direction: column; align-items: center; padding: 16px; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: inherit; background: #f9f9f9; transition: background 0.2s;"
           onmouseover="this.style.background='#f0f0f0'"
           onmouseout="this.style.background='#f9f9f9'">
            <span style="font-size: 3em;"><?=ICON_FOLDER?></span>
            <span style="margin-top: 8px; text-align: center; word-break: break-word;"><?= htmlspecialchars($dirName, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h2>Files</h2>

<div id="upload-section" style="margin-bottom: 32px; padding: 24px; background: #f5f5f5; border-radius: 8px;">
    <h3 style="margin-top: 0;">Upload Files<?= !empty($currentDir) ? ' to ' . htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') : '' ?></h3>
    <div id="drop-zone" style="border: 2px dashed #ccc; padding: 40px; text-align: center; border-radius: 8px; background: white; cursor: pointer; transition: all 0.3s;">
        <p style="margin: 0; font-size: 1.1em;">Drag and drop files here or click to select</p>
        <p style="margin: 8px 0 0 0; font-size: 0.9em; color: #666;">Supports images, PDFs, ZIP files, and more</p>
    </div>
    <input type="file" id="file-input" multiple style="display: none;">
    <input type="hidden" id="current-directory" value="<?= htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') ?>">

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
                        <td>
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
                        <td class="table-nowrap">
                            <a href="/uploads/<?= htmlspecialchars($asset['path'], ENT_QUOTES, 'UTF-8') ?>"
                               target="_blank"
                               class="btn btn-icon btn-light"
                               title="View/Download"><?=ICON_EYE?></a>
                            <button type="button"
                                    class="btn btn-icon btn-light"
                                    onclick="copyToClipboard('/uploads/<?= htmlspecialchars($asset['path'], ENT_QUOTES, 'UTF-8') ?>')"
                                    title="Copy URL"><?=ICON_LINK?></button>
                            <button type="button"
                                    class="btn btn-icon btn-light"
                                    onclick="showMoveDialog(<?= (int)$asset['id'] ?>, '<?= htmlspecialchars($asset['directory'], ENT_QUOTES, 'UTF-8') ?>')"
                                    title="Move"><?=ICON_FILE_ARROW_RIGHT?></button>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this asset?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>">
                                <button type="submit" class="btn-icon btn-danger" title="Delete"><?=ICON_TRASH?></button>
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
    const currentDirectory = document.getElementById('current-directory').value;

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
                <strong>${escapeHtml(file.name)}</strong>
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
                formData.append('directory', currentDirectory);
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

function showCreateDirectoryDialog() {
    const dialog = document.getElementById('create-directory-dialog');
    const input = dialog.querySelector('input[name="dirname"]');
    if (input) input.value = '';
    dialog.showModal();
}

function showRenameDirectoryDialog(){
  const dlg = document.getElementById('rename-directory-dialog');
  if(!dlg) return;
  const input = dlg.querySelector('input[name="new_dir_name"]');
  if(input) input.value='';
  dlg.showModal();
}

function showMoveDialog(assetId, currentDir) {
    const dialog = document.getElementById('move-asset-dialog');
    document.getElementById('move-asset-id').value = assetId;
    const select = document.getElementById('move-directory-select');
    select.value = currentDir;
    dialog.showModal();
}

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
