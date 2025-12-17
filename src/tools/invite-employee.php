<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Функция для проверки авторизации
function isLoggedIn() {
    return isset($_SESSION['user']) && $_SESSION['user']['id'] > 0;
}

// Проверка, что пользователь — менеджер
if (!isLoggedIn() || $_SESSION['user']['role'] !== 'manager') {
    header('Location: /login.php');
    exit;
}

$managerId = $_SESSION['user']['id'];
$employeeId = $_POST['user_id'] ?? null;

if (!$employeeId) {
    die('Ошибка: не указан сотрудник.');
}

// Получаем станцию менеджера
$stmt = $pdo->prepare("SELECT station_id FROM users WHERE id = :id");
$stmt->execute(['id' => $managerId]);
$stationId = $stmt->fetchColumn();

if (!$stationId) {
    die('Ошибка: у менеджера не привязана СТО.');
}

// Проверим, существует ли сотрудник и свободен ли он
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role IN ('mechanic', 'accountant')");
$stmt->execute(['id' => $employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    die('Сотрудник не найден или не соответствует роли.');
}

// Проверим, нет ли уже активного приглашения
$stmt = $pdo->prepare("SELECT * FROM station_invitations WHERE user_id = :user_id AND station_id = :station_id AND status = 'pending'");
$stmt->execute([
    'user_id' => $employeeId,
    'station_id' => $stationId
]);

if ($stmt->fetch()) {
    die('Вы уже отправили приглашение этому сотруднику.');
}

// Добавим новое приглашение
$stmt = $pdo->prepare("INSERT INTO station_invitations (user_id, station_id, status, created_at) VALUES (:user_id, :station_id, 'pending', NOW())");
$stmt->execute([
    'user_id' => $employeeId,
    'station_id' => $stationId
]);

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
