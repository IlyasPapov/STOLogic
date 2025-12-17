<?php
require_once __DIR__ . '/../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка роли
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'manager') {
    header('Location: /unauthorized');
    exit;
}

// Получение ID СТО
$userId = $_SESSION['user']['id'];
$query = $pdo->prepare("SELECT id FROM stations WHERE manager_id = ?");
$query->execute([$userId]);
$stationId = $query->fetchColumn();

$details = [];
$lowStockParts = [];

// Если ID СТО найден
if ($stationId) {
    // Получение деталей на складе с ценой
    $stmt = $pdo->prepare("
        SELECT sp.*, 
               pc.name, 
               pc.brand AS part_brand, 
               pc.description, 
               ptc.car_id, 
               c.brand AS car_brand, 
               c.model AS car_model
        FROM station_parts sp
        JOIN parts_catalog pc ON sp.part_id = pc.id
        LEFT JOIN parts_to_cars ptc ON ptc.part_id = pc.id
        LEFT JOIN cars c ON ptc.car_id = c.id
        WHERE sp.station_id = ?
    ");
    $stmt->execute([$stationId]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получение деталей с малым количеством
    $lowStockStmt = $pdo->prepare("
        SELECT sp.id, pc.name, pc.brand AS part_brand, sp.quantity, sp.minimum_quantity
        FROM station_parts sp
        JOIN parts_catalog pc ON sp.part_id = pc.id
        WHERE sp.station_id = ? AND sp.quantity < sp.minimum_quantity
    ");
    $lowStockStmt->execute([$stationId]);
    $lowStockParts = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);
}

$lowStockParts = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);



// Получение каталога деталей
$catalog = $pdo->query("
    SELECT id, name, brand
    FROM parts_catalog
")->fetchAll(PDO::FETCH_ASSOC);

// Получение автомобилей
$cars = $pdo->query("
    SELECT DISTINCT cars.id, cars.brand, cars.model, cars.year
    FROM cars
    INNER JOIN parts_to_cars ptc ON cars.id = ptc.car_id
    ORDER BY cars.brand, cars.model, cars.year
")->fetchAll(PDO::FETCH_ASSOC);

// Обработка добавления новой детали
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stationId = (int) $_POST['station_id'];
    $partId = (int) $_POST['part_id'];
    $quantity = (int) $_POST['quantity'];
    $minimumQuantity = (int) $_POST['minimum_quantity'];
    $addImmediately = (int) $_POST['add_immediately'];
    $carId = !empty($_POST['car_id']) ? (int) $_POST['car_id'] : null;
    $deliveryDays = isset($_POST['delivery_days']) ? (int) $_POST['delivery_days'] : null;

    $status = $addImmediately ? 'available' : 'pending';
    $expectedDelivery = ($status === 'pending' && $deliveryDays) ? (new DateTime())->modify("+$deliveryDays days")->format('Y-m-d') : null;

    $check = $pdo->prepare("SELECT id, quantity FROM station_parts WHERE station_id = ? AND part_id = ?");
    $check->execute([$stationId, $partId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($status === 'available') {
            $update = $pdo->prepare("
                UPDATE station_parts
                SET quantity = quantity + ?, minimum_quantity = ?
                WHERE id = ?
            ");
            $update->execute([$quantity, $minimumQuantity, $existing['id']]);
        } else {
            $insertPending = $pdo->prepare("
                INSERT INTO station_parts (station_id, part_id, quantity, minimum_quantity, status, expected_delivery, car_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insertPending->execute([
                $stationId,
                $partId,
                $quantity,
                $minimumQuantity,
                'pending',
                $expectedDelivery,
                $carId
            ]);
        }
    } else {
        $insert = $pdo->prepare("
            INSERT INTO station_parts (station_id, part_id, quantity, minimum_quantity, status, expected_delivery, car_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $stationId,
            $partId,
            $addImmediately ? $quantity : 0,
            $minimumQuantity,
            $status,
            $expectedDelivery,
            $carId
        ]);
    }

    header('Location: /manager/warehouse');
    exit;
}
?>


<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Склад СТО</title>
    <link rel="stylesheet" href="/styles.css">
</head>

<body>
    <header>
        <h2 style="color: white;"> STOLogic — Склад деталей</h2>
    </header>


    <a href='/dashboard_manager' class="btn btn-back">Назад в дашборд</a>

    <input type="text" id="searchInput" placeholder="Поиск по деталям..." class="search-input">

    <div id="partsContainer" class="grid">
        <?php foreach ($details as $part): ?>
            <div class="card">
                <h4><?= htmlspecialchars($part['name']) ?> (<?= htmlspecialchars($part['part_brand']) ?>)</h4>
                <p><?= htmlspecialchars($part['description']) ?></p>

                <p>Цена: <?= number_format($part['price'], 2, ',', ' ') ?> ₽</p>

                <p>На складе:
                    <?= $part['quantity'] ?>
                    <?php if ($part['status'] === 'pending' && $part['expected_delivery']): ?>
                        <span class="text-muted">(+<?= $part['quantity'] ?> до
                            <?= date('d.m.Y', strtotime($part['expected_delivery'])) ?>)</span>
                    <?php endif; ?>
                </p>

                <?php if ($part['car_id']): ?>
                    <p>Для автомобиля: <?= htmlspecialchars($part['car_brand']) ?>         <?= htmlspecialchars($part['car_model']) ?>
                    </p>
                <?php endif; ?>

                <label>Минимальное количество:</label>
                <input type="number" value="<?= $part['minimum_quantity'] ?>" disabled class="input-min-quantity">

                <a href="/edit-part?id=<?= $part['id'] ?>" class="btn btn-edit">Редактировать</a>
            </div>
        <?php endforeach; ?>

        <div class="card add-card" onclick="openPartModal()">
            <h3>+</h3>
            <p>Добавить деталь</p>
        </div>
    </div>
    <div class="centered-button">
        <a href="/check-part-req" class="btn btn-primary">Перейти к заявкам на детали</a>
    </div>

    <!-- Модальное окно добавления детали -->
    <div class="modal" id="addPartModal">
        <div class="modal-content">
            <h3>Добавить деталь на склад</h3>

            <form id="partForm" method="POST">
                <input type="hidden" name="station_id" value="<?= htmlspecialchars($stationId) ?>">
                <input type="hidden" name="part_id" id="part_id_real" required>

                <div class="form-group">
                    <label>Выберите автомобиль (необязательно):</label>
                    <select id="car_select" name="car_id" onchange="searchParts()" class="input-select">
                        <option value="">Все автомобили</option>
                        <?php foreach ($cars as $car): ?>
                            <option value="<?= $car['id'] ?>">
                                <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' ' . $car['year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top: 10px;">
                        <a href="/register-car?redirect=add-part" style="color: #007bff;">Не нашли нужный? Можете
                            зарегистрировать автомобиль тут</a>
                    </div>
                </div>

                <div class="autocomplete-container">
                    <label>Выберите деталь:</label>
                    <input type="text" id="part_input" placeholder="Начните вводить название..." autocomplete="off"
                        required class="input-text-large">
                    <div id="part-suggestions" class="autocomplete-suggestions"></div>
                </div>

                <div style="margin: 10px 0;">
                    <a href="/add-part" style="color: #007bff;">Не нашли деталь в системе? Добавьте её здесь</a>
                </div>

                <div class="form-group">
                    <label>Количество:</label>
                    <input type="number" name="quantity" min="1" required class="input-quantity-small">
                </div>

                <div class="form-group">
                    <label>Минимальное количество:</label>
                    <input type="number" name="minimum_quantity" min="0" required class="input-quantity-small">
                </div>

                <div class="form-group">
                    <label>Цена за штуку (₽):</label>
                    <input type="number" name="price" step="0.01" min="0" required class="input-quantity-small">
                </div>

                <div class="form-group">
                    <label>Добавить сразу на склад?</label>
                    <select name="add_immediately" id="add_immediately" onchange="toggleDeliveryFields()" required
                        class="input-select">
                        <option value="1">Да, сразу</option>
                        <option value="0">Нет, будет доставка</option>
                    </select>
                </div>

                <div id="deliveryFields" style="display:none;">
                    <div class="form-group">
                        <label>Ожидаемые дни доставки:</label>
                        <input type="number" name="delivery_days" min="1" class="input-quantity-small">
                    </div>
                </div>

                <!-- Контейнер для кнопок с одинаковой шириной -->
                <div class="modal-buttons">
                    <button type="submit" class="btn btn-submit">Добавить</button>
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Отмена</button>
                </div>
            </form>

        </div>
        <!-- Кнопка для перехода к просмотру заявок -->


    </div>




    <script>
        const catalog = <?= json_encode($catalog) ?>;

        function openPartModal() {
            document.getElementById('addPartModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('addPartModal').style.display = 'none';
        }

        window.onclick = function (event) {
            const modal = document.getElementById('addPartModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        document.getElementById('part_input').addEventListener('input', searchParts);

        function searchParts() {
            const input = document.getElementById('part_input').value.toLowerCase();
            const carId = document.getElementById('car_select').value;
            let filteredParts = catalog;

            if (carId) {
                filteredParts = catalog.filter(part => part.car_ids.includes(parseInt(carId)));
            }

            if (input) {
                filteredParts = filteredParts.filter(part => part.name.toLowerCase().includes(input));
            }

            showSuggestions(filteredParts);
        }

        function showSuggestions(parts) {
            const suggestions = document.getElementById('part-suggestions');
            suggestions.innerHTML = '';

            parts.forEach(part => {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion';
                div.textContent = `${part.name} (${part.brand})`;
                div.onclick = () => selectPart(part);
                suggestions.appendChild(div);
            });
        }

        function selectPart(part) {
            document.getElementById('part_input').value = part.name;
            document.getElementById('part_id_real').value = part.id;
            document.getElementById('part-suggestions').innerHTML = '';
        }

        function toggleDeliveryFields() {
            const deliveryFields = document.getElementById('deliveryFields');
            const addImmediately = document.getElementById('add_immediately').value;
            deliveryFields.style.display = addImmediately === '0' ? 'block' : 'none';
        }

        // Поиск по складу
        document.getElementById('searchInput').addEventListener('input', function () {
            const filter = this.value.toLowerCase();
            const parts = document.querySelectorAll('#partsContainer .card');

            parts.forEach(part => {
                const title = part.querySelector('h4')?.textContent.toLowerCase() || '';
                if (title.includes(filter)) {
                    part.style.display = 'block';
                } else {
                    part.style.display = 'none';
                }
            });
        });
    </script>

</body>

</html>