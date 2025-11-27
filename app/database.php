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
            $stmt = $this->connection->query("SHOW TABLES LIKE 'cms_users'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function hasAdminUser() {
        try {
            $stmt = $this->connection->query("SELECT COUNT(*) FROM cms_users WHERE role = 'admin'");
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function createUser($username, $password, $role) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->connection->prepare("INSERT INTO cms_users (username, password, role) VALUES (:username, :password, :role)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':role', $role);
        return $stmt->execute();
    }

    public function getUserByUsername(string $username)
    {
        $stmt = $this->connection->prepare("SELECT id, username, password, role FROM cms_users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch();
    }

    private function normalizeEditorPermissionMode(?string $mode): string
    {
        $allowed = ['read-only', 'edit-only', 'full-access'];
        if ($mode === null || $mode === '') {
            return 'read-only';
        }
        return in_array($mode, $allowed, true) ? $mode : 'read-only';
    }

    public function getContentTypes()
    {
        $stmt = $this->connection->query("SELECT id, name, is_singleton, schema, editor_permission FROM cms_content_types ORDER BY name ASC");
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['schema'] = json_decode($row['schema'], true);
            $row['editor_permission'] = $this->normalizeEditorPermissionMode($row['editor_permission'] ?? null);
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
        if (strpos($name, 'cms_') === 0 || substr($name, -10) === '_localized') {
            throw new InvalidArgumentException('Content type name cannot start with cms_ or end with _localized');
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Content type name may only contain letters, digits, and underscores, and must not start with a digit');
        }

        $tableName = $name;
        $localizedTableName = $name . '_localized';

        try {
            // Ensure name is unique in content_types
            $stmt = $this->connection->prepare('SELECT id FROM cms_content_types WHERE name = :name LIMIT 1');
            $stmt->execute([':name' => $name]);
            if ($stmt->fetchColumn() !== false) {
                throw new RuntimeException('Content type name already exists');
            }

            // Insert into content_types with empty schema
            $emptySchema = json_encode(['fields' => []], JSON_UNESCAPED_UNICODE);
            $stmt = $this->connection->prepare('INSERT INTO cms_content_types (name, is_singleton, schema, editor_permission) VALUES (:name, :is_singleton, :schema, :editor_permission)');
            $stmt->execute([
                ':name' => $name,
                ':is_singleton' => $isSingleton ? 1 : 0,
                ':schema' => $emptySchema,
                ':editor_permission' => 'read-only',
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
        if (strpos($name, 'cms_') === 0 || substr($name, -10) === '_localized') {
            throw new InvalidArgumentException('Content type name cannot start with cms_ or end with _localized');
        }

        $ct = $this->getContentType($id);

        $stmt = $this->connection->prepare("UPDATE cms_content_types SET name = :name WHERE id = :id");
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

    public function updateContentTypePreview(int $id, array $fields, ?string $orderField, ?string $orderDirection): void
    {
        $ct = $this->getContentType($id);
        $schema = $ct['schema'];
        $schema['preview'] = [
            'fields' => $fields,
            'order_field' => $orderField,
            'order_direction' => $orderDirection,
        ];
        $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE);
        $stmt = $this->connection->prepare('UPDATE cms_content_types SET schema = :schema WHERE id = :id');
        $stmt->execute([
            ':schema' => $schemaJson,
            ':id' => $id,
        ]);
    }

    public function getContentType(int $id)
    {
        $stmt = $this->connection->prepare("SELECT id, name, is_singleton, schema, editor_permission FROM cms_content_types WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $contentType = null;
        if ($row = $stmt->fetch()) {
            $row['schema'] = json_decode($row['schema'], true);
            $row['editor_permission'] = $this->normalizeEditorPermissionMode($row['editor_permission'] ?? null);
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
                if ($finalName === 'id') {
                    throw new InvalidArgumentException('Field name cannot be "id"');
                }
                if ($finalType === null || $finalType === '') {
                    throw new InvalidArgumentException('Field type is required');
                }
                if ($finalTrans === null) {
                    $finalTrans = false;
                }

                // Resolve SQL type via FieldRegistry / FieldType
                $fieldTypeObj = FieldRegistry::get($finalType);
                if (!$fieldTypeObj instanceof FieldType) {
                    throw new InvalidArgumentException('Unknown field type: ' . $finalType);
                }
                $columnType = $fieldTypeObj->getSqlType();

                // Enforce uniqueness of NEW field names within this payload
                if (isset($schemaByName[$finalName]) && (!$isExisting || $finalName !== $origName)) {
                    throw new InvalidArgumentException('Duplicate field name: ' . $finalName);
                }

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

            $currentSchema['fields'] = $newSchemaFields;

            // Persist new schema on content_types
            $schemaJson = json_encode($currentSchema, JSON_UNESCAPED_UNICODE);
            $stmt = $this->connection->prepare('UPDATE cms_content_types SET schema = :schema WHERE id = :id');
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
        $stmt = $this->connection->prepare("DELETE FROM cms_content_types WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getEntriesForContentType(array $contentType, string $locale, bool $orderFieldLocalized, string $orderField, string $orderDirection): array
    {
        $table = $contentType['name'];
        $table_localized = $table . '_localized';
        $columns_localized = [];
        foreach ($contentType['schema']['fields'] as $field) {
            if ($field['is_translatable']) {
                $columns_localized[] = 'l.' . $field['name'];
            }
        }
        $query = "SELECT b.*";
        if (!empty($columns_localized)) {
            $query .= ", " . implode(', ', $columns_localized);
        }
        $query .= " FROM {$table} b LEFT JOIN {$table_localized} l ON b.id = l.id AND l.locale = :locale WHERE 1";
        if ($orderField !== '') {
            $orderFieldEscaped = str_replace('`', '``', $orderField);
            $orderFieldTable = $orderFieldLocalized ? 'l' : 'b';
            $query .= " ORDER BY {$orderFieldTable}.`{$orderFieldEscaped}` " . ($orderDirection === 'desc' ? 'DESC' : 'ASC');
        } else {
            $query .= " ORDER BY b.id ASC";
        }
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

        // Build a map of fieldName => FieldType for this content type
        $fieldTypesByName = [];
        $schemaFields = $ct['schema']['fields'] ?? [];
        foreach ($schemaFields as $fieldDef) {
            if (!isset($fieldDef['name'], $fieldDef['type'])) {
                continue;
            }
            try {
                $fieldTypesByName[$fieldDef['name']] = FieldRegistry::get($fieldDef['type']);
            } catch (Throwable $e) {
                // If type is unknown, skip special handling and let raw value through
            }
        }

        $preparedValues = [];
        foreach ($valuesByFieldName as $fieldName => $value) {
            if (isset($fieldTypesByName[$fieldName]) && $fieldTypesByName[$fieldName] instanceof FieldType) {
                $preparedValues[$fieldName] = $fieldTypesByName[$fieldName]->saveToDb($value);
            } else {
                $preparedValues[$fieldName] = $value;
            }
        }

        // Decide target table: empty locale => base table, otherwise localized
        if ($locale === '') {
            // Base table update, one row per entry id
            $sets = [];
            foreach ($preparedValues as $fieldName => $value) {
                $col = '`' . str_replace('`', '``', $fieldName) . '`';
                $sets[] = "$col = :$fieldName";
            }
            if (empty($sets)) {
                return;
            }

            $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            foreach ($preparedValues as $fieldName => $value) {
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
            foreach ($preparedValues as $fieldName => $value) {
                $col = '`' . str_replace('`', '``', $fieldName) . '`';
                $sets[] = "$col = :$fieldName";
            }
            if (empty($sets)) {
                return;
            }

            $sql = "UPDATE `{$tableLocalized}` SET " . implode(', ', $sets) . " WHERE id = :id AND locale = :locale";
            $stmt = $this->connection->prepare($sql);
            foreach ($preparedValues as $fieldName => $value) {
                $param = ":$fieldName";
                $stmt->bindValue($param, $value);
            }
            $stmt->bindValue(':id', $entryId, PDO::PARAM_INT);
            $stmt->bindValue(':locale', $locale);
            $stmt->execute();
        }
    }

    public function deleteEntry(array $ct, int $entryId)
    {
        $stmt = $this->connection->prepare("DELETE FROM {$ct['name']} WHERE id = :id");
        $stmt->execute([':id' => $entryId]);
        $stmt = $this->connection->prepare("DELETE FROM {$ct['name']}_localized WHERE id = :id");
        $stmt->execute([':id' => $entryId]);
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
        // Find content type by name and ensure it is a singleton
        $stmt = $this->connection->prepare("SELECT id, name, is_singleton, schema FROM cms_content_types WHERE name = :name LIMIT 1");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $ct = $stmt->fetch();
        if (!$ct) {
            return null;
        }
        $ct['schema'] = json_decode($ct['schema'], true) ?: ['fields' => []];
        if (!(bool)$ct['is_singleton']) {
            return null;
        }

        $table = $ct['name'];
        $tableLocalized = $table . '_localized';

        // There should be at most one row in the base table
        $baseRow = $this->connection->query("SELECT * FROM `{$table}` LIMIT 1")->fetch();
        if (!$baseRow) {
            return null; // no data yet
        }

        $result = [
            'id' => (int)$baseRow['id'],
        ];

        // Normalize locales: null => use first configured locale if any
        if ($locales === null) {
            $locales = CMS_LOCALES;
        }

        $fieldsInSchema = $ct['schema']['fields'] ?? [];

        // Helper: should this field be included based on $fieldFilter
        $fieldFilterSet = is_array($fieldFilter) ? array_flip($fieldFilter) : null;
        $shouldInclude = static function (string $fieldName) use ($fieldFilterSet): bool {
            if ($fieldFilterSet === null) {
                return true;
            }
            return isset($fieldFilterSet[$fieldName]);
        };

        // Build FieldType map for this content type
        $fieldTypesByName = [];
        foreach ($fieldsInSchema as $fieldDef) {
            if (!isset($fieldDef['name'], $fieldDef['type'])) {
                continue;
            }
            try {
                $fieldTypesByName[$fieldDef['name']] = FieldRegistry::get($fieldDef['type']);
            } catch (Throwable $e) {
                // ignore unknown types
            }
        }

        // Load localized rows for requested locales in one go
        $localizedByLocale = [];
        if (!empty($locales)) {
            $placeholders = implode(',', array_fill(0, count($locales), '?'));
            $sql = "SELECT * FROM `{$tableLocalized}` WHERE id = ? AND locale IN ({$placeholders})";
            $params = array_merge([(int)$baseRow['id']], $locales);
            $stmtLoc = $this->connection->prepare($sql);
            $stmtLoc->execute($params);
            while ($row = $stmtLoc->fetch()) {
                $localizedByLocale[$row['locale']] = $row;
            }
        }

        $multipleLocales = count($locales) > 1;

        foreach ($fieldsInSchema as $field) {
            $fieldName = $field['name'];
            if (!$shouldInclude($fieldName)) {
                continue;
            }
            $isTrans = !empty($field['is_translatable']);
            $ft = $fieldTypesByName[$fieldName] ?? null;

            if ($isTrans) {
                if ($multipleLocales) {
                    $valueByLocale = [];
                    foreach ($locales as $loc) {
                        $row = $localizedByLocale[$loc] ?? null;
                        if ($row && array_key_exists($fieldName, $row)) {
                            $raw = $row[$fieldName];
                            $val = $ft instanceof FieldType ? $ft->serializeToJson($raw) : $raw;
                            $valueByLocale[$loc] = $val;
                        }
                    }
                    $result[$fieldName] = $valueByLocale;
                } else {
                    $loc = $locales[0] ?? null;
                    if ($loc !== null) {
                        $row = $localizedByLocale[$loc] ?? null;
                        $raw = $row[$fieldName] ?? null;
                        $val = $raw;
                        if ($ft instanceof FieldType && $raw !== null) {
                            $val = $ft->serializeToJson($raw);
                        }
                        $result[$fieldName] = $val;
                    }
                }
            } else {
                // Non-translatable: read from base table
                $raw = $baseRow[$fieldName] ?? null;
                $val = $raw;
                if ($ft instanceof FieldType && $raw !== null) {
                    $val = $ft->serializeToJson($raw);
                }
                $result[$fieldName] = $val;
            }
        }

        if (is_array($extraLocales) && !empty($extraLocales)) {
            $table = $ct['name'];
            $tableLocalized = $table . '_localized';
            $entryId = (int)$baseRow['id'];

            $extraOut = [];
            foreach ($extraLocales as $fieldName => $locSpec) {
                // Respect fieldFilter if present
                if ($fieldFilterSet !== null && !isset($fieldFilterSet[$fieldName])) {
                    continue;
                }

                // Only for translatable fields
                $fieldMeta = null;
                foreach ($fieldsInSchema as $f) {
                    if ($f['name'] === $fieldName) {
                        $fieldMeta = $f;
                        break;
                    }
                }
                if ($fieldMeta === null || empty($fieldMeta['is_translatable'])) {
                    continue;
                }

                // Determine which locales to fetch
                if ($locSpec === '*') {
                    // All locales for this field
                    $sql = "SELECT locale, `{$fieldName}` AS val FROM `{$tableLocalized}` WHERE id = :id";
                    $stmtX = $this->connection->prepare($sql);
                    $stmtX->execute([':id' => $entryId]);
                } else {
                    $list = is_array($locSpec) ? $locSpec : [$locSpec];
                    $list = array_values(array_filter(array_map('trim', $list), fn($x) => $x !== ''));
                    if (empty($list)) {
                        continue;
                    }
                    $ph = implode(',', array_fill(0, count($list), '?'));
                    $sql = "SELECT locale, `{$fieldName}` AS val FROM `{$tableLocalized}` WHERE id = ? AND locale IN ({$ph})";
                    $params = array_merge([$entryId], $list);
                    $stmtX = $this->connection->prepare($sql);
                    $stmtX->execute($params);
                }

                $fieldLocales = [];
                while ($r = $stmtX->fetch()) {
                    $fieldLocales[$r['locale']] = $r['val'];
                }
                if (!empty($fieldLocales)) {
                    $extraOut[$fieldName] = $fieldLocales;
                }
            }

            if (!empty($extraOut)) {
                $result['extraLocales'] = $extraOut;
            }
        }

        return $result;
    }

    public function getCollectionByName(string $name, ?array $locales = null, int $limit = 100, int $offset = 0, ?array $extraLocales = null, ?array $fieldFilter = null, ?array $valueFilter = null, ?array $sort = null): array
    {
        // Find content type by name and ensure it's a collection
        $stmt = $this->connection->prepare("SELECT id, name, is_singleton, schema FROM cms_content_types WHERE name = :name LIMIT 1");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $ct = $stmt->fetch();
        if (!$ct || (bool)$ct['is_singleton']) {
            return [];
        }
        $ct['schema'] = json_decode($ct['schema'], true) ?: ['fields' => []];

        $table = $ct['name'];
        $tableLocalized = $table . '_localized';
        $fieldsInSchema = $ct['schema']['fields'] ?? [];

        // Normalize locales
        if ($locales === null) {
            $locales = CMS_LOCALES;
        }
        $multipleLocales = count($locales) > 1;

        // Field filter helper
        $fieldFilterSet = is_array($fieldFilter) ? array_flip($fieldFilter) : null;
        $shouldInclude = static function (string $fieldName) use ($fieldFilterSet): bool {
            if ($fieldFilterSet === null) {
                return true;
            }
            return isset($fieldFilterSet[$fieldName]);
        };

        // Build FieldType map for this content type
        $fieldTypesByName = [];
        foreach ($fieldsInSchema as $fieldDef) {
            if (!isset($fieldDef['name'], $fieldDef['type'])) {
                continue;
            }
            try {
                $fieldTypesByName[$fieldDef['name']] = FieldRegistry::get($fieldDef['type']);
            } catch (Throwable $e) {
                // ignore unknown types
            }
        }

        // Build base query (without localized fields)
        $baseSql = "SELECT * FROM `{$table}`";
        $whereClauses = [];
        $params = [];

        // Simple filter: field == value, optionally on a specific locale
        if ($valueFilter && !empty($valueFilter['field']) && array_key_exists('value', $valueFilter)) {
            $filterField = $valueFilter['field'];
            $filterValue = $valueFilter['value'];
            $filterLocale = $valueFilter['locale'] ?? null;

            $fieldMeta = null;
            foreach ($fieldsInSchema as $f) {
                if ($f['name'] === $filterField) {
                    $fieldMeta = $f;
                    break;
                }
            }

            if ($fieldMeta !== null) {
                $quotedField = '`' . str_replace('`', '``', $filterField) . '`';
                if (!empty($fieldMeta['is_translatable'])) {
                    // Join localized table for filter
                    $baseSql .= " INNER JOIN `{$tableLocalized}` AS flt ON flt.id = {$table}.id";
                    $whereClauses[] = "flt.{$quotedField} = :flt_value";
                    $params[':flt_value'] = $filterValue;
                    if ($filterLocale !== null && $filterLocale !== '') {
                        $whereClauses[] = "flt.locale = :flt_locale";
                        $params[':flt_locale'] = $filterLocale;
                    }
                } else {
                    $whereClauses[] = "{$table}.{$quotedField} = :flt_value";
                    $params[':flt_value'] = $filterValue;
                }
            }
        }

        if (!empty($whereClauses)) {
            $baseSql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        // Sorting
        if ($sort && !empty($sort['field'])) {
            $sortField = $sort['field'];
            $dir = strtolower($sort['direction'] ?? 'asc');
            if ($dir !== 'asc' && $dir !== 'desc') {
                $dir = 'asc';
            }

            if ($sortField === 'id') {
                $baseSql .= " ORDER BY {$table}.id {$dir}";
            } else {
                // Find schema field
                $fieldMeta = null;
                foreach ($fieldsInSchema as $f) {
                    if ($f['name'] === $sortField) {
                        $fieldMeta = $f;
                        break;
                    }
                }
                if ($fieldMeta !== null) {
                    $quotedField = '`' . str_replace('`', '``', $sortField) . '`';
                    if (!empty($fieldMeta['is_translatable'])) {
                        // Order by localized value for first requested locale
                        $sortLocale = $locales[0] ?? (CMS_LOCALES[0] ?? '');
                        $baseSql .= " LEFT JOIN `{$tableLocalized}` AS sort_l ON sort_l.id = {$table}.id AND sort_l.locale = :sort_locale";
                        $params[':sort_locale'] = $sortLocale;
                        $baseSql .= " ORDER BY sort_l.{$quotedField} {$dir}, {$table}.id {$dir}";
                    } else {
                        $baseSql .= " ORDER BY {$table}.{$quotedField} {$dir}, {$table}.id {$dir}";
                    }
                } else {
                    $baseSql .= " ORDER BY {$table}.id {$dir}";
                }
            }
        } else {
            // Default: newest first
            $baseSql .= " ORDER BY {$table}.id DESC";
        }

        // Limit/offset
        $baseSql .= " LIMIT :limit OFFSET :offset";

        $stmt = $this->connection->prepare($baseSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }

        $result = [];

        // Collect all IDs to load localized values in batches
        $ids = array_map(static fn($r) => (int)$r['id'], $rows);

        $localizedByIdAndLocale = [];
        if (!empty($locales)) {
            $inIds = implode(',', array_fill(0, count($ids), '?'));
            $inLocales = implode(',', array_fill(0, count($locales), '?'));
            $sqlLoc = "SELECT * FROM `{$tableLocalized}` WHERE id IN ({$inIds}) AND locale IN ({$inLocales})";
            $paramsLoc = array_merge($ids, $locales);
            $stmtLoc = $this->connection->prepare($sqlLoc);
            $stmtLoc->execute($paramsLoc);
            while ($rowLoc = $stmtLoc->fetch()) {
                $localizedByIdAndLocale[(int)$rowLoc['id']][$rowLoc['locale']] = $rowLoc;
            }
        }

        foreach ($rows as $row) {
            $entry = [
                'id' => (int)$row['id'],
            ];

            foreach ($fieldsInSchema as $field) {
                $fieldName = $field['name'];
                if (!$shouldInclude($fieldName)) {
                    continue;
                }
                $isTrans = !empty($field['is_translatable']);
                $ft = $fieldTypesByName[$fieldName] ?? null;

                if ($isTrans) {
                    if ($multipleLocales) {
                        $valueByLocale = [];
                        foreach ($locales as $loc) {
                            $locRow = $localizedByIdAndLocale[(int)$row['id']][$loc] ?? null;
                            if ($locRow && array_key_exists($fieldName, $locRow)) {
                                $raw = $locRow[$fieldName];
                                $val = $ft instanceof FieldType ? $ft->serializeToJson($raw) : $raw;
                                $valueByLocale[$loc] = $val;
                            }
                        }
                        $entry[$fieldName] = $valueByLocale;
                    } else {
                        $loc = $locales[0] ?? null;
                        if ($loc !== null) {
                            $locRow = $localizedByIdAndLocale[(int)$row['id']][$loc] ?? null;
                            $raw = $locRow[$fieldName] ?? null;
                            $val = $raw;
                            if ($ft instanceof FieldType && $raw !== null) {
                                $val = $ft->serializeToJson($raw);
                            }
                            $entry[$fieldName] = $val;
                        }
                    }
                } else {
                    $raw = $row[$fieldName] ?? null;
                    $val = $raw;
                    if ($ft instanceof FieldType && $raw !== null) {
                        $val = $ft->serializeToJson($raw);
                    }
                    $entry[$fieldName] = $val;
                }
            }

            // Implement extraLocales per entry
            if (is_array($extraLocales) && !empty($extraLocales)) {
                $tableLocalized = $tableLocalized ?? ($ct['name'] . '_localized');
                $extraOut = [];

                foreach ($extraLocales as $fieldName => $locSpec) {
                    if ($fieldFilterSet !== null && !isset($fieldFilterSet[$fieldName])) {
                        continue;
                    }

                    $fieldMeta = null;
                    foreach ($fieldsInSchema as $f) {
                        if ($f['name'] === $fieldName) {
                            $fieldMeta = $f;
                            break;
                        }
                    }
                    if ($fieldMeta === null || empty($fieldMeta['is_translatable'])) {
                        continue;
                    }

                    if ($locSpec === '*') {
                        $sql = "SELECT locale, `{$fieldName}` AS val FROM `{$tableLocalized}` WHERE id = :id";
                        $stmtX = $this->connection->prepare($sql);
                        $stmtX->execute([':id' => $entry['id']]);
                    } else {
                        $list = is_array($locSpec) ? $locSpec : [$locSpec];
                        $list = array_values(array_filter(array_map('trim', $list), fn($x) => $x !== ''));
                        if (empty($list)) {
                            continue;
                        }
                        $ph = implode(',', array_fill(0, count($list), '?'));
                        $sql = "SELECT locale, `{$fieldName}` AS val FROM `{$tableLocalized}` WHERE id = ? AND locale IN ({$ph})";
                        $params = array_merge([$entry['id']], $list);
                        $stmtX = $this->connection->prepare($sql);
                        $stmtX->execute($params);
                    }

                    $fieldLocales = [];
                    while ($r = $stmtX->fetch()) {
                        $fieldLocales[$r['locale']] = $r['val'];
                    }
                    if (!empty($fieldLocales)) {
                        $extraOut[$fieldName] = $fieldLocales;
                    }
                }

                if (!empty($extraOut)) {
                    $entry['extraLocales'] = $extraOut;
                }
            }

            $result[] = $entry;
        }

        return $result;
    }

    public function getCollectionTotalCount(string $name, ?array $valueFilter = null): int
    {
        $stmt = $this->connection->prepare("SELECT id, name, is_singleton, schema FROM cms_content_types WHERE name = :name LIMIT 1");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $ct = $stmt->fetch();
        if (!$ct || (bool)$ct['is_singleton']) {
            return 0;
        }
        $ct['schema'] = json_decode($ct['schema'], true) ?: ['fields' => []];

        $table = $ct['name'];
        $tableLocalized = $table . '_localized';
        $fieldsInSchema = $ct['schema']['fields'] ?? [];

        $sql = "SELECT COUNT(*) FROM `{$table}`";
        $whereClauses = [];
        $params = [];

        if ($valueFilter && !empty($valueFilter['field']) && array_key_exists('value', $valueFilter)) {
            $filterField = $valueFilter['field'];
            $filterValue = $valueFilter['value'];
            $filterLocale = $valueFilter['locale'] ?? null;

            $fieldMeta = null;
            foreach ($fieldsInSchema as $f) {
                if ($f['name'] === $filterField) {
                    $fieldMeta = $f;
                    break;
                }
            }

            if ($fieldMeta !== null) {
                $quotedField = '`' . str_replace('`', '``', $filterField) . '`';
                if (!empty($fieldMeta['is_translatable'])) {
                    $sql .= " INNER JOIN `{$tableLocalized}` AS flt ON flt.id = {$table}.id";
                    $whereClauses[] = "flt.{$quotedField} = :flt_value";
                    $params[':flt_value'] = $filterValue;
                    if ($filterLocale !== null && $filterLocale !== '') {
                        $whereClauses[] = "flt.locale = :flt_locale";
                        $params[':flt_locale'] = $filterLocale;
                    }
                } else {
                    $whereClauses[] = "{$table}.{$quotedField} = :flt_value";
                    $params[':flt_value'] = $filterValue;
                }
            }
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // Asset Methods

    /**
     * Create asset record
     */
    public function createAsset(string $filename, string $path, ?string $mimeType, ?int $size, string $directory = ''): int
    {
        $stmt = $this->connection->prepare("INSERT INTO cms_assets (filename, path, directory, mime_type, size) VALUES (:filename, :path, :directory, :mime, :size)");
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
            $stmt = $this->connection->prepare("SELECT id, filename, path, directory, mime_type, size, created_at FROM cms_assets WHERE directory = :dir ORDER BY created_at DESC");
            $stmt->bindParam(':dir', $directory);
            $stmt->execute();
            return $stmt->fetchAll();
        }
        $stmt = $this->connection->query("SELECT id, filename, path, directory, mime_type, size, created_at FROM cms_assets ORDER BY directory ASC, created_at DESC");
        return $stmt->fetchAll();
    }

    /**
     * Get all unique directories
     */
    public function getAssetDirectories(): array
    {
        $stmt = $this->connection->query("SELECT DISTINCT directory FROM cms_assets ORDER BY directory ASC");
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $results;
    }

    /**
     * Get asset by ID
     */
    public function getAssetById(int $id): ?array
    {
        $stmt = $this->connection->prepare("SELECT id, filename, path, directory, mime_type, size, created_at FROM cms_assets WHERE id = :id");
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
        $stmt = $this->connection->prepare("UPDATE cms_assets SET path = :path, directory = :directory WHERE id = :id");
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
            $stmt = $this->connection->prepare("SELECT id, path, directory FROM cms_assets WHERE directory = :dir OR directory LIKE :dirlike");
            $stmt->execute([
                ':dir' => $oldDir,
                ':dirlike' => $oldDir . '/%'
            ]);
            $rows = $stmt->fetchAll();
            if ($rows) {
                $update = $this->connection->prepare("UPDATE cms_assets SET path = :path, directory = :directory WHERE id = :id");
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
        $stmt = $this->connection->prepare("DELETE FROM cms_assets WHERE id = :id");
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
            $sql = "SELECT id, filename, path, directory, mime_type, size, created_at FROM cms_assets";
            if ($mimeCondition) $sql .= " WHERE $mimeCondition";
            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        } else {
            $like = '%' . $query . '%';
            $sql = "SELECT id, filename, path, directory, mime_type, size, created_at FROM cms_assets WHERE (filename LIKE :like OR path LIKE :like)";
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
            $sql = "SELECT COUNT(*) FROM cms_assets";
            if ($mimeCondition) $sql .= " WHERE $mimeCondition";
            $countStmt = $this->connection->query($sql);
            $total = (int)$countStmt->fetchColumn();
        } else {
            $sql = "SELECT COUNT(*) FROM cms_assets WHERE (filename LIKE :like OR path LIKE :like)";
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

        $sql = "SELECT COUNT(*) FROM cms_assets WHERE directory = :dir";
        if ($mimeCondition) $sql .= " AND ($mimeCondition)";
        $countStmt = $this->connection->prepare($sql);
        $countStmt->bindParam(':dir', $directory);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT id, filename, path, directory, mime_type, size, created_at FROM cms_assets WHERE directory = :dir";
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
        $stmt = $this->connection->query("SELECT id, username, role FROM cms_users ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function getUserById(int $id): ?array {
        $stmt = $this->connection->prepare("SELECT id, username, password, role FROM cms_users WHERE id = :id");
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
        $stmt = $this->connection->prepare("UPDATE cms_users SET username = :username, password = :password, role = :role WHERE id = :id");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $passToSet);
        $stmt->bindParam(':role', $roleToSet);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteUser(int $id): bool {
        $stmt = $this->connection->prepare("DELETE FROM cms_users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updateContentTypeEditorPermissionMode(int $id, string $mode): void
    {
        $mode = $this->normalizeEditorPermissionMode($mode);
        $stmt = $this->connection->prepare('UPDATE cms_content_types SET editor_permission = :mode WHERE id = :id');
        $stmt->execute([
            ':mode' => $mode,
            ':id' => $id,
        ]);
    }
}
