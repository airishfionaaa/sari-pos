<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/src/helpers/EnvLoader.php';
EnvLoader::load(__DIR__ . '/.env');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/helpers/Security.php';
require_once __DIR__ . '/src/helpers/PusherService.php';
require_once __DIR__ . '/src/middleware/Auth.php';

foreach (glob(__DIR__ . '/src/controllers/*.php') as $f) {
    require_once $f;
}

Auth::start();
Security::setHeaders();

$page   = Security::sanitizeString($_GET['page']   ?? 'login');
$action = Security::sanitizeString($_GET['action'] ?? '');

if (!in_array($page, ['login'])) {
    Auth::requireLogin();
}

switch ($page) {
    case 'login':
        if (Auth::check()) {
            header('Location: /sari-pos/index.php?page=dashboard');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::handleLogin();
        } else {
            require_once __DIR__ . '/views/auth/login.php';
        }
        break;

    case 'logout':
        Auth::logout();
        header('Location: /sari-pos/index.php?page=login');
        exit;

    case 'dashboard':
        require_once __DIR__ . '/views/dashboard/index.php';
        break;

    case 'pos':
        require_once __DIR__ . '/views/pos/index.php';
        break;

    case 'products':
        if ($action==='save'    && $_SERVER['REQUEST_METHOD']==='POST') { ProductController::save();    exit; }
        if ($action==='delete'  && $_SERVER['REQUEST_METHOD']==='POST') { ProductController::delete();  exit; }
        if ($action==='restock' && $_SERVER['REQUEST_METHOD']==='POST') { ProductController::restock(); exit; }
        if ($action==='list'    && $_SERVER['REQUEST_METHOD']==='GET')  { ProductController::list();    exit; }
        require_once __DIR__ . '/views/products/index.php';
        break;

    case 'transactions':
        if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST') { TransactionController::create(); exit; }
        if ($action==='void'   && $_SERVER['REQUEST_METHOD']==='POST') { TransactionController::void();   exit; }
        if ($action==='list'   && $_SERVER['REQUEST_METHOD']==='GET')  { TransactionController::list();   exit; }
        if ($action==='detail' && $_SERVER['REQUEST_METHOD']==='GET')  { TransactionController::detail(); exit; }
        require_once __DIR__ . '/views/transactions/index.php';
        break;

    case 'customers':
        if ($action==='save'       && $_SERVER['REQUEST_METHOD']==='POST') { CustomerController::save();      exit; }
        if ($action==='delete'     && $_SERVER['REQUEST_METHOD']==='POST') { CustomerController::delete();    exit; }
        if ($action==='pay'        && $_SERVER['REQUEST_METHOD']==='POST') { CustomerController::pay();       exit; }
        if ($action==='add-charge' && $_SERVER['REQUEST_METHOD']==='POST') { CustomerController::addCharge(); exit; }
        if ($action==='list'       && $_SERVER['REQUEST_METHOD']==='GET')  { CustomerController::list();      exit; }
        if ($action==='ledger'     && $_SERVER['REQUEST_METHOD']==='GET')  { CustomerController::ledger();    exit; }
        require_once __DIR__ . '/views/customers/index.php';
        break;

    case 'expenses':
        if ($action==='save'   && $_SERVER['REQUEST_METHOD']==='POST') { ExpenseController::save();   exit; }
        if ($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST') { ExpenseController::delete(); exit; }
        if ($action==='list'   && $_SERVER['REQUEST_METHOD']==='GET')  { ExpenseController::list();   exit; }
        require_once __DIR__ . '/views/dashboard/expenses.php';
        break;

    case 'cashfund':
        if ($action==='save' && $_SERVER['REQUEST_METHOD']==='POST') { CashFundController::save(); exit; }
        if ($action==='get'  && $_SERVER['REQUEST_METHOD']==='GET')  { CashFundController::get();  exit; }
        break;

    case 'reports':
        if ($action==='run'        && $_SERVER['REQUEST_METHOD']==='POST') { ReportController::run();       exit; }
        if ($action==='dashboard'  && $_SERVER['REQUEST_METHOD']==='GET')  { ReportController::dashboard(); exit; }
        if ($action==='zreading'   && $_SERVER['REQUEST_METHOD']==='GET')  { ReportController::zreading();  exit; }
        if ($action==='export-csv' && $_SERVER['REQUEST_METHOD']==='POST') { ReportController::exportCsv(); exit; }
        Auth::requireAdmin();
        require_once __DIR__ . '/views/reports/index.php';
        break;

    case 'users':
        Auth::requireAdmin();
        if ($action==='save'   && $_SERVER['REQUEST_METHOD']==='POST') { UserController::save();   exit; }
        if ($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST') { UserController::delete(); exit; }
        if ($action==='list'   && $_SERVER['REQUEST_METHOD']==='GET')  { UserController::list();   exit; }
        require_once __DIR__ . '/views/dashboard/users.php';
        break;

    case 'pusher-config':
        header('Content-Type: application/json');
        Auth::requireLogin();
        echo json_encode((new PusherService())->getJsConfig());
        exit;

    default:
        http_response_code(404);
        echo '<h1>404 - Page not found</h1>';
}