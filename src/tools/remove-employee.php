<?php
// Проверка, если сессия уже запущена, то не запускать её снова
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'manager') {
    header('Location: /login');
    exit;
}

$managerId = $_SESSION['user']['id'];

// Проверяем, что запрос был отправлен методом GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die("Некорректный запрос");
}

$employeeId = $_GET['employee_id'] ?? null;

if (!$employeeId) {
    die("Некорректный запрос");
}

// Получаем СТО, которой управляет данный менеджер
$stmt = $pdo->prepare("SELECT id FROM stations WHERE manager_id = :manager_id");
$stmt->execute(['manager_id' => $managerId]);
$station = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$station) {
    die("Вы не управляете СТО.");
}

// Проверяем, что сотрудник работает на этой СТО
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND station_id = :station_id");
$stmt->execute([
    'id' => $employeeId,
    'station_id' => $station['id']
]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Пользователь не найден или не работает на вашей СТО.");
}

// Удаляем привязку сотрудника
$stmt = $pdo->prepare("UPDATE users SET station_id = NULL WHERE id = :id");
$stmt->execute(['id' => $employeeId]);

// Уведомление об успешном удалении
$_SESSION['message'] = "Сотрудник успешно удален.";

header('Location: /dashboard_manager');
exit;
