<?php
const ORDER_STATUSES = [
    'en_attente',
    'accepte',
    'en_preparation',
    'en_livraison',
    'livre',
    'attente_retour_materiel',
    'terminee',
    'annulee',
];

const ORDER_STATUS_LABELS = [
    'en_attente' => 'En attente',
    'accepte' => 'Accepté',
    'en_preparation' => 'En préparation',
    'en_livraison' => 'En cours de livraison',
    'livre' => 'Livré',
    'attente_retour_materiel' => 'En attente du retour de matériel',
    'terminee' => 'Terminée',
    'annulee' => 'Annulée',
];

function normalize_order_status(?string $status): string
{
    $status = trim((string) $status);
    $status = strtr($status, [
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'à' => 'a',
        'â' => 'a',
        'î' => 'i',
        'ï' => 'i',
        'ô' => 'o',
        'ù' => 'u',
        'û' => 'u',
        'ç' => 'c',
        'É' => 'e',
        'È' => 'e',
        'Ê' => 'e',
        'À' => 'a',
        'Ç' => 'c',
    ]);
    $status = strtolower($status);
    $status = preg_replace('/[\s-]+/', '_', $status) ?? $status;

    $aliases = [
        'en_attente' => 'en_attente',
        'attente' => 'en_attente',
        'accepte' => 'accepte',
        'acceptee' => 'accepte',
        'en_preparation' => 'en_preparation',
        'preparation' => 'en_preparation',
        'en_cours_de_livraison' => 'en_livraison',
        'en_livraison' => 'en_livraison',
        'livraison' => 'en_livraison',
        'livre' => 'livre',
        'livree' => 'livre',
        'en_attente_du_retour_de_materiel' => 'attente_retour_materiel',
        'attente_retour_materiel' => 'attente_retour_materiel',
        'retour_materiel' => 'attente_retour_materiel',
        'terminee' => 'terminee',
        'termine' => 'terminee',
        'annulee' => 'annulee',
        'annule' => 'annulee',
    ];

    return $aliases[$status] ?? $status;
}

function order_status_label(?string $status): string
{
    $status = normalize_order_status($status);

    return ORDER_STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function order_status_is_allowed(?string $status): bool
{
    return in_array(normalize_order_status($status), ORDER_STATUSES, true);
}

function order_status_is_cancelled(?string $status): bool
{
    return normalize_order_status($status) === 'annulee';
}

function order_status_is_waiting(?string $status): bool
{
    return normalize_order_status($status) === 'en_attente';
}

function order_status_is_finished(?string $status): bool
{
    return normalize_order_status($status) === 'terminee';
}

function order_status_badge_class(?string $status): string
{
    $status = normalize_order_status($status);

    if ($status === 'accepte' || $status === 'en_preparation') {
        return 'badge-accepted';
    }

    if ($status === 'en_livraison' || $status === 'attente_retour_materiel') {
        return 'badge-delivery';
    }

    if ($status === 'livre' || $status === 'terminee') {
        return 'badge-success';
    }

    if ($status === 'annulee') {
        return 'badge-cancelled';
    }

    return 'badge-waiting';
}

function order_status_database_values(string $canonical): array
{
    $canonical = normalize_order_status($canonical);

    $legacy = [
        'en_attente' => ['en_attente', 'en attente'],
        'accepte' => ['accepte', 'accepté', 'acceptee'],
        'en_preparation' => ['en_preparation', 'en préparation'],
        'en_livraison' => ['en_livraison', 'en cours de livraison'],
        'livre' => ['livre', 'livré', 'livree'],
        'attente_retour_materiel' => ['attente_retour_materiel', 'en attente du retour de matériel'],
        'terminee' => ['terminee', 'terminée', 'termine'],
        'annulee' => ['annulee', 'annulée', 'annulee'],
    ];

    return array_values(array_unique($legacy[$canonical] ?? [$canonical]));
}
