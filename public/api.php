<?php
require __DIR__ . '/../app/config.php';
$db = Database::getInstance();

header('Content-Type: application/json');

try {
    if (isset($_GET['singleton'])) {
        $singletonName = trim($_GET['singleton']);

        if (empty($singletonName)) {
            sendError(400, 'Singleton name is required');
        }

        $locales = parseLocaleParameter();
        $extraLocales = parseExtraLocalesPerField();
        $fields = parseFieldsParameter();
        $data = $db->getSingletonByName($singletonName, $locales, $extraLocales, $fields);

        if ($data === null) {
            sendError(404, 'Singleton not found');
        }

        sendResponse(['data' => $data]);
    }

    if (isset($_GET['collection'])) {
        $collectionName = trim($_GET['collection']);

        if (empty($collectionName)) {
            sendError(400, 'Collection name is required');
        }

        $locales = parseLocaleParameter();
        $extraLocales = parseExtraLocalesPerField();
        $fields = parseFieldsParameter();
        if (is_array($fields) && is_array($extraLocales) && count($fields) > 0 && count($extraLocales) > 0) {
            $fields = array_values(array_unique(array_merge($fields, array_keys($extraLocales))));
        }
        $filter = parseFilterParameter();
        $sort = parseSortParameter();
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        if ($limit < 1) $limit = 100;
        if ($limit > 1000) $limit = 1000;
        if ($offset < 0) $offset = 0;

        $data = $db->getCollectionByName($collectionName, $locales, $limit, $offset, $extraLocales, $fields, $filter, $sort);
        $total = $db->getCollectionTotalCount($collectionName, $filter);

        sendResponse([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    }

    sendError(400, 'Invalid request', [
        'singleton' => 'api.php?singleton=<name>&locale=<locale>&fields=field1,field2',
        'collection' => 'api.php?collection=<name>&locale=<locale>&limit=<limit>&offset=<offset>&fields=field1,field2'
    ]);
} catch (Exception $e) {
    sendError(500, 'Internal server error', null, $e->getMessage());
}

function parseLocaleParameter()
{
    if (!isset($_GET['locale'])) {
        return null;
    }

    $locale = $_GET['locale'];

    if (is_array($locale)) {
        $locales = [];
        foreach ($locale as $loc) {
            $loc = trim($loc);
            if (!empty($loc)) {
                $locales[] = $loc;
            }
        }
        return empty($locales) ? null : $locales;
    }

    $locale = trim($locale);
    if (empty($locale)) {
        return null;
    }

    return [$locale];
}

/**
 * Parse per-field extra locale requests from the query string.
 *
 * Supported syntaxes:
 *   extraLocales[slug]=*               // all locales for field "slug"
 *   extraLocales[title]=en,de          // specific locales (comma-separated)
 *   extraLocales[title][]=en&extraLocales[title][]=de
 *
 * Returns an associative array:
 *   [ 'slug' => '*', 'title' => ['en','de'] ]
 * or null if nothing is specified.
 */
function parseExtraLocalesPerField(): ?array
{
    if (!isset($_GET['extraLocales']) || !is_array($_GET['extraLocales'])) {
        return null;
    }

    $result = [];
    foreach ($_GET['extraLocales'] as $field => $value) {
        $field = trim((string)$field);
        if ($field === '') {
            continue;
        }

        if (is_array($value)) {
            $locales = [];
            foreach ($value as $loc) {
                $loc = trim((string)$loc);
                if ($loc !== '') {
                    $locales[] = $loc;
                }
            }
            if (!empty($locales)) {
                $result[$field] = array_values(array_unique($locales));
            }
        } else {
            $val = trim((string)$value);
            if ($val === '') {
                continue;
            }
            if ($val === '*') {
                $result[$field] = '*';
            } else {
                $locales = array_filter(array_map('trim', explode(',', $val)), fn($x) => $x !== '');
                if (!empty($locales)) {
                    $result[$field] = array_values(array_unique($locales));
                }
            }
        }
    }

    return empty($result) ? null : $result;
}

/**
 * Parse a standardized fields filter.
 *
 * Supported syntaxes:
 *   fields=title,slug,date
 *   fields[]=title&fields[]=slug
 *
 * Returns array of field names, or null to indicate no filtering.
 */
function parseFieldsParameter(): ?array
{
    if (!isset($_GET['fields'])) {
        return null;
    }

    $raw = $_GET['fields'];

    $names = [];
    if (is_array($raw)) {
        foreach ($raw as $v) {
            $v = trim((string)$v);
            if ($v !== '') {
                $names[] = $v;
            }
        }
    } else {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return null;
        }
        foreach (explode(',', $raw) as $v) {
            $v = trim($v);
            if ($v !== '') {
                $names[] = $v;
            }
        }
    }

    if (empty($names)) {
        return null;
    }

    return array_values(array_unique($names));
}

/**
 * Parse a simple field filter for collections.
 *
 * Supported syntaxes (field is required, locale optional):
 *   filter[field]=title&filter[value]=Hello
 *   filter[field]=title&filter[locale]=en&filter[value]=Hello
 *
 * Returns an associative array:
 *   [ 'field' => 'title', 'value' => 'Hello', 'locale' => 'en'|null ]
 * or null if no valid filter is present.
 */
function parseFilterParameter(): ?array
{
    if (!isset($_GET['filter']) || !is_array($_GET['filter'])) {
        return null;
    }

    $field = isset($_GET['filter']['field']) ? trim((string)$_GET['filter']['field']) : '';
    $value = isset($_GET['filter']['value']) ? (string)$_GET['filter']['value'] : '';
    $locale = isset($_GET['filter']['locale']) ? trim((string)$_GET['filter']['locale']) : null;

    if ($field === '' || $value === '') {
        return null;
    }

    return [
        'field' => $field,
        'value' => $value,
        'locale' => $locale !== '' ? $locale : null,
    ];
}

/**
 * Parse sort options for collections.
 *
 * Supported syntaxes:
 *   sort[field]=title&sort[direction]=asc
 *   sort[field]=id&sort[direction]=desc
 *
 * Returns:
 *   [ 'field' => 'title', 'direction' => 'asc'|'desc' ]
 * or null if not specified/invalid.
 */
function parseSortParameter(): ?array
{
    if (!isset($_GET['sort']) || !is_array($_GET['sort'])) {
        return null;
    }

    $field = isset($_GET['sort']['field']) ? trim((string)$_GET['sort']['field']) : '';
    $dir   = isset($_GET['sort']['direction']) ? strtolower(trim((string)$_GET['sort']['direction'])) : 'asc';

    if ($field === '') {
        return null;
    }

    if ($dir !== 'asc' && $dir !== 'desc') {
        $dir = 'asc';
    }

    return [
        'field' => $field,
        'direction' => $dir,
    ];
}

function sendResponse($data) {
    http_response_code(200);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function sendError($code, $message, $info = null, $debug = null) {
    http_response_code($code);
    $error = [
        'error' => [
            'code' => $code,
            'message' => $message
        ]
    ];
    if ($info !== null) {
        $error['error']['info'] = $info;
    }
    if ($debug !== null) {
        $error['error']['debug'] = $debug;
    }
    echo json_encode($error, JSON_PRETTY_PRINT);
    exit;
}
