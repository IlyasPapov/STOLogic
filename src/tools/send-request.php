<?php
// public/send-request.php

session_start();
require_once __DIR__ . '/../config/db.php';

// Проверка авторизации
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['mechanic', 'accountant', 'manager'])) {
    header('Location: /login.php');
    exit;
}

$user = $_SESSION['user'];

// Проверка данных формы
$station_id = $_POST['station_id'] ?? null;

if ($station_id) {
    // Проверка на дублирующую заявку
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM station_requests WHERE user_id = :user_id AND station_id = :station_id AND status = 'pending'");
    $stmt->execute([
        'user_id' => $user['id'],
        'station_id' => $station_id
    ]);
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        // Добавление заявки
        $stmt = $pdo->prepare("INSERT INTO station_requests (user_id, station_id, status) VALUES (:user_id, :station_id, 'pending')");
        $stmt->execute([
            'user_id' => $user['id'],
            'station_id' => $station_id
        ]);
    }
}

// Перенаправление на соответствующий дашборд
switch ($user['role']) {
    case 'mechanic':
        header('Location: /dashboard_mechanic');
        break;
    case 'accountant':
        header('Location: /dashboard_accountant');
        break;
    case 'manager':
        header('Location: /dashboard_manager');
        break;
    default:
        header('Location: /login.php');
}
exit;
