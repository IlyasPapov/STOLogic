<?php
// Подключаем БД

require_once __DIR__ . '/../config/db.php'; // Подключение к базе данных

// Получаем ID станции из сессии или запроса
$stationId = $_SESSION['station_id'] ?? 0;

// Получаем заявки для выбранной станции
$query = "
    SELECT spr.id, spr.station_id, spr.part_id, spr.requested_quantity, spr.user_id, spr.status, spr.created_at, 
           u.username, p.name AS part_name, p.brand, p.description
    FROM station_parts_requests spr
    JOIN users u ON spr.user_id = u.id
    JOIN parts_catalog p ON spr.part_id = p.id
    WHERE spr.station_id = :station_id AND spr.status = 'pending'
";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':station_id', $stationId, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем информацию о наличии деталей на складе
$query_stock = "
    SELECT sp.part_id, sp.quantity
    FROM station_parts sp
    WHERE sp.station_id = :station_id
";
$stmt_stock = $pdo->prepare($query_stock);
$stmt_stock->bindParam(':station_id', $stationId, PDO::PARAM_INT);
$stmt_stock->execute();
$stock = $stmt_stock->fetchAll(PDO::FETCH_ASSOC);
$stock_data = [];
foreach ($stock as $item) {
    $stock_data[$item['part_id']] = $item['quantity'];
}
// Обработка кнопки "Добавить"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_part_id'])) {
    $checkPartId = (int) $_POST['check_part_id'];

    // Проверяем наличие на складе
    if (isset($stock_data[$checkPartId]) && $stock_data[$checkPartId] > 0) {
        header("Location: edit-part?part_id=$checkPartId");
        exit();
    } else {
        $openModal = true; // Флаг для открытия модального окна
        $partIdToAdd = $checkPartId;
    }
}

// Обработка удаления заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request_id'])) {
    $requestId = $_POST['delete_request_id'];
    $delete_query = "DELETE FROM station_parts_requests WHERE id = :id";
    $stmt_delete = $pdo->prepare($delete_query);
    $stmt_delete->bindParam(':id', $requestId, PDO::PARAM_INT);
    $stmt_delete->execute();
    // Перенаправляем обратно на страницу
    header('Location: check-part-req');
    exit();
}
var_dump($_SESSION);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Заявки на детали</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 30px;
        }

        h1 {
            text-align: center;
            color: #003366;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        thead {
            background-color: #003366;
            color: white;
        }

        th,
        td {
            padding: 12px 14px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        tbody tr:hover {
            background-color: #f1f1f1;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-add {
            background-color: #007bff;
            color: white;
        }

        .btn-delete {
            background-color: #e74c3c;
            color: white;
        }

        .btn-back {
            background-color: #ccc;
            color: #000;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 30px;
        }

        .btn-back:hover {
            background-color: #bbb;
        }

        .back-button-container {
            text-align: center;
        }
    </style>
</head>

<body>
    <h1>Заявки на детали</h1>

    <table>
        <thead>
            <tr>
                <th>№ п/п</th>
                <th>Деталь</th>
                <th>Описание</th>
                <th>Наличие на складе</th>
                <th>Запрашиваемое количество</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php $index = 1; ?>
            <?php foreach ($requests as $request): ?>
                <?php
                $is_in_stock = isset($stock_data[$request['part_id']]) ? $stock_data[$request['part_id']] : 0;
                ?>
                <tr>
                    <td><?= $index++ ?></td>
                    <td><?= htmlspecialchars($request['part_name']) ?> (<?= htmlspecialchars($request['brand']) ?>)</td>
                    <td><?= htmlspecialchars($request['description']) ?></td>
                    <td><?= $is_in_stock > 0 ? 'Есть: ' . $is_in_stock : 'Нет' ?></td>
                    <td><?= $request['requested_quantity'] ?></td>
                    <td>
                        <form action="check-part-req" method="POST" style="display:inline;">
                            <input type="hidden" name="delete_request_id" value="<?= $request['id'] ?>">
                            <button type="submit" class="btn btn-delete"
                                onclick="return confirm('Вы уверены, что хотите удалить заявку?')">Удалить</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="check_part_id" value="<?= $request['part_id'] ?>">
                            <button type="submit" class="btn btn-add">Добавить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="back-button-container">
        <a href="/dashboard_manager" class="btn-back">Назад</a>
    </div>
</body>

</html>