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
        $allowed = ['string', 'text', 'integer', 'decimal', 'boolean'];
        if (!in_array($field_type, $allowed, true)) {
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
        $allowed = ['string', 'text', 'integer', 'decimal', 'boolean'];
        if (!in_array($field_type, $allowed, true)) {
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
        // Use upsert
        $sql = "INSERT INTO field_values (entry_id, field_id, locale, value) VALUES (:eid, :fid, :loc, :val)
                ON DUPLICATE KEY UPDATE value = VALUES(value)";
        $stmt = $this->connection->prepare($sql);
        foreach ($valuesByFieldId as $fieldId => $val) {
            $fid = (int)$fieldId;
            $stmt->bindParam(':eid', $entryId, PDO::PARAM_INT);
            $stmt->bindParam(':fid', $fid, PDO::PARAM_INT);
            $stmt->bindParam(':loc', $locale);
            $stmt->bindParam(':val', $val);
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
    public function getSingletonByName(string $name, ?array $locales = null): ?array
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
        $data = $this->buildEntryData($entry['id'], $fields, $locales);

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
    public function getCollectionByName(string $name, ?array $locales = null, int $limit = 100, int $offset = 0): array
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
            $result[] = $this->buildEntryData($entry['id'], $fields, $locales);
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
    private function buildEntryData(int $entryId, array $fields, ?array $locales = null): array
    {
        $data = ['id' => $entryId];

        // Build WHERE clause for locales
        if ($locales === null) {
            // Fetch all locales
            $localeWhere = "1=1";
            $params = [':eid' => $entryId];
        } else {
            // Fetch specific locales + non-translatable (locale='')
            $localeList = array_merge([''], $locales); // Always include empty string for non-translatable
            $placeholders = [];
            $params = [':eid' => $entryId];
            foreach ($localeList as $i => $loc) {
                $key = ":loc{$i}";
                $placeholders[] = $key;
                $params[$key] = $loc;
            }
            $localeWhere = "locale IN (" . implode(',', $placeholders) . ")";
        }

        $sql = "SELECT field_id, locale, value FROM field_values WHERE entry_id = :eid AND {$localeWhere}";
        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $values = $stmt->fetchAll();

        // Organize values by field
        foreach ($fields as $field) {
            $fieldId = $field['id'];
            $fieldName = $field['name'];
            $isTranslatable = (bool)$field['is_translatable'];

            if ($isTranslatable) {
                // For translatable fields, create an object with locale keys
                $data[$fieldName] = new stdClass();
                foreach ($values as $val) {
                    if ((int)$val['field_id'] === $fieldId && $val['locale'] !== '') {
                        $locale = $val['locale'];
                        $data[$fieldName]->$locale = $this->castValue($val['value'], $field['field_type']);
                    }
                }
            } else {
                // For non-translatable fields, use the value directly
                foreach ($values as $val) {
                    if ((int)$val['field_id'] === $fieldId && $val['locale'] === '') {
                        $data[$fieldName] = $this->castValue($val['value'], $field['field_type']);
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Cast value to appropriate type based on field type
     * @param mixed $value The value to cast
     * @param string $fieldType The field type
     * @return mixed The casted value
     */
    private function castValue($value, string $fieldType)
    {
        if ($value === null) {
            return null;
        }

        switch ($fieldType) {
            case 'integer':
                return (int)$value;
            case 'decimal':
                return (float)$value;
            case 'boolean':
                return (bool)$value;
            case 'string':
            case 'text':
            default:
                return $value;
        }
    }

    // Asset Methods

    /**
     * Create asset record
     */
    public function createAsset(string $filename, string $path, ?string $mimeType, ?int $size): int
    {
        $stmt = $this->connection->prepare("INSERT INTO assets (filename, path, mime_type, size) VALUES (:filename, :path, :mime, :size)");
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':path', $path);
        $stmt->bindParam(':mime', $mimeType);
        $stmt->bindParam(':size', $size, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->connection->lastInsertId();
    }

    /**
     * Get all assets
     */
    public function getAssets(): array
    {
        $stmt = $this->connection->query("SELECT id, filename, path, mime_type, size, created_at FROM assets ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    /**
     * Get asset by ID
     */
    public function getAssetById(int $id): ?array
    {
        $stmt = $this->connection->prepare("SELECT id, filename, path, mime_type, size, created_at FROM assets WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
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
}
