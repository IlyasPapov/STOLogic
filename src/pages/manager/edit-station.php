<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'manager') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$success = '';
$error = '';

// Получаем информацию о СТО
$stmt = $pdo->prepare("SELECT * FROM stations WHERE manager_id = :manager_id");
$stmt->execute(['manager_id' => $user['id']]);
$station = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$station) {
    die('СТО не найдена.');
}

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $lift_count = intval($_POST['lift_count'] ?? 0);
    $has_parking = isset($_POST['has_parking']) ? 1 : 0;
    $parking_slots = $has_parking ? intval($_POST['parking_slots'] ?? 0) : 0;
    $working_hours = trim($_POST['working_hours'] ?? '');

    if ($name && $address && $lift_count > 0 && $working_hours) {
        $update = $pdo->prepare("UPDATE stations SET 
            name = :name,
            address = :address,
            lift_count = :lift_count,
            has_parking = :has_parking,
            parking_slots = :parking_slots,
            working_hours = :working_hours
            WHERE id = :id");
        $update->execute([
            'name' => $name,
            'address' => $address,
            'lift_count' => $lift_count,
            'has_parking' => $has_parking,
            'parking_slots' => $parking_slots,
            'working_hours' => $working_hours,
            'id' => $station['id']
        ]);
        $success = "Данные СТО успешно обновлены. Перенаправление...";
        // Обновляем данные в переменной
        $station = array_merge($station, $_POST);
        $station['has_parking'] = $has_parking;
    } else {
        $error = 'Пожалуйста, заполните все поля корректно.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Редактировать СТО</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .form-container {
            background: white;
            padding: 20px;
            max-width: 600px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }

        .form-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 16px;
            background: #003366;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn:hover {
            background: #002244;
        }

        .btn-link {
            background: #aaa;
            text-decoration: none;
            color: white;
            border-radius: 4px;

        }

        .success {
            color: green;
            margin-top: 15px;
        }

        .error {
            color: red;
            margin-top: 15px;
        }
    </style>
    <?php if ($success): ?>
        <script>
            setTimeout(() => {
                window.location.href = '/dashboard_manager';
            }, 2000);
        </script>
    <?php endif; ?>
</head>

<body>
    <header style="background-color: #003366; color: white; padding: 10px 20px;">
        <h2 style="margin: 0; color:white"> Редактировать СТО</h2>
    </header>

    <div class="form-container">
        <?php if ($success): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
        <?php elseif ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <a href="/dashboard_manager" class="btn-link">Отмена</a>
            <label for="name">Название СТО:</label>
            <input type="text" name="name" id="name" value="<?= htmlspecialchars($station['name']) ?>" required>

            <label for="address">Адрес:</label>
            <input type="text" name="address" id="address" value="<?= htmlspecialchars($station['address']) ?>"
                required>

            <label for="lift_count">Количество подъемников:</label>
            <input type="number" name="lift_count" id="lift_count" value="<?= (int) $station['lift_count'] ?>" min="1"
                required>

            <label>
                <input type="checkbox" name="has_parking" id="has_parking" <?= $station['has_parking'] ? 'checked' : '' ?> onchange="toggleParking()"> Есть парковка
            </label>

            <div id="parking_section" style="<?= $station['has_parking'] ? '' : 'display:none;' ?>">
                <label for="parking_slots">Количество мест на парковке:</label>
                <input type="number" name="parking_slots" id="parking_slots"
                    value="<?= (int) $station['parking_slots'] ?>">
            </div>

            <label for="working_hours">График работы:</label>
            <input type="text" name="working_hours" id="working_hours"
                value="<?= htmlspecialchars($station['working_hours']) ?>" required>

            <div class="form-actions">
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>

    <script>
        function toggleParking() {
            const checkbox = document.getElementById('has_parking');
            const section = document.getElementById('parking_section');
            section.style.display = checkbox.checked ? 'block' : 'none';
        }
    </script>
</body>

</html>