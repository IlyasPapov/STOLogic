<?php
// src/pages/login.php

session_start();
require_once __DIR__ . '/../config/db.php';

// Если пользователь уже авторизован — редирект
if (isset($_SESSION['user'])) {
    switch ($_SESSION['user']['role']) {
        case 'manager':
            header('Location: /dashboard_manager');
            break;
        case 'mechanic':
            header('Location: /dashboard_mechanic');
            break;
        case 'accountant':
            header('Location: /dashboard_accountant');
            break;
        default:
            header('Location: /dashboard');
            break;
    }
    exit;
}

$error = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, username, password, role, email_verified, station_id, full_name FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        if (!$user['email_verified']) {
            $error = 'Пожалуйста, подтвердите email перед входом.';
        } else {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'station_id' => $user['station_id'],
                'full_name' => $user['full_name'],
            ];

            switch ($user['role']) {
                case 'manager':
                    header('Location: /dashboard_manager');
                    break;
                case 'mechanic':
                    header('Location: /dashboard_mechanic');
                    break;
                case 'accountant':
                    header('Location: /dashboard_accountant');
                    break;
                default:
                    header('Location: /dashboard');
                    break;
            }
            exit;
        }
    } else {
        $error = 'Неверный логин или пароль.';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Вход в STOLogic</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        main {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: start;
            min-height: 100vh;
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
        }

        header {
            width: 100%;
            background-color: #003366;
            color: #fff;
            padding: 20px;
            text-align: center;
        }

        .container {
            width: 100%;
            max-width: 450px;
            background-color: #fff;
            margin-top: 50px;
            padding: 30px 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);

        }

        h1 {
            text-align: center;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            background-color: #003366;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #0055a5;
        }

        .message.error {
            background-color: #ffdddd;
            color: #d8000c;
            padding: 10px;
            margin-bottom: 15px;
            border-left: 5px solid #d8000c;
            border-radius: 4px;
        }

        p {
            text-align: center;
            margin-top: 15px;
        }

        a {
            color: #003366;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        header {
            background-color: #003366;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }
    </style>
</head>

<body>

    <header>
        STOLogic — Система управления СТО
    </header>

    <main>
        <div class="container">
            <h1>Авторизация</h1>

            <?php if (!empty($error)): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/login" class="form">
                <div class="form-group">
                    <label for="username">Логин:</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Пароль:</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn">Войти</button>
                </div>
            </form>

            <p>Нет аккаунта? <a href="/register">Зарегистрироваться</a></p>
        </div>
    </main>

</body>

</html>