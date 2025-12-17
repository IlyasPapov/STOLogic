<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$requestId = $_POST['request_id'] ?? null;

if (!$requestId) {
    die("Некорректный запрос");
}

// Получаем заявку
$stmt = $pdo->prepare("SELECT * FROM station_requests WHERE id = :id");
$stmt->execute(['id' => $requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die("Заявка не найдена");
}

$canDelete = false;

// Если пользователь отправил заявку сам
if ($user['id'] === $request['user_id']) {
    $canDelete = true;
}

// Если пользователь — менеджер и заявка на его СТО
if ($user['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT id FROM stations WHERE manager_id = :id");
    $stmt->execute(['id' => $user['id']]);
    $station = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($station && $station['id'] == $request['station_id']) {
        $canDelete = true;
    }
}

if (!$canDelete || $request['status'] !== 'pending') {
    die("Вы не можете удалить эту заявку.");
}

$stmt = $pdo->prepare("DELETE FROM station_requests WHERE id = :id");
$stmt->execute(['id' => $requestId]);

// Перенаправление в зависимости от роли пользователя
if ($user['role'] === 'mechanic') {
    header('Location: /dashboard_mechanic');
} elseif ($user['role'] === 'manager') {
    header('Location: /dashboard_manager');
} elseif ($user['role'] === 'accountant') {
    header('Location: /dashboard_accountant');
} else {
    header('Location: /dashboard');
}
exit;
