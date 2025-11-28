<?php
// No additional role restrictions; both admins and editors may manage assets.

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
                    header('Location: index.php?page=assets' . (isset($_GET['dir']) ? '&dir=' . urlencode($_GET['dir']) : ''), true, 303);
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

                        header('Location: index.php?page=assets' . (!empty($newDirectory) ? '&dir=' . urlencode($newDirectory) : ''), true, 303);
                        exit;
                    }
                }
            }
        } elseif ($action === 'delete_directory') {
            $dirToDelete = $_POST['asset_directory'] ?? '';
            $dirToDelete = trim(str_replace(['..', '\\'], ['', '/'], $dirToDelete), '/');
            if ($dirToDelete !== '') {
                $fullPathAbs = CMS_UPLOAD_DIR . '/' . $dirToDelete;
                if (is_dir($fullPathAbs)) {
                    // Ensure directory is empty
                    $contents = array_diff(scandir($fullPathAbs), ['.', '..']);
                    if (empty($contents)) {
                        if (@rmdir($fullPathAbs)) {
                            header('Location: index.php?page=assets' . (dirname($dirToDelete) !== '.' ? '&dir=' . urlencode(dirname($dirToDelete)) : ''), true, 303);
                            exit;
                        } else {
                            echo '<div class="alert alert-danger">Failed to delete directory.</div>';
                        }
                    } else {
                        echo '<div class="alert alert-danger">Directory is not empty.</div>';
                    }
                } else {
                    echo '<div class="alert alert-danger">Directory not found.</div>';
                }
            } else {
                echo '<div class="alert alert-danger">Invalid directory.</div>';
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
                        header('Location: index.php?page=assets&dir=' . urlencode($fullPathRel), true, 303);
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
                        header('Location: index.php?page=assets&dir=' . urlencode($newFull), true, 303);
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
    <?php if (!empty($currentDir)): ?>
    <nav class="breadcrumb" aria-label="breadcrumb">
        <ol>
            <li><a href="?page=assets">Assets</a></li>
            <?php
                $parts = explode('/', $currentDir);
                $breadcrumbPath = '';
                foreach ($parts as $part):
                    $breadcrumbPath .= ($breadcrumbPath ? '/' : '') . $part;
            ?>
                <li><a href="?page=assets&dir=<?= urlencode($breadcrumbPath) ?>"><?= htmlspecialchars($part, ENT_QUOTES, 'UTF-8') ?></a></li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php endif; ?>
    <h1>Assets</h1>
</div>

<div class="buttons">
    <button type="button" onclick="showFilePicker()" class="btn-primary"><?=ICON_FILE_UPLOAD?> Upload Files</button>
    <button type="button" onclick="showCreateDirectoryDialog()" class="btn-primary"><?=ICON_FOLDER_PLUS?> New Directory</button>
    <?php if (!empty($currentDir)): ?>
        <button type="button" onclick="showRenameDirectoryDialog()" class="btn-secondary"><?=ICON_PENCIL?> Rename Directory</button>
    <?php endif; ?>
    <?php if (!empty($currentDir) && empty($subDirs) && empty($assets)): ?>
        <button type="button" onclick="deleteDirectory()" class="btn-danger"><?=ICON_TRASH?> Delete Directory</button>
        <form method="post" id="delete-dir-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="delete_directory">
            <input type="hidden" name="asset_directory" value="<?=$currentDir?>">
        </form>
    <?php endif; ?>
</div>

<dialog id="create-directory-dialog" class="dialog">
    <div>
        <h2>Create New Directory</h2>
        <form method="post" action="?page=assets<?= $currentDir !== '' ? '&dir=' . urlencode($currentDir) : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create_directory">
            <input type="hidden" name="parent" value="<?= htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') ?>">
            <label>
                <span>Directory Name:</span>
                <input type="text" name="dirname" required pattern="[a-zA-Z0-9_-]+" title="Only letters, numbers, hyphens and underscores allowed">
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
    <form method="post" action="?page=assets&dir=<?= urlencode($currentDir) ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="rename_directory">
      <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir, ENT_QUOTES) ?>">
      <label>
        <span>New Name</span>
        <input type="text" name="new_dir_name" required pattern="[a-zA-Z0-9_-]+" title="Letters, numbers, hyphens, underscores">
      </label>
      <div class="dialog-footer">
        <button type="submit" class="btn-primary">Rename</button>
        <button type="button" onclick="document.getElementById('rename-directory-dialog').close()" class="btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<dialog id="move-asset-dialog" class="dialog">
    <div>
        <h2>Move Asset</h2>
        <form method="post" id="move-asset-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="asset_id" id="move-asset-id">
            <label>
                <span>Move to directory:</span>
                <select name="new_directory" id="move-directory-select">
                    <option value="">Root</option>
                    <?php foreach ($allDirectories as $dir): if ($dir === '') continue; ?>
                        <option value="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"><?= htmlspecialchars($dir, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="dialog-footer">
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
?>

<?php if (!empty($subDirs)): ?>
<h2>Directories</h2>
<div class="directory-grid">
    <?php foreach ($subDirs as $subDir):
        $dirName = empty($currentDir) ? $subDir : substr($subDir, strlen($currentDir) + 1);
        $fullPath = $subDir;
    ?>
        <a href="?page=assets&dir=<?= urlencode($fullPath) ?>" class="directory-tile">
            <?=ICON_FOLDER?>
            <span class="directory-name"><?= htmlspecialchars($dirName, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h2>Files</h2>

<div id="upload-section" class="upload-section">
    <h3 class="section-title">Upload Files<?= !empty($currentDir) ? ' to ' . htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') : '' ?></h3>
    <div id="drop-zone" class="drop-zone" tabindex="0">
        <p class="drop-zone-line1">Drag and drop files here or click to select</p>
        <p class="drop-zone-line2">Existing files with the same name will be overwritten</p>
    </div>
    <input type="file" id="file-input" multiple class="hidden-input">
    <input type="hidden" id="current-directory" value="<?= htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') ?>">

    <div id="upload-progress" class="upload-progress hidden">
        <h3 class="section-subtitle">Uploading...</h3>
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
                                     class="asset-thumb">
                            <?php else: ?>
                                <span class="file-icon">📄</span>
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
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this asset?');">
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
        dropZone.classList.add('drop-zone--dragover');
    });

    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drop-zone--dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drop-zone--dragover');
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
        uploadProgress.classList.remove('hidden');
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
        progressItem.className = 'progress-item';
        progressItem.innerHTML = `
            <div class="progress-summary">
                <strong class="progress-name">${escapeHtml(file.name)}</strong>
                <span id="progress-${fileId}" class="progress-percent">0%</span>
            </div>
            <div class="progress-bar-container">
                <div id="bar-${fileId}" class="progress-bar"></div>
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

                const response = await fetch('index.php?page=asset-upload', {
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

            progressBar.classList.add('progress-success');
            progressText.textContent = '✓ Complete';
        } catch (error) {
            progressBar.classList.add('progress-failed');
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

function showFilePicker() {
    document.getElementById('file-input').click();
}

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

function deleteDirectory(){
    if(!confirm('Delete this empty directory?')) return;
    const form = document.getElementById('delete-dir-form');
    if(form) form.submit();
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
