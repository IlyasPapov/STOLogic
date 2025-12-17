<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // Подключение к базе данных

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'manager') {
    header('Location: /login');
    exit;
}

$partId = $_GET['id'] ?? null;
$stationId = $_SESSION['user']['station_id'] ?? null;
$username = $_SESSION['user']['username'] ?? 'Пользователь';

if (!$partId || !$stationId) {
    echo "Ошибка: некорректный запрос.";
    exit;
}

// Получаем информацию о детали, включая название и бренд из parts_catalog
$stmt = $pdo->prepare("
    SELECT sp.*, pc.name AS catalog_name, pc.brand AS catalog_brand
    FROM station_parts sp
    JOIN parts_catalog pc ON sp.part_id = pc.id
    WHERE sp.id = ? AND sp.station_id = ?
");
$stmt->execute([$partId, $stationId]);
$part = $stmt->fetch();

if (!$part) {
    echo "Деталь не найдена.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = $_POST['quantity'];
    $minimum_quantity = $_POST['minimum_quantity'];
    $status = $_POST['status'];
    $expected_delivery = $_POST['expected_delivery'] ?: null;
    $price = $_POST['price'];
    $car_id = $_POST['car_id'] ?: null;

    $stmt = $pdo->prepare("
        UPDATE station_parts
        SET quantity = ?, minimum_quantity = ?, status = ?, expected_delivery = ?, price = ?, car_id = ?
        WHERE id = ? AND station_id = ?
    ");
    $stmt->execute([$quantity, $minimum_quantity, $status, $expected_delivery, $price, $car_id, $partId, $stationId]);

    header("Location: /manager/warehouse");
    exit;
}

// Список автомобилей
$cars = $pdo->prepare("SELECT id, brand, model, year FROM cars ORDER BY brand, model, year");
$cars->execute();
$carList = $cars->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать деталь</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group input[readonly], .form-group select[disabled] {
            background-color: #f1f1f1;
            border: 1px solid #ccc;
        }

        .form-group button.edit-btn {
            position: absolute;
            right: 0;
            top: 0;
            transform: translateY(50%);
            font-size: 12px;
            padding: 4px 10px;
            background: #003366;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-group {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .btn-group .btn {
            width: 48%;
            background: #003366;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-group .btn:hover {
            background-color: #002244;
        }

        .link-btn {
            background: none;
            border: none;
            color: #003366;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
            margin-top: 8px;
            font-size: 0.9em;
        }
    </style>
    <script>
        function enableField(fieldId) {
            const field = document.getElementById(fieldId);
            field.removeAttribute('readonly');
            field.removeAttribute('disabled');
            field.focus();
        }
    </script>
</head>
<body>

<header>
    <h2>STOLogic — Панель управления</h2>
    <div class="profile-wrapper">
        <div class="profile-btn"><?= htmlspecialchars($username) ?> (менеджер)</div>
        <div class="dropdown">
            <a href="/logout" class="logout-btn">Выход</a>
        </div>
    </div>
</header>

<main>
    <div class="container">
        <h1 style="text-align:center;">
            Редактировать деталь: <?= htmlspecialchars($part['catalog_brand']) ?> — <?= htmlspecialchars($part['catalog_name']) ?>
        </h1>
        <form method="POST">
            <div class="form-group">
                <label for="quantity">Количество</label>
                <input type="number" id="quantity" name="quantity" value="<?= htmlspecialchars($part['quantity']) ?>" readonly required>
                <button type="button" class="edit-btn" onclick="enableField('quantity')">Изменить</button>
            </div>

            <div class="form-group">
                <label for="minimum_quantity">Минимальное количество</label>
                <input type="number" id="minimum_quantity" name="minimum_quantity" value="<?= htmlspecialchars($part['minimum_quantity']) ?>" readonly required>
                <button type="button" class="edit-btn" onclick="enableField('minimum_quantity')">Изменить</button>
            </div>

            <div class="form-group">
                <label for="status">Статус</label>
                <select id="status" name="status" disabled required>
                    <option value="available" <?= $part['status'] === 'available' ? 'selected' : '' ?>>В наличии</option>
                    <option value="pending" <?= $part['status'] === 'pending' ? 'selected' : '' ?>>В ожидании</option>
                    <option value="unavailable" <?= $part['status'] === 'unavailable' ? 'selected' : '' ?>>Нет в наличии</option>
                </select>
                <button type="button" class="edit-btn" onclick="enableField('status')">Изменить</button>
            </div>

            <div class="form-group">
                <label for="expected_delivery">Ожидаемая поставка</label>
                <input type="date" id="expected_delivery" name="expected_delivery" value="<?= htmlspecialchars($part['expected_delivery']) ?>" readonly>
                <button type="button" class="edit-btn" onclick="enableField('expected_delivery')">Изменить</button>
            </div>

            <div class="form-group">
                <label for="price">Цена (₽)</label>
                <input type="number" step="0.01" id="price" name="price" value="<?= htmlspecialchars($part['price']) ?>" readonly>
                <button type="button" class="edit-btn" onclick="enableField('price')">Изменить</button>
            </div>

            <div class="form-group">
                <label for="car_id">Автомобиль (если применимо)</label>
                <select id="car_id" name="car_id" disabled>
                    <option value="">— Без привязки —</option>
                    <?php foreach ($carList as $car): ?>
                        <option value="<?= $car['id'] ?>" <?= $part['car_id'] == $car['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' ' . $car['year']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="edit-btn" onclick="enableField('car_id')">Изменить</button>
                <br>
                <a href="/register-car?from=edit-part&id=<?= $partId ?>" class="link-btn">Не нашли нужный? Зарегистрируйте сами</a>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn">Сохранить изменения</button>
                <a href="/manager/warehouse" class="btn">Отмена</a>
            </div>
        </form>
    </div>
</main>

</body>
</html>
