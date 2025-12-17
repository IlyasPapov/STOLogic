<?php
session_start();        // Запускаем сессию
session_destroy();      // Удаляем все данные сессии
header('Location: /login');  // Перенаправляем на страницу входа
exit;
