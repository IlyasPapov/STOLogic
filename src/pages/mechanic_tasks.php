<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Проверка авторизации
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mechanic') {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user']['id'];

// === Обработка POST-запросов ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' && !empty($_POST['task'])) {
            $stmt = $pdo->prepare("INSERT INTO mechanic_tasks (user_id, task_text) VALUES (:user_id, :task)");
            $stmt->execute(['user_id' => $user_id, 'task' => trim($_POST['task'])]);
        }

        if ($action === 'toggle' && isset($_POST['task_id'])) {
            $stmt = $pdo->prepare("
                UPDATE mechanic_tasks SET is_completed = NOT is_completed
                WHERE id = :id AND user_id = :user_id
            ");
            $stmt->execute(['id' => $_POST['task_id'], 'user_id' => $user_id]);
        }

        if ($action === 'delete' && isset($_POST['task_id'])) {
            $stmt = $pdo->prepare("DELETE FROM mechanic_tasks WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $_POST['task_id'], 'user_id' => $user_id]);
        }
    }

    header('Location: /mechanic_tasks');
    exit;
}

// === Получение задач ===
$stmt = $pdo->prepare("SELECT * FROM mechanic_tasks WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute(['user_id' => $user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Список задач</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .small-btn {
            padding: 10px 30px;
            font-size: 14px;
            background-color: #003366;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .small-btn:hover {
            background-color: #b33d3d;
        }

        .small-btn-danger {
            background-color: #b33d3d;
        }

        .small-btn-danger:hover {
            background-color: #900;
        }

        .unstyled-btn {
            margin: 0 px;
            background: none;
            border: none;
            font-size: 25px;
            cursor: pointer;
            padding: 0;
            margin-right: 10px;
        }

        .btn-center {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        main {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        ul {
            list-style: none;
            padding-left: 0;
            margin-top: 20px;
        }

        li {
            width: 100%;
            max-width: 900px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #ccc;
            padding-bottom: 8px;
        }

        .task-text {
            flex-grow: 1;
            margin-left: 10px;
        }

        .form-input {
            padding: 8px;
            font-size: 14px;
            width: 100%;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .task-actions {
            display: flex;
            align-items: center;
        }

        form {
            display: flex;
            /* flex-direction: column; */
            gap: 10px;
            width: 100%;
            max-width: 800px;
        }
    </style>
</head>

<body>

    <header>
        STOLogic — Список задач
        <div class="profile-wrapper">
            <div class="profile-btn"><?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'Профиль') ?> ⯆</div>
            <div class="dropdown">
                <a href="/dashboard_mechanic">Панель механика</a>
                <a href="/logout.php">Выйти</a>
            </div>
        </div>
    </header>

    <main>
        <a href="/dashboard_mechanic" class="small-btn">Назад</a>
        <h1 style="text-align: center;">Мой список задач</h1>

        <form method="POST" class="btn-center">
            <input type="text" name="task" placeholder="Введите новую задачу" class="form-input" required>
            <input type="hidden" name="action" value="add">
            <button type="submit" class="small-btn">Добавить</button>
        </form>

        <?php if (empty($tasks)): ?>
            <p style="margin-top: 20px; text-align: center;">У вас пока нет задач.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($tasks as $task): ?>
                    <li>
                        <span class="task-text"
                            style="<?= $task['is_completed'] ? 'text-decoration: line-through; color: gray;' : '' ?>">
                            <?= htmlspecialchars($task['task_text']) ?>
                        </span>
                        <div class="task-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <button class="unstyled-btn" title="Отметить как выполнено">
                                    <?= $task['is_completed'] ? '✅' : '⬜' ?>
                                </button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <button class="small-btn">Удалить</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>

</body>

</html>