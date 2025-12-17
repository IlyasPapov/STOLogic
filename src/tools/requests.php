<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Проверка авторизации
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'mechanic' && $_SESSION['user']['role'] !== 'accountant')) {
    header('Location: /login.php');
    exit;
}

$user = $_SESSION['user'];

// Получение заявок на привязку (пользователи без station_id, у которых указана роль "mechanic" или "accountant")
$stmt = $pdo->prepare("
    SELECT id, full_name, username, email, phone, role 
    FROM users 
    WHERE station_id IS NULL 
      AND role IN ('mechanic', 'accountant') 
      AND email_verified = TRUE
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Маппинг ролей
$roleMap = [
    'mechanic' => 'Механик',
    'accountant' => 'Бухгалтер'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Заявки на привязку</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        a.button {
            display: inline-block;
            padding: 6px 12px;
            margin: 2px;
            background-color: #1e90ff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        a.button:hover {
            background-color: #1c86ee;
        }
    </style>
</head>
<body>
    <h2>Заявки на привязку сотрудников к СТО</h2>

    <?php if (count($requests) === 0): ?>
        <p>Нет новых заявок.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ФИО</th>
                    <th>Логин</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Должность</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?= htmlspecialchars($request['full_name']) ?></td>
                        <td><?= htmlspecialchars($request['username']) ?></td>
                        <td><?= htmlspecialchars($request['email']) ?></td>
                        <td><?= htmlspecialchars($request['phone']) ?></td>
                        <td><?= $roleMap[$request['role']] ?? $request['role'] ?></td>
                        <td>
                            <a href="/approve-request.php?user_id=<?= $request['id'] ?>" class="button">Одобрить</a>
                            <a href="/reject-request.php?user_id=<?= $request['id'] ?>" class="button" style="background-color: darkred;">Отклонить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3>Отправить заявку на присоединение к СТО</h3>
    <form method="POST" action="send-request.php">
        <label for="station_id">Выберите СТО:</label>
        <select name="station_id" id="station_id" required>
            <?php
            // Получаем все СТО для выбора
            $stmt = $pdo->prepare("SELECT id, name FROM stations");
            $stmt->execute();
            $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($stations as $station) {
                echo "<option value='{$station['id']}'>{$station['name']}</option>";
            }
            ?>
        </select>
        <button type="submit">Отправить заявку</button>
    </form>

    <?php
    $dashboardPath = match ($user['role']) {
        'mechanic' => '/dashboard-mechanic.php',
        'accountant' => '/dashboard-accountant.php',
        'manager' => '/dashboard-manager.php',
        default => '/login.php'
    };
    ?>
    <p><a href="<?= $dashboardPath ?>">Вернуться в панель</a></p>

</body>
</html>
