<?php
require_once __DIR__ . '/../config/db.php'; // Подключение к базе данных

$station_id = $_GET['station_id'] ?? 1;

// Получаем рабочие часы и количество подъемников
$station_stmt = $pdo->prepare("SELECT working_hours, lift_count FROM stations WHERE id = ?");
$station_stmt->execute([$station_id]);
$station = $station_stmt->fetch();

if (!$station) {
    echo json_encode(['error' => 'Станция не найдена']);
    exit;
}

// Разбор рабочего времени станции (например, "08:00-20:00")
list($start, $end) = explode('-', $station['working_hours']);
$start_time = DateTime::createFromFormat('H:i', $start);
$end_time = DateTime::createFromFormat('H:i', $end);
$interval = $start_time->diff($end_time);
$hours_per_day = $interval->h + ($interval->i / 60);

// Расчёт смен
$shifts_per_day = 2;
$shift_hours = $hours_per_day / $shifts_per_day;
$working_days_per_week = 5;
$lift_count = intval($station['lift_count']);

$total_required_shifts = $shifts_per_day * $working_days_per_week * $lift_count;

// Получаем механиков
$mechanic_stmt = $pdo->prepare("
    SELECT u.id, u.full_name, m.max_shifts_per_week, m.min_shifts_per_week, m.hourly_rate, m.priority
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

// Классификация механиков
$max_priority = [];
$min_priority = [];
$neutral = [];

foreach ($mechanics as $mech) {
    if ($mech['priority'] === 'max') {
        $max_priority[] = $mech;
    } elseif ($mech['priority'] === 'min') {
        $min_priority[] = $mech;
    } else {
        $neutral[] = $mech;
    }
}

$schedule = [];
$remaining_shifts = $total_required_shifts;

// Назначаем максимальные
foreach ($max_priority as $mech) {
    $shifts = $mech['max_shifts_per_week'];
    $shifts = min($shifts, $remaining_shifts);
    $hours = $shifts * $shift_hours;
    $pay = $hours * $mech['hourly_rate'];

    $schedule[] = [
        'mechanic_id' => $mech['id'],
        'name' => $mech['full_name'],
        'priority' => 'max',
        'shifts' => $shifts,
        'hours' => $hours,
        'hourly_rate' => $mech['hourly_rate'],
        'estimated_pay' => round($pay, 2)
    ];

    $remaining_shifts -= $shifts;
}

// Назначаем минимальные
foreach ($min_priority as $mech) {
    $shifts = $mech['min_shifts_per_week'];
    $shifts = min($shifts, $remaining_shifts);
    $hours = $shifts * $shift_hours;
    $pay = $hours * $mech['hourly_rate'];

    $schedule[] = [
        'mechanic_id' => $mech['id'],
        'name' => $mech['full_name'],
        'priority' => 'min',
        'shifts' => $shifts,
        'hours' => $hours,
        'hourly_rate' => $mech['hourly_rate'],
        'estimated_pay' => round($pay, 2)
    ];

    $remaining_shifts -= $shifts;
}

// Остальные — делим оставшиеся смены
$neutral_count = count($neutral);
if ($neutral_count > 0 && $remaining_shifts > 0) {
    $avg = floor($remaining_shifts / $neutral_count);
    foreach ($neutral as $i => $mech) {
        $shifts = ($i == $neutral_count - 1) ? $remaining_shifts : $avg; // последний — остаток
        $hours = $shifts * $shift_hours;
        $pay = $hours * $mech['hourly_rate'];

        $schedule[] = [
            'mechanic_id' => $mech['id'],
            'name' => $mech['full_name'],
            'priority' => 'normal',
            'shifts' => $shifts,
            'hours' => $hours,
            'hourly_rate' => $mech['hourly_rate'],
            'estimated_pay' => round($pay, 2)
        ];

        $remaining_shifts -= $shifts;
    }
}

// Проверка на нехватку
if ($remaining_shifts > 0) {
    echo json_encode([
        'error' => 'Недостаточно механиков. Осталось непокрытых смен: ' . $remaining_shifts
    ]);
    exit;
}

// Общая сумма оплаты
$total_pay = array_sum(array_column($schedule, 'estimated_pay'));

echo json_encode([
    'success' => true,
    'algorithm' => 'priority_based',
    'total_required_shifts' => $total_required_shifts,
    'shift_hours' => $shift_hours,
    'total_hours' => $total_required_shifts * $shift_hours,
    'total_payroll' => round($total_pay, 2),
    'schedule' => $schedule
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
