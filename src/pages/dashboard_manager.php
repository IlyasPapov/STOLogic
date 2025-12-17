<?php

require_once __DIR__ . '/../config/db.php';
session_start();  // Обязательно вызываем session_start() в начале файла

// Проверяем, что пользователь авторизован
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];

// Маппинг ролей
$roleMap = [
    'manager' => 'Менеджер',
    'mechanic' => 'Механик',
    'accountant' => 'Бухгалтер'
];
$roleName = $roleMap[$user['role']] ?? $user['role'];

// Ищем станцию для менеджера
$managerStation = null;
if ($user['role'] === 'manager') {
    // Получаем станцию для менеджера
    $stmt = $pdo->prepare("SELECT * FROM stations WHERE manager_id = :id");
    $stmt->execute(['id' => $user['id']]);
    $managerStation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($managerStation) {
        $_SESSION['station_id'] = $managerStation['id'];  // Сохраняем station_id в сессию
    } else {
        $_SESSION['station_id'] = null;
    }
}
if ($managerStation) {
    $_SESSION['station_id'] = $managerStation['id'];

    // Расчёт времени работы по сменам
    $firstShiftStart = $managerStation['first_shift_start'];
    $secondShiftEnd = $managerStation['second_shift_end'];

    if ($firstShiftStart && $secondShiftEnd) {
        $startTime = date('H:i', strtotime($firstShiftStart));
        $endTime = date('H:i', strtotime($secondShiftEnd));
        $workingHoursFormatted = "с $startTime до $endTime";
    } else {
        $workingHoursFormatted = "не задан";
    }
} else {
    $_SESSION['station_id'] = null;

}

$successMessage = '';
$errorMessage = '';

// Добавление услуги
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service']) && $managerStation) {
    $serviceName = trim($_POST['service_name']);
    $price = floatval($_POST['price']);
    $duration = intval($_POST['duration']);

    if ($serviceName && $price > 0 && $duration > 0) {
        $stmt = $pdo->prepare("INSERT INTO station_services (station_id, service_name, price, duration_minutes) VALUES (:station_id, :service_name, :price, :duration)");
        $stmt->execute([
            'station_id' => $managerStation['id'],
            'service_name' => $serviceName,
            'price' => $price,
            'duration' => $duration
        ]);
        $successMessage = 'Услуга добавлена.';
    } else {
        $errorMessage = 'Пожалуйста, заполните все поля корректно.';
    }
}

// Удаление услуги
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service']) && $managerStation) {
    $serviceId = intval($_POST['delete_service']);
    $stmt = $pdo->prepare("DELETE FROM station_services WHERE id = :id AND station_id = :station_id");
    $stmt->execute([
        'id' => $serviceId,
        'station_id' => $managerStation['id']
    ]);
    $successMessage = 'Услуга удалена.';
}

// Получение списка услуг
$services = [];
if ($managerStation) {
    $stmt = $pdo->prepare("SELECT * FROM station_services WHERE station_id = :id ORDER BY created_at DESC");
    $stmt->execute(['id' => $managerStation['id']]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Панель менеджера</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .button {
            background-color: #003366;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .button:hover {
            background-color: #002244;
        }

        main {
            display: flex;
            justify-content: center;
        }

        .container {
            max-width: 800px;
            width: 100%;
        }

        input[type="text"],
        input[type="number"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
        }

        /* Стили для выпадающего меню */
        .profile-wrapper {
            position: relative;
        }

        .profile-btn {
            font-weight: bold;
            cursor: pointer;
            position: relative;
        }

        .profile-wrapper .dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            color: black;
            border: 1px solid #ccc;
            min-width: 150px;
            z-index: 1000;
            border-radius: 6px;
        }

        .profile-wrapper:hover .dropdown {
            display: block;
        }

        .dropdown a {
            text-decoration: none;
            color: black;
            display: block;
            padding: 10px;
            background-color: white;
            border-radius: 6px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .dropdown a:hover {
            background-color: #e74c3c;
            /* Красный при наведении */
            color: white;
            /* Белый текст при наведении */
        }
    </style>
    <script>
        function toggleServices() {
            const block = document.getElementById('services-list');
            const btn = document.getElementById('toggle-btn');
            if (block.style.display === 'none') {
                block.style.display = 'block';
                btn.textContent = 'Скрыть список услуг';
            } else {
                block.style.display = 'none';
                btn.textContent = 'Показать список услуг';
            }
        }
    </script>
</head>

<body>
    <header>
        <h2 style="color: white;"> STOLogic — Панель управления</h2>
        <div class="profile-wrapper">
            <div><?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($roleName) ?>)</div>
            <div class="dropdown">
                <a href="/logout" class="logout-btn">Выйти</a>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <h3>Добро пожаловать, <?= htmlspecialchars($user['username']) ?>!</h3>

            <?php if ($user['role'] === 'manager'): ?>
                <section style="margin-top: 30px;">
                    <h3>Информация о вашей СТО</h3>
                    <?php if (!$managerStation): ?>
                        <p>Вы ещё не зарегистрировали свою СТО.</p>
                        <a class="button" href="/create-station">Зарегистрировать СТО</a>
                    <?php else: ?>
                        <div style="border: 1px solid #ccc; border-radius: 8px; padding: 20px; background: #fff;">
                            <p><strong>Название:</strong> <?= htmlspecialchars($managerStation['name']) ?></p>
                            <p><strong>Адрес:</strong> <?= htmlspecialchars($managerStation['address']) ?></p>
                            <p><strong>Количество подъемников:</strong> <?= (int) $managerStation['lift_count'] ?></p>
                            <p><strong>Парковка:</strong> <?= $managerStation['has_parking'] ? 'Есть' : 'Нет' ?></p>
                            <?php if ($managerStation['has_parking']): ?>
                                <p><strong>Мест на парковке:</strong> <?= (int) $managerStation['parking_slots'] ?></p>
                            <?php endif; ?>
                            <p><strong>График работы:</strong> <?= htmlspecialchars($workingHoursFormatted) ?></p>

                            <p><strong>Дата создания:</strong>
                                <?= date('d.m.Y H:i', strtotime($managerStation['created_at'])) ?></p>

                            <div style="
                                display: flex; 
                                justify-content: center; 
                                gap: 15px; 
                                flex-wrap: wrap;
                                margin-top: 20px;
                                ">
                                <a class="button" href="/manager/station"
                                    style="flex: 1 1 140px; text-align: center;">Управление СТО</a>
                                <a class="button" href="/manager/edit-station"
                                    style="flex: 1 1 140px; text-align: center;">Редактировать СТО</a>
                                <a class="button" href="/manager/warehouse" style="flex: 1 1 140px; text-align: center;">Перейти
                                    к складу</a>
                                <a class="button" href="/schedule" style="flex: 1 1 140px; text-align: center;">Расписание</a>
                                <a class="button" href="/order" style="flex: 1 1 140px; text-align: center;">Посмотреть
                                    заказы</a>
                                <a class="button" href="/financial_report" style="flex: 1 1 140px; text-align: center;">Перейти
                                    к отчётам</a>
                            </div>


                        </div>

                        <div style="margin-top: 40px;">
                            <h3>Добавить услугу</h3>
                            <?php if ($successMessage): ?>
                                <p style="color: green;"><?= htmlspecialchars($successMessage) ?></p>
                            <?php elseif ($errorMessage): ?>
                                <p style="color: red;"><?= htmlspecialchars($errorMessage) ?></p>
                            <?php endif; ?>

                            <form method="post" style="display: flex; flex-direction: column; gap: 10px;">
                                <input type="text" name="service_name" placeholder="Вид услуги" required>
                                <input type="number" step="0.01" name="price" placeholder="Стоимость (₽)" required>
                                <input type="number" name="duration" placeholder="Время выполнения (мин)" required>
                                <button type="submit" name="add_service" class="button">Добавить услугу</button>
                            </form>

                            <button onclick="toggleServices()" id="toggle-btn" class="button" style="margin-top: 20px;">Показать
                                список услуг</button>

                            <div id="services-list" style="margin-top: 20px; display: none;">
                                <?php if (!empty($services)): ?>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Услуга</th>
                                                <th>Стоимость</th>
                                                <th>Время (мин)</th>
                                                <th>Добавлена</th>
                                                <th>Действие</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($services as $s): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($s['service_name']) ?></td>
                                                    <td><?= number_format($s['price'], 2, ',', ' ') ?> ₽</td>
                                                    <td><?= (int) $s['duration_minutes'] ?> мин</td>
                                                    <td><?= date('d.m.Y H:i', strtotime($s['created_at'])) ?></td>
                                                    <td>
                                                        <form method="post"
                                                            onsubmit="return confirm('Вы уверены, что хотите удалить эту услугу?');">
                                                            <input type="hidden" name="delete_service" value="<?= $s['id'] ?>">
                                                            <button type="submit" class="button"
                                                                style="background-color: #e74c3c;">Удалить</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p>Услуги ещё не добавлены.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>