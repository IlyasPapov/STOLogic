<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'manager') {
    header("Location: /login");
    exit;
}

$user_id = $_SESSION['user']['id'];
$station_id = $_SESSION['station_id'] ?? null;

if (!$station_id) {
    $stmt = $pdo->prepare("SELECT station_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $station_id = $stmt->fetchColumn();
    $_SESSION['station_id'] = $station_id;
}

// Получение данных станции
$stmt = $pdo->prepare("
    SELECT 
        s.id AS station_id, 
        s.name, 
        s.lift_count, 
        s.minimum_employees_per_shift, 
        s.working_days,
        s.first_shift_start,
        s.second_shift_start,
        s.second_shift_end
    FROM stations s
    WHERE s.id = ?
");
$stmt->execute([$station_id]);
$station = $stmt->fetch();

if (!$station) {
    die("Станция не найдена.");
}

// Обработка POST-запроса: обновление рабочих дней
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_working_days'])) {
    $working_days = $_POST['working_days'] ?? [];
    $working_days_str = implode(',', $working_days);

    $update_stmt = $pdo->prepare("UPDATE stations SET working_days = ? WHERE id = ?");
    $update_stmt->execute([$working_days_str, $station_id]);

    header("Location: schedule");
    exit;
}

// Получение механиков
$mechs_stmt = $pdo->prepare("
    SELECT m.id, m.user_id, m.mechanic_name, m.hourly_rate, m.min_shifts_per_week, m.max_shifts_per_week, m.priority
    FROM mechanics m
    WHERE m.station_id = ?
");
$mechs_stmt->execute([$station_id]);
$mechanics = $mechs_stmt->fetchAll();

// Получение расписания
$schedules_stmt = $pdo->prepare("
    SELECT s.*, m.mechanic_name
    FROM schedules s
    LEFT JOIN mechanics m ON s.mechanic_id = m.id
    WHERE s.station_id = ?
    ORDER BY s.work_date, s.lift_number, s.shift_number
");
$schedules_stmt->execute([$station_id]);
$schedules = $schedules_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Расписание</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .button {
            background-color: #003366;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-family: Arial, sans-serif;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #0055aa;
        }

        .button {
            background-color: #003366;
            color: white;
            padding: 10px 20px;
            border: none;
            text-decoration: none;
            font-size: 16px;
            border-radius: 8px;
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #0055aa;
        }

        .button-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            text-align: center;
            font-family: sans-serif;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
        }

        thead {
            background-color: #003366;
            color: white;
        }


        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #003366;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <header>
        <h2 style="color: white;"> STOLogic — Расписание</h2>
    </header>
    <h1>Текущее расписание</h1>

    <div style="width: 100%; overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Подъёмник</th>
                    <?php
                    $start_date = new DateTime();
                    $dates = [];
                    for ($i = 0; $i < 7; $i++) {
                        $date = (clone $start_date)->modify("+$i day");
                        $dates[] = $date;
                        foreach ([['label' => '1 смена', 'shift_number' => 1], ['label' => '2 смена', 'shift_number' => 2]] as $slot) {
                            echo "<th>{$date->format('Y-m-d')}<br>({$date->format('D')})<br>{$slot['label']}</th>";
                        }
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $first_shift_start = !empty($station['first_shift_start']) ? new DateTime($station['first_shift_start']) : new DateTime('09:00');
                $second_shift_start = !empty($station['second_shift_start']) ? new DateTime($station['second_shift_start']) : new DateTime('17:00');
                $second_shift_end = !empty($station['second_shift_end']) ? new DateTime($station['second_shift_end']) : new DateTime('23:00');

                $time_slots = [
                    ['label' => '1 смена (' . $first_shift_start->format('H:i') . ' - ' . $second_shift_start->format('H:i') . ')', 'shift_number' => 1],
                    ['label' => '2 смена (' . $second_shift_start->format('H:i') . ' - ' . $second_shift_end->format('H:i') . ')', 'shift_number' => 2],
                ];

                $schedules_by_day = [];
                foreach ($schedules as $s) {
                    $schedules_by_day[$s['work_date']][$s['lift_number']][$s['shift_number']][] = $s;
                }

                for ($lift = 1; $lift <= $station['lift_count']; $lift++) {
                    echo "<tr><td><strong>П-{$lift}</strong></td>";
                    foreach ($dates as $date) {
                        $day_key = $date->format('Y-m-d');
                        $day_schedule = $schedules_by_day[$day_key] ?? [];

                        foreach ([1, 2] as $shift_number) {
                            $mechanics_list = [];

                            if (!empty($day_schedule[$lift][$shift_number])) {
                                foreach ($day_schedule[$lift][$shift_number] as $entry) {
                                    $mechanics_list[] = htmlspecialchars($entry['mechanic_name'] ?? 'Без имени');
                                }
                            }

                            echo "<td>" . (!empty($mechanics_list) ? implode('<br>', $mechanics_list) : '—') . "</td>";
                        }
                    }
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 30px; display: flex; gap: 10px; justify-content: center;">
        <a href="dashboard_manager" class="button">Назад</a>
        <a href="create-schedule" class="button">Сгенерировать новое расписание</a>
        <a href="edit-schedule" class="button">Редактировать</a>
    </div>

    </div>
</body>

</html>