<?php
// src/pages/dashboard_mechanic.php

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mechanic') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$station = null;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => $user['id']]);
$updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($updatedUser && ($updatedUser['station_id'] !== $user['station_id'] || $updatedUser['approved'] != $user['approved'])) {
    $_SESSION['user'] = $updatedUser;
    $user = $updatedUser;
}

if (!empty($user['station_id']) && $user['approved']) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name AS manager_name
        FROM stations s
        JOIN users u ON s.manager_id = u.id
        WHERE s.id = :station_id
    ");
    $stmt->execute(['station_id' => $user['station_id']]);
    $station = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Обработка обновления личных данных механика
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mechanic'])) {
    $minShifts = (int) $_POST['min_shifts_per_week'];
    $maxShifts = (int) $_POST['max_shifts_per_week'];
    $rate = (float) $_POST['hourly_rate'];


    $stmt = $pdo->prepare("
        UPDATE mechanics
        SET min_shifts_per_week = :min, max_shifts_per_week = :max, hourly_rate = :rate
        WHERE user_id = :user_id
    ");
    $stmt->execute([
        'min' => $minShifts,
        'max' => $maxShifts,
        'rate' => $rate,
        'user_id' => $user['id']
    ]);

    header("Location: /dashboard_mechanic");
    exit;
}
// Присваиваем роль для пользователя
$roleName = match ($user['role']) {
    'mechanic' => 'Механик',
    'accountant' => 'Бухгалтер',
    'manager' => 'Менеджер',
    default => 'Неизвестная роль'
};

// Получение текущих данных механика
$mechanicData = null;
$stmt = $pdo->prepare("SELECT * FROM mechanics WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user['id']]);
$mechanicData = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Панель механика</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .hidden {
            display: none;
        }

        .toggle-btn {
            color: #003366;
            text-decoration: underline;
            cursor: pointer;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            text-align: center;
            font-family: sans-serif;
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #003366;
            color: white;
        }

        table,
        th,
        td {
            border: 1px solid #003366;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
        }

        .btn {
            color: white;
            padding: 10px 15px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            align-items: center;
            text-align: center;


        }

        .btn:hover {
            background-color: #00509e;
        }

        .btn-danger {
            background-color: #b33d3d;
        }

        .btn-danger:hover {
            background-color: #900;
        }

        form>* {
            margin-bottom: 10px;
            display: block;
            align-items: center;
            text-align: center;
        }

        label {
            font-weight: bold;
        }

        main {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            padding: 20px;
        }

        section {
            align-items: center;
            text-align: center;
            width: 100%;
            max-width: 800px;
        }

        .form-container {
            width: 100%;
            max-width: 800px;
        }

        .dropdown a:hover {
            background-color: #e74c3c;
            /* Красный при наведении */
            color: white;
            /* Белый текст при наведении */
        }

        @media (max-width: 768px) {
            section {
                width: 100%;
            }

            .form-container {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <header>
        <h2 style="color: white;">STOLogic — Панель управления</h2>
        <div class="profile-wrapper">
            <div><?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($roleName) ?>)</div>
            <div class="dropdown">
                <a href="/logout" class="logout-btn">Выйти</a>
            </div>
        </div>
    </header>

    <main>
        <?php if ($station): ?>
            <section>
                <h2>Моё СТО</h2>
                <p style="display: flex;flex-direction: column; align-items: center; text-align: center">
                    <strong>СТО:</strong>
                    <?= htmlspecialchars($station['name']) ?>
                </p>
                <p></p>
                <p><strong>Менеджер:</strong> <?= htmlspecialchars($station['manager_name']) ?></p>
                <p><strong>Продолжительность смены:</strong>
                    <?php
                    if ($station['working_hours']) {
                        [$start, $end] = explode('-', $station['working_hours']);
                        [$startH, $startM] = explode(':', $start);
                        [$endH, $endM] = explode(':', $end);

                        $startMinutes = $startH * 60 + $startM;
                        $endMinutes = $endH * 60 + $endM;
                        $totalMinutes = $endMinutes - $startMinutes;
                        $shiftMinutes = $totalMinutes / 2;

                        $hours = floor($shiftMinutes / 60);
                        $minutes = $shiftMinutes % 60;

                        echo "$hours ч." . ($minutes > 0 ? " $minutes мин." : '');
                    } else {
                        echo 'не указано';
                    }
                    ?>
                </p>
                <button type="submit" class="btn" onclick="location.href='/my-schedule'">Посмотреть мое расписание</button>
            </section>
        <?php else: ?>
            <section>
                <h2>Заявка на присоединение к СТО</h2>
                <?php
                $stmt = $pdo->prepare("
                SELECT s.* 
                FROM stations s 
                WHERE s.id NOT IN (
                    SELECT station_id 
                    FROM station_requests 
                    WHERE user_id = :user_id AND status != 'rejected'
                )
            ");
                $stmt->execute(['user_id' => $user['id']]);
                $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if (!empty($stations)): ?>
                    <form method="POST" action="send-request" class="form-block">
                        <label for="station_id">Выберите СТО:</label>
                        <select name="station_id" required>
                            <option value="">Выберите СТО</option>
                            <?php foreach ($stations as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn">Отправить заявку</button>
                    </form>
                <?php else: ?>
                    <p>Нет доступных СТО для подачи заявки.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="form-container">
            <h2>Личные настройки</h2>
            <?php if ($mechanicData): ?>
                <form method="POST">
                    <input type="hidden" name="update_mechanic" value="1">

                    <label>Минимум смен в неделю:</label>
                    <input type="number" name="min_shifts_per_week" value="<?= $mechanicData['min_shifts_per_week'] ?>"
                        min="0" max="12" required>

                    <label>Максимум смен в неделю:</label>
                    <input type="number" name="max_shifts_per_week" value="<?= $mechanicData['max_shifts_per_week'] ?>"
                        min="0" max="12" required>

                    <label>Ставка за час (₽):</label>
                    <input type="number" name="hourly_rate" value="<?= $mechanicData['hourly_rate'] ?>" step="0.01"
                        min="240" max="1000" required>

                    <button type="submit" class="btn">Сохранить изменения</button>
                </form>
            <?php else: ?>
                <p>Ваши данные механика пока не настроены. Обратитесь к менеджеру.</p>
            <?php endif; ?>
        </section>

        <section>
            <h2>Мои заявки</h2>
            <?php
            $stmt = $pdo->prepare("
            SELECT sr.*, s.name AS station_name
            FROM station_requests sr
            JOIN stations s ON sr.station_id = s.id
            WHERE sr.user_id = :user_id
            ORDER BY sr.created_at DESC
        ");
            $stmt->execute(['user_id' => $user['id']]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if ($requests): ?>
                <a type=" submit" class="btn" href="#" onclick="toggleSection('myRequests')">Показать/Скрыть заявки</a>
                <div id="myRequests" class="hidden">
                    <table>
                        <thead>
                            <tr>
                                <th>СТО</th>
                                <th>Статус</th>
                                <th>Дата</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['station_name']) ?></td>
                                    <td><?= htmlspecialchars($r['status']) ?></td>
                                    <td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <form class="inline" method="post" action="/delete-invite"
                                                onsubmit="return confirm('Удалить заявку?');">
                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                <button type="submit" class="btn btn-danger">Удалить</button>
                                            </form>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Вы ещё не подавали заявок.</p>
            <?php endif; ?>
        </section>

        <section>
            <h2>Переход на склад</h2>
            <a href="/warehouseMech" type=" submit" class="btn">Перейти на склад</a>
        </section>

        <section>
            <h2>Список задач</h2>
            <p>Вы можете вести собственный список задач, отмечать выполненные и удалять ненужные.</p>
            <a href="/mechanic_tasks" class="btn">Мой список задач</a>
        </section>
    </main>

    <script>
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            section.classList.toggle('hidden');
        }
    </script>
</body>

</html>