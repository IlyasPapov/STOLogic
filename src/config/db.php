<?php
// Параметры подключения
$host = 'localhost';
$port = '5432';
$dbname = 'sto_db';
$user = 'postgres';
$password = '1234';

// Строка подключения
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    // Создаем PDO объект
    $pdo = new PDO($dsn, $user, $password);

    // Устанавливаем режим ошибок на исключения
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Успешное подключение

} catch (PDOException $e) {
    // Ошибка подключения
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
