<?php
require_once __DIR__ . '/../config/db.php'; // Подключение к базе данных

$station_id = $_GET['station_id'] ?? 1;

// Получаем параметры станции
$station_stmt = $pdo->prepare("SELECT working_hours, lift_count FROM stations WHERE id = ?");
$station_stmt->execute([$station_id]);
$station = $station_stmt->fetch();

if (!$station) {
    echo json_encode(['error' => 'Станция не найдена']);
    exit;
}

// Разбор времени работы (формат "08:00-20:00")
list($start, $end) = explode('-', $station['working_hours']);
$start_time = DateTime::createFromFormat('H:i', $start);
$end_time = DateTime::createFromFormat('H:i', $end);
$interval = $start_time->diff($end_time);
$hours_per_day = $interval->h + ($interval->i / 60);

// Считаем смены (2 смены в день)
$shifts_per_day = 2;
$shift_hours = $hours_per_day / $shifts_per_day;
$working_days_per_week = 7; // по умолчанию
$lift_count = intval($station['lift_count']);
$week_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']; // можно сделать настраиваемым позже

$total_shifts_per_lift = $shifts_per_day * $working_days_per_week;
$total_required_shifts = $total_shifts_per_lift * $lift_count;

// Получаем механиков и их настройки смен
$mechanic_stmt = $pdo->prepare("
    SELECT u.id, u.full_name, m.max_shifts_per_week, m.min_shifts_per_week, m.hourly_rate
    FROM users u
    JOIN mechanic_settings m ON u.id = m.user_id
    WHERE u.station_id = ? AND u.role = 'mechanic' AND u.approved = 1
");
$mechanic_stmt->execute([$station_id]);
$mechanics = $mechanic_stmt->fetchAll();

if (empty($mechanics)) {
    echo json_encode(['error' => 'На станции нет механиков']);
    exit;
}

// Распределение смен
$schedule = [];
$remaining_shifts = $total_required_shifts;
$mechanic_count = count($mechanics);
$avg_shifts = floor($total_required_shifts / $mechanic_count);

foreach ($mechanics as &$mechanic) {
    $desired_shifts = min($avg_shifts, $mechanic['max_shifts_per_week']);
    $assigned_shifts = max($desired_shifts, $mechanic['min_shifts_per_week']);

    if ($assigned_shifts > $remaining_shifts) {
        $assigned_shifts = $remaining_shifts;
    }

    $mechanic['assigned_shifts'] = $assigned_shifts;
    $remaining_shifts -= $assigned_shifts;

    $hours = $assigned_shifts * $shift_hours;
    $pay = $hours * $mechanic['hourly_rate'];

    $schedule[] = [
        'mechanic_id' => $mechanic['id'],
        'name' => $mechanic['full_name'],
        'shifts' => $assigned_shifts,
        'hours' => $hours,
        'hourly_rate' => $mechanic['hourly_rate'],
        'estimated_pay' => round($pay, 2)
    ];

    if ($remaining_shifts <= 0) break;
}

// Проверка на нехватку смен
if ($remaining_shifts > 0) {
    echo json_encode([
        'error' => 'Недостаточно механиков для покрытия всех смен. Осталось непокрытых смен: ' . $remaining_shifts
    ]);
    exit;
}

// Общая зарплата
$total_pay = array_sum(array_column($schedule, 'estimated_pay'));

echo json_encode([
    'success' => true,
    'algorithm' => 'shifts_based',
    'total_required_shifts' => $total_required_shifts,
    'shift_hours' => $shift_hours,
    'total_hours' => $total_required_shifts * $shift_hours,
    'total_payroll' => round($total_pay, 2),
    'schedule' => $schedule
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
