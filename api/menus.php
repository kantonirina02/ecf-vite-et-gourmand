<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/classes/MenuRepository.php';

try {
    $menuRepository = new MenuRepository($pdo);

    echo json_encode([
        'success' => true,
        'data' => $menuRepository->findAllForCatalog(),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Impossible de charger les menus.',
    ], JSON_UNESCAPED_UNICODE);
}
