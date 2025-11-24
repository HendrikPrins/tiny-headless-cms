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
        $data = $db->getSingletonByName($singletonName, $locales);

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
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        if ($limit < 1) $limit = 100;
        if ($limit > 1000) $limit = 1000;
        if ($offset < 0) $offset = 0;

        $data = $db->getCollectionByName($collectionName, $locales, $limit, $offset);
        $total = $db->getCollectionTotalCount($collectionName);

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
        'singleton' => 'api.php?singleton=<name>&locale=<locale>',
        'collection' => 'api.php?collection=<name>&locale=<locale>&limit=<limit>&offset=<offset>'
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
