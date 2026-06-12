<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/nosql_stats.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Acces refuse.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    nosql_sync_stats_from_sql($pdo);
    $stats = nosql_read_stats();

    $filtreMenu = clean_text_input($_GET['stats_menu'] ?? '', 20);
    $dateDebut = clean_text_input($_GET['date_debut'] ?? '', 10);
    $dateFin = clean_text_input($_GET['date_fin'] ?? '', 10);

    if ($dateDebut !== '' && !is_valid_date_string($dateDebut)) {
        $dateDebut = '';
    }

    if ($dateFin !== '' && !is_valid_date_string($dateFin)) {
        $dateFin = '';
    }

    $statsFiltrees = [];

    foreach ($stats as $entry) {
        if ($filtreMenu !== '' && (string) $entry['id_menu'] !== $filtreMenu) {
            continue;
        }

        $count = 0;
        $chiffreAffaires = 0;

        foreach (($entry['commandes'] ?? []) as $commande) {
            $date = $commande['date'] ?? '';

            if ($dateDebut !== '' && $date < $dateDebut) {
                continue;
            }

            if ($dateFin !== '' && $date > $dateFin) {
                continue;
            }

            $count++;
            $chiffreAffaires += (float) ($commande['montant'] ?? 0);
        }

        $statsFiltrees[] = [
            'id_menu' => (int) $entry['id_menu'],
            'nom_menu' => (string) $entry['nom_menu'],
            'nombre_commandes' => $count,
            'chiffre_affaires' => round($chiffreAffaires, 2),
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => array_column($statsFiltrees, 'nom_menu'),
            'commandes' => array_column($statsFiltrees, 'nombre_commandes'),
            'chiffre_affaires' => array_column($statsFiltrees, 'chiffre_affaires'),
            'rows' => $statsFiltrees,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Impossible de charger les statistiques.',
    ], JSON_UNESCAPED_UNICODE);
}
