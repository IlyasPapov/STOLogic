<?php
require_once __DIR__ . '/../config/db.php';
session_start();

// Проверяем авторизацию
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];

// Получаем station_id
if (empty($_SESSION['station_id'])) {
    if ($user['role'] === 'manager') {
        $stmt = $pdo->prepare("SELECT id FROM stations WHERE manager_id = :id");
        $stmt->execute([':id' => $user['id']]);
        $station = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($station) {
            $_SESSION['station_id'] = $station['id'];
        } else {
            die("Станция не найдена");
        }
    } else {
        die("Станция не определена");
    }
}
$stationId = $_SESSION['station_id'];

// Обработка отметки заказа как выполненного
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order_id'])) {
    $completeOrderId = (int) $_POST['complete_order_id'];

    // Проверяем, что заказ принадлежит станции
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE id = :id AND station_id = :station_id");
    $stmt->execute([':id' => $completeOrderId, ':station_id' => $stationId]);
    if ($stmt->fetchColumn() > 0) {
        // Обновляем статус
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Выполнен' WHERE id = :id");
        $stmt->execute([':id' => $completeOrderId]);
    }
    // Перезагрузка страницы, чтобы избежать повторной отправки
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Обработка формы создания заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['complete_order_id'])) {
    $clientName = trim($_POST['client_name'] ?? '');
    $clientPhone = trim($_POST['client_phone'] ?? '');
    $car = trim($_POST['car'] ?? '');
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $mechanicId = (int) ($_POST['mechanic_id'] ?? 0);
    $orderDatetimeRaw = $_POST['date'] ?? '';
    $workPrice = (float) ($_POST['work_price'] ?? 0);
    $partsPrice = (float) ($_POST['parts_price'] ?? 0);
    $totalPrice = (float) ($_POST['total_price'] ?? 0);
    $estimatedHours = (float) ($_POST['time_hours'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // Проверка обязательных полей
    if (!$clientName || !$clientPhone || !$car || !$serviceId || !$mechanicId || !$orderDatetimeRaw) {
        $error = "Пожалуйста, заполните все обязательные поля.";
    } else {
        // Преобразуем формат даты из datetime-local (YYYY-MM-DDTHH:MM) в SQL TIMESTAMP
        $orderDatetime = str_replace('T', ' ', $orderDatetimeRaw) . ':00'; // Добавляем секунды

        // Проверяем, что услуга и механик принадлежат станции
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM station_services WHERE id = :service_id AND station_id = :station_id");
        $stmt->execute([':service_id' => $serviceId, ':station_id' => $stationId]);
        if ($stmt->fetchColumn() == 0) {
            $error = "Выбрана неверная услуга.";
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mechanics WHERE id = :mechanic_id AND station_id = :station_id");
        $stmt->execute([':mechanic_id' => $mechanicId, ':station_id' => $stationId]);
        if ($stmt->fetchColumn() == 0) {
            $error = "Выбран неверный механик.";
        }

        if (empty($error)) {
            // Вставляем заказ со статусом "Новый" по умолчанию
            $stmt = $pdo->prepare("
                INSERT INTO orders 
                (station_id, client_name, phone, car, service_id, mechanic_id, order_datetime, work_price, parts_price, total_price, estimated_hours, description, status, created_at)
                VALUES
                (:station_id, :client_name, :phone, :car, :service_id, :mechanic_id, :order_datetime, :work_price, :parts_price, :total_price, :estimated_hours, :description, 'Новый', NOW())
            ");
            $stmt->execute([
                ':station_id' => $stationId,
                ':client_name' => $clientName,
                ':phone' => $clientPhone,
                ':car' => $car,
                ':service_id' => $serviceId,
                ':mechanic_id' => $mechanicId,
                ':order_datetime' => $orderDatetime,
                ':work_price' => $workPrice,
                ':parts_price' => $partsPrice,
                ':total_price' => $totalPrice,
                ':estimated_hours' => $estimatedHours,
                ':description' => $description,
            ]);

            // Перезагрузка страницы чтобы избежать повторной отправки формы
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}

// Получаем список услуг станции
$stmtServices = $pdo->prepare("SELECT * FROM station_services WHERE station_id = :station_id");
$stmtServices->execute([':station_id' => $stationId]);
$services = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

// Получаем список механиков станции
$stmtMechanics = $pdo->prepare("SELECT * FROM mechanics WHERE station_id = :station_id");
$stmtMechanics->execute([':station_id' => $stationId]);
$mechanics = $stmtMechanics->fetchAll(PDO::FETCH_ASSOC);

// Получаем список заказов с JOIN, чтобы вывести имена услуги и механика
$stmtOrders = $pdo->prepare("
    SELECT o.*, s.service_name, m.mechanic_name
    FROM orders o
    JOIN station_services s ON o.service_id = s.id
    JOIN mechanics m ON o.mechanic_id = m.id
    WHERE o.station_id = :station_id
    ORDER BY o.order_datetime DESC
");
$stmtOrders->execute([':station_id' => $stationId]);
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8" />
    <title>Заказы — STOLogic</title>
    <style>
        body {
            background-color: #f9f9f9;
            font-family: Arial, sans-serif;
            margin: 20px 20px 40px;
            color: #222;
            padding: 0 20px 40px;
            /* чуть адаптировал под оба варианта */
        }

        header {
            background-color: #003366;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        header h2 {
            margin: 0;
            font-weight: 600;
        }

        .profile-wrapper {
            position: relative;
            cursor: pointer;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .profile-wrapper>div:first-child {
            user-select: none;
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
            box-shadow: 0 2px 5px rgb(0 0 0 / 0.15);
        }

        .profile-wrapper:hover .dropdown {
            display: block;
        }

        .dropdown a {
            text-decoration: none;
            color: black;
            display: block;
            padding: 10px;
            border-radius: 6px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .dropdown a:hover {
            background-color: #e74c3c;
            color: white;
        }

        .logout-btn {
            color: white;
            text-decoration: none;
            background: #004080;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 600;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #0059b3;
        }

        h1,
        h2 {
            color: #003366;
            margin-bottom: 20px;
        }

        main {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            padding: 20px 30px;
            border-radius: 6px
                /* согласовал с border-radius других блоков */
            ;
            box-shadow: 0 0 8px rgb(0 0 0 / 0.1);
        }

        # createForm {
            border: 1px solid #ccc;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 6px;
            background: #fafafa;
        }

        label,
        form label {
            display: block;
            margin-bottom: 6px;
            margin-top: 12px;
            font-weight: 600;
            color: #003366;
        }

        input[type="text"],
        input[type="number"],
        input[type="datetime-local"],
        select,
        textarea,
        form input[type="text"],
        form input[type="number"],
        form input[type="datetime-local"],
        form select,
        form textarea {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
            margin-bottom: 15px;
            resize: vertical;
            font-family: inherit;
            margin-top: 4px;
        }

        textarea {
            min-height: 60px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 900px;
            /* для прокрутки */
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px 12px;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
            white-space: nowrap;
        }

        th {
            background-color: #003366;
            color: white;
            font-weight: 600;
            user-select: none;
        }

        tr:nth-child(even) {
            background-color: #f2f6fb;
        }

        .alert {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }

        .btn-sm {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }

        .btn-success {
            background-color: #28a745;
            border: none;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s ease;
            padding: 4px 10px;
            font-size: 13px;
            border-radius: 4px;
            font-weight: 600;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-secondary,
        button.secondary {
            background-color: #6c757d;
            border: none;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-secondary:hover,
        button.secondary:hover {
            background-color: #5a6268;
        }

        .button,
        button {
            background-color: #003366;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .button:hover,
        button:hover {
            background-color: #002244;
        }

        .button.secondary {
            background-color: #555;
        }

        .button.secondary:hover {
            background-color: #333;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            color: white;
        }

        .bg-success,
        .badge.bg-success {
            background-color: #28a745;
            color: white;
            padding: 3px 7px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Обертка с горизонтальной прокруткой для таблицы */
        .table-wrapper {
            overflow-x: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px 0;
            margin-top: 20px;
        }

        /* Поиск */
        #searchInput {
            margin-bottom: 15px;
            padding: 8px 12px;
            width: 300px;
            max-width: 100%;
            font-size: 14px;
            border-radius: 6px;
            border: 1px solid #ccc;
            outline-offset: 0;
            outline: none;
            transition: border-color 0.3s;
        }

        #searchInput:focus {
            border-color: #003366;
        }
    </style>
</head>

<body>
    <header>
        <h2>STOLogic — Панель управления</h2>
        <div class="profile-wrapper">
            <div><?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars("Менеджер") ?>)</div>
            <div class="dropdown">
                <a href="/logout" class="logout-btn">Выйти</a>
            </div>
        </div>
    </header>

    <main>
        <h1>Заказы</h1>

        <button class="button secondary mb-3"
            onclick="window.location.href='http://localhost:8000/dashboard_manager'">Назад</button>

        <?php if (!empty($error)): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <button onclick="document.getElementById('createForm').style.display='block'" class="button mb-3">Создать
            заказ</button>

        <div id="createForm" style="display: none;">
            <form method="post" novalidate>
                <label>ФИО клиента:</label>
                <input type="text" name="client_name" required>

                <label>Телефон:</label>
                <input type="text" name="client_phone" required>

                <label>Автомобиль:</label>
                <input type="text" name="car" required>

                <label>Услуга:</label>
                <select name="service_id" required>
                    <option value="">-- выберите услугу --</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= $service['id'] ?>">
                            <?= htmlspecialchars($service['service_name']) ?> —
                            <?= number_format($service['price'], 2, ',', ' ') ?> ₽
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Механик:</label>
                <select name="mechanic_id" required>
                    <option value="">-- выберите механика --</option>
                    <?php foreach ($mechanics as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['mechanic_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Дата и время:</label>
                <input type="datetime-local" name="date" required>

                <label>Стоимость работ:</label>
                <input type="number" step="0.01" name="work_price" required>

                <label>Стоимость деталей:</label>
                <input type="number" step="0.01" name="parts_price" required>

                <label>Общая стоимость:</label>
                <input type="number" step="0.01" name="total_price" required>

                <label>Предполагаемое время (часы):</label>
                <input type="number" step="0.1" name="time_hours" required>

                <label>Описание:</label>
                <textarea name="description"></textarea>

                <button type="submit" class="button">Сохранить заказ</button>
                <button type="button" class="button secondary"
                    onclick="document.getElementById('createForm').style.display='none'">Отмена</button>
            </form>
        </div>

        <input type="text" id="searchInput" placeholder="Поиск по ФИО клиента..."
            title="Введите имя клиента для поиска">

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Клиент</th>
                        <th>Телефон</th>
                        <th>Автомобиль</th>
                        <th>Услуга</th>
                        <th>Механик</th>
                        <th>Дата и время</th>
                        <th>Стоимость работ</th>
                        <th>Стоимость деталей</th>
                        <th>Общая стоимость</th>
                        <th>Время (ч)</th>
                        <th>Описание</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php if (count($orders) === 0): ?>
                        <tr>
                            <td colspan="13" style="text-align:center;">Заказы отсутствуют</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['client_name']) ?></td>
                                <td><?= htmlspecialchars($order['phone']) ?></td>
                                <td><?= htmlspecialchars($order['car']) ?></td>
                                <td><?= htmlspecialchars($order['service_name']) ?></td>
                                <td><?= htmlspecialchars($order['mechanic_name']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($order['order_datetime'])) ?></td>
                                <td><?= number_format($order['work_price'], 2, ',', ' ') ?> ₽</td>
                                <td><?= number_format($order['parts_price'], 2, ',', ' ') ?> ₽</td>
                                <td><?= number_format($order['total_price'], 2, ',', ' ') ?> ₽</td>
                                <td><?= htmlspecialchars($order['estimated_hours']) ?></td>
                                <td><?= nl2br(htmlspecialchars($order['description'])) ?></td>
                                <td><?= htmlspecialchars($order['status'] ?? 'Новый') ?></td>
                                <td>
                                    <?php if (($order['status'] ?? 'Новый') !== 'Выполнен'): ?>
                                        <form method="post" style="margin:0;">
                                            <input type="hidden" name="complete_order_id" value="<?= $order['id'] ?>">
                                            <button type="submit" class="btn-success btn-sm">Выполнить</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-success">Выполнен</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Поиск по ФИО клиента
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('ordersTableBody');

        searchInput.addEventListener('input', () => {
            const filter = searchInput.value.toLowerCase();
            const rows = tableBody.querySelectorAll('tr');

            rows.forEach(row => {
                const clientNameCell = row.cells[0];
                if (!clientNameCell) return; // если нет ячеек (например, пустая строка)

                const clientName = clientNameCell.textContent.toLowerCase();
                if (clientName.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>

</html>