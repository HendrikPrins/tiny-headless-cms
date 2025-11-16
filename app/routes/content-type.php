<?php
$types = Database::getInstance()->getContentTypes();

$collections = [];
$singletons = [];
foreach ($types as $t) {
    if (!empty($t['is_singleton'])) { $singletons[] = $t; } else { $collections[] = $t; }
}
?>
<h1>Content Types</h1>

<h2>Collections</h2>
<a href="?page=content-type-create&singleton=false">Create collection</a>
<?php if (empty($collections)): ?>
    <p>No collections found.</p>
<?php else: ?>
    <div class="table-wrapper">
        <table>
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
                        <a href="?page=content-type-edit&id=<?= (int)$c['id'] ?>">Edit</a>
                        |
                        <a href="?page=content-entries&ct=<?= (int)$c['id'] ?>">Content</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2>Singletons</h2>
<a href="?page=content-type-create&singleton=true">Create singleton</a>
<?php if (empty($singletons)): ?>
    <p>No singletons found.</p>
<?php else: ?>
    <div class="table-wrapper">
        <table>
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
                        <a href="?page=content-type-edit&id=<?= (int)$s['id'] ?>">Edit</a>
                        |
                        <a href="?page=content-entries&ct=<?= (int)$s['id'] ?>">Content</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
