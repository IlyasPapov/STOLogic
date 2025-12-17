<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Проверка на авторизацию и роль менеджера
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещен']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if (!$action) {
    echo json_encode(['error' => 'Не указано действие']);
    exit;
}

// Обработка действия в зависимости от переданного параметра
switch ($action) {
    case 'copy':
        copySchedule();
        break;
    case 'edit':
        editSchedule();
        break;
    case 'history':
        getScheduleHistory();
        break;
    case 'swap':
        swapMechanics();
        break;
    case 'replace':
        replaceMechanic();
        break;
    default:
        echo json_encode(['error' => 'Неизвестное действие']);
        break;
}

// === ФУНКЦИИ ДЕЙСТВИЙ === //

// Функция для копирования расписания
function copySchedule() {
    global $pdo;

    // Получаем необходимые параметры
    $from_week = $_POST['from_week'] ?? null;
    $to_week = $_POST['to_week'] ?? null;
    $station_id = $_SESSION['station_id'];

    if (!$from_week || !$to_week) {
        echo json_encode(['error' => 'Неделя не указана']);
        return;
    }

    // Получаем старое расписание
    $stmt = $pdo->prepare("SELECT * FROM weekly_schedules WHERE station_id = ? AND week_start_date = ?");
    $stmt->execute([$station_id, $from_week]);
    $oldSchedule = $stmt->fetch();

    if (!$oldSchedule) {
        echo json_encode(['error' => 'Расписание не найдено']);
        return;
    }

    // Вставляем новое расписание как копию
    $stmt = $pdo->prepare("INSERT INTO weekly_schedules (station_id, week_start_date, data, name) VALUES (?, ?, ?, ?)");
    $stmt->execute([$station_id, $to_week, $oldSchedule['data'], $oldSchedule['name'] . ' (копия)']);

    echo json_encode(['success' => true]);
}

// Функция для редактирования расписания
function editSchedule() {
    global $pdo;

    $schedule_id = $_POST['schedule_id'] ?? null;
    $new_data = $_POST['data'] ?? null;

    if (!$schedule_id || !$new_data) {
        echo json_encode(['error' => 'Недостаточно данных']);
        return;
    }

    // Обновляем расписание
    $stmt = $pdo->prepare("UPDATE weekly_schedules SET data = ? WHERE id = ?");
    $stmt->execute([$new_data, $schedule_id]);

    echo json_encode(['success' => true]);
}

// Функция для получения истории расписаний
function getScheduleHistory() {
    global $pdo;

    $station_id = $_SESSION['station_id'];

    // Получаем историю расписаний
    $stmt = $pdo->prepare("SELECT id, week_start_date, name FROM weekly_schedules WHERE station_id = ? ORDER BY week_start_date DESC");
    $stmt->execute([$station_id]);

    $history = $stmt->fetchAll();

    echo json_encode(['success' => true, 'history' => $history]);
}

// Функция для обмена механиками в расписании
function swapMechanics() {
    global $pdo;

    $schedule_id = $_POST['schedule_id'] ?? null;
    $day = $_POST['day'] ?? null;
    $lift = $_POST['lift'] ?? null;
    $mech1 = $_POST['mech1'] ?? null;
    $mech2 = $_POST['mech2'] ?? null;

    if (!$schedule_id || !$day || $lift === null || !$mech1 || !$mech2) {
        echo json_encode(['error' => 'Недостаточно данных для обмена']);
        return;
    }

    // Загружаем расписание
    $stmt = $pdo->prepare("SELECT data FROM weekly_schedules WHERE id = ?");
    $stmt->execute([$schedule_id]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['error' => 'Расписание не найдено']);
        return;
    }

    // Расшифровываем данные расписания
    $data = json_decode($row['data'], true);

    // Меняем местами механиков
    $tmp = $data[$day][$lift][$mech1];
    $data[$day][$lift][$mech1] = $data[$day][$lift][$mech2];
    $data[$day][$lift][$mech2] = $tmp;

    // Сохраняем обновленные данные
    $stmt = $pdo->prepare("UPDATE weekly_schedules SET data = ? WHERE id = ?");
    $stmt->execute([json_encode($data), $schedule_id]);

    echo json_encode(['success' => true]);
}

// Функция для замены механика в расписании
function replaceMechanic() {
    global $pdo;

    $schedule_id = $_POST['schedule_id'] ?? null;
    $day = $_POST['day'] ?? null;
    $lift = $_POST['lift'] ?? null;
    $old_mechanic = $_POST['old_mechanic'] ?? null;
    $new_mechanic = $_POST['new_mechanic'] ?? null;

    if (!$schedule_id || !$day || $lift === null || !$old_mechanic || !$new_mechanic) {
        echo json_encode(['error' => 'Недостаточно данных для замены']);
        return;
    }

    // Загружаем расписание
    $stmt = $pdo->prepare("SELECT data FROM weekly_schedules WHERE id = ?");
    $stmt->execute([$schedule_id]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['error' => 'Расписание не найдено']);
        return;
    }

    // Расшифровываем данные расписания
    $data = json_decode($row['data'], true);

    // Заменяем механика
    if (isset($data[$day][$lift]) && $data[$day][$lift] == $old_mechanic) {
        $data[$day][$lift] = $new_mechanic;
    }

    // Сохраняем обновленные данные
    $stmt = $pdo->prepare("UPDATE weekly_schedules SET data = ? WHERE id = ?");
    $stmt->execute([json_encode($data), $schedule_id]);

    echo json_encode(['success' => true]);
}
?>
