<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];

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

$requests = [];
if ($user['role'] === 'manager' && $managerStation) {
    $stmt = $pdo->prepare("
        SELECT sr.*, u.full_name, u.username, u.email, u.phone, u.role
        FROM station_requests sr
        JOIN users u ON sr.user_id = u.id
        WHERE sr.station_id = :station_id AND sr.status = 'pending'
    ");
    $stmt->execute(['station_id' => $managerStation['id']]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($user['role'] === 'mechanic' || $user['role'] === 'accountant')) {
    $station_id = $_POST['station_id'];

    if ($user['station_id']) {
        echo "Вы уже привязаны к СТО.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM station_requests WHERE user_id = :user_id AND station_id = :station_id AND status = 'pending'");
        $stmt->execute([
            'user_id' => $user['id'],
            'station_id' => $station_id
        ]);
        if ($stmt->fetch()) {
            echo "Вы уже отправили заявку на эту СТО.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO station_requests (user_id, station_id, status) VALUES (:user_id, :station_id, 'pending')");
            $stmt->execute([
                'user_id' => $user['id'],
                'station_id' => $station_id
            ]);
            echo "Запрос на привязку отправлен.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Панель управления</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
        }

        header {
            background-color: #1e90ff;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .profile-wrapper {
            position: relative;
        }

        .profile-btn {
            font-weight: bold;
            cursor: pointer;
        }

        .dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            color: black;
            border: 1px solid #ccc;
            min-width: 150px;
            z-index: 1000;
        }

        .dropdown a {
            text-decoration: none;
            color: black;
            display: block;
            padding: 10px;
        }

        .dropdown a:hover {
            background-color: #f0f0f0;
        }

        .profile-wrapper:hover .dropdown {
            display: block;
        }

        .role-specific-content {
            margin-top: 20px;
        }

        .create-btn,
        .toggle-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
            margin-right: 10px;
        }

        .create-btn:hover,
        .toggle-btn:hover {
            background-color: #218838;
        }

        .hidden {
            display: none;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
        }

        th {
            background-color: #f8f8f8;
        }

        form.inline {
            display: inline;
        }
    </style>
    <script>
        function toggleSection(id) {
            const el = document.getElementById(id);
            el.classList.toggle('hidden');
        }
    </script>
</head>

<body>
    <header>
        <h2>STOLogic — Панель управления</h2>
        <div class="profile-wrapper">
            <div class="profile-btn">
                <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($roleName) ?>)
            </div>
            <div class="dropdown">
                <a href="/logout">Выход</a>
            </div>
        </div>
    </header>

    <main style="padding: 20px;">
        <h3>Добро пожаловать, <?= htmlspecialchars($user['username']) ?>!</h3>

        <div class="role-specific-content">
            <?php if ($user['role'] === 'manager'): ?>
                <h3>Менеджер</h3>

                <?php if (!$managerStation): ?>
                    <p>Вы ещё не зарегистрировали свою СТО.</p>
                    <a class="create-btn" href="/create-station">Зарегистрировать СТО</a>
                <?php else: ?>
                    <p><strong>Ваша СТО:</strong> <?= htmlspecialchars($managerStation['name']) ?></p>

                    <a class="toggle-btn" href="#" onclick="toggleSection('employeeList')">Показать сотрудников</a>
                    <a class="toggle-btn" href="#" onclick="toggleSection('inviteHistory')">История полученных заявок</a>
                    <a class="toggle-btn" href="#" onclick="toggleSection('findEmployee')">Добавить сотрудника</a>

                    <!-- Список сотрудников -->
                    <div id="employeeList"
                        class="<?= (!empty($_GET['search']) || !empty($_GET['role']) || isset($_GET['free_only'])) ? '' : 'hidden' ?>">
                        <h4>Сотрудники вашей СТО:</h4>
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE station_id = :station_id AND id != :manager_id");
                        $stmt->execute([
                            'station_id' => $managerStation['id'],
                            'manager_id' => $user['id']
                        ]);
                        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <?php if ($employees): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ФИО</th>
                                        <th>Роль</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($emp['full_name']) ?></td>
                                            <td><?= $roleMap[$emp['role']] ?? $emp['role'] ?></td>
                                            <td>
                                                <form class="inline" method="post" action="/remove-employee"
                                                    onsubmit="return confirm('Удалить сотрудника?');">
                                                    <input type="hidden" name="employee_id" value="<?= $emp['id'] ?>">
                                                    <button type="submit">Удалить</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Сотрудники не найдены.</p>
                        <?php endif; ?>
                    </div>

                    <!-- История заявок -->
                    <div id="inviteHistory"
                        class="<?= (!empty($_GET['search']) || !empty($_GET['role']) || isset($_GET['free_only'])) ? '' : 'hidden' ?>">
                        <h4>Отправленные заявки:</h4>
                        <?php
                        $stmt = $pdo->prepare("
                        SELECT sr.*, u.full_name, u.role
                        FROM station_requests sr
                        JOIN users u ON sr.user_id = u.id
                        WHERE sr.station_id = :station_id
                        ORDER BY sr.created_at DESC
                    ");
                        $stmt->execute(['station_id' => $managerStation['id']]);
                        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <?php if ($history): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Сотрудник</th>
                                        <th>Роль</th>
                                        <th>Статус</th>
                                        <th>Дата</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $h): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($h['full_name']) ?></td>
                                            <td><?= $roleMap[$h['role']] ?? $h['role'] ?></td>
                                            <td><?= $h['status'] ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td>
                                            <td>
                                                <?php if ($h['status'] === 'pending'): ?>
                                                    <form class="inline" method="post" action="/delete-invite"
                                                        onsubmit="return confirm('Удалить заявку?');">
                                                        <input type="hidden" name="request_id" value="<?= $h['id'] ?>">
                                                        <button type="submit">Удалить</button>
                                                    </form>
                                                <?php else: ?>—<?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Заявок нет.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Добавить сотрудника -->
                    <div id="findEmployee"
                        class="<?= (!empty($_GET['search']) || !empty($_GET['role']) || isset($_GET['free_only'])) ? '' : 'hidden' ?>">

                        <h4>Добавить сотрудника</h4>

                        <form method="get" style="margin-bottom: 10px;">
                            <input type="text" name="search" placeholder="Поиск по ФИО..."
                                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            <select name="role">
                                <option value="">Все роли</option>
                                <option value="mechanic" <?= ($_GET['role'] ?? '') === 'mechanic' ? 'selected' : '' ?>>Механик
                                </option>
                                <option value="accountant" <?= ($_GET['role'] ?? '') === 'accountant' ? 'selected' : '' ?>>
                                    Бухгалтер</option>
                            </select>
                            <label>
                                <input type="checkbox" name="free_only" <?= isset($_GET['free_only']) ? 'checked' : '' ?>> Только
                                свободные
                            </label>
                            <button type="submit">Фильтровать</button>
                        </form>

                        <?php
                        $conditions = ["u.role IN ('mechanic', 'accountant')", "u.id != :manager_id"];
                        $params = ['manager_id' => $user['id']];

                        if (!empty($_GET['search'])) {
                            $conditions[] = "u.full_name ILIKE :search";
                            $params['search'] = '%' . $_GET['search'] . '%';
                        }

                        if (!empty($_GET['role'])) {
                            $conditions[] = "u.role = :role";
                            $params['role'] = $_GET['role'];
                        }

                        if (isset($_GET['free_only'])) {
                            $conditions[] = "u.station_id IS NULL";
                        }

                        $sql = "SELECT u.*, s.name AS station_name
                            FROM users u
                            LEFT JOIN stations s ON u.station_id = s.id
                            WHERE " . implode(" AND ", $conditions) . "
                            ORDER BY u.full_name ASC";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php if ($allEmployees): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ФИО</th>
                                        <th>Дата рождения</th>
                                        <th>Роль</th>
                                        <th>СТО</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allEmployees as $emp): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($emp['full_name']) ?></td>
                                            <td><?= htmlspecialchars($emp['birthdate']) ?></td>
                                            <td><?= $roleMap[$emp['role']] ?? $emp['role'] ?></td>
                                            <td><?= $emp['station_name'] ? htmlspecialchars($emp['station_name']) : '<em>Не привязан</em>' ?>
                                            </td>
                                            <td>
                                                <?php if (!$emp['station_id']): ?>
                                                    <form class="inline" method="post" action="/invite-employee">
                                                        <input type="hidden" name="user_id" value="<?= $emp['id'] ?>">
                                                        <button type="submit">Пригласить</button>
                                                    </form>
                                                <?php else: ?>
                                                    <em>Занят</em>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Сотрудники не найдены.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($user['role'] === 'mechanic' || $user['role'] === 'accountant'): ?>
            <a class="toggle-btn" href="#" onclick="toggleSection('myRequests')">Мои заявки</a>
            <div id="myRequests" class="hidden">
                <h4>Заявки, отправленные вами:</h4>
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
                                    <td><?= $r['status'] ?></td>
                                    <td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <form class="inline" method="post" action="/delete-invite"
                                                onsubmit="return confirm('Удалить заявку?');">
                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                <button type="submit">Удалить</button>
                                            </form>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Вы ещё не подавали заявок.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>