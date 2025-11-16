<?php
$collections = Database::getInstance()->getCollections();
?>
<h1>Collections</h1>
<p><a href="?page=collections-create">Create new collection</a></p>
<?php if (empty($collections)): ?>
    <p>No collections found.</p>
<?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Fields</th>
                <th>Entries</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($collections as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$c['fields_count'] ?></td>
                    <td><?= (int)$c['entries_count'] ?></td>
                    <td>
                        <a href="?page=collections-edit&id=<?= (int)$c['id'] ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
