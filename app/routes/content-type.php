<?php
// Allow any logged-in user to view content types, but only admins may modify them.
requireLogin();

$title = 'Content Types';
$types = Database::getInstance()->getContentTypes();

$collections = [];
$singletons = [];
foreach ($types as $t) {
    if (!empty($t['is_singleton'])) { $singletons[] = $t; } else { $collections[] = $t; }
}
?>
<h1>Content Types</h1>

<h2>Collections</h2>
<?php if (isAdmin()): ?>
<a href="?page=content-type-create&singleton=false">Create collection</a>
<?php endif; ?>
<?php if (empty($collections)): ?>
    <p>No collections found.</p>
<?php else: ?>
    <div class="table-wrapper">
        <table class="striped bordered">
            <thead>
            <tr>
                <th>Name</th>
                <th>Entries</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($collections as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$c['entries_count'] ?></td>
                    <td>
                        <?php if (isAdmin()): ?>
                        <a href="?page=content-type-edit&id=<?= (int)$c['id'] ?>">Edit</a>
                        |
                        <?php endif; ?>
                        <a href="?page=content-entries&ct=<?= (int)$c['id'] ?>">Content</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2>Singletons</h2>
<?php if (isAdmin()): ?>
<a href="?page=content-type-create&singleton=true">Create singleton</a>
<?php endif; ?>
<?php if (empty($singletons)): ?>
    <p>No singletons found.</p>
<?php else: ?>
    <div class="table-wrapper">
        <table class="striped bordered">
            <thead>
            <tr>
                <th>Name</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($singletons as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (isAdmin()): ?>
                        <a href="?page=content-type-edit&id=<?= (int)$s['id'] ?>">Edit</a>
                        |
                        <?php endif; ?>
                        <?php if ($s['singleton_entry_id']): ?>
                            <a href="?page=content-entry-edit&ct=<?= (int)$s['id'] ?>&id=<?= (int)$s['singleton_entry_id'] ?>">Edit Content</a>
                        <?php else: ?>
                            <?php if (isAdmin()): // only admins may create the singleton entry ?>
                            <a href="?page=content-entry-edit&ct=<?= (int)$s['id'] ?>">Create Content</a>
                            <?php else: ?>
                            <span class="text-secondary">No content yet</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
