<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Необходимо авторизоваться']);
    exit;
}

$user = $_SESSION['user'];

$dashboardPath = match ($user['role']) {
    'mechanic' => '/dashboard_mechanic',
    'accountant' => '/dashboard_accountant',
    'manager' => '/dashboard_manager',
    default => '/login.php'
};

if ($user['role'] !== 'manager') {
    echo json_encode(['status' => 'error', 'message' => 'Доступ только для менеджеров']);
    exit;
}

// Получаем ID заявки из POST
$requestId = $_POST['request_id'] ?? null;

if (!$requestId) {
    echo json_encode(['status' => 'error', 'message' => 'Нет ID заявки']);
    exit;
}

// Получаем заявку
$stmt = $pdo->prepare("SELECT * FROM station_requests WHERE id = :id AND status = 'pending'");
$stmt->execute(['id' => $requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo json_encode(['status' => 'error', 'message' => 'Заявка не найдена или уже обработана']);
    exit;
}

$userId = $request['user_id'];
$stationId = $request['station_id'];

// Получаем пользователя и станцию
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->execute(['user_id' => $userId]);
$userRequesting = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM stations WHERE id = :station_id");
$stmt->execute(['station_id' => $stationId]);
$station = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userRequesting || !$station) {
    echo json_encode(['status' => 'error', 'message' => 'Пользователь или станция не найдены']);
    exit;
}

// Проверка: этот менеджер ли управляет станцией
if ($station['manager_id'] != $user['id']) {
    echo json_encode(['status' => 'error', 'message' => 'Вы не управляете данной СТО']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Если уже привязан
    if ($userRequesting['station_id'] == $stationId && $userRequesting['approved']) {
        $stmt = $pdo->prepare("DELETE FROM station_requests WHERE id = :id");
        $stmt->execute(['id' => $requestId]);
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Пользователь уже состоит в СТО. Заявка удалена']);
        exit;
    }

    // Обновляем заявку
    $stmt = $pdo->prepare("UPDATE station_requests SET status = 'approved' WHERE id = :id");
    $stmt->execute(['id' => $requestId]);

    // Обновляем пользователя
    $stmt = $pdo->prepare("UPDATE users SET station_id = :station_id, approved = true WHERE id = :user_id");
    $stmt->execute([
        'station_id' => $stationId,
        'user_id' => $userId
    ]);

    // Если роль — mechanic, добавляем в mechanics (если ещё нет)
    if ($userRequesting['role'] === 'mechanic') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mechanics WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $stmt = $pdo->prepare("
                INSERT INTO mechanics (user_id, hourly_rate, min_shifts_per_week, max_shifts_per_week, station_id)
                VALUES (:user_id, 300.00, 4, 8, :station_id)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'station_id' => $stationId
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Пользователь успешно добавлен на СТО']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Ошибка при обработке заявки: ' . $e->getMessage()]);
}
?>
