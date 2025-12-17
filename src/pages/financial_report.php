<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // для Dompdf

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'manager') {
    header('Location: /login');
    exit;
}

$station_id = $_SESSION['station_id'] ?? null;
if (!$station_id) {
    die("Станция не найдена.");
}

$from = $_POST['from'] ?? '';
$to = $_POST['to'] ?? '';
$bonus = floatval($_POST['bonus'] ?? 0);
$data = [];

if ($from && $to) {
    $stmt = $pdo->prepare("SELECT m.*, u.full_name FROM mechanics m JOIN users u ON m.user_id = u.id WHERE m.station_id = :station_id");
    $stmt->execute(['station_id' => $station_id]);
    $mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mechanicCount = count($mechanics);
    $shiftHours = 7;

    $startDate = new DateTime($from);
    $endDate = new DateTime($to);
    $interval = $startDate->diff($endDate)->days + 1;
    $totalShifts = $interval * 2;

    $shiftDistribution = array_fill(0, $mechanicCount, 0);
    for ($i = 0; $i < $totalShifts; $i++) {
        $index = rand(0, $mechanicCount - 1);
        $shiftDistribution[$index]++;
    }

    $salaryTotal = 0;
    $report = [];
    foreach ($mechanics as $i => $mech) {
        $shifts = $shiftDistribution[$i];
        $salary = $shifts * $shiftHours * $mech['hourly_rate'];
        $salaryTotal += $salary;

        $report[] = [
            'name' => $mech['full_name'] ?? $mech['mechanic_name'],
            'shifts' => $shifts,
            'salary' => $salary
        ];
    }

    $stmt = $pdo->prepare("SELECT SUM(parts_price) FROM orders WHERE station_id = :station_id AND status = 'Выполнен' AND order_datetime BETWEEN :from AND :to");
    $stmt->execute(['station_id' => $station_id, 'from' => $from, 'to' => $to]);
    $partsCost = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE station_id = :station_id AND status = 'Выполнен' AND order_datetime BETWEEN :from AND :to");
    $stmt->execute(['station_id' => $station_id, 'from' => $from, 'to' => $to]);
    $totalIncome = $stmt->fetchColumn() ?: 0;

    $totalWithBonus = $salaryTotal + $bonus;
    $totalCosts = $totalWithBonus + $partsCost;
    $netProfit = $totalIncome - $totalCosts;

    $data = compact('report', 'salaryTotal', 'bonus', 'totalWithBonus', 'partsCost', 'totalIncome', 'totalCosts', 'netProfit', 'from', 'to');
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8" />
    <title>Финансовый отчет — STOLogic</title>
    <style>
        /* === Вставлены твои стили, чтобы не копировать сюда весь блок, я просто их добавлю при внедрении === */
        body {
            background-color: #f9f9f9;
            font-family: Arial, sans-serif;
            margin: 20px 20px 40px;
            color: #222;
            padding: 0 20px 40px;
        }

        header {
            background-color: #003366;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        header h2 {
            margin: 0;
            font-weight: 600;
        }

        main {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            padding: 20px 30px;
            border-radius: 6px;
            box-shadow: 0 0 8px rgb(0 0 0 / 0.1);
        }

        label {
            display: block;
            margin-bottom: 6px;
            margin-top: 12px;
            font-weight: 600;
            color: #003366;
        }

        input[type="date"],
        input[type="number"] {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
            margin-bottom: 15px;
            resize: vertical;
            font-family: inherit;
            margin-top: 4px;
        }

        button,
        .button {
            background-color: #003366;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }

        button:hover,
        .button:hover {
            background-color: #002244;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 900px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px 12px;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
            white-space: nowrap;
        }

        th {
            background-color: #003366;
            color: white;
            font-weight: 600;
            user-select: none;
        }

        tr:nth-child(even) {
            background-color: #f2f6fb;
        }

        p strong {
            color: #003366;
        }

        /* Обертка для таблицы с прокруткой */
        .table-wrapper {
            overflow-x: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px 0;
            margin-top: 20px;
            margin-bottom: 30px;
        }
    </style>
</head>

<body>
    <header>
        <h2>STOLogic — Финансовый отчет</h2>
        <!-- Можешь добавить профиль, если надо -->
    </header>

    <main>
        <button class="button secondary mb-3"
            onclick="window.location.href='http://localhost:8000/dashboard_manager'">Назад</button>

        <form method="post" novalidate>
            <label for="from">Период с:
                <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>" required>
            </label>
            <label for="to">по:
                <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>" required>
            </label>
            <label for="bonus">Премии, ₽:
                <input type="number" step="0.01" id="bonus" name="bonus" value="<?= htmlspecialchars($bonus) ?>">
            </label>
            <button type="submit">Сформировать отчет</button>
        </form>

        <?php if ($data): ?>
            <h3>Отчет за период: <?= htmlspecialchars($from) ?> – <?= htmlspecialchars($to) ?></h3>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Механик</th>
                            <th>Смен</th>
                            <th>Зарплата (₽)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['report'] as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= (int) $row['shifts'] ?></td>
                                <td><?= number_format($row['salary'], 2, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p><strong>Общая ЗП:</strong> <?= number_format($data['salaryTotal'], 2, ',', ' ') ?> ₽</p>
            <p><strong>Премии:</strong> <?= number_format($data['bonus'], 2, ',', ' ') ?> ₽</p>
            <p><strong>ЗП с премиями:</strong> <?= number_format($data['totalWithBonus'], 2, ',', ' ') ?> ₽</p>
            <p><strong>Затраты на детали:</strong> <?= number_format($data['partsCost'], 2, ',', ' ') ?> ₽</p>
            <p><strong>Общая прибыль:</strong> <?= number_format($data['totalIncome'], 2, ',', ' ') ?> ₽</p>
            <p><strong>Итоговые затраты:</strong> <?= number_format($data['totalCosts'], 2, ',', ' ') ?> ₽</p>
            <p><strong>Чистая прибыль:</strong> <?= number_format($data['netProfit'], 2, ',', ' ') ?> ₽</p>

            <form method="post" action="export_pdf" target="_blank">
                <textarea name="html" style="display:none;"><?= htmlspecialchars($html) ?></textarea>
                <button type="submit" class="button">Сохранить PDF</button>
            </form>
        <?php endif; ?>
    </main>
</body>

</html>