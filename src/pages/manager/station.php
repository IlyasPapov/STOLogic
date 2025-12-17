<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user'])) {
    session_start();
}

$user = $_SESSION['user'];

$station_id = $_SESSION['station_id'] ?? null;
if (!$station_id) {
    die("Станция не найдена для этого менеджера.");
}

$roleMap = [
    'manager' => 'Менеджер',
    'mechanic' => 'Механик',
    'accountant' => 'Бухгалтер'
];
$roleName = $roleMap[$user['role']] ?? $user['role'];

$managerStation = null;
if ($user['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT * FROM stations WHERE manager_id = :id");
    $stmt->execute(['id' => $user['id']]);
    $managerStation = $stmt->fetch(PDO::FETCH_ASSOC);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'manager') {
    $action = $_POST['action'] ?? null;
    $requestId = $_POST['request_id'] ?? null;

    if ($action && $requestId) {
        $stmt = $pdo->prepare("SELECT * FROM station_requests WHERE id = :id AND status = 'pending'");
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($request && $request['station_id'] == $managerStation['id']) {
            if ($action === 'approve') {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE users SET station_id = :station_id, approved = true WHERE id = :user_id");
                $stmt->execute([
                    'station_id' => $managerStation['id'],
                    'user_id' => $request['user_id']
                ]);

                $stmt = $pdo->prepare("UPDATE station_requests SET status = 'approved' WHERE id = :id");
                $stmt->execute(['id' => $requestId]);

                $pdo->commit();
                $message = 'Заявка успешно одобрена.';
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE station_requests SET status = 'rejected' WHERE id = :id");
                $stmt->execute(['id' => $requestId]);
                $message = 'Заявка отклонена.';
            }
        }
    }
}

$requests = [];
if ($user['role'] === 'manager' && $managerStation) {
    $stmt = $pdo->prepare("SELECT sr.*, u.full_name FROM station_requests sr JOIN users u ON sr.user_id = u.id WHERE sr.station_id = :station_id AND sr.status = 'pending'");
    $stmt->execute(['station_id' => $managerStation['id']]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$employeeStmt = $pdo->prepare("
    SELECT u.*, s.name AS station_name 
    FROM users u 
    LEFT JOIN stations s ON u.station_id = s.id 
    LEFT JOIN station_requests sr ON sr.user_id = u.id AND sr.station_id = s.id 
    WHERE s.id = :station_id AND sr.status = 'approved'
");
$employeeStmt->execute(['station_id' => $managerStation['id']]);
$employees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>STOLogic — Станция</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f4f4;
        }

        header {
            background-color: #003366;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }


        h3,
        h4 {
            color: #003366;
        }

        main {
            padding: 20px;
            align-items: center;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }

        th {
            background-color: #eaeaea;
        }

        .button,
        .toggle-btn {
            padding: 8px 16px;
            background-color: #003366;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px 5px 5px 0;
            display: inline-block;
            border: none;
            cursor: pointer;
        }

        .button:hover,
        .toggle-btn:hover {
            background-color: #00509e;
        }

        .reject-btn {
            background-color: #b33d3d;
        }

        .reject-btn:hover {
            background-color: #990000;
        }

        .logout-btn {
            background-color: white;
            color: #003366;
            border: 1px solid #003366;
            border-radius: 0;
            padding: 10px;
            display: block;
            text-align: left;
        }

        .logout-btn:hover {
            background-color: #990000;
            color: white;
            border-color: #990000;
        }

        .profile-wrapper {
            position: relative;
        }

        .dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            border: 1px solid #ccc;
            min-width: 150px;
            z-index: 1000;
        }

        .profile-wrapper:hover .dropdown {
            display: block;
        }

        .dropdown a {
            display: block;
            text-decoration: none;
            border-bottom: 1px solid #eee;
        }

        .hidden {
            display: none;
        }

        .top-buttons {
            margin-bottom: 20px;
        }
    </style>
    <script>
        function toggleSection(id) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden');
        }
    </script>
</head>

<body>
    <header>
        <h2>STOLogic — Станция</h2>
        <div class="profile-wrapper">
            <div><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($roleName) ?>)</div>
            <div class="dropdown">
                <a href="/logout" class="logout-btn">Выйти</a>
            </div>
        </div>
    </header>

    <main>
        <div class="top-buttons">
            <a href="/dashboard_manager" class="button">Назад</a>
        </div>

        <h3>Добро пожаловать, <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>!</h3>

        <?php if ($message): ?>
            <p style="color: green;"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php if ($user['role'] === 'manager'): ?>
            <h4>Панель менеджера</h4>

            <?php if (!$managerStation): ?>
                <p>У вас ещё нет зарегистрированной СТО.</p>
                <a href="/create-station" class="button">Создать СТО</a>
            <?php else: ?>
                <p><strong>СТО:</strong> <?= htmlspecialchars($managerStation['name']) ?></p>

                <a href="#" class="toggle-btn" onclick="toggleSection('employeeList')">Сотрудники</a>
                <a href="#" class="toggle-btn" onclick="toggleSection('requestList')">Заявки на привязку</a>

                <div id="employeeList" class="hidden">
                    <h4>Список сотрудников</h4>
                    <?php if ($employees): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ФИО</th>
                                    <th>Email</th>
                                    <th>Телефон</th>
                                    <th>СТО</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $e): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($e['full_name']) ?></td>
                                        <td><?= htmlspecialchars($e['email']) ?></td>
                                        <td><?= htmlspecialchars($e['phone']) ?></td>
                                        <td><?= htmlspecialchars($e['station_name']) ?></td>
                                        <td><a class="button" href="/remove-employee?employee_id=<?= $e['id'] ?>">Удалить</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Нет привязанных сотрудников.</p>
                    <?php endif; ?>
                </div>

                <div id="requestList" class="hidden">
                    <h4>Заявки на привязку</h4>
                    <?php if ($requests): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ФИО</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button class="button" type="submit">Принять</button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button class="button reject-btn" type="submit">Отклонить</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Нет заявок.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p>Эта страница доступна только для менеджеров.</p>
        <?php endif; ?>
    </main>
</body>

</html>