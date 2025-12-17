<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Отладочная информация
echo "<pre>Содержимое SESSION:\n";
print_r($_SESSION);
echo "</pre>";

// Проверка роли и привязки к СТО
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mechanic') {
    echo "<div class='message error'>Ошибка: Вы не авторизованы как механик или не имеете доступа. Пожалуйста, войдите как механик.</div>";
    exit();
}

$attachedToStation = isset($_SESSION['user']['station_id']) && !empty($_SESSION['user']['station_id']);

function getCars($pdo)
{
    $cars_query = $pdo->query("SELECT id, brand, model, year FROM cars ORDER BY brand, model, year");
    return $cars_query->fetchAll(PDO::FETCH_ASSOC);
}

$cars = getCars($pdo);
$parts = [];

if ($attachedToStation) {
    $parts_query = $pdo->prepare("SELECT p.id, p.part_number, p.name, p.brand, p.image_url, sp.quantity 
                                  FROM parts_catalog p
                                  JOIN station_parts sp ON p.id = sp.part_id 
                                  WHERE sp.station_id = ?");
    $parts_query->execute([$_SESSION['user']['station_id']]);
    $parts = $parts_query->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Склад механика</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .container {
            max-width: 1000px;
            margin: auto;
        }

        .btn {
            background-color: #003366;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            margin: 5px 0;
        }

        .message {
            margin-top: 10px;
            padding: 10px;
            border-radius: 8px;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
        }

        .parts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .part-tile {
            border: 1px solid #ccc;
            border-radius: 12px;
            padding: 10px;
            text-align: center;
        }

        .part-tile img {
            max-width: 100%;
            max-height: 120px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        header {
            background-color: #003366;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .profile-wrapper {
            position: relative;
        }

        .profile-btn {
            cursor: pointer;
        }

        .dropdown {
            position: absolute;
            top: 35px;
            right: 0;
            background-color: white;
            color: black;
            border: 1px solid #ccc;
            padding: 5px;
            display: none;
            z-index: 10;
        }

        .dropdown a {
            text-decoration: none;
            color: #003366;
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
            color: #003366;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 8px;
            display: inline-block;
        }

        .logout-btn:hover {
            color: red;
            background-color: #f8d7da;
        }

        .parts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .part-tile {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 15px;
            background-color: #f9f9f9;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .part-tile img {
            max-width: 100%;
            max-height: 150px;
            object-fit: contain;
            margin-bottom: 10px;
        }

        .part-tile h3 {
            margin: 10px 0 5px;
        }

        .part-tile p {
            margin: 3px 0;
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
    </style>
</head>

<body>

    <header>
        <h2 style="color: white">STOLogic — Cклад деталей</h2>
        <div class="profile-wrapper" id="profileWrapper">
            <div class="profile-btn" id="profileBtn"><?= htmlspecialchars($_SESSION['user']['username']) ?> (Механик)
            </div>
            <div class="dropdown" id="dropdownMenu">
                <a href="/logout" class="logout-btn">Выход</a>
            </div>
        </div>
    </header>

    <main style="padding: 20px;">
        <div style="max-width: 100%; margin: 0 auto;">
            <!-- кнопки -->
            <button onclick="window.location.href='/dashboard_mechanic'" class="btn">Назад</button>
            <button onclick="window.location.href='/send-parts-req'" class="btn" style="margin-left: 10px;">Создать
                заявку на добавление детали</button>

            <!-- сообщения -->
            <?php if (!$attachedToStation): ?>
                <div class="message error">Вы не привязаны к СТО. Пожалуйста, подождите подтверждения менеджера.</div>
            <?php endif; ?>

            <h1>Склад</h1>

            <?php if (!empty($_SESSION['error_message'])): ?>
                <div class="message error"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php elseif (!empty($_SESSION['success_message'])): ?>
                <div class="message success">
                    <?= htmlspecialchars($_SESSION['success_message']);
                    unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Витрина каталога вне ограниченной ширины -->
        <?php if ($attachedToStation): ?>
            <div class="parts-grid" style="padding: 0 20px;">
                <?php foreach ($parts as $part): ?>
                    <div class="part-tile">
                        <img src="<?= htmlspecialchars($part['image_url']) ?>" alt="<?= htmlspecialchars($part['name']) ?>" />
                        <h3><?= htmlspecialchars($part['name']) ?></h3>
                        <p>Бренд: <?= htmlspecialchars($part['brand']) ?></p>
                        <p>Номер: <?= htmlspecialchars($part['part_number']) ?></p>
                        <p>Количество: <?= htmlspecialchars($part['quantity']) ?></p>
                        <form method="POST">
                            <input type="hidden" name="part_id" value="<?= $part['id'] ?>">
                            <label for="quantity">Списать:</label>
                            <input type="number" name="quantity" min="1" value="1" required>
                            <button type="submit" name="use_part" class="btn">Использовать</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- форма добавления детали -->
        <div class="container">
            <h2 style="margin-top: 30px;">Добавить новую деталь в каталог</h2>
            <form method="post">
                <input type="text" name="part_number" placeholder="Номер детали" required>
                <input type="text" name="name" placeholder="Название" required>
                <input type="text" name="brand" placeholder="Бренд" required>
                <textarea name="description" placeholder="Описание"></textarea>
                <input type="text" name="image_url" placeholder="URL изображения">
                <select name="car_id">
                    <option value="">Не выбрано</option>
                    <?php foreach ($cars as $car): ?>
                        <option value="<?= $car['id'] ?>">
                            <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' ' . $car['year']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="quantity" value="1" min="1" required>
                <button type="submit" name="add_part_to_catalog" class="btn">Добавить</button>
            </form>
        </div>
    </main>