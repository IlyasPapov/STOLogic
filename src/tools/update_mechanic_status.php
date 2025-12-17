<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mechanic_id = $_POST['mechanic_id'] ?? null;
    $is_active = $_POST['is_active_schedule'] ?? null;

    if (!$mechanic_id || !isset($is_active)) {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE mechanics SET is_active_schedule = ? WHERE id = ?");
    $stmt->execute([$is_active, $mechanic_id]);

    // Обновим количество активных
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mechanics WHERE is_active_schedule = TRUE AND station_id = (SELECT station_id FROM mechanics WHERE id = ?)");
    $stmt->execute([$mechanic_id]);
    $active_count = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'active_count' => $active_count]);
}
