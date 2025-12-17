<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'manager') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $lifts = intval($_POST['lifts'] ?? 0);
    $has_parking = isset($_POST['has_parking']) ? 1 : 0;
    $parking_spaces = $has_parking ? intval($_POST['parking_spaces'] ?? 0) : 0;
    $working_hours = trim($_POST['working_hours'] ?? '');

    if ($name && $address && $lifts > 0 && $working_hours) {
        $stmt = $pdo->prepare("INSERT INTO stations 
            (name, address, lift_count, has_parking, parking_slots, manager_id, working_hours) 
            VALUES (:name, :address, :lifts, :has_parking, :parking_slots, :manager_id, :working_hours)");
        $stmt->execute([
            'name' => $name,
            'address' => $address,
            'lifts' => $lifts,
            'has_parking' => $has_parking,
            'parking_slots' => $parking_spaces,
            'manager_id' => $user['id'],
            'working_hours' => $working_hours
        ]);

        $station_id = $pdo->lastInsertId();
        $update = $pdo->prepare("UPDATE users SET station_id = :station_id WHERE id = :id");
        $update->execute([
            'station_id' => $station_id,
            'id' => $user['id']
        ]);
        $_SESSION['user']['station_id'] = $station_id;
        $success = 'СТО успешно создана и вы к ней привязаны!';
    } else {
        $error = 'Пожалуйста, заполните все поля корректно.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Создание СТО</title>
    <link rel="stylesheet" href="/styles.css"> <!-- Подключаем общий стиль -->

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .form-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 400px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        form label {
            display: block;
            margin-top: 15px;
        }

        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            margin-top: 20px;
            padding: 10px 16px;
            background-color: #003366;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
        }

        button:hover {
            background-color: #002244;
        }

        .cancel-btn {
            display: inline-block;
            margin-top: 15px;
            text-align: center;
            padding: 8px 16px;
            background-color: #ccc;
            color: #000;
            border-radius: 8px;
            text-decoration: none;
            width: 100%;
            box-sizing: border-box;
        }

        .cancel-btn:hover {
            background-color: #bbb;
        }

        .success {
            color: green;
            text-align: center;
        }

        .error {
            color: red;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="form-container">
        <h2>Создание новой СТО</h2>

        <?php if ($success): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
            <a class="cancel-btn" href="/dashboard">Вернуться в панель</a>
        <?php else: ?>
            <?php if ($error): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="post" action="">
                <label>Название СТО:
                    <input type="text" name="name" required>
                </label>
                <label>Адрес:
                    <input type="text" name="address" required>
                </label>
                <label>Количество подъёмников:
                    <input type="number" name="lifts" min="1" required>
                </label>
                <label>
                    <input type="checkbox" name="has_parking" id="has_parking" onchange="toggleParking()"> Есть стоянка
                </label>
                <label id="parking_label" style="display:none;">
                    Количество мест на стоянке:
                    <input type="number" name="parking_spaces" min="0">
                </label>
                <label>График работы:
                    <input type="text" name="working_hours" placeholder="например: Пн–Пт 9:00–18:00" required>
                </label>
                <button type="submit">Создать СТО</button>
            </form>

            <a href="/dashboard" class="cancel-btn">Отмена</a>

            <script>
                function toggleParking() {
                    const checkbox = document.getElementById('has_parking');
                    const parkingLabel = document.getElementById('parking_label');
                    parkingLabel.style.display = checkbox.checked ? 'block' : 'none';
                }
            </script>
        <?php endif; ?>
    </div>
</body>