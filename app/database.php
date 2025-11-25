<?php
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $host = CMS_DB_HOST;
        $user = CMS_DB_USER;
        $pass = CMS_DB_PASS;
        $dbname = CMS_DB_NAME;
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $this->connection = new PDO($dsn, $user, $pass, $options);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function hasSchema() {
        try {
            $stmt = $this->connection->query("SHOW TABLES LIKE 'users'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function hasAdminUser() {
        try {
            $stmt = $this->connection->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function createUser($username, $password, $role) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->connection->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':role', $role);
        return $stmt->execute();
    }

    public function getUserByUsername(string $username)
    {
        $stmt = $this->connection->prepare("SELECT id, username, password, role FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getContentTypes()
    {
        $stmt = $this->connection->query("SELECT id, name, is_singleton, schema FROM content_types ORDER BY name ASC");
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['schema'] = json_decode($row['schema'], true);
            $rows[] = $row;
        }
        return $rows;
    }

    public function createContentType(string $name, bool $isSingleton): int
    {
        // TODO:
        // - check if name is unique
        // - insert into content_types
        // - create base table (only default columns)
        // - create localized table (only default columns)
    }

    // Add: update content type name
    public function updateContentTypeName(int $id, string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Name is required');
        }
        if (strlen($name) > 255) {
            throw new InvalidArgumentException('Name must be 255 characters or fewer');
        }
        // TODO:
        // update content_types set name = :name where id = :id
        // rename table and localized table
    }

    public function getContentType(int $id)
    {
        $stmt = $this->connection->prepare("SELECT id, name, is_singleton, schema FROM content_types WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $contentType = null;
        if ($row = $stmt->fetch()) {
            $row['schema'] = json_decode($row['schema'], true);
            $contentType = $row;
        }
        return $contentType;
    }

    public function setContentTypeSchema(int $contentTypeId, $schema)
    {

        // TODO:
        // - check if name is unique
        // - update content_type
        // - add column to base table if not translatable
        // - add column to localized table if translatable
    }


    public function deleteContentType(int $id): bool
    {
        // TODO:
        // - fetch content type
        // - delete all entries
        // - delete base and localized tables
        // - delete content type
    }

    public function getEntriesForContentType(int $contentTypeId, $locale)
    {
        $ct = $this->getContentType($contentTypeId);
        if ($ct === null) {
            return [];
        }
        $table = $ct['name'];
        $table_localized = $table . '_localized';
        $columns_localized = [];
        foreach ($ct['schema']['fields'] as $field) {
            if ($field['is_translatable']) {
                $columns_localized[] = 'l.' . $field['field_name'];
            }
        }
        $query = "SELECT b.*, " . implode(', ', $columns_localized) . " FROM {$table} b LEFT JOIN {$table_localized} l ON b.id = l.entry_id AND l.locale = :locale WHERE 1";
        $stmt = $this->connection->query($query);
        return $stmt->fetchAll();
    }

    public function getEntryCountForContentType(int $contentTypeId): int
    {
        $ct = $this->getContentType($contentTypeId);
        if ($ct === null) {
            return 0;
        }
        $table = $ct['name'];
        return (int)$this->connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    public function getEntryById(int $entryId)
    {

    }

    public function getFieldValuesForEntry(int $entryId, string $locale): array
    {

    }

    public function createEntry(int $contentTypeId): int
    {

    }

    public function saveEntryValues(int $entryId, array $valuesByFieldId, string $locale)
    {

    }

    public function deleteEntry(int $entryId): bool
    {

    }

    public function migrateFieldToTranslatable(int $fieldId, string $primaryLocale)
    {
        // TODO:
        // create column in localized table
        // for each record:
        //   select value from base table
        //   insert into localized table with value from base table for the primary locale
        // delete column from base table
    }

    public function migrateFieldToNonTranslatable(int $fieldId, string $primaryLocale)
    {
        // TODO:
        // create column in base table
        // for each record:
        //   select value for primary locale from localized table
        //   update base table with value from localized table
        // delete column from localized table
    }

    // API Methods
    public function getSingletonByName(string $name, ?array $locales = null, ?array $extraLocales = null, ?array $fieldFilter = null): ?array
    {

    }

    public function getCollectionByName(string $name, ?array $locales = null, int $limit = 100, int $offset = 0, ?array $extraLocales = null, ?array $fieldFilter = null, ?array $valueFilter = null, ?array $sort = null): array
    {

    }

    public function getCollectionTotalCount(string $name, ?array $valueFilter = null): int
    {

    }

    private function determineFilterLocale(int $contentTypeId, int $fieldId, ?string $overrideLocale, ?array $requestedLocales): string
    {
        if ($overrideLocale !== null && $overrideLocale !== '') {
            return $overrideLocale;
        }
        if (is_array($requestedLocales) && count($requestedLocales) > 0) {
            return (string)$requestedLocales[0];
        }
        // default to first configured CMS locale or empty string
        return CMS_LOCALES[0] ?? '';
    }


    // Asset Methods

    /**
     * Create asset record
     */
    public function createAsset(string $filename, string $path, ?string $mimeType, ?int $size, string $directory = ''): int
    {
        $stmt = $this->connection->prepare("INSERT INTO assets (filename, path, directory, mime_type, size) VALUES (:filename, :path, :directory, :mime, :size)");
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':path', $path);
        $stmt->bindParam(':directory', $directory);
        $stmt->bindParam(':mime', $mimeType);
        $stmt->bindParam(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->connection->lastInsertId();
    }

    /**
     * Get all assets
     */
    public function getAssets(?string $directory = null): array
    {
        if ($directory !== null) {
            $stmt = $this->connection->prepare("SELECT id, filename, path, directory, mime_type, size, created_at FROM assets WHERE directory = :dir ORDER BY created_at DESC");
            $stmt->bindParam(':dir', $directory);
            $stmt->execute();
            return $stmt->fetchAll();
        }
        $stmt = $this->connection->query("SELECT id, filename, path, directory, mime_type, size, created_at FROM assets ORDER BY directory ASC, created_at DESC");
        return $stmt->fetchAll();
    }

    /**
     * Get all unique directories
     */
    public function getAssetDirectories(): array
    {
        $stmt = $this->connection->query("SELECT DISTINCT directory FROM assets ORDER BY directory ASC");
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $results;
    }

    /**
     * Get asset by ID
     */
    public function getAssetById(int $id): ?array
    {
        $stmt = $this->connection->prepare("SELECT id, filename, path, directory, mime_type, size, created_at FROM assets WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Update asset path and directory
     */
    public function updateAssetPath(int $id, string $path, string $directory): bool
    {
        $stmt = $this->connection->prepare("UPDATE assets SET path = :path, directory = :directory WHERE id = :id");
        $stmt->bindParam(':path', $path);
        $stmt->bindParam(':directory', $directory);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Rename a directory (recursively updates all assets whose directory is the target or inside it)
     * @param string $oldDir
     * @param string $newDir
     */
    public function renameAssetDirectory(string $oldDir, string $newDir): void
    {
        $oldDir = trim($oldDir, '/');
        $newDir = trim($newDir, '/');
        if ($oldDir === '' || $newDir === '' || $oldDir === $newDir) {
            return; // nothing to do
        }
        $this->connection->beginTransaction();
        try {
            $stmt = $this->connection->prepare("SELECT id, path, directory FROM assets WHERE directory = :dir OR directory LIKE :dirlike");
            $stmt->execute([
                ':dir' => $oldDir,
                ':dirlike' => $oldDir . '/%'
            ]);
            $rows = $stmt->fetchAll();
            if ($rows) {
                $update = $this->connection->prepare("UPDATE assets SET path = :path, directory = :directory WHERE id = :id");
                $prefixOld = $oldDir . '/';
                $prefixNew = $newDir . '/';
                foreach ($rows as $r) {
                    $id = (int)$r['id'];
                    $dir = $r['directory'];
                    $path = $r['path'];
                    // compute new directory
                    if ($dir === $oldDir) {
                        $newDirectory = $newDir;
                    } elseif (strpos($dir, $prefixOld) === 0) {
                        $newDirectory = $prefixNew . substr($dir, strlen($prefixOld));
                    } else {
                        $newDirectory = $dir; // fallback (should not happen)
                    }
                    // compute new path
                    if (strpos($path, $prefixOld) === 0) {
                        $newPath = $prefixNew . substr($path, strlen($prefixOld));
                    } elseif ($path === $oldDir) { // rare edge
                        $newPath = $newDir;
                    } else {
                        // If path did not include oldDir (unexpected), keep as is
                        $newPath = $path;
                    }
                    $update->execute([
                        ':path' => $newPath,
                        ':directory' => $newDirectory,
                        ':id' => $id
                    ]);
                }
            }
            $this->connection->commit();
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Delete asset
     */
    public function deleteAsset(int $id): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM assets WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Search assets by partial filename or path. Empty query returns paginated list.
     * @param string $query Partial match string
     * @param int $limit Max items to return (1-200)
     * @param int $offset Offset for pagination
     * @param string $filter Filter type: 'all', 'images', 'other'
     * @return array{total:int,limit:int,offset:int,items:array<int,array{id:int,filename:string,path:string,directory:string|null,mime_type:?string,size:?int,created_at:string}>}
     */
    public function searchAssets(string $query, int $limit = 50, int $offset = 0, string $filter = 'all'): array
    {
        $query = trim($query);

        // Build MIME type filter
        $mimeCondition = $this->buildMimeFilterCondition($filter);

        if ($query === '') {
            // Fallback to simple listing with pagination
            $sql = "SELECT id, filename, path, directory, mime_type, size, created_at FROM assets";
            if ($mimeCondition) $sql .= " WHERE $mimeCondition";
            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        } else {
            $like = '%' . $query . '%';
            $sql = "SELECT id, filename, path, directory, mime_type, size, created_at FROM assets WHERE (filename LIKE :like OR path LIKE :like)";
            if ($mimeCondition) $sql .= " AND ($mimeCondition)";
            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':like', $like, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }
        // total count for query
        if ($query === '') {
            $sql = "SELECT COUNT(*) FROM assets";
            if ($mimeCondition) $sql .= " WHERE $mimeCondition";
            $countStmt = $this->connection->query($sql);
            $total = (int)$countStmt->fetchColumn();
        } else {
            $sql = "SELECT COUNT(*) FROM assets WHERE (filename LIKE :like OR path LIKE :like)";
            if ($mimeCondition) $sql .= " AND ($mimeCondition)";
            $countStmt = $this->connection->prepare($sql);
            $like = '%' . $query . '%';
            $countStmt->bindParam(':like', $like, PDO::PARAM_STR);
            $countStmt->execute();
            $total = (int)$countStmt->fetchColumn();
        }
        return [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'items' => $rows,
        ];
    }

    /**
     * Paginated assets within a directory (empty string for root directory assets).
     * @param string $directory
     * @param int $limit
     * @param int $offset
     * @param string $filter Filter type: 'all', 'images', 'other'
     * @return array{total:int,limit:int,offset:int,items:array<int,array{id:int,filename:string,path:string,directory:string|null,mime_type:?string,size:?int,created_at:string}>}
     */
    public function getAssetsPaged(string $directory, int $limit = 50, int $offset = 0, string $filter = 'all'): array {
        $directory = trim($directory);

        // Build MIME type filter
        $mimeCondition = $this->buildMimeFilterCondition($filter);

        $sql = "SELECT COUNT(*) FROM assets WHERE directory = :dir";
        if ($mimeCondition) $sql .= " AND ($mimeCondition)";
        $countStmt = $this->connection->prepare($sql);
        $countStmt->bindParam(':dir', $directory);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT id, filename, path, directory, mime_type, size, created_at FROM assets WHERE directory = :dir";
        if ($mimeCondition) $sql .= " AND ($mimeCondition)";
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':dir', $directory);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return [ 'total' => $total, 'limit' => $limit, 'offset' => $offset, 'items' => $rows ];
    }

    /**
     * Build SQL condition for MIME type filtering
     * @param string $filter 'all', 'images', or 'other'
     * @return string SQL condition or empty string
     */
    private function buildMimeFilterCondition(string $filter): string {
        if ($filter === 'images') {
            return "mime_type LIKE 'image/%'";
        } elseif ($filter === 'other') {
            return "(mime_type IS NULL OR mime_type NOT LIKE 'image/%')";
        }
        return ''; // 'all' - no filter
    }

    public function getAllUsers(): array {
        $stmt = $this->connection->query("SELECT id, username, role FROM users ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function getUserById(int $id): ?array {
        $stmt = $this->connection->prepare("SELECT id, username, password, role FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateUser(int $id, string $username, ?string $newPassword, ?string $newRole, bool $allowRoleChange): bool {
        $current = $this->getUserById($id);
        if (!$current) { throw new InvalidArgumentException('User not found'); }
        $username = trim($username);
        if ($username === '') { throw new InvalidArgumentException('Username required'); }
        $roleToSet = $allowRoleChange && $newRole !== null ? $newRole : $current['role'];
        $passToSet = $current['password'];
        if ($newPassword !== null && $newPassword !== '') {
            $passToSet = password_hash($newPassword, PASSWORD_BCRYPT);
        }
        $stmt = $this->connection->prepare("UPDATE users SET username = :username, password = :password, role = :role WHERE id = :id");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $passToSet);
        $stmt->bindParam(':role', $roleToSet);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteUser(int $id): bool {
        $stmt = $this->connection->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
