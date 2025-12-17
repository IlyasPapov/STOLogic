<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Проверка доступа
if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'manager') {
    header("Location: /login");
    exit;
}

$user_id = $_SESSION['user']['id'];
$station_id = $_SESSION['station_id'] ?? null;
//var_dump($_SESSION['station_id']);

// Получаем данные смен из таблицы stations
$stmt = $pdo->prepare("SELECT first_shift_start, second_shift_start, second_shift_end FROM stations WHERE id = ?");
$stmt->execute([$station_id]);
$station = $stmt->fetch(PDO::FETCH_ASSOC);

// Если станция найдена, разбираем времена смен
if ($station) {
    $firstShiftStart = $station['first_shift_start']; // 07:00:00
    $secondShiftStart = $station['second_shift_start']; // 15:00:00
    $secondShiftEnd = $station['second_shift_end']; // 23:00:00

    // Функция для расчета длительности смены в часах
    function calculateShiftDuration($start, $end)
    {
        $startTime = new DateTime($start);
        $endTime = new DateTime($end);
        $interval = $startTime->diff($endTime);
        return $interval->h + ($interval->i / 60); // часы + доля часа
    }

    // Расчет длительности смен
    $firstShiftHours = calculateShiftDuration($firstShiftStart, $secondShiftStart);
    $secondShiftHours = calculateShiftDuration($secondShiftStart, $secondShiftEnd);
} else {
    // Значения по умолчанию, если станция не найдена
    $firstShiftStart = '08:00:00';
    $secondShiftStart = '15:00:00';
    $secondShiftEnd = '23:00:00';
    $firstShiftHours = 7;
    $secondShiftHours = 8;
}
echo "<script>
    var firstShiftHours = {$firstShiftHours};
    var secondShiftHours = {$secondShiftHours};
</script>";

// === POST — сохранить расписание ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule = $_POST['schedule'] ?? [];
    $template = $_SESSION['generated_schedule'] ?? [];

    // 1) Берём из сессии данные станции, если они там есть
    $stationData = $template['station'] ?? [];
    $start_date = $stationData['start_date'] ?? null;
    $end_date = $stationData['end_date'] ?? null;

    // 2) Если конца недели ещё нет, вычисляем и сохраняем
    if ($start_date && !$end_date) {
        $dt = new DateTime($start_date);
        $dt->modify('+6 days');
        $end_date = $dt->format('Y-m-d');
        // Запоминаем, чтобы не считать заново
        $_SESSION['generated_schedule']['station']['end_date'] = $end_date;
    }

    // 3) Удаляем старые записи только если у нас есть оба диапазона
    if ($start_date && $end_date) {
        $del = $pdo->prepare("
            DELETE FROM schedules
            WHERE station_id = ? 
              AND work_date BETWEEN ? AND ?
        ");
        $del->execute([$station_id, $start_date, $end_date]);
    }

    // 4) Составляем map механиков из базы данных
    $mechStmt = $pdo->prepare("
        SELECT id, mechanic_name 
        FROM mechanics 
        WHERE station_id = ?
    ");
    $mechStmt->execute([$station_id]);
    $mechanicMap = [];
    foreach ($mechStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $mechanicMap[$m['id']] = $m['mechanic_name'];
    }

    // ID шаблона
    $template_id = $template['template_id'] ?? null;

    // Вставка новых ячеек в таблицу расписаний
    $ins = $pdo->prepare("
        INSERT INTO schedules
          (station_id, mechanic_id, mechanic_name, lift_number, work_date, shift_number, is_lift_disabled, template_id)
        VALUES (?, ?, ?, ?, ?, ?, false, ?)
    ");
    foreach ($schedule as $cell) {
        $day = $cell['day'] ?? null;
        $shift = $cell['shift'] ?? null;
        $lift = $cell['lift'] ?? null;
        $mid = $cell['mechanic_id'] ?? null;

        if ($day && $shift && $lift && $mid) {
            $ins->execute([
                $station_id,
                $mid,
                $mechanicMap[$mid] ?? '',
                $lift,
                $day,
                $shift,
                $template_id
            ]);
        }
    }

    header("Location: /schedule");
    exit;
}

// === GET — показать форму редактирования ===
$template = $_SESSION['generated_schedule'] ?? null;

// Проверка, если шаблон расписания отсутствует в сессии
if (!$template) {
    echo "<h2>Ошибка: шаблон расписания не найден в сессии.</h2>";
    header("Location: /schedule");
    exit;
}

$schedule = $template['schedule'];
$working_days = $template['station']['working_dates']; // Используем рабочие дни из шаблона
$lift_count = $template['station']['lift_count'];
$station_id = $_SESSION['station_id'] ?? null;
if (!$station_id) {
    die('Ошибка: station_id не найден.');
}


// Подтягиваем механиков с нужными полями
$stmt = $pdo->prepare("
    SELECT id, mechanic_name, hourly_rate, min_shifts_per_week, max_shifts_per_week
    FROM mechanics
    WHERE station_id = ? AND is_active_schedule = true
");
$stmt->execute([$station_id]);
$mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Заполняем lookup уже назначенных механиков
$table = [];
foreach ($schedule as $cell) {
    $d = $cell['day'];
    $s = $cell['shift'];
    $l = $cell['lift'];
    $table[$d][$s][$l] = $cell['mechanic_id'];
}

?>


<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Редактирование расписания</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        body {
            margin: 0;
            font-family: sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            background-color: #f5f5f5;
        }

        .wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            overflow-x: auto;
            padding: 0 20px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 100%;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 1500px;
            background-color: white;
            border: 1px solid #ccc;
            text-align: center;
        }


        h2 {
            text-align: center;
            margin-top: 20px;
        }

        .wrapper {
            max-width: 100%;
            overflow-x: auto;
            padding: 0 20px;
        }


        th,
        td {
            border: 1px solid #999;
            padding: 8px;
            text-align: center;
        }

        select {
            width: 100%;
            padding: 6px;
        }

        .center {
            text-align: center;
            align-items: center;
        }

        .styled-button {
            background-color: #003366;
            color: white;
            padding: 10px 20px;
            margin: 20px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .styled-button:hover {
            background-color: #0055a5;
        }

        #totalCost {
            font-size: 18px;
            margin-top: 10px;
        }

        @media screen and (max-width: 768px) {
            table {
                font-size: 12px;
            }
        }
    </style>
</head>

<?php
// Предполагаем, что данные для $firstShiftHours и $secondShiftHours передаются из PHP
echo "<script>
    var firstShiftHours = {$firstShiftHours};
    var secondShiftHours = {$secondShiftHours};
    console.log('Первичная смена (часы):', firstShiftHours);
    console.log('Вторичная смена (часы):', secondShiftHours);
</script>";
?>

<body>
    <h2>Редактирование расписания</h2>

    <div class="center">
        <button type="button" onclick="returnToSchedule()" class="styled-button">
            Вернуться к созданию расписания
        </button>
    </div>

    <form method="post" class="center">
        <div class="wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Смена</th>
                        <?php for ($l = 1; $l <= $lift_count; $l++): ?>
                            <th>Подъемник <?= $l ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Функция для получения дня недели
                    function getDayOfWeek($date)
                    {
                        $days = [
                            'Monday' => 'Пн',
                            'Tuesday' => 'Вт',
                            'Wednesday' => 'Ср',
                            'Thursday' => 'Чт',
                            'Friday' => 'Пт',
                            'Saturday' => 'Сб',
                            'Sunday' => 'Вс'
                        ];
                        $day = date('l', strtotime($date));
                        return $days[$day] ?? '';
                    }

                    // Создаем map механиков
                    $mechanicMap = [];
                    foreach ($mechanics as $mech) {
                        $mechanicMap[$mech['id']] = [
                            'name' => $mech['mechanic_name'],
                            'rate' => $mech['hourly_rate'],
                            'min' => $mech['min_shifts_per_week'],
                            'max' => $mech['max_shifts_per_week']
                        ];
                    }

                    // Цикл по рабочим дням и сменам
                    foreach ($working_days as $day):
                        $weekday = getDayOfWeek($day);
                        for ($shift = 1; $shift <= 2; $shift++): ?>
                            <tr>
                                <td><?= htmlspecialchars($day) . " ($weekday)" ?></td>
                                <td><?= $shift ?></td>
                                <?php for ($lift = 1; $lift <= $lift_count; $lift++):
                                    $cellKey = $day . '-' . $shift . '-' . $lift;
                                    $selectedId = $table[$day][$shift][$lift] ?? '';
                                    ?>
                                    <td>
                                        <select name="schedule[<?= $cellKey ?>][mechanic_id]" onchange="updateTotal()">
                                            <option value="">Оставить пустым</option>
                                            <?php foreach ($mechanics as $mech):
                                                $rate = $mech['hourly_rate'];
                                                $min = $mech['min_shifts_per_week'];
                                                $max = $mech['max_shifts_per_week'];
                                                $info = " ({$rate}₽/ч, смен: $min-$max)"; ?>
                                                <option value="<?= $mech['id'] ?>" data-rate="<?= $rate ?>"
                                                    <?= ($selectedId == $mech['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($mech['mechanic_name']) . $info ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="schedule[<?= $cellKey ?>][day]" value="<?= $day ?>">
                                        <input type="hidden" name="schedule[<?= $cellKey ?>][shift]" value="<?= $shift ?>">
                                        <input type="hidden" name="schedule[<?= $cellKey ?>][lift]" value="<?= $lift ?>">
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor;
                    endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="totalCost" class="center">Общая стоимость: <span id="costValue">0</span> ₽</div>
        <button type="submit" class="styled-button">Утвердить расписание</button>
    </form>

    <script>
        function updateTotal() {
            let total = 0;
            const selects = document.querySelectorAll('select[name*="mechanic_id"]');

            selects.forEach(select => {
                const selected = select.options[select.selectedIndex];
                const rate = parseFloat(selected.getAttribute('data-rate'));
                const row = select.closest('tr');
                const shift = parseInt(row.querySelector('td:nth-child(2)').textContent.trim());

                // Используем значения длительности смен, переданные из PHP
                let shiftHours = 0;
                if (shift === 1) {
                    shiftHours = firstShiftHours;
                } else if (shift === 2) {
                    shiftHours = secondShiftHours;
                }

                // Проверим, что значения правильные
                console.log(`Смена: ${shift}, Длительность смены: ${shiftHours} ч, Ставка: ${rate}`);

                if (!isNaN(rate) && shiftHours > 0) {
                    total += rate * shiftHours;
                }
            });

            // Обновляем стоимость
            document.getElementById('costValue').textContent = Math.round(total);
            console.log('Общая стоимость:', total);
        }

        window.addEventListener('DOMContentLoaded', updateTotal);

        function returnToSchedule() {
            fetch('/clear-template')
                .then(() => window.location.href = '/schedule');
        }
    </script>
</body>