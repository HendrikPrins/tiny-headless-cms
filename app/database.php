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

    public function getCollections()
    {
        $sql = "
            SELECT
                ct.id,
                ct.name,
                (SELECT COUNT(*) FROM fields f WHERE f.content_type_id = ct.id)   AS fields_count,
                (SELECT COUNT(*) FROM entries e WHERE e.content_type_id = ct.id)  AS entries_count
            FROM content_types ct
            WHERE ct.is_singleton = 0
            ORDER BY ct.name
        ";
        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll();
    }

    public function createCollectionType(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Name is required');
        }

        $stmt = $this->connection->prepare(
            "INSERT INTO content_types (name, is_singleton) VALUES (:name, 0)"
        );
        $stmt->bindParam(':name', $name);
        $stmt->execute();

        return (int)$this->connection->lastInsertId();
    }

    // New: list all content types (collections and singletons)
    public function getContentTypes()
    {
        $sql = "
            SELECT
                ct.id,
                ct.name,
                ct.is_singleton,
                (SELECT COUNT(*) FROM fields f WHERE f.content_type_id = ct.id)   AS fields_count,
                (SELECT COUNT(*) FROM entries e WHERE e.content_type_id = ct.id)  AS entries_count,
                CASE WHEN ct.is_singleton = 1 THEN (
                    SELECT e.id FROM entries e WHERE e.content_type_id = ct.id ORDER BY e.id ASC LIMIT 1
                ) ELSE NULL END AS singleton_entry_id
            FROM content_types ct
            ORDER BY ct.name
        ";
        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll();
    }

    // New: create content type with singleton flag
    public function createContentType(string $name, bool $isSingleton): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Name is required');
        }
        $is = $isSingleton ? 1 : 0;
        $stmt = $this->connection->prepare(
            "INSERT INTO content_types (name, is_singleton) VALUES (:name, :is_singleton)"
        );
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':is_singleton', $is, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->connection->lastInsertId();
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
        $stmt = $this->connection->prepare("UPDATE content_types SET name = :name WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Fetch a single collection/content type by id
    public function getContentType(int $id)
    {
        $stmt = $this->connection->prepare("SELECT id, name, is_singleton FROM content_types WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Fetch fields for a given content type, ordered by `order` then id
    public function getFieldsForContentType(int $contentTypeId)
    {
        $stmt = $this->connection->prepare("SELECT id, name, field_type, is_required, is_translatable, `order` FROM fields WHERE content_type_id = :ctid ORDER BY `order` ASC, id ASC");
        $stmt->bindParam(':ctid', $contentTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Create a field and return its new id
    public function createField(int $contentTypeId, string $name, string $field_type, bool $is_required = false, bool $is_translatable = false, int $order = 0): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Field name is required');
        }
        if (!FieldRegistry::isValidType($field_type)) {
            throw new InvalidArgumentException('Invalid field type');
        }

        $stmt = $this->connection->prepare("INSERT INTO fields (content_type_id, name, field_type, is_required, is_translatable, `order`) VALUES (:ctid, :name, :ftype, :req, :trans, :ord)");
        $req = $is_required ? 1 : 0;
        $trans = $is_translatable ? 1 : 0;
        $stmt->bindParam(':ctid', $contentTypeId, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':ftype', $field_type);
        $stmt->bindParam(':req', $req, PDO::PARAM_INT);
        $stmt->bindParam(':trans', $trans, PDO::PARAM_INT);
        $stmt->bindParam(':ord', $order, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->connection->lastInsertId();
    }

    // Update an existing field
    public function updateField(int $id, string $name, string $field_type, bool $is_required = false, bool $is_translatable = false, int $order = 0): bool
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Field name is required');
        }
        if (!FieldRegistry::isValidType($field_type)) {
            throw new InvalidArgumentException('Invalid field type');
        }

        $stmt = $this->connection->prepare("UPDATE fields SET name = :name, field_type = :ftype, is_required = :req, is_translatable = :trans, `order` = :ord WHERE id = :id");
        $req = $is_required ? 1 : 0;
        $trans = $is_translatable ? 1 : 0;
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':ftype', $field_type);
        $stmt->bindParam(':req', $req, PDO::PARAM_INT);
        $stmt->bindParam(':trans', $trans, PDO::PARAM_INT);
        $stmt->bindParam(':ord', $order, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Delete a field by id
    public function deleteField(int $id): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM fields WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Delete a content type (CASCADE will delete fields, entries, and field_values)
    public function deleteContentType(int $id): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM content_types WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // List entries for a content type
    public function getEntriesForContentType(int $contentTypeId)
    {
        $stmt = $this->connection->prepare("SELECT id FROM entries WHERE content_type_id = :ctid ORDER BY id DESC");
        $stmt->bindParam(':ctid', $contentTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getEntryCountForContentType(int $contentTypeId): int
    {
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM entries WHERE content_type_id = :ctid");
        $stmt->bindParam(':ctid', $contentTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function getEntryById(int $entryId)
    {
        $stmt = $this->connection->prepare("SELECT id, content_type_id FROM entries WHERE id = :id");
        $stmt->bindParam(':id', $entryId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Map of field_id => value for locale NULL
    public function getFieldValuesForEntry(int $entryId, string $locale): array
    {
        $stmt = $this->connection->prepare("SELECT field_id, value FROM field_values WHERE entry_id = :eid AND locale = :loc");
        $stmt->bindParam(':eid', $entryId, PDO::PARAM_INT);
        $stmt->bindParam(':loc', $locale);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[(int)$r['field_id']] = $r['value']; }
        return $out;
    }

    public function createEntry(int $contentTypeId): int
    {
        $stmt = $this->connection->prepare("INSERT INTO entries (content_type_id) VALUES (:ctid)");
        $stmt->bindParam(':ctid', $contentTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->connection->lastInsertId();
    }

    // Save or update values for locale NULL
    public function saveEntryValues(int $entryId, array $valuesByFieldId, string $locale)
    {
        if (empty($valuesByFieldId)) {
            return;
        }

        // Get field information to determine field types
        $fieldIds = array_keys($valuesByFieldId);
        $placeholders = implode(',', array_fill(0, count($fieldIds), '?'));
        $stmt = $this->connection->prepare("SELECT id, field_type FROM fields WHERE id IN ({$placeholders})");
        $stmt->execute(array_values($fieldIds));
        $fieldTypes = [];
        while ($row = $stmt->fetch()) {
            $fieldTypes[(int)$row['id']] = $row['field_type'];
        }

        // Use upsert
        $sql = "INSERT INTO field_values (entry_id, field_id, locale, value) VALUES (:eid, :fid, :loc, :val)
                ON DUPLICATE KEY UPDATE value = VALUES(value)";
        $stmt = $this->connection->prepare($sql);
        foreach ($valuesByFieldId as $fieldId => $val) {
            $fid = (int)$fieldId;

            // Convert value to DB format using FieldType
            $dbValue = $val;
            if (isset($fieldTypes[$fid])) {
                $fieldTypeObj = FieldRegistry::get($fieldTypes[$fid]);
                if ($fieldTypeObj !== null) {
                    $dbValue = $fieldTypeObj->saveToDb($val);
                }
            }

            $stmt->bindParam(':eid', $entryId, PDO::PARAM_INT);
            $stmt->bindParam(':fid', $fid, PDO::PARAM_INT);
            $stmt->bindParam(':loc', $locale);
            $stmt->bindParam(':val', $dbValue);
            $stmt->execute();
        }
    }

    public function deleteEntry(int $entryId): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM entries WHERE id = :id");
        $stmt->bindParam(':id', $entryId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function migrateFieldToTranslatable(int $fieldId, string $primaryLocale)
    {
        // Copy value from locale='' to primary locale if not already present
        $stmt = $this->connection->prepare("
        INSERT INTO field_values (entry_id, field_id, locale, value)
        SELECT entry_id, field_id, :loc, value
        FROM field_values
        WHERE field_id = :fid AND locale = ''
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
        $stmt->bindParam(':fid', $fieldId, PDO::PARAM_INT);
        $stmt->bindParam(':loc', $primaryLocale);
        $stmt->execute();

        // Delete all locale='' values for this field
        $stmt = $this->connection->prepare("DELETE FROM field_values WHERE field_id = :fid AND locale = ''");
        $stmt->bindParam(':fid', $fieldId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function migrateFieldToNonTranslatable(int $fieldId, string $primaryLocale)
    {
        // Copy value from primary locale to locale='' if not already present
        $stmt = $this->connection->prepare("
        INSERT INTO field_values (entry_id, field_id, locale, value)
        SELECT entry_id, field_id, '', value
        FROM field_values
        WHERE field_id = :fid AND locale = :loc
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
        $stmt->bindParam(':fid', $fieldId, PDO::PARAM_INT);
        $stmt->bindParam(':loc', $primaryLocale);
        $stmt->execute();

        // Delete all locale-specific values for this field
        $stmt = $this->connection->prepare("DELETE FROM field_values WHERE field_id = :fid AND locale != ''");
        $stmt->bindParam(':fid', $fieldId, PDO::PARAM_INT);
        $stmt->execute();
    }

    // API Methods

    /**
     * Get singleton data by content type name
     * @param string $name Content type name
     * @param array|null $locales Array of locales to fetch, null for all
     * @return array|null Singleton data or null if not found
     */
    public function getSingletonByName(string $name, ?array $locales = null, ?array $extraLocales = null): ?array
    {
        // Get content type
        $stmt = $this->connection->prepare("SELECT id, name FROM content_types WHERE name = :name AND is_singleton = 1");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $contentType = $stmt->fetch();

        if (!$contentType) {
            return null;
        }

        // Get the singleton entry
        $stmt = $this->connection->prepare("SELECT id FROM entries WHERE content_type_id = :ctid LIMIT 1");
        $stmt->bindParam(':ctid', $contentType['id'], PDO::PARAM_INT);
        $stmt->execute();
        $entry = $stmt->fetch();

        if (!$entry) {
            return null;
        }

        // Get fields
        $fields = $this->getFieldsForContentType($contentType['id']);

        // Get field values
        $data = $this->buildEntryData($entry['id'], $fields, $locales, $extraLocales);

        return $data;
    }

    /**
     * Get collection entries by content type name
     * @param string $name Content type name
     * @param array|null $locales Array of locales to fetch, null for all
     * @param int $limit Maximum number of entries to return
     * @param int $offset Number of entries to skip
     * @return array Array of entries
     */
    public function getCollectionByName(string $name, ?array $locales = null, int $limit = 100, int $offset = 0, ?array $extraLocales = null): array
    {
        // Get content type
        $stmt = $this->connection->prepare("SELECT id, name FROM content_types WHERE name = :name AND is_singleton = 0");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $contentType = $stmt->fetch();

        if (!$contentType) {
            return [];
        }

        // Get entries with limit and offset
        $stmt = $this->connection->prepare("SELECT id FROM entries WHERE content_type_id = :ctid ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindParam(':ctid', $contentType['id'], PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $entries = $stmt->fetchAll();

        if (empty($entries)) {
            return [];
        }

        // Get fields
        $fields = $this->getFieldsForContentType($contentType['id']);

        // Build data for each entry
        $result = [];
        foreach ($entries as $entry) {
            $result[] = $this->buildEntryData($entry['id'], $fields, $locales, $extraLocales);
        }

        return $result;
    }

    /**
     * Get total count of entries for a collection by content type name
     * @param string $name Content type name
     * @return int Total number of entries
     */
    public function getCollectionTotalCount(string $name): int
    {
        $stmt = $this->connection->prepare("SELECT id FROM content_types WHERE name = :name AND is_singleton = 0");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $contentType = $stmt->fetch();
        if (!$contentType) {
            return 0;
        }
        $stmt = $this->connection->prepare("SELECT COUNT(*) as total FROM entries WHERE content_type_id = :ctid");
        $stmt->bindParam(':ctid', $contentType['id'], PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (int)$row['total'] : 0;
    }

    /**
     * Build entry data with field values
     * @param int $entryId Entry ID
     * @param array $fields Array of field definitions
     * @param array|null $locales Array of locales to fetch, null for all
     * @return array Entry data with id and field values
     */
    private function buildEntryData(int $entryId, array $fields, ?array $locales = null, ?array $extraLocales = null): array
    {
        $data = ['id' => $entryId];

        // Map field names to IDs and types for quick lookup
        $fieldNameToId = [];
        $fieldIdToMeta = [];
        foreach ($fields as $f) {
            $fid = (int)$f['id'];
            $fname = $f['name'];
            $fieldNameToId[$fname] = $fid;
            $fieldIdToMeta[$fid] = $f;
        }

        // Determine locale set for main data (per field)
        $mainLocales = [];
        if ($locales !== null) {
            foreach ($locales as $loc) {
                $loc = (string)$loc;
                if ($loc !== '') {
                    $mainLocales[$loc] = true;
                }
            }
        } else {
            // If no locales param, we default to all configured locales for main data
            foreach (CMS_LOCALES as $loc) {
                $loc = (string)$loc;
                if ($loc !== '') {
                    $mainLocales[$loc] = true;
                }
            }
        }

        // Build a set of required (field_id, locale) combinations
        $required = [];
        // Always need non-translatable bucket ('') for non-translatable fields
        foreach ($fields as $f) {
            $fid = (int)$f['id'];
            $isTranslatable = (bool)$f['is_translatable'];
            if (!$isTranslatable) {
                $required[$fid][''] = true;
            } else {
                // For translatable fields, add main locales
                foreach (array_keys($mainLocales) as $loc) {
                    $required[$fid][$loc] = true;
                }
            }
        }

        // Add extraLocales requirements (per field name)
        if ($extraLocales !== null) {
            foreach ($extraLocales as $fieldName => $spec) {
                if (!isset($fieldNameToId[$fieldName])) continue;
                $fid = $fieldNameToId[$fieldName];
                $wanted = [];
                if ($spec === '*') {
                    $wanted = CMS_LOCALES;
                } elseif (is_array($spec)) {
                    $wanted = $spec;
                }
                foreach ($wanted as $loc) {
                    $loc = (string)$loc;
                    if ($loc === '') continue;
                    $required[$fid][$loc] = true;
                }
            }
        }

        // If nothing specific required (edge case), keep existing behavior: fetch all
        if (empty($required)) {
            $sql = "SELECT field_id, locale, value FROM field_values WHERE entry_id = :eid";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(':eid', $entryId, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            // Build a single query using IN on (field_id, locale) pairs
            $parts = [];
            $params = [':eid' => $entryId];
            $i = 0;
            foreach ($required as $fid => $localesForField) {
                foreach (array_keys($localesForField) as $loc) {
                    $fidKey = ":f{$i}";
                    $locKey = ":l{$i}";
                    $parts[] = "(field_id = {$fidKey} AND locale = {$locKey})";
                    $params[$fidKey] = (int)$fid;
                    $params[$locKey] = (string)$loc;
                    $i++;
                }
            }
            $wherePairs = implode(' OR ', $parts);
            $sql = "SELECT field_id, locale, value FROM field_values WHERE entry_id = :eid AND ({$wherePairs})";
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $val) {
                if ($key === ':eid') {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
            }
            $stmt->execute();
        }

        $values = $stmt->fetchAll();

        // Organize values by field id and locale for quick lookup
        $byField = [];
        foreach ($values as $val) {
            $fid = (int)$val['field_id'];
            $loc = (string)$val['locale'];
            $byField[$fid][$loc] = $val['value'];
        }

        $extraOut = [];

        foreach ($fields as $field) {
            $fieldId = (int)$field['id'];
            $fieldName = $field['name'];
            $fieldType = $field['field_type'];
            $isTranslatable = (bool)$field['is_translatable'];

            // Main data
            if ($isTranslatable) {
                $obj = new stdClass();
                if (isset($byField[$fieldId])) {
                    foreach ($byField[$fieldId] as $loc => $rawVal) {
                        if ($loc === '') continue; // skip non-translatable bucket
                        $obj->$loc = $this->castValue($rawVal, $fieldType);
                    }
                }
                $data[$fieldName] = $obj;
            } else {
                $value = null;
                if (isset($byField[$fieldId][''])) {
                    $value = $this->castValue($byField[$fieldId][''], $fieldType);
                }
                $data[$fieldName] = $value;
            }

            // Extra locales per field
            if ($extraLocales !== null && array_key_exists($fieldName, $extraLocales)) {
                $spec = $extraLocales[$fieldName];
                $wantedLocales = [];
                if ($spec === '*') {
                    $wantedLocales = CMS_LOCALES;
                } elseif (is_array($spec)) {
                    $wantedLocales = $spec;
                }

                if (!empty($wantedLocales)) {
                    foreach ($wantedLocales as $loc) {
                        $loc = (string)$loc;
                        if (!isset($extraOut[$fieldName])) {
                            $extraOut[$fieldName] = [];
                        }
                        if (isset($byField[$fieldId][$loc])) {
                            $extraOut[$fieldName][$loc] = $this->castValue($byField[$fieldId][$loc], $fieldType);
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Cast value to appropriate type based on field type using FieldRegistry
     * @param mixed $value The value to cast
     * @param string $fieldType The field type
     * @return mixed The casted value
     */
    private function castValue($value, string $fieldType)
    {
        if ($value === null) {
            return null;
        }

        $fieldTypeObj = FieldRegistry::get($fieldType);
        if ($fieldTypeObj === null) {
            return $value;
        }
        return $fieldTypeObj->serializeToJson($fieldTypeObj->readFromDb((string)$value));
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
