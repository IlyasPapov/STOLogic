<?php
require_once __DIR__ . '/../config/db.php';

$username = 'admin';
$password = 'admin123';
$role = 'manager';

// Хешируем пароль
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Вставляем в базу
$stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
$stmt->execute([
    'username' => $username,
    'password' => $hashedPassword,
    'role' => $role
]);

echo "Пользователь успешно добавлен!";
