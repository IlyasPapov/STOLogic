<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['code']) || empty($_GET['code'])) {
    echo "<p style='color:red;'>Отсутствует код подтверждения или он пустой.</p>";
    exit;
}

$code = $_GET['code'];

try {
    // Проверяем, есть ли такой код в базе
    $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE verification_code = :code");
    $stmt->execute(['code' => $code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['email_verified']) {
            echo "<p style='color:blue;'>Email уже подтверждён. Можете войти в систему.</p>";
        } else {
            // Обновляем поле подтверждения
            $updateStmt = $pdo->prepare("UPDATE users SET email_verified = true WHERE id = :id");
            $updateStmt->execute(['id' => $user['id']]);

            echo "<p style='color:green;'>Email успешно подтверждён! Теперь вы можете войти в систему.</p>";

            // Перенаправляем пользователя на страницу входа (редирект)
            header('Location: /login');
            exit;
        }
    } else {
        echo "<p style='color:red;'>Недействительный код подтверждения.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Ошибка при подтверждении: " . $e->getMessage() . "</p>";
}
