<?php
session_start();
require_once __DIR__ . '/../config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name']);
    $birthdate  = $_POST['birthdate'];
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $username   = trim($_POST['username']);
    $password   = $_POST['password'];
    $role       = $_POST['role'];
    $station_id = ($role === 'manager' || isset($_POST['station_later'])) ? null : $_POST['station_id'];

    if (empty($full_name) || empty($birthdate) || empty($email) || empty($phone) || empty($username) || empty($password)) {
        $errors[] = "Пожалуйста, заполните все поля.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Пользователь с таким логином уже существует.";
        }
    }

    if (empty($errors)) {
        try {
            $hashedPassword    = password_hash($password, PASSWORD_DEFAULT);
            $verification_code = bin2hex(random_bytes(16));
            $email_verified    = false;
            $approved          = ($role === 'manager');

            // Вставка нового пользователя
            $stmt = $pdo->prepare("INSERT INTO users 
                (username, password, role, station_id, full_name, birthdate, email, phone, approved, email_verified, verification_code) 
                VALUES 
                (:username, :password, :role, :station_id, :full_name, :birthdate, :email, :phone, :approved, :email_verified, :verification_code)");

            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':password', $hashedPassword);
            $stmt->bindValue(':role', $role);
            $stmt->bindValue(':station_id', $station_id, $station_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':full_name', $full_name);
            $stmt->bindValue(':birthdate', $birthdate);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':phone', $phone);
            $stmt->bindValue(':approved', $approved, PDO::PARAM_BOOL);
            $stmt->bindValue(':email_verified', $email_verified, PDO::PARAM_BOOL);
            $stmt->bindValue(':verification_code', $verification_code);

            $stmt->execute();

            $user_id = $pdo->lastInsertId();

            // Заявка в таблицу station_requests, если не менеджер и станция указана
            if (!$approved && $station_id !== null) {
                $stmtReq = $pdo->prepare("INSERT INTO station_requests (user_id, station_id, status, created_at) 
                                          VALUES (:user_id, :station_id, 'pending', NOW())");
                $stmtReq->execute([
                    'user_id' => $user_id,
                    'station_id' => $station_id
                ]);
            }

            // Отправка письма
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'ilaspapov8@gmail.com';
            $mail->Password   = 'ecpz wcay hych hill';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';

            $mail->setFrom('ilaspapov8@gmail.com', 'STOLogic');
            $mail->addAddress($email, $full_name);
            $mail->isHTML(true);
            $mail->Subject = 'Подтверждение регистрации';
            $mail->Body    = "Здравствуйте, $full_name!<br>Пожалуйста, подтвердите ваш email, перейдя по ссылке: 
            <a href='http://localhost:8000/verify?code=$verification_code'>Подтвердить email</a>";

            $mail->send();

            $success = 'Пользователь успешно зарегистрирован! Проверьте вашу почту для подтверждения.';
        } catch (Exception $e) {
            $errors[] = "Ошибка: " . $e->getMessage();
        }
    }
}

$stmtStations = $pdo->query("SELECT id, name FROM stations");
$stations = $stmtStations->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация | STOLogic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        header {
            background-color: #003366;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 26px;
            font-weight: bold;
        }
        main {
            max-width: 450px;
            margin: 30px auto;
            padding: 25px 30px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #333;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }
        button {
            background-color: #003366;
            color: #fff;
            padding: 10px;
            border: none;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background-color: #001f3f;
        }
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>

<header>Регистрация в STOLogic</header>

<main>
    <?php if (!empty($errors)): ?>
        <div class="message error">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php elseif (!empty($success)): ?>
        <div class="message success">
            <p><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label for="full_name">ФИО:</label>
        <input type="text" name="full_name" id="full_name" required>

        <label for="birthdate">Дата рождения:</label>
        <input type="date" name="birthdate" id="birthdate" required>

        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>

        <label for="phone">Телефон:</label>
        <input type="tel" name="phone" id="phone" required>

        <label for="username">Логин:</label>
        <input type="text" name="username" id="username" required>

        <label for="password">Пароль:</label>
        <input type="password" name="password" id="password" required>

        <label for="role">Роль:</label>
        <select name="role" id="role" required>
            <option value="">Выберите роль</option>
            <option value="manager">Менеджер</option>
            <option value="mechanic">Механик</option>
            <option value="accountant">Бухгалтер</option>
        </select>

        <div id="station_select" style="display:none;">
            <label for="station_id">Выберите СТО:</label>
            <select name="station_id" id="station_id">
                <?php foreach ($stations as $station): ?>
                    <option value="<?= $station['id'] ?>"><?= htmlspecialchars($station['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="station_later_checkbox" style="display:none;">
            <label><input type="checkbox" name="station_later" id="station_later"> Укажу СТО позже</label>
        </div>

        <button type="submit">Зарегистрироваться</button>
    </form>
</main>

<script>
    const roleSelect = document.getElementById('role');
    const stationSelect = document.getElementById('station_select');
    const stationLaterCheckbox = document.getElementById('station_later_checkbox');
    const stationLaterInput = document.getElementById('station_later');

    roleSelect.addEventListener('change', function () {
        const selectedRole = this.value;
        const isMechOrAcc = selectedRole === 'mechanic' || selectedRole === 'accountant';

        stationSelect.style.display = (isMechOrAcc && !stationLaterInput.checked) ? 'block' : 'none';
        stationLaterCheckbox.style.display = isMechOrAcc ? 'block' : 'none';
    });

    stationLaterInput.addEventListener('change', function () {
        stationSelect.style.display = this.checked ? 'none' : 'block';
    });
</script>

</body>
</html>
