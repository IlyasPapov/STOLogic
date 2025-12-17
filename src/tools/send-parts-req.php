<?php
require_once __DIR__ . '/../config/db.php';
//var_dump($_SESSION);  // Проверка содержимого сессии

// Начало сессии, если еще не было начато
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Получаем данные пользователя из сессии
$user = $_SESSION['user'] ?? null;
$user_id = $user['id'] ?? null;  // ← правильное получение id из вложенного user


// Если пользователь не авторизован
// Проверка на авторизацию
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo "Ошибка: пользователь не авторизован.";
    exit();
}


// Привязка к СТО
$station_id = $user['station_id'] ?? null;

if (!$station_id) {
    echo "Ошибка: Механик не привязан к СТО.";
    exit();
}

// Все ок, продолжаем выполнение страницы
echo 'Все ок. Механик авторизован и привязан к СТО.';

// Обработка формы
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['part_type'] ?? '';

    $requested_quantity = (int) ($_POST['requested_quantity'] ?? 0);

    if ($type === 'existing') {
        $part_id = (int) ($_POST['existing_part_id'] ?? 0);
        if ($part_id > 0 && $requested_quantity > 0) {
            try {
                // Вставка заявки на деталь
                $stmt = $pdo->prepare("INSERT INTO station_parts_requests (station_id, part_id, requested_quantity, user_id, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                $stmt->execute([$station_id, $part_id, $requested_quantity, $user_id]);
                $success_message = 'Заявка успешно отправлена.';
            } catch (PDOException $e) {
                $error_message = 'Ошибка при отправке заявки: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Пожалуйста, выберите деталь и укажите количество.';
        }
    } elseif ($type === 'new') {
        $catalog_part_id = (int) ($_POST['catalog_part_id'] ?? 0);
        if ($catalog_part_id > 0 && $requested_quantity > 0) {
            // Проверка: существует ли эта деталь уже на складе
            try {
                $stmt = $pdo->prepare("SELECT * FROM station_parts WHERE station_id = ? AND part_id = ?");
                $stmt->execute([$station_id, $catalog_part_id]);
                if ($stmt->fetch()) {
                    $error_message = 'Ошибка: эта деталь уже есть на складе. Пожалуйста, выберите дозаказ.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO station_parts_requests (station_id, part_id, requested_quantity, user_id, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                    $stmt->execute([$station_id, $catalog_part_id, $requested_quantity, $user_id]);
                    $success_message = 'Заявка на новую деталь успешно отправлена.';
                }
            } catch (PDOException $e) {
                $error_message = 'Ошибка при обработке запроса: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Пожалуйста, выберите деталь и укажите количество.';
        }
    } else {
        $error_message = 'Пожалуйста, выберите тип заявки.';
    }
}

// Получаем детали со склада станции
try {
    $stmt = $pdo->prepare("SELECT sp.part_id, pc.name, pc.brand FROM station_parts sp JOIN parts_catalog pc ON sp.part_id = pc.id WHERE sp.station_id = ?");
    $stmt->execute([$station_id]);
    $station_parts = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Ошибка при получении данных о деталях со склада: ' . $e->getMessage();
}

// Получаем детали из общего каталога
try {
    $stmt = $pdo->prepare("SELECT id, name, brand FROM parts_catalog");
    $stmt->execute();
    $catalog_parts = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Ошибка при получении данных из каталога: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Создать заявку на деталь</title>
    <link rel="stylesheet" href="styles.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .form-section {
            margin-top: 20px;
            display: none;
        }

        .card {
            max-width: 500px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            box-sizing: border-box;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);

        }

        select,
        input[type="number"] {
            width: 100%;
            padding: 8px;
            margin-top: 8px;
            margin-bottom: 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .btn {
            display: inline-block;
            background-color: #003366;
            color: #fff;
            padding: 10px 50px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #0055a5;
        }

        .header {
            text-align: center;
            padding: 20px;
        }

        .success {
            color: green;
            text-align: center;
            margin-bottom: 10px;
        }

        .error {
            color: red;
            text-align: center;
            margin-bottom: 10px;
        }
    </style>

    <script>
        function toggleSection(value) {
            document.getElementById('existing_section').style.display = value === 'existing' ? 'block' : 'none';
            document.getElementById('new_section').style.display = value === 'new' ? 'block' : 'none';
        }
    </script>
</head>

<body>
    <div class="header">
        <h1>Создание заявки на деталь</h1>
        <a href="/warehouseMech" class="btn">Назад</a>
    </div>
    <?php if ($success_message): ?>
        <div class="success"><?= htmlspecialchars($success_message) ?></div>
    <?php elseif ($error_message): ?>
        <div class="error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="/send-parts-req">
            <p>Вы хотите дозаказать уже имеющуюся на складе деталь или добавить новую из общего каталога системы?</p>
            <label>
                <input type="radio" name="part_type" value="existing" onchange="toggleSection(this.value)"> Дозаказать
                со склада
            </label>
            <label>
                <input type="radio" name="part_type" value="new" onchange="toggleSection(this.value)"> Новая из каталога
            </label>

            <div class="form-section" id="existing_section">
                <label for="existing_part_id">Выберите деталь со склада:</label>
                <select name="existing_part_id">
                    <option value="">-- выберите деталь --</option>
                    <?php foreach ($station_parts as $part): ?>
                        <option value="<?= $part['part_id'] ?>">
                            <?= htmlspecialchars($part['brand'] . ' - ' . $part['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-section" id="new_section">
                <label for="catalog_part_id">Выберите деталь из каталога:</label>
                <select name="catalog_part_id">
                    <option value="">-- выберите деталь --</option>
                    <?php foreach ($catalog_parts as $part): ?>
                        <option value="<?= $part['id'] ?>">
                            <?= htmlspecialchars($part['brand'] . ' - ' . $part['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label for="requested_quantity">Количество:</label>
            <input type="number" name="requested_quantity" min="1" required>

            <button type="submit" class="btn">Отправить заявку</button>
        </form>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            $('select[name="existing_part_id"]').select2({
                placeholder: "-- выберите деталь --",
                width: '100%'
            });

            $('select[name="catalog_part_id"]').select2({
                placeholder: "-- выберите деталь --",
                width: '100%'
            });
        });
    </script>
</body>

</html>