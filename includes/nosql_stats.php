<?php
$mongodbConfigPath = __DIR__ . '/mongodb_config.php';

if (is_file($mongodbConfigPath)) {
    require_once $mongodbConfigPath;
}

function nosql_stats_path(): string
{
    $dir = dirname(__DIR__) . '/data';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir . '/statistiques_menus.json';
}

function nosql_int_value($value): int
{
    if (is_array($value)) {
        foreach (['$numberInt', '$numberLong', '$numberDouble', '$numberDecimal'] as $key) {
            if (isset($value[$key])) {
                return (int) $value[$key];
            }
        }
    }

    return (int) $value;
}

function nosql_float_value($value): float
{
    if (is_array($value)) {
        foreach (['$numberDouble', '$numberDecimal', '$numberInt', '$numberLong'] as $key) {
            if (isset($value[$key])) {
                return (float) $value[$key];
            }
        }
    }

    return (float) $value;
}

function nosql_order_date_value($value): string
{
    if (is_array($value) && isset($value['$date'])) {
        return substr((string) $value['$date'], 0, 10);
    }

    return (string) $value;
}

function nosql_normalize_commandes($commandes): array
{
    if (!is_array($commandes)) {
        return [];
    }

    $normalized = [];

    foreach ($commandes as $commande) {
        if (!is_array($commande)) {
            continue;
        }

        $normalized[] = [
            'date' => nosql_order_date_value($commande['date'] ?? ''),
            'montant' => nosql_float_value($commande['montant'] ?? 0),
        ];
    }

    return $normalized;
}

function nosql_normalize_stats(array $documents): array
{
    $stats = [];

    foreach ($documents as $document) {
        if (!is_array($document) || !isset($document['id_menu'])) {
            continue;
        }

        $idMenu = nosql_int_value($document['id_menu']);
        $commandes = nosql_normalize_commandes($document['commandes'] ?? []);

        $stats[(string) $idMenu] = [
            'id_menu' => $idMenu,
            'nom_menu' => (string) ($document['nom_menu'] ?? ('Menu ' . $idMenu)),
            'nombre_commandes' => isset($document['nombre_commandes'])
                ? nosql_int_value($document['nombre_commandes'])
                : count($commandes),
            'chiffre_affaires' => nosql_float_value($document['chiffre_affaires'] ?? 0),
            'commandes' => $commandes,
        ];
    }

    return $stats;
}

function mongodb_collection_name(): string
{
    if (defined('MONGODB_COLLECTION') && trim((string) MONGODB_COLLECTION) !== '') {
        return (string) MONGODB_COLLECTION;
    }

    return 'statistiques_menus';
}

function mongodb_defined_string(string $constant): string
{
    if (defined($constant)) {
        return trim((string) constant($constant));
    }

    $value = getenv($constant);

    if ($value !== false && $value !== '') {
        return trim((string) $value);
    }

    if ($constant === 'MONGODB_DATABASE') {
        $alias = getenv('MONGODB_DB');
        return $alias === false ? '' : trim((string) $alias);
    }

    return '';
}

function mongodb_driver_enabled(): bool
{
    return class_exists('\MongoDB\Driver\Manager')
        && mongodb_defined_string('MONGODB_URI') !== ''
        && mongodb_defined_string('MONGODB_DATABASE') !== '';
}

function mongodb_namespace(): string
{
    return mongodb_defined_string('MONGODB_DATABASE') . '.' . mongodb_collection_name();
}

function mongodb_driver_manager(): ?object
{
    if (!mongodb_driver_enabled()) {
        return null;
    }

    try {
        return new \MongoDB\Driver\Manager(mongodb_defined_string('MONGODB_URI'));
    } catch (\Throwable $exception) {
        return null;
    }
}

function mongodb_driver_read_stats(): ?array
{
    $manager = mongodb_driver_manager();

    if ($manager === null) {
        return null;
    }

    try {
        $query = new \MongoDB\Driver\Query([], [
            'projection' => ['_id' => 0],
            'sort' => ['nom_menu' => 1],
            'limit' => 1000,
        ]);

        $cursor = $manager->executeQuery(mongodb_namespace(), $query);
        $documents = [];

        foreach ($cursor as $document) {
            $arrayDocument = json_decode(json_encode($document), true);

            if (is_array($arrayDocument)) {
                $documents[] = $arrayDocument;
            }
        }

        return nosql_normalize_stats($documents);
    } catch (\Throwable $exception) {
        return null;
    }
}

function mongodb_driver_record_order(int $idMenu, string $nomMenu, float $montant, string $dateCommande): bool
{
    $manager = mongodb_driver_manager();

    if ($manager === null) {
        return false;
    }

    try {
        $now = date('c');
        $roundedAmount = round($montant, 2);
        $bulk = new \MongoDB\Driver\BulkWrite();

        $bulk->update(
            ['id_menu' => $idMenu],
            [
                '$set' => [
                    'id_menu' => $idMenu,
                    'nom_menu' => $nomMenu,
                    'updated_at' => $now,
                ],
                '$setOnInsert' => [
                    'created_at' => $now,
                ],
                '$inc' => [
                    'nombre_commandes' => 1,
                    'chiffre_affaires' => $roundedAmount,
                ],
                '$push' => [
                    'commandes' => [
                        'date' => $dateCommande,
                        'montant' => $roundedAmount,
                    ],
                ],
            ],
            ['upsert' => true]
        );

        $manager->executeBulkWrite(mongodb_namespace(), $bulk);

        return true;
    } catch (\Throwable $exception) {
        return false;
    }
}

function mongodb_http_api_url(): string
{
    $url = mongodb_defined_string('MONGODB_HTTP_API_URL');

    if ($url !== '') {
        return $url;
    }

    return mongodb_defined_string('MONGODB_DATA_API_URL');
}

function mongodb_http_api_key(): string
{
    $apiKey = mongodb_defined_string('MONGODB_HTTP_API_KEY');

    if ($apiKey !== '') {
        return $apiKey;
    }

    return mongodb_defined_string('MONGODB_API_KEY');
}

function mongodb_http_api_enabled(): bool
{
    return function_exists('curl_init')
        && mongodb_http_api_url() !== ''
        && mongodb_http_api_key() !== ''
        && mongodb_defined_string('MONGODB_DATA_SOURCE') !== ''
        && mongodb_defined_string('MONGODB_DATABASE') !== '';
}

function mongodb_http_action_url(string $action): string
{
    $baseUrl = rtrim(mongodb_http_api_url(), '/');
    $actionPosition = strpos($baseUrl, '/action/');

    if ($actionPosition !== false) {
        $baseUrl = substr($baseUrl, 0, $actionPosition + strlen('/action'));
    }

    if (substr($baseUrl, -7) !== '/action') {
        $baseUrl .= '/action';
    }

    return $baseUrl . '/' . $action;
}

function mongodb_http_payload(array $payload): array
{
    return array_merge([
        'dataSource' => mongodb_defined_string('MONGODB_DATA_SOURCE'),
        'database' => mongodb_defined_string('MONGODB_DATABASE'),
        'collection' => mongodb_collection_name(),
    ], $payload);
}

function mongodb_http_api_request(string $action, array $payload): ?array
{
    if (!mongodb_http_api_enabled()) {
        return null;
    }

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if ($jsonPayload === false) {
        return null;
    }

    $curl = curl_init(mongodb_http_action_url($action));

    if ($curl === false) {
        return null;
    }

    $apiKeyHeader = mongodb_defined_string('MONGODB_API_KEY_HEADER') ?: 'api-key';

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Access-Control-Request-Headers: *',
            $apiKeyHeader . ': ' . mongodb_http_api_key(),
        ],
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($curl);
    curl_close($curl);

    if ($response === false || $curlError !== 0 || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : null;
}

function mongodb_http_read_stats(): ?array
{
    $response = mongodb_http_api_request('find', mongodb_http_payload([
        'filter' => (object) [],
        'projection' => ['_id' => 0],
        'sort' => ['nom_menu' => 1],
        'limit' => 1000,
    ]));

    if ($response === null || !isset($response['documents']) || !is_array($response['documents'])) {
        return null;
    }

    return nosql_normalize_stats($response['documents']);
}

function mongodb_http_record_order(int $idMenu, string $nomMenu, float $montant, string $dateCommande): bool
{
    $now = date('c');
    $roundedAmount = round($montant, 2);

    $response = mongodb_http_api_request('updateOne', mongodb_http_payload([
        'filter' => ['id_menu' => $idMenu],
        'update' => [
            '$set' => [
                'id_menu' => $idMenu,
                'nom_menu' => $nomMenu,
                'updated_at' => $now,
            ],
            '$setOnInsert' => [
                'created_at' => $now,
            ],
            '$inc' => [
                'nombre_commandes' => 1,
                'chiffre_affaires' => $roundedAmount,
            ],
            '$push' => [
                'commandes' => [
                    'date' => $dateCommande,
                    'montant' => $roundedAmount,
                ],
            ],
        ],
        'upsert' => true,
    ]));

    return $response !== null;
}

function mongodb_read_stats(): ?array
{
    $driverStats = mongodb_driver_read_stats();

    if ($driverStats !== null) {
        return $driverStats;
    }

    return mongodb_http_read_stats();
}

function mongodb_record_order(int $idMenu, string $nomMenu, float $montant, string $dateCommande): bool
{
    return mongodb_driver_record_order($idMenu, $nomMenu, $montant, $dateCommande)
        || mongodb_http_record_order($idMenu, $nomMenu, $montant, $dateCommande);
}

function mongodb_driver_replace_stats(array $stats): bool
{
    $manager = mongodb_driver_manager();

    if ($manager === null) {
        return false;
    }

    try {
        $bulk = new \MongoDB\Driver\BulkWrite();
        $bulk->delete([]);

        foreach ($stats as $entry) {
            $bulk->insert($entry);
        }

        $manager->executeBulkWrite(mongodb_namespace(), $bulk);

        return true;
    } catch (\Throwable $exception) {
        error_log($exception->getMessage());
        return false;
    }
}

function mongodb_http_replace_stats(array $stats): bool
{
    $deleteResponse = mongodb_http_api_request('deleteMany', mongodb_http_payload([
        'filter' => (object) [],
    ]));

    if ($deleteResponse === null) {
        return false;
    }

    if (empty($stats)) {
        return true;
    }

    $insertResponse = mongodb_http_api_request('insertMany', mongodb_http_payload([
        'documents' => array_values($stats),
    ]));

    return $insertResponse !== null;
}

function mongodb_replace_stats(array $stats): bool
{
    return mongodb_driver_replace_stats($stats) || mongodb_http_replace_stats($stats);
}

function nosql_read_json_stats(): array
{
    $path = nosql_stats_path();

    if (!is_file($path)) {
        return [];
    }

    $content = file_get_contents($path);
    $data = json_decode($content ?: '[]', true);

    return is_array($data) ? nosql_normalize_stats($data) : [];
}

function nosql_write_json_stats(array $stats): void
{
    $path = nosql_stats_path();
    @file_put_contents($path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function nosql_record_json_order(int $idMenu, string $nomMenu, float $montant, string $dateCommande): void
{
    $stats = nosql_read_json_stats();
    $key = (string) $idMenu;

    if (!isset($stats[$key])) {
        $stats[$key] = [
            'id_menu' => $idMenu,
            'nom_menu' => $nomMenu,
            'nombre_commandes' => 0,
            'chiffre_affaires' => 0,
            'commandes' => [],
        ];
    }

    $stats[$key]['nom_menu'] = $nomMenu;
    $stats[$key]['nombre_commandes']++;
    $stats[$key]['chiffre_affaires'] = round((float) $stats[$key]['chiffre_affaires'] + $montant, 2);
    $stats[$key]['commandes'][] = [
        'date' => $dateCommande,
        'montant' => round($montant, 2),
    ];

    nosql_write_json_stats($stats);
}

function nosql_read_stats(): array
{
    $mongodbStats = mongodb_read_stats();

    if ($mongodbStats !== null) {
        return $mongodbStats;
    }

    return nosql_read_json_stats();
}

function nosql_record_order(int $idMenu, string $nomMenu, float $montant, string $dateCommande): void
{
    mongodb_record_order($idMenu, $nomMenu, $montant, $dateCommande);
    nosql_record_json_order($idMenu, $nomMenu, $montant, $dateCommande);
}

function nosql_build_stats_from_sql(PDO $pdo): array
{
    require_once __DIR__ . '/order_status.php';

    $stmt = $pdo->query("
        SELECT c.id_commande, c.date_prestation, c.prix_total, c.statut, m.id_menu, m.titre
        FROM commande c
        JOIN menu m ON c.id_menu = m.id_menu
        ORDER BY c.date_prestation ASC, c.id_commande ASC
    ");

    $stats = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $commande) {
        if (order_status_is_cancelled($commande['statut'] ?? '')) {
            continue;
        }

        $idMenu = (int) $commande['id_menu'];
        $key = (string) $idMenu;

        if (!isset($stats[$key])) {
            $stats[$key] = [
                'id_menu' => $idMenu,
                'nom_menu' => (string) $commande['titre'],
                'nombre_commandes' => 0,
                'chiffre_affaires' => 0,
                'commandes' => [],
            ];
        }

        $montant = round((float) $commande['prix_total'], 2);
        $stats[$key]['nombre_commandes']++;
        $stats[$key]['chiffre_affaires'] = round((float) $stats[$key]['chiffre_affaires'] + $montant, 2);
        $stats[$key]['commandes'][] = [
            'id_commande' => (int) $commande['id_commande'],
            'date' => (string) $commande['date_prestation'],
            'montant' => $montant,
        ];
    }

    return $stats;
}

function nosql_sync_stats_from_sql(PDO $pdo): void
{
    try {
        $stats = nosql_build_stats_from_sql($pdo);
        mongodb_replace_stats($stats);
        nosql_write_json_stats($stats);
    } catch (\Throwable $exception) {
        error_log($exception->getMessage());
    }
}
