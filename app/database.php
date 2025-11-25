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
        foreach ($rows as &$row) {
            if (!$row['is_singleton']) {
                $row['entries_count'] = (int)$this->connection->query("SELECT COUNT(*) FROM {$row['name']}")->fetchColumn();
            } else {
                $row['singleton_entry_id'] = (int)$this->connection->query("SELECT id FROM {$row['name']}")->fetchColumn();
            }
        }
        return $rows;
    }

    public function createContentType(string $name, bool $isSingleton): int
    {
        // Basic validation
        if ($name === '') {
            throw new InvalidArgumentException('Content type name must not be empty');
        }
        if (trim($name) !== $name) {
            throw new InvalidArgumentException('Content type name must not have leading or trailing whitespace');
        }
        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Content type name must be at most 255 characters');
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Content type name may only contain letters, digits, and underscores, and must not start with a digit');
        }

        $tableName = $name;
        $localizedTableName = $name . '_localized';

        try {
            // Ensure name is unique in content_types
            $stmt = $this->connection->prepare('SELECT id FROM content_types WHERE name = :name LIMIT 1');
            $stmt->execute([':name' => $name]);
            if ($stmt->fetchColumn() !== false) {
                throw new RuntimeException('Content type name already exists');
            }

            // Insert into content_types with empty schema
            $emptySchema = json_encode(['fields' => []], JSON_UNESCAPED_UNICODE);
            $stmt = $this->connection->prepare('INSERT INTO content_types (name, is_singleton, schema) VALUES (:name, :is_singleton, :schema)');
            $stmt->execute([
                ':name' => $name,
                ':is_singleton' => $isSingleton ? 1 : 0,
                ':schema' => $emptySchema,
            ]);

            $id = (int)$this->connection->lastInsertId();
            if ($id <= 0) {
                throw new RuntimeException('Failed to create content type');
            }

            // Create base table
            $sqlBase = "CREATE TABLE `{$tableName}` (\n" .
                "    id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n" .
                "    PRIMARY KEY (id)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->connection->exec($sqlBase);

            // Create localized table
            $sqlLocalized = "CREATE TABLE `{$localizedTableName}` (\n" .
                "    id INT UNSIGNED NOT NULL,\n" .
                "    locale VARCHAR(255) NOT NULL,\n" .
                "    PRIMARY KEY (id, locale)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->connection->exec($sqlLocalized);

            return $id;
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function updateContentTypeName(int $id, string $name)
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Name is required');
        }
        if (strlen($name) > 255) {
            throw new InvalidArgumentException('Name must be 255 characters or fewer');
        }

        $ct = $this->getContentType($id);

        $stmt = $this->connection->prepare("UPDATE content_types SET name = :name WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $tableName = $name;
        $localizedTableName = $name . '_localized';
        $tableNameOld = $ct['name'];
        $tableNameOld_localized = $tableNameOld . '_localized';
        $stmt = $this->connection->prepare("RENAME TABLE {$tableNameOld} TO {$tableName}, {$tableNameOld_localized} TO {$localizedTableName}");
        $stmt->execute();
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

    public function setContentTypeSchema(int $contentTypeId, $fields)
    {
        // $fields is an array of associative arrays with keys:
        // name, type, is_translatable, $name, $type, $is_translatable, optional deleted
        // - name/type/is_translatable represent the ORIGINAL values (may be null for new fields)
        // - $name/$type/$is_translatable represent the NEW values
        // Fields are matched by original name (content type id + name is unique).

        if (!is_array($fields)) {
            throw new InvalidArgumentException('Fields payload must be an array');
        }

        $ct = $this->getContentType($contentTypeId);
        if ($ct === null) {
            throw new InvalidArgumentException('Content type not found');
        }

        $tableName = $ct['name'];
        $localizedTableName = $tableName . '_localized';

        // Current schema structure in DB
        $currentSchema = $ct['schema'];
        if (!is_array($currentSchema)) {
            $currentSchema = ['fields' => []];
        }
        if (!isset($currentSchema['fields']) || !is_array($currentSchema['fields'])) {
            $currentSchema['fields'] = [];
        }

        // Index current schema by field name for quick lookup
        $schemaByName = [];
        foreach ($currentSchema['fields'] as $fieldDef) {
            if (!isset($fieldDef['field'])) {
                continue;
            }
            $schemaByName[$fieldDef['field']] = $fieldDef;
        }

        // Helper: map logical types to MySQL column types
        $mapType = function (string $logicalType): string {
            switch ($logicalType) {
                case 'string':
                    return 'VARCHAR(255)';
                case 'text':
                    return 'TEXT';
                case 'integer':
                    return 'INT';
                case 'decimal':
                    return 'DECIMAL(10,2)';
                case 'boolean':
                    return 'TINYINT(1)';
                case 'date':
                    return 'DATE';
                case 'datetime':
                    return 'DATETIME';
                default:
                    // fallback to TEXT for unknown types
                    return 'TEXT';
            }
        };

        try {
            // We will build a new schema array from the NEW values
            $newSchemaFields = [];

            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $origName = $field['name'] ?? null;
                $origType = $field['type'] ?? null;
                $origTrans = array_key_exists('is_translatable', $field) ? (bool)$field['is_translatable'] : null;

                $newName = $field['$name'] ?? null;
                $newType = $field['$type'] ?? null;
                $newTrans = array_key_exists('$is_translatable', $field) ? (bool)$field['$is_translatable'] : null;
                $deleted = !empty($field['deleted']);

                // Skip if no new name (empty row) and nothing to delete
                if (($newName === null || $newName === '') && !$deleted) {
                    continue;
                }

                // If this represents deletion of an existing field
                if ($deleted && $origName) {
                    $origNameQuoted = "`" . str_replace("`", "``", $origName) . "`";

                    if ($origTrans) {
                        // Column existed in localized table only
                        $sql = "ALTER TABLE `{$localizedTableName}` DROP COLUMN {$origNameQuoted}";
                        $this->connection->exec($sql);
                    } else {
                        // Column existed in base table only
                        $sql = "ALTER TABLE `{$tableName}` DROP COLUMN {$origNameQuoted}";
                        $this->connection->exec($sql);
                    }

                    // Also remove from schemaByName (if present)
                    unset($schemaByName[$origName]);
                    // Do NOT add to $newSchemaFields (field is gone)
                    continue;
                }

                // From here on, we are either updating an existing field or creating a new one.
                $isExisting = $origName !== null && $origName !== '';

                // Basic validation for new state
                $finalName = $newName;
                $finalType = $newType;
                $finalTrans = $newTrans;

                if ($finalName === null || $finalName === '') {
                    throw new InvalidArgumentException('Field name must not be empty');
                }
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $finalName)) {
                    throw new InvalidArgumentException('Field name may only contain letters, digits, and underscores, and must not start with a digit');
                }
                if ($finalType === null || $finalType === '') {
                    throw new InvalidArgumentException('Field type is required');
                }
                if ($finalTrans === null) {
                    $finalTrans = false;
                }

                // Enforce uniqueness of NEW field names within this payload
                if (isset($schemaByName[$finalName]) && (!$isExisting || $finalName !== $origName)) {
                    throw new InvalidArgumentException('Duplicate field name: ' . $finalName);
                }

                $columnType = $mapType($finalType);
                $finalNameQuoted = "`" . str_replace("`", "``", $finalName) . "`";

                if ($isExisting) {
                    $origNameQuoted = "`" . str_replace("`", "``", $origName) . "`";

                    // Determine where the column currently lives and where it should live
                    $wasTrans = (bool)$origTrans;
                    $willBeTrans = (bool)$finalTrans;

                    // 1) Name change (and/or type change) but same translatable flag
                    if ($wasTrans === $willBeTrans) {
                        if ($wasTrans) {
                            // localized table only
                            if ($origName !== $finalName) {
                                $sql = "ALTER TABLE `{$localizedTableName}` CHANGE COLUMN {$origNameQuoted} {$finalNameQuoted} {$columnType}";
                            } elseif ($origType !== $finalType) {
                                $sql = "ALTER TABLE `{$localizedTableName}` MODIFY COLUMN {$finalNameQuoted} {$columnType}";
                            } else {
                                $sql = null;
                            }
                        } else {
                            // base table only
                            if ($origName !== $finalName) {
                                $sql = "ALTER TABLE `{$tableName}` CHANGE COLUMN {$origNameQuoted} {$finalNameQuoted} {$columnType}";
                            } elseif ($origType !== $finalType) {
                                $sql = "ALTER TABLE `{$tableName}` MODIFY COLUMN {$finalNameQuoted} {$columnType}";
                            } else {
                                $sql = null;
                            }
                        }

                        if ($sql !== null) {
                            $this->connection->exec($sql);
                        }
                    } else {
                        // 2) Toggled translatable flag: move data between tables
                        if ($wasTrans && !$willBeTrans) {
                            // Move from localized -> base
                            // 2.1: add column to base table
                            $sql = "ALTER TABLE `{$tableName}` ADD COLUMN {$finalNameQuoted} {$columnType} NULL";
                            $this->connection->exec($sql);

                            // 2.2: copy values for some chosen/default locale (first CMS_LOCALES)
                            $primaryLocale = CMS_LOCALES[0] ?? '';
                            if ($primaryLocale !== '') {
                                $primaryLocaleParam = $primaryLocale;
                                $sqlUpdate = "UPDATE `{$tableName}` b
                                    LEFT JOIN `{$localizedTableName}` l
                                      ON b.id = l.id AND l.locale = :locale
                                    SET b.{$finalNameQuoted} = l.{$origNameQuoted}";
                                $stmt = $this->connection->prepare($sqlUpdate);
                                $stmt->execute([':locale' => $primaryLocaleParam]);
                            }

                            // 2.3: drop column from localized table
                            $sql = "ALTER TABLE `{$localizedTableName}` DROP COLUMN {$origNameQuoted}";
                            $this->connection->exec($sql);
                        } elseif (!$wasTrans && $willBeTrans) {
                            // Move from base -> localized
                            // 2.1: add column to localized table
                            $sql = "ALTER TABLE `{$localizedTableName}` ADD COLUMN {$finalNameQuoted} {$columnType} NULL";
                            $this->connection->exec($sql);

                            // 2.2: copy values from base into localized table for primary locale
                            $primaryLocale = CMS_LOCALES[0] ?? '';
                            if ($primaryLocale !== '') {
                                $primaryLocaleParam = $primaryLocale;
                                // Ensure row exists in localized table for each base row
                                $sqlInsert = "INSERT IGNORE INTO `{$localizedTableName}` (id, locale)
                                              SELECT id, :locale FROM `{$tableName}`";
                                $stmt = $this->connection->prepare($sqlInsert);
                                $stmt->execute([':locale' => $primaryLocaleParam]);

                                // Now update the new column
                                $sqlUpdate = "UPDATE `{$localizedTableName}` l
                                    JOIN `{$tableName}` b ON b.id = l.id
                                    SET l.{$finalNameQuoted} = b.{$origNameQuoted}
                                    WHERE l.locale = :locale";
                                $stmt = $this->connection->prepare($sqlUpdate);
                                $stmt->execute([':locale' => $primaryLocaleParam]);
                            }

                            // 2.3: drop column from base table
                            $sql = "ALTER TABLE `{$tableName}` DROP COLUMN {$origNameQuoted}";
                            $this->connection->exec($sql);
                        }
                    }

                    // Update schemaByName index (handle rename)
                    unset($schemaByName[$origName]);
                    $schemaByName[$finalName] = [
                        'name' => $finalName,
                        'type' => $finalType,
                        'is_translatable' => $finalTrans,
                    ];
                } else {
                    // New field: add column to appropriate table
                    if ($finalTrans) {
                        $sql = "ALTER TABLE `{$localizedTableName}` ADD COLUMN {$finalNameQuoted} {$columnType} NULL";
                        $this->connection->exec($sql);
                    } else {
                        $sql = "ALTER TABLE `{$tableName}` ADD COLUMN {$finalNameQuoted} {$columnType} NULL";
                        $this->connection->exec($sql);
                    }

                    // Add to schema index as well
                    $schemaByName[$finalName] = [
                        'name' => $finalName,
                        'type' => $finalType,
                        'is_translatable' => $finalTrans,
                    ];
                }
            }

            // Build new schema.fields array from schemaByName (preserve stable order by key)
            foreach ($schemaByName as $fieldName => $def) {
                $newSchemaFields[] = $def;
            }

            $newSchema = [
                'fields' => $newSchemaFields,
            ];

            // Persist new schema on content_types
            $schemaJson = json_encode($newSchema, JSON_UNESCAPED_UNICODE);
            $stmt = $this->connection->prepare('UPDATE content_types SET schema = :schema WHERE id = :id');
            $stmt->execute([
                ':schema' => $schemaJson,
                ':id' => $contentTypeId,
            ]);

        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function deleteContentType(int $id): bool
    {
        $ct = $this->getContentType($id);
        $stmt = $this->connection->prepare("DROP TABLE IF EXISTS {$ct['name']}");
        $stmt->execute();
        $stmt = $this->connection->prepare("DROP TABLE IF EXISTS {$ct['name']}_localized");
        $stmt->execute();
        $stmt = $this->connection->prepare("DELETE FROM content_types WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getEntriesForContentType(array $contentType, string $locale): array
    {
        $table = $contentType['name'];
        $table_localized = $table . '_localized';
        $columns_localized = [];
        foreach ($contentType['schema']['fields'] as $field) {
            if ($field['is_translatable']) {
                $columns_localized[] = 'l.' . $field['name'];
            }
        }
        $query = "SELECT b.*, " . implode(', ', $columns_localized) . " FROM {$table} b LEFT JOIN {$table_localized} l ON b.id = l.id AND l.locale = :locale WHERE 1";
        $stmt = $this->connection->prepare($query);
        $stmt->bindParam(':locale', $locale);
        $stmt->execute();
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

    public function getEntryById(array $ct, int $entryId): array
    {
        $table = $ct['name'];
        $query = "SELECT * FROM {$table} WHERE id = :id";
        $stmt = $this->connection->prepare($query);
        $stmt->bindParam(':id', $entryId);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getFieldValuesForEntry(array $contentType, int $entryId, string $locale): array
    {
        $fields = $contentType['schema']['fields'];
        $table = $locale === '' ? $contentType['name'] : $contentType['name'] . '_localized';
        $query = "SELECT * FROM `{$table}` WHERE id = :id";
        if (!empty($locale)) {
            $query .= " AND locale = :locale";
        }
        $stmt = $this->connection->prepare($query);
        $stmt->bindParam(':id', $entryId);
        if (!empty($locale)) {
            $stmt->bindParam(':locale', $locale);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        $values = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $values[$name] = $row[$name] ?? null;
        }
        return $values;
    }

    public function createEntry(array $contentType): int
    {
        $table = $contentType['name'];
        $sql = "INSERT INTO `{$table}` () VALUES ()";
        $this->connection->exec($sql);
        $id = (int)$this->connection->lastInsertId();
        if ($id <= 0) {
            throw new RuntimeException('Failed to create entry');
        }
        return $id;
    }

    /**
     * Persist values for an entry by field name.
     *
     * For non-translatable fields, pass $locale = '' and valuesByFieldName for base table columns.
     * For translatable fields, pass a real locale string and valuesByFieldName for localized table columns.
     */
    public function saveEntryValues(array $ct, int $entryId, array $valuesByFieldName, string $locale)
    {
        if (empty($valuesByFieldName)) {
            return;
        }

        $table = $ct['name'];
        $tableLocalized = $table . '_localized';

        // Decide target table: empty locale => base table, otherwise localized
        if ($locale === '') {
            // Base table update, one row per entry id
            $sets = [];
            foreach ($valuesByFieldName as $fieldName => $value) {
                $col = '`' . str_replace('`', '``', $fieldName) . '`';
                $sets[] = "$col = :$fieldName";
            }
            if (empty($sets)) {
                return;
            }

            $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            foreach ($valuesByFieldName as $fieldName => $value) {
                $param = ":$fieldName";
                $stmt->bindValue($param, $value);
            }
            $stmt->bindValue(':id', $entryId, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            // Localized table: ensure row exists, then update columns
            // Upsert row for this (id, locale)
            $sqlInsert = "INSERT IGNORE INTO `{$tableLocalized}` (id, locale) VALUES (:id, :locale)";
            $stmt = $this->connection->prepare($sqlInsert);
            $stmt->execute([
                ':id' => $entryId,
                ':locale' => $locale,
            ]);

            $sets = [];
            foreach ($valuesByFieldName as $fieldName => $value) {
                $col = '`' . str_replace('`', '``', $fieldName) . '`';
                $sets[] = "$col = :$fieldName";
            }
            if (empty($sets)) {
                return;
            }

            $sql = "UPDATE `{$tableLocalized}` SET " . implode(', ', $sets) . " WHERE id = :id AND locale = :locale";
            $stmt = $this->connection->prepare($sql);
            foreach ($valuesByFieldName as $fieldName => $value) {
                $param = ":$fieldName";
                $stmt->bindValue($param, $value);
            }
            $stmt->bindValue(':id', $entryId, PDO::PARAM_INT);
            $stmt->bindValue(':locale', $locale);
            $stmt->execute();
        }
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
