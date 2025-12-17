<?php
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'manager') {
    header('Location: /unauthorized');
    exit;
}

// API параметры
$apiKey = '453b6435a5e0';

function getCarInfoByVin($vin) {
    global $apiKey;
    $url = "https://api.vindecoder.eu/2.0/decode?vin=" . urlencode($vin) . "&apikey=" . urlencode($apiKey);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Получаем и сохраняем параметры from и part_id
$from = $_GET['from'] ?? null;
$partId = $_GET['part_id'] ?? null;

if ($from) {
    $_SESSION['car_registration_from'] = $from;
}
if ($partId) {
    $_SESSION['car_registration_part_id'] = $partId;
}

// Используем из сессии при POST-запросе
$redirectBack = $_SESSION['car_registration_from'] ?? 'add-part';
$partId = $_SESSION['car_registration_part_id'] ?? null;

$vin = '';
$brand = '';
$model = '';
$year = '';
$engine = '';
$transmission = '';
$engine_fuel_type = '';
$error = '';
$success = '';
$manual = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vin = trim($_POST['vin'] ?? '');
    $manual = isset($_POST['manual_data']);

    if ($vin && !$manual) {
        $data = getCarInfoByVin($vin);

        if (!empty($data['specification'])) {
            $spec = $data['specification'];
            $brand = $spec['make'] ?? '';
            $model = $spec['model'] ?? '';
            $year = $spec['year'] ?? '';
            $engine = $spec['engine'] ?? '';
            $transmission = $spec['transmission'] ?? '';
        } else {
            $error = 'Ошибка получения данных по VIN. Введите данные вручную.';
            $manual = true;
        }
    }

    if ($manual || ($brand && $model && $year)) {
        $brand = trim($_POST['brand'] ?? $brand);
        $model = trim($_POST['model'] ?? $model);
        $year = (int)($_POST['year'] ?? $year);
        $engine = trim($_POST['engine'] ?? $engine);
        $transmission = trim($_POST['transmission'] ?? $transmission);
        $engine_fuel_type = trim($_POST['engine_fuel_type'] ?? $engine_fuel_type);

        if ($brand && $model && $year && $engine && $transmission && $engine_fuel_type) {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM cars WHERE brand = :brand AND model = :model AND year = :year AND engine = :engine AND transmission = :transmission");
                $checkStmt->execute([
                    ':brand' => $brand,
                    ':model' => $model,
                    ':year' => $year,
                    ':engine' => $engine,
                    ':transmission' => $transmission,
                ]);
                $existingCar = $checkStmt->fetch();

                if ($existingCar) {
                    $error = 'Такой автомобиль уже зарегистрирован.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cars (vin, brand, model, year, engine, transmission, engine_fuel_type) 
                                           VALUES (:vin, :brand, :model, :year, :engine, :transmission, :engine_fuel_type)");
                    $stmt->execute([
                        ':vin' => $vin,
                        ':brand' => $brand,
                        ':model' => $model,
                        ':year' => $year,
                        ':engine' => $engine,
                        ':transmission' => $transmission,
                        ':engine_fuel_type' => $engine_fuel_type,
                    ]);

                    $success = 'Автомобиль успешно добавлен!';

                    $queryParams = "car_id=" . $pdo->lastInsertId();
                    if ($partId) {
                        $queryParams .= "&part_id=$partId";
                    }

                    // Очищаем сессию после использования
                    unset($_SESSION['car_registration_from'], $_SESSION['car_registration_part_id']);

                    header("Location: /$redirectBack?$queryParams");
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Ошибка при добавлении: ' . htmlspecialchars($e->getMessage());
            }
        } else {
            $error = 'Пожалуйста, заполните все поля.';
        }
    }
}
?>



<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавление автомобиля</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .card.narrow {
            width: 50%;
            margin: 0 auto;
        }

        .form-checkbox.inline {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-checkbox.inline label {
            margin: 0;
        }
    </style>
</head>
<body class="centered">

<div class="card narrow">
    <h2 class="card-title">Добавить автомобиль</h2>

    <!-- Кнопка "Назад" -->
    <a href="/<?= htmlspecialchars($_GET['from'] ?? 'add-part') ?>" class="btn btn-secondary" style="margin-bottom: 15px;">Назад</a>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="from" value="<?= htmlspecialchars($_GET['from'] ?? 'add-part') ?>">

        <div class="form-group">
            <label for="vin">VIN номер:</label>
            <input type="text" id="vin" name="vin" class="input" value="<?= htmlspecialchars($vin) ?>" <?= $manual ? 'disabled' : '' ?>>
        </div>

        <div class="form-group form-checkbox inline">
            <label for="manual_data">Ввести данные вручную</label>
            <input type="checkbox" id="manual_data" name="manual_data" <?= $manual ? 'checked' : '' ?> onchange="this.form.submit();">
        </div>

        <?php if ($manual || $brand || $model): ?>
            <!-- Остальные поля формы -->
            <div class="form-group">
                <label for="brand">Марка:</label>
                <input type="text" id="brand" name="brand" class="input" value="<?= htmlspecialchars($brand) ?>" required>
            </div>
            <div class="form-group">
                <label for="model">Модель:</label>
                <input type="text" id="model" name="model" class="input" value="<?= htmlspecialchars($model) ?>" required>
            </div>
            <div class="form-group">
                <label for="year">Год выпуска:</label>
                <input type="number" id="year" name="year" class="input" value="<?= htmlspecialchars($year) ?>" min="1950" max="2100" required>
            </div>
            <div class="form-group">
                <label for="engine">Двигатель (объём):</label>
                <input type="text" id="engine" name="engine" class="input" value="<?= htmlspecialchars($engine) ?>" required>
            </div>
            <div class="form-group">
                <label for="transmission">Тип КПП:</label>
                <select id="transmission" name="transmission" class="input" required>
                    <option value="">-- Выберите тип --</option>
                    <option value="МКПП" <?= $transmission === 'МКПП' ? 'selected' : '' ?>>МКПП</option>
                    <option value="АКПП" <?= $transmission === 'АКПП' ? 'selected' : '' ?>>АКПП</option>
                    <option value="Вариатор" <?= $transmission === 'Вариатор' ? 'selected' : '' ?>>Вариатор</option>
                </select>
            </div>
            <div class="form-group">
                <label for="engine_fuel_type">Тип двигателя/топлива:</label>
                <select id="engine_fuel_type" name="engine_fuel_type" class="input" required>
                    <option value="">-- Выберите тип двигателя/топлива --</option>
                    <option value="Бензин" <?= $engine_fuel_type === 'Бензин' ? 'selected' : '' ?>>Бензин</option>
                    <option value="Дизель" <?= $engine_fuel_type === 'Дизель' ? 'selected' : '' ?>>Дизель</option>
                    <option value="Электро" <?= $engine_fuel_type === 'Электро' ? 'selected' : '' ?>>Электро</option>
                    <option value="Гибрид" <?= $engine_fuel_type === 'Гибрид' ? 'selected' : '' ?>>Гибрид</option>
                </select>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</div>


