<?php
require_once __DIR__ . '/../config/db.php';
session_start();

$station_id = $_SESSION['user']['station_id'] ?? null;
if (!$station_id) {
    echo json_encode(['error' => 'Станция не определена.']);
    exit;
}

// 1) Получаем станцию
$stmt = $pdo->prepare("
  SELECT working_days, lift_count, first_shift_start, second_shift_start, second_shift_end 
  FROM stations WHERE id = ?
");
$stmt->execute([$station_id]);
$station_data = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$station_data || empty($station_data['working_days'])) {
    echo json_encode(['error' => 'Не удалось получить данные станции или рабочие дни!']);
    exit;
}

$working_days = explode(',', $station_data['working_days']);
$lift_count = (int) $station_data['lift_count'];

// 2) Получаем активных механиков
$stmt = $pdo->prepare("
  SELECT mechanics.*, users.full_name 
  FROM mechanics 
    JOIN users ON mechanics.user_id = users.id 
  WHERE mechanics.station_id = ? 
    AND mechanics.is_active_schedule = true
");
$stmt->execute([$station_id]);
$all_mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3) Фильтруем некорректные
$mechanics = array_filter(
    $all_mechanics,
    fn($m) =>
    !empty($m['full_name']) &&
    is_numeric($m['min_shifts_per_week']) &&
    is_numeric($m['max_shifts_per_week'])
);
if (!$mechanics) {
    echo json_encode(['error' => 'Нет активных механиков с корректными данными.']);
    exit;
}

// 4) Сортируем по цене
usort($mechanics, fn($a, $b) => $a['hourly_rate'] <=> $b['hourly_rate']);

// 5) Считаем суммарные min/max
$total_min = $total_max = 0;
foreach ($mechanics as &$m) {
    $m['min_shifts'] = (int) $m['min_shifts_per_week'];
    $m['max_shifts'] = (int) $m['max_shifts_per_week'];
    $total_min += $m['min_shifts'];
    $total_max += $m['max_shifts'];
}
unset($m);

$slots_needed = count($working_days) * $lift_count * 2;
if ($slots_needed < $total_min) {
    echo json_encode(['error' => "Недостаточно смен. Нужно минимум $total_min сл."]);
    exit;
}
if ($slots_needed > $total_max) {
    echo json_encode(['error' => "Недостаточно механиков. Нужны ещё " . ($slots_needed - $total_max) . " сл."]);
    exit;
}

// 6) Распределяем всем минимум
$dist = [];
$assigned_total = 0;
foreach ($mechanics as $m) {
    $dist[$m['id']] = $m['min_shifts'];
    $assigned_total += $m['min_shifts'];
}
$remaining = $slots_needed - $assigned_total;

// 7) Довозим до квот по цене
usort($mechanics, fn($a, $b) => $a['hourly_rate'] <=> $b['hourly_rate']);
foreach ($mechanics as $m) {
    $id = $m['id'];
    $can = $m['max_shifts'] - $dist[$id];
    $add = min($can, $remaining);
    $dist[$id] += $add;
    $remaining -= $add;
}

// 8) Получаем рабочие даты на неделю
$start_date = $_SESSION['generated_schedule']['station']['start_date'] ?? date('Y-m-d');
$station = $_SESSION['generated_schedule']['station'] ?? null;

if (!$station || empty($station['working_dates'])) {
    echo json_encode(['error' => 'Нет данных станции или рабочих дат в сессии.']);
    exit;
}

$working_dates = $station['working_dates'];

// 9) Собираем все слоты
$slots = [];
foreach ($working_dates as $date) {
    for ($lift = 1; $lift <= $lift_count; $lift++) {
        for ($shift = 1; $shift <= 2; $shift++) {
            $slots[] = ['day' => $date, 'lift' => $lift, 'shift' => $shift];
        }
    }
}

$schedule = [];
$occupied = [];
$mechanic_ids = array_keys($dist);

foreach ($slots as $slot) {
    foreach ($mechanic_ids as $mid) {
        if ($dist[$mid] > 0 && empty($occupied[$slot['day']][$slot['shift']][$mid])) {
            $conflict = false;
            foreach ($schedule as $e) {
                if (
                    $e['day'] === $slot['day'] &&
                    $e['shift'] === $slot['shift'] &&
                    $e['mechanic_id'] === $mid
                ) {
                    $conflict = true;
                    break;
                }
            }
            if ($conflict) {
                continue;
            }

            $schedule[] = [
                'day' => $slot['day'],
                'lift' => $slot['lift'],
                'shift' => $slot['shift'],
                'mechanic_id' => $mid
            ];
            $dist[$mid]--;
            $occupied[$slot['day']][$slot['shift']][$mid] = true;
            break;
        }
    }
}

// Найдём незаполненные слоты
$leftoverSlots = [];
foreach ($slots as $slot) {
    $filled = false;
    foreach ($schedule as $e) {
        if (
            $e['day'] === $slot['day'] &&
            $e['lift'] === $slot['lift'] &&
            $e['shift'] === $slot['shift']
        ) {
            $filled = true;
            break;
        }
    }
    if (!$filled) {
        $leftoverSlots[] = $slot;
    }
}

// 10) Создание шаблона расписания и сохранение его в базе
$template_name = sprintf(
    "Шаблон %s — %s: %d механиков, %d подъемника, %d рабочих дней",
    $start_date,
    date('Y-m-d', strtotime("$start_date +6 days")),
    count($mechanics),
    $lift_count,
    count($working_days)
);

$sql = "INSERT INTO schedule_templates (
    station_id, name, start_date, working_dates, 
    schedule_json, shift1_start, shift2_start, shift2_end, 
    total_mechanics, total_cost, created_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($sql);


$working_dates = array_map(function ($date) {
    return date('Y-m-d', strtotime($date));
}, $working_dates); // Преобразуем каждый элемент в формат YYYY-MM-DD, если это необходимо

// Добавляем current_user_id в запрос (получаем из сессии)
$current_user_id = $_SESSION['user']['id'] ?? null;  // Пример получения текущего пользователя

$stmt->execute([
    $station_id,
    $template_name,
    $start_date,
    json_encode($working_dates), // Передаем как JSON массив
    json_encode($schedule),      // Передаем как JSON
    $station_data['first_shift_start'],
    $station_data['second_shift_start'],
    $station_data['second_shift_end'],
    count($mechanics),
    0, // Стоимость (пока нулевая, можно заменить по необходимости)
    $current_user_id            // ID пользователя, создавшего шаблон
]);


$template_id = $pdo->lastInsertId();

// Сохраняем в сессию
$_SESSION['generated_schedule']['template_id'] = $template_id;
$_SESSION['generated_schedule']['schedule'] = array_values($schedule);
$_SESSION['generated_schedule']['station']['lift_count'] = $lift_count;
$_SESSION['generated_schedule']['station']['working_days'] = $working_days;
$_SESSION['generated_schedule']['station']['start_date'] = $start_date;
$_SESSION['generated_schedule']['station']['end_date'] = date('Y-m-d', strtotime("$start_date +6 days"));

$_SESSION['generated_schedule']['leftover_slots'] = $leftoverSlots;
$_SESSION['generated_schedule']['shifts_needed'] = $slots_needed;
$_SESSION['generated_schedule']['total_min'] = $total_min;
$_SESSION['generated_schedule']['total_max'] = $total_max;
$_SESSION['generated_schedule']['mechanics'] = $mechanics;

// 11) Переход на страницу редактирования
header('Location: schedule-review');
exit;
