<?php
// Сессия уже стартована в index.php, повторно вызывать session_start() не нужно

require_once __DIR__ . '/../config/db.php'; // Подключение к базе данных

// Получение списка машин для привязки
$cars_query = $pdo->query("SELECT id, brand, model, year FROM cars ORDER BY brand, model, year");
$cars = $cars_query->fetchAll(PDO::FETCH_ASSOC);

$error_message = null; // Переменная для ошибок

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $part_number = trim($_POST['part_number']);
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url']);
    $car_id = !empty($_POST['car_id']) ? (int)$_POST['car_id'] : null;
    $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    try {
        // Проверка наличия детали в каталоге
        $stmt = $pdo->prepare("SELECT id FROM parts_catalog WHERE part_number = ?");
        $stmt->execute([$part_number]);
        $part = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($part) {
            // Если деталь уже есть в каталоге
            $error_message = "Деталь с таким номером уже есть в каталоге!";
        } else {
            // Если детали нет — добавляем в каталог
            $insert_part = $pdo->prepare("INSERT INTO parts_catalog (part_number, name, brand, image_url, description) VALUES (?, ?, ?, ?, ?)");
            $insert_part->execute([$part_number, $name, $brand, $image_url, $description]);
            $part_id = $pdo->lastInsertId();

            // Привязка детали к машине, если выбрана
            if ($car_id) {
                $check_link = $pdo->prepare("SELECT 1 FROM parts_to_cars WHERE part_id = ? AND car_id = ?");
                $check_link->execute([$part_id, $car_id]);
                if (!$check_link->fetch()) {
                    $link_part = $pdo->prepare("INSERT INTO parts_to_cars (part_id, car_id) VALUES (?, ?)");
                    $link_part->execute([$part_id, $car_id]);
                }
            }

            // В зависимости от роли пользователя
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
                // Менеджер: сразу добавляем деталь на склад
                $insert_station_part = $pdo->prepare("
                    INSERT INTO station_parts (station_id, part_id, quantity, minimum_quantity, status)
                    VALUES (?, ?, ?, ?, 'available')
                ");
                $insert_station_part->execute([$_SESSION['station_id'], $part_id, $quantity, 1]); // Минимум по умолчанию — 1

                $_SESSION['success_message'] = "Деталь успешно добавлена на склад!";
                header("Location: /warehouse");
                exit();
            } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'mechanic') {
                // Механик: создаём заявку
                $insert_request = $pdo->prepare("
                    INSERT INTO station_parts_requests (station_id, part_id, requested_quantity, user_id)
                    VALUES (?, ?, ?, ?)
                ");
                $insert_request->execute([$_SESSION['station_id'], $part_id, $quantity, $_SESSION['user_id']]);

                $_SESSION['success_message'] = "Заявка на деталь отправлена!";
                header("Location: /warehouse");
                exit();
            }
        }
    } catch (Exception $e) {
        $error_message = "Ошибка: " . htmlspecialchars($e->getMessage());
    }
}
?>

<?php
session_start();

// Здесь должно быть получение данных о пользователе, например:
$user = [
    'username' => $_SESSION['username'] ?? 'Гость',
];
$roleName = match ($_SESSION['role'] ?? '') {
    'manager' => 'Менеджер',
    'mechanic' => 'Механик',
    'accountant' => 'Бухгалтер',
    default => 'Неизвестная роль',
};
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить деталь</title>
    <link rel="stylesheet" href="/styles.css"> <!-- Подключение внешних стилей -->
</head>
<body>

<!-- ШАПКА -->
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
    <div class="container">
        <h1>Добавить новую деталь</h1>

        <!-- Сообщения об успехе или ошибке -->
        <?php if (!empty($error_message)): ?>
            <div class="message error"><?= htmlspecialchars($error_message) ?></div>
        <?php elseif (isset($_SESSION['success_message'])): ?>
            <div class="message success"><?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <form method="post" class="form form-centered">
            <div class="form-group">
                <label for="part_number">Номер детали</label>
                <input type="text" id="part_number" name="part_number" required>
            </div>

            <div class="form-group">
                <label for="name">Название</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="brand">Бренд</label>
                <input type="text" id="brand" name="brand" required>
            </div>

            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description"></textarea>
            </div>

            <div class="form-group">
                <label for="image_url">URL изображения</label>
                <input type="text" id="image_url" name="image_url">
            </div>

            <div class="form-group">
                <label for="car_id">Привязать к автомобилю (необязательно)</label>
                <select id="car_id" name="car_id">
                    <option value="">Не выбрано</option>
                    <?php foreach ($cars as $car): ?>
                        <option value="<?= htmlspecialchars($car['id']) ?>">
                            <?= htmlspecialchars($car['brand']) . " " . htmlspecialchars($car['model']) . " " . htmlspecialchars($car['year']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p><a href="/register-car?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn-link">Не нашли нужный? Можете зарегистрировать автомобиль тут</a></p>
            </div>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'mechanic'): ?>
                <div class="form-group">
                    <label for="quantity">Количество для заявки</label>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" required>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <button type="submit" class="btn">Добавить</button>
            </div>
        </form>
    </div>
</main>

</body>
</html>
