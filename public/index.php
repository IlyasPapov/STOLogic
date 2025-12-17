<?php
// Запускаем сессию
session_start();

// Подключаем файл с подключением к БД
require_once __DIR__ . '/../src/config/db.php';

// Получаем текущий URL без параметров
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);



// Обработка маршрутов
switch ($uri) {
    case '/':
    case '/login':
        require __DIR__ . '/../src/pages/login.php';
        break;

    case '/register':
        require __DIR__ . '/../src/pages/register.php';
        break;

    case '/dashboard':
        require __DIR__ . '/../src/pages/dashboard.php';
        break;

    // Дашборды для разных ролей
    case '/dashboard_manager':
        require __DIR__ . '/../src/pages/dashboard_manager.php';
        break;

    case '/dashboard_mechanic':
        require __DIR__ . '/../src/pages/dashboard_mechanic.php';
        break;

    case '/dashboard_accountant':
        require __DIR__ . '/../src/pages/dashboard_accountant.php';
        break;

    case '/adduser':
        require __DIR__ . '/../src/tools/add_user.php';
        break;

    case '/verify':
        require __DIR__ . '/../src/pages/verify.php';
        break;

    case '/logout':
        require __DIR__ . '/../src/tools/logout.php';
        break;

    case '/create-station':
        require __DIR__ . '/../src/pages/create-station.php';
        break;

    case '/mechanic_tasks':
        require __DIR__ . '/../src/pages/mechanic_tasks.php';
        break;

    case '/requests':
        require __DIR__ . '/../src/tools/requests.php';
        break;

    case '/check-part-req':
        require __DIR__ . '/../src/tools/check-part-req.php';
        break;

    case '/approve-request':
        require __DIR__ . '/../src/tools/approve-request.php';
        break;

    case '/reject-request':
        require __DIR__ . '/../src/tools/reject-request.php';
        break;


    case '/order':
        require __DIR__ . '/../src/pages/order.php';
        break;

    case '/financial_report':
        require __DIR__ . '/../src/pages/financial_report.php';
        break;

    case '/export_pdf':
        require __DIR__ . '/../src/pages/export_pdf.php';
        break;

    case '/remove-employee':
        require __DIR__ . '/../src/tools/remove-employee.php';
        break;

    case '/delete-invite':
        require __DIR__ . '/../src/tools/delete-invite.php';
        break;

    case '/invite-employee':
        require __DIR__ . '/../src/tools/invite-employee.php';
        break;

    case '/manager/station':
        require __DIR__ . '/../src/pages/manager/station.php';
        break;

    case '/manager/warehouse':
        require __DIR__ . '/../src/pages/manager/warehouse.php';
        break;

    case '/warehouseMech':
        require __DIR__ . '/../src/pages/warehouseMech.php';
        break;

    case '/schedule':
        require __DIR__ . '/../src/pages/schedule.php';
        break;

    case '/send-request':
        require __DIR__ . '/../src/tools/send-request.php';
        break;

    case '/add-part':
        require __DIR__ . '/../src/tools/add-part.php';
        break;

    case '/generate_schedule_cost':
        require __DIR__ . '/../src/tools/generate_schedule_cost.php';
        break;

    case '/generate_schedule_balanced':
        require __DIR__ . '/../src/tools/generate_schedule_balanced.php';
        break;

    case '/schedule_actions':
        require __DIR__ . '/../src/tools/schedule_actions.php';
        break;

    case '/generate_schedule_priorety':
        require __DIR__ . '/../src/tools/generate_schedule_priorety.php';
        break;

    case '/edit-part':
        require __DIR__ . '/../src/tools/edit-part.php';
        break;

    case '/send-parts-req':
        require __DIR__ . '/../src/tools/send-parts-req.php';
        break;

    case '/schedule-review':
        require __DIR__ . '/../src/tools/schedule-review.php';
        break;

    case '/register-car':
        require __DIR__ . '/../src/tools/register-car.php';
        break;

    case '/update_mechanic_status':
        require __DIR__ . '/../src/tools/update_mechanic_status.php';
        break;

    case '/manager/edit-station':
        require __DIR__ . '/../src/pages/manager/edit-station.php';
        break;

    case '/create-schedule':
        require __DIR__ . '/../src/pages/create-schedule.php';
        break;


    default:
        http_response_code(404);
        echo "Страница не найдена (404)";
}
