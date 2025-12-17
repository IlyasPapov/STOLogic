<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Проверка авторизации
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

$user = $_SESSION['user'];

// Определение панели в зависимости от роли
$dashboardPath = match ($user['role']) {
    'mechanic' => '/dashboard_mechanic',
    'accountant' => '/dashboard_accountant',
    'manager' => '/dashboard_manager',
    default => '/login.php'
};

// Проверка, что пользователь — менеджер
if ($user['role'] !== 'manager') {
    header("Location: $dashboardPath");
    exit;
}

$requestId = $_POST['request_id'] ?? $_GET['id'] ?? null;

if (!$requestId) {
    echo "Ошибка: Нет ID заявки. Параметр ID не передан или некорректен.";
    exit;
}

try {
    // Проверка наличия заявки
    $stmt = $pdo->prepare("SELECT * FROM station_requests WHERE id = :id AND status = 'pending'");
    $stmt->execute(['id' => $requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo "Заявка не найдена или уже обработана.";
        exit;
    }

    // Получаем информацию о пользователе
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->execute(['user_id' => $request['user_id']]);
    $userRequesting = $stmt->fetch(PDO::FETCH_ASSOC);

    // Получаем информацию о СТО
    $stmt = $pdo->prepare("SELECT * FROM stations WHERE id = :station_id");
    $stmt->execute(['station_id' => $request['station_id']]);
    $station = $stmt->fetch(PDO::FETCH_ASSOC);

    // Обработка отклонения заявки
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Проверка: пользователь уже привязан к этой СТО и одобрен
        if (
            $userRequesting['station_id'] == $station['id'] &&
            $userRequesting['approved'] == 1
        ) {
            // Удаляем дубликат заявки
            $stmt = $pdo->prepare("DELETE FROM station_requests WHERE id = :id");
            $stmt->execute(['id' => $requestId]);

            echo "<p>Пользователь уже состоит в данной СТО. Заявка удалена.</p>";
            echo "<a href='$dashboardPath'>Вернуться назад</a>";
            exit;
        }

        // Отклоняем заявку обычным способом
        $stmt = $pdo->prepare("UPDATE station_requests SET status = 'rejected' WHERE id = :id");
        if ($stmt->execute(['id' => $requestId])) {
            header("Location: $dashboardPath");
            exit;
        } else {
            echo "Ошибка при отклонении заявки.";
            exit;
        }
    }

} catch (PDOException $e) {
    echo "Ошибка базы данных: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отклонение заявки</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        h3 { color: #333; }
        .btn {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <h3>Отклонение заявки на привязку</h3>

    <p><strong>Пользователь:</strong> <?= htmlspecialchars($userRequesting['full_name']) ?> (<?= htmlspecialchars($userRequesting['username']) ?>)</p>
    <p><strong>Станция:</strong> <?= htmlspecialchars($station['name']) ?></p>

    <form method="POST">
        <input type="hidden" name="request_id" value="<?= htmlspecialchars($requestId) ?>">
        <p>Вы уверены, что хотите <strong>отклонить</strong> эту заявку?</p>
        <button type="submit" class="btn btn-danger">Отклонить заявку</button>
        <a href="<?= $dashboardPath ?>" class="btn btn-secondary">Отмена</a>
    </form>
</body>
</html>
