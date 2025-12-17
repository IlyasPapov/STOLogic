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

$stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
$stmt->execute([$station_id]);
$station = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$station) {
    die('Станция не найдена');
}

// Получение механиков текущей станции
$stmt = $pdo->prepare("SELECT * FROM mechanics WHERE station_id = ?");
$stmt->execute([$station_id]);
$mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Сохраняем механиков в сессии
$_SESSION['generated_schedule']['mechanics'] = $mechanics;

// Диагностика: Выводим данные сессии
// echo '<pre>';
// // print_r($_SESSION['generated_schedule']);
// echo '</pre>';

$total_mechanics = count($mechanics);
$active_mechanics = count(array_filter($mechanics, fn($m) => $m['is_active_schedule']));

$week_schedule = null;
$summary_info = '';

// Получаем текущие данные станции
$stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
$stmt->execute([$station_id]);
$station = $stmt->fetch(PDO::FETCH_ASSOC);

$saved_working_days = explode(',', $station['working_days']);

// Карта дней недели
$day_name_to_number = ['Пн' => 1, 'Вт' => 2, 'Ср' => 3, 'Чт' => 4, 'Пт' => 5, 'Сб' => 6, 'Вс' => 7];

function getWorkingDatesFromWeek($start_date, $working_days)
{
    global $day_name_to_number;
    $result = [];

    // Проверяем, что start_date — это валидная дата в формате Y-m-d
    if (strtotime($start_date) === false) {
        return [];  // Возвращаем пустой массив, если дата некорректна
    }

    // Проверяем, что working_days — это массив
    if (!is_array($working_days)) {
        return [];  // Возвращаем пустой массив, если working_days не является массивом
    }

    // Делаем проверку на корректность данных в working_days
    foreach ($working_days as $wd) {
        if (!in_array($wd, ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'])) {
            return [];  // Возвращаем пустой массив, если день недели некорректен
        }
    }

    // Генерация рабочих дней
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("$start_date +$i days"));
        $dayOfWeek = date('N', strtotime($date)); // 1 = Пн, 7 = Вс

        foreach ($working_days as $wd) {
            if ($day_name_to_number[$wd] == $dayOfWeek) {
                $result[] = $date;
                break;  // Прерываем цикл, если день совпал с рабочим
            }
        }
    }

    return $result;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start_date = $_POST['start_date'];
    $working_days = $_POST['working_days'] ?? [];
    $algorithm = $_POST['algorithm'];

    $first_shift_start = $_POST['first_shift_start'];
    $second_shift_start = $_POST['second_shift_start'];
    $second_shift_end = $_POST['second_shift_end'];

    // Сохраняем рабочие дни как строки "Пн,Вт,..."
    $working_days_str = implode(',', $working_days);
    $stmt = $pdo->prepare("UPDATE stations SET working_days = ?, first_shift_start = ?, second_shift_start = ?, second_shift_end = ? WHERE id = ?");
    $stmt->execute([$working_days_str, $first_shift_start, $second_shift_start, $second_shift_end, $station_id]);

    // Обновляем данные станции
    $stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
    $stmt->execute([$station_id]);
    $station = $stmt->fetch(PDO::FETCH_ASSOC);

    // Получаем даты рабочих дней на текущей неделе
    $working_dates = getWorkingDatesFromWeek($start_date, $working_days);

    // Сохраняем всё в сессию
    $_SESSION['generated_schedule']['station'] = [
        'start_date' => $start_date,
        'end_date' => date('Y-m-d', strtotime("$start_date +6 days")),
        'working_days' => $working_days,
        'working_dates' => $working_dates,
        'first_shift_start' => $first_shift_start,
        'second_shift_start' => $second_shift_start,
        'second_shift_end' => $second_shift_end,
    ];

    // Переход к выбранному алгоритму
    switch ($algorithm) {
        case 'cost':
            require_once __DIR__ . '/../tools/generate_schedule_cost.php';
            break;
        case 'balanced':
            require_once __DIR__ . '/../tools/generate_schedule_balanced.php';
            break;
        case 'priority':
            require_once __DIR__ . '/../tools/generate_schedule_priority.php';
            break;
        default:
            $week_schedule = [];
            $summary_info = 'Неверный выбор алгоритма.';
    }
}
?>


<!DOCTYPE html>
<html lang="ru">

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Создание расписания</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .days-grid {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .day-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            font-weight: bold;
            font-size: 14px;
        }

        .day-column input[type="checkbox"] {
            transform: scale(1.3);
            margin-top: 6px;
            cursor: pointer;
        }

        .mechanic-list {
            margin-top: 2rem;
            padding: 1rem;
            border: 1px solid #ccc;
            background: #f9f9f9;
            border-radius: 5px;
        }

        .mechanic-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .mechanic-row:last-child {
            border-bottom: none;
        }

        .status {
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
        }

        th,
        td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: center;
        }

        th {
            background-color: #003366;
            color: white;
        }

        .btn {
            background-color: #003366;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn:hover {
            background-color: #002244;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="/schedule" class="btn" style="margin-bottom: 1rem; display: inline-block;">Назад к списку
            расписаний</a>

        <h1>Создание нового расписания</h1>

        <form method="POST" action="create-schedule">
            <div class="form-group">
                <label for="start_date">Выберите день начала расписания (с понедельника):</label><br>
                <input type="date" name="start_date" id="start_date" required>
            </div>

            <div class="form-group">
                <label>Выберите рабочие дни:</label>
                <div class="days-grid">
                    <?php
                    $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                    foreach ($days as $day):
                        $checked = in_array($day, $saved_working_days) ? 'checked' : '';
                        ?>
                        <div class="day-column">
                            <?= $day ?>
                            <input type="checkbox" name="working_days[]" value="<?= $day ?>" <?= $checked ?>>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="algorithm">Выберите алгоритм генерации:</label><br>
                <select name="algorithm" id="algorithm" required>
                    <option value="cost">Минимальная стоимость</option>
                    <option value="balanced">Сбалансированное распределение</option>
                    <option value="priority">По приоритету</option>
                </select>
            </div>

            <div class="form-group">
                <label for="first_shift_start">Время начала первой смены:</label><br>
                <input type="time" name="first_shift_start" id="first_shift_start"
                    value="<?= htmlspecialchars($station['first_shift_start']) ?>" required>
            </div>

            <div class="form-group">
                <label for="second_shift_start">Время начала второй смены:</label><br>
                <input type="time" name="second_shift_start" id="second_shift_start"
                    value="<?= htmlspecialchars($station['second_shift_start']) ?>" required>
            </div>

            <div class="form-group">
                <label for="second_shift_end">Время окончания второй смены:</label><br>
                <input type="time" name="second_shift_end" id="second_shift_end"
                    value="<?= htmlspecialchars($station['second_shift_end']) ?>" required>
            </div>

            <div class="mechanic-list">
                <h2>Участвующие в расписании механики</h2>
                <p><span class="status">Всего:</span> <?= $total_mechanics ?> <span class="status">Активны:</span>
                    <span id="active-count"><?= $active_mechanics ?></span>
                </p>

                <?php foreach ($mechanics as $mech): ?>
                    <div class="mechanic-row">
                        <div>
                            <?= htmlspecialchars($mech['mechanic_name']) ?>
                            (<?= $mech['hourly_rate'] ?>₽/ч, смен:
                            <?= $mech['min_shifts_per_week'] ?>–<?= $mech['max_shifts_per_week'] ?>)
                        </div>
                        <div>
                            <label>
                                <input type="checkbox" class="active-toggle" data-id="<?= $mech['id'] ?>"
                                    <?= $mech['is_active_schedule'] ? 'checked' : '' ?>>
                                Участвует
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn" style="margin-top: 25px;">Создать новое расписание</button>
        </form>

        <?php if ($week_schedule): ?>
            <h2 style="margin-top: 40px;">Результат генерации</h2>
            <?php if (!empty($summary_info)): ?>
                <p><strong>Информация:</strong> <?= htmlspecialchars($summary_info) ?></p>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>День</th>
                        <?php for ($lift = 1; $lift <= $station['lift_count']; $lift++): ?>
                            <th>Подъемник <?= $lift ?> (Смена 1)</th>
                            <th>Подъемник <?= $lift ?> (Смена 2)</th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($week_schedule as $day => $lifts): ?>
                        <tr>
                            <td><?= $day ?></td>
                            <?php foreach ($lifts as $shift_data): ?>
                                <td><?= $shift_data['shift1'] ?? '—' ?></td>
                                <td><?= $shift_data['shift2'] ?? '—' ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        document.querySelectorAll('.active-toggle').forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                const mechanicId = this.dataset.id;
                const isActive = this.checked ? 1 : 0;

                fetch('update_mechanic_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'mechanic_id=' + mechanicId + '&is_active_schedule=' + isActive
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('active-count').textContent = data.active_count;
                        } else {
                            alert('Ошибка при обновлении статуса.');
                        }
                    })
                    .catch(() => alert('Ошибка соединения с сервером.'));
            });
        });
    </script>

</body>

</html>