<?php
// ── CustomerController ─────────────────────────────────────
class CustomerController
{
    public static function list(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $q = Security::sanitizeString($_GET['q'] ?? '');
        $sql = "SELECT id, name, phone, address, credit_limit, balance, notes, is_active, created_at
                FROM customers WHERE is_active=1";
        $params = [];
        if ($q !== '') { $sql .= " AND (name LIKE ? OR phone LIKE ?)"; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }
        $sql .= " ORDER BY name";
        echo json_encode(['success'=>true,'data'=>Database::query($sql,$params)->fetchAll()]);
    }

    public static function ledger(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $id = Security::sanitizeInt($_GET['id'] ?? 0);
        $rows = Database::query(
            "SELECT cl.*, u.username FROM credit_ledger cl LEFT JOIN users u ON u.id=cl.user_id WHERE cl.customer_id=? ORDER BY cl.created_at DESC LIMIT 50",
            [$id]
        )->fetchAll();
        echo json_encode(['success'=>true,'data'=>$rows]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed.']); return; }
        $id     = Security::sanitizeInt($_POST['id'] ?? 0);
        $name   = Security::sanitizeString($_POST['name'] ?? '');
        $phone  = Security::sanitizeString($_POST['phone'] ?? '');
        $addr   = Security::sanitizeString($_POST['address'] ?? '');
        $limit  = Security::sanitizeFloat($_POST['credit_limit'] ?? 500);
        $notes  = Security::sanitizeString($_POST['notes'] ?? '');
        if (empty($name)) { echo json_encode(['success'=>false,'message'=>'Pangalan ay kinakailangan.']); return; }
        if ($id > 0) {
            Database::query("UPDATE customers SET name=?,phone=?,address=?,credit_limit=?,notes=? WHERE id=?",[$name,$phone,$addr,$limit,$notes,$id]);
            $msg = 'Customer na-update.';
        } else {
            Database::query("INSERT INTO customers (name,phone,address,credit_limit,notes) VALUES (?,?,?,?,?)",[$name,$phone,$addr,$limit,$notes]);
            $id = (int)Database::lastId(); $msg = 'Customer na-add.';
        }
        echo json_encode(['success'=>true,'message'=>$msg,'id'=>$id]);
    }

    public static function delete(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed.']); return; }
        $id = Security::sanitizeInt($_POST['id'] ?? 0);
        Database::query("UPDATE customers SET is_active=0 WHERE id=?",[$id]);
        echo json_encode(['success'=>true,'message'=>'Customer na-deactivate.']);
    }

    public static function pay(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed.']); return; }
        $custId = Security::sanitizeInt($_POST['customer_id'] ?? 0);
        $amount = Security::sanitizeFloat($_POST['amount'] ?? 0);
        $notes  = Security::sanitizeString($_POST['notes'] ?? '');
        $userId = Auth::id();
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("CALL sp_record_payment(?,?,?,?,@r,@m)");
        $stmt->execute([$custId,$amount,$userId,$notes]);
        $res = $pdo->query("SELECT @r AS r, @m AS m")->fetch();
        (new PusherService())->trigger('customers','payment-recorded',['customer_id'=>$custId,'amount'=>$amount]);
        echo json_encode(['success'=>(bool)$res['r'],'message'=>$res['m']]);
    }

    public static function addCharge(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed.']); return; }
        $custId = Security::sanitizeInt($_POST['customer_id'] ?? 0);
        $amount = Security::sanitizeFloat($_POST['amount'] ?? 0);
        $notes  = Security::sanitizeString($_POST['notes'] ?? '');
        $userId = Auth::id();
        if ($amount <= 0) { echo json_encode(['success'=>false,'message'=>'Halaga ay dapat higit sa 0.']); return; }
        $cust = Database::query("SELECT balance, credit_limit FROM customers WHERE id=?",[$custId])->fetch();
        if (!$cust) { echo json_encode(['success'=>false,'message'=>'Customer hindi nahanap.']); return; }
        $newBal = $cust['balance'] + $amount;
        if ($newBal > $cust['credit_limit']) { echo json_encode(['success'=>false,'message'=>'Lampas na sa credit limit.']); return; }
        Database::query("UPDATE customers SET balance=balance+? WHERE id=?",[$amount,$custId]);
        Database::query("INSERT INTO credit_ledger (customer_id,type,amount,balance_after,notes,user_id) VALUES (?,'charge',?,?,?,?)",[$custId,$amount,$newBal,$notes,$userId]);
        echo json_encode(['success'=>true,'message'=>"Nai-charge: ₱{$amount}."]);
    }
}

// ── ReportController ───────────────────────────────────────
class ReportController
{
    public static function dashboard(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $todayStats = Database::query(
            "SELECT COUNT(*) AS txn_count, COALESCE(SUM(grand_total),0) AS net_sales,
                    COALESCE(SUM(discount_amount),0) AS discounts,
                    COALESCE(AVG(grand_total),0) AS avg_txn
             FROM transactions WHERE status='completed' AND DATE(created_at)=?",
            [$today]
        )->fetch();

        $monthStats = Database::query(
            "SELECT COALESCE(SUM(grand_total),0) AS net_sales
             FROM transactions WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ?",
            [$monthStart, $today]
        )->fetch();

        $profitToday = Database::query(
            "SELECT COALESCE(SUM((ti.unit_price - ti.buy_price)*ti.quantity),0) AS profit
             FROM transaction_items ti JOIN transactions t ON t.id=ti.transaction_id
             WHERE t.status='completed' AND DATE(t.created_at)=?",
            [$today]
        )->fetchColumn();

        $lowStock = Database::query(
            "SELECT COUNT(*) FROM products WHERE stock_qty <= low_stock AND is_active=1"
        )->fetchColumn();

        $nearExpiry = Database::query(
            "SELECT COUNT(*) FROM products WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND is_active=1"
        )->fetchColumn();

        $cashFund = Database::query(
            "SELECT balance FROM cash_fund ORDER BY id DESC LIMIT 1"
        )->fetchColumn();

        $totalUtang = Database::query(
            "SELECT COALESCE(SUM(balance),0) FROM customers WHERE is_active=1"
        )->fetchColumn();

        // Hourly sales
        $hourly = Database::query(
            "SELECT HOUR(created_at) AS hr, SUM(grand_total) AS sales
             FROM transactions WHERE status='completed' AND DATE(created_at)=?
             GROUP BY HOUR(created_at) ORDER BY hr",
            [$today]
        )->fetchAll();

        // Payment breakdown today
        $payments = Database::query(
            "SELECT payment_method, COUNT(*) AS cnt, SUM(grand_total) AS total
             FROM transactions WHERE status='completed' AND DATE(created_at)=?
             GROUP BY payment_method",
            [$today]
        )->fetchAll();

        // Top 5 products today
        $topProducts = Database::query(
            "SELECT ti.product_name, SUM(ti.quantity) AS qty, SUM(ti.line_total) AS sales
             FROM transaction_items ti JOIN transactions t ON t.id=ti.transaction_id
             WHERE t.status='completed' AND DATE(t.created_at)=?
             GROUP BY ti.product_id, ti.product_name ORDER BY sales DESC LIMIT 5",
            [$today]
        )->fetchAll();

        // Week sales
        $weekSales = Database::query(
            "SELECT DATE(created_at) AS day, SUM(grand_total) AS sales
             FROM transactions WHERE status='completed'
               AND created_at >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)
             GROUP BY DATE(created_at) ORDER BY day",
        )->fetchAll();

        // Low stock items
        $lowStockItems = Database::query(
            "SELECT p.name, p.stock_qty, p.low_stock, c.name AS category
             FROM products p JOIN categories c ON c.id=p.category_id
             WHERE p.stock_qty <= p.low_stock AND p.is_active=1
             ORDER BY p.stock_qty ASC LIMIT 8"
        )->fetchAll();

        // Near expiry items
        $expiryItems = Database::query(
            "SELECT p.name, p.stock_qty, p.expiry_date,
                    DATEDIFF(p.expiry_date, CURDATE()) AS days_left
             FROM products p WHERE p.expiry_date IS NOT NULL
               AND p.expiry_date <= DATE_ADD(CURDATE(),INTERVAL 14 DAY)
               AND p.is_active=1
             ORDER BY p.expiry_date ASC LIMIT 8"
        )->fetchAll();

        echo json_encode([
            'success'       => true,
            'today'         => $todayStats,
            'month'         => $monthStats,
            'profit_today'  => $profitToday,
            'low_stock'     => $lowStock,
            'near_expiry'   => $nearExpiry,
            'cash_fund'     => $cashFund,
            'total_utang'   => $totalUtang,
            'hourly'        => $hourly,
            'payments'      => $payments,
            'top_products'  => $topProducts,
            'week_sales'    => $weekSales,
            'low_stock_items' => $lowStockItems,
            'expiry_items'  => $expiryItems,
        ]);
    }

    public static function run(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');

        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) {
            http_response_code(403);
            echo json_encode(['success'=>false,'message'=>'CSRF validation failed.']); return;
        }

        $dateFrom   = Security::sanitizeDate($_POST['date_from']   ?? date('Y-m-01'));
        $dateTo     = Security::sanitizeDate($_POST['date_to']     ?? date('Y-m-d'));
        $categoryId = Security::sanitizeInt($_POST['category_id']  ?? 0);
        $userId     = Security::sanitizeInt($_POST['user_id']      ?? 0);
        $groupBy    = Security::sanitizeString($_POST['group_by']  ?? 'day');

        $allowed = ['day','week','month','product','category','cashier','payment'];
        if (!in_array($groupBy, $allowed)) $groupBy = 'day';

        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("CALL sp_adhoc_report(?,?,?,?,?)");
        $stmt->execute([$dateFrom, $dateTo, $categoryId, $userId, $groupBy]);
        $rows = $stmt->fetchAll();

        // Summary KPIs
        $summary = Database::query(
            "SELECT COUNT(*) AS transactions,
                    COALESCE(SUM(grand_total),0) AS net_sales,
                    COALESCE(SUM(discount_amount),0) AS discounts,
                    COALESCE(AVG(grand_total),0) AS avg_txn
             FROM transactions WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        )->fetch();

        $profitSummary = Database::query(
            "SELECT COALESCE(SUM((ti.unit_price-ti.buy_price)*ti.quantity),0) AS profit
             FROM transaction_items ti JOIN transactions t ON t.id=ti.transaction_id
             WHERE t.status='completed' AND DATE(t.created_at) BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        )->fetchColumn();

        $expenses = Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        )->fetchColumn();

        echo json_encode([
            'success'   => true,
            'rows'      => $rows,
            'summary'   => $summary,
            'profit'    => $profitSummary,
            'expenses'  => $expenses,
            'net_profit'=> $profitSummary - $expenses,
            'meta'      => [
                'date_from'    => $dateFrom,
                'date_to'      => $dateTo,
                'group_by'     => $groupBy,
                'generated_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    public static function zreading(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $date = Security::sanitizeDate($_GET['date'] ?? date('Y-m-d'));

        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("CALL sp_zreading(?)");
        $stmt->execute([$date]);
        $summary = $stmt->fetch();

        $topItems = Database::query(
            "SELECT ti.product_name, SUM(ti.quantity) AS qty, SUM(ti.line_total) AS sales
             FROM transaction_items ti JOIN transactions t ON t.id=ti.transaction_id
             WHERE t.status='completed' AND DATE(t.created_at)=?
             GROUP BY ti.product_id, ti.product_name ORDER BY sales DESC LIMIT 10",
            [$date]
        )->fetchAll();

        $hourly = Database::query(
            "SELECT HOUR(created_at) AS hr, COUNT(*) AS cnt, SUM(grand_total) AS sales
             FROM transactions WHERE status='completed' AND DATE(created_at)=?
             GROUP BY HOUR(created_at) ORDER BY hr",
            [$date]
        )->fetchAll();

        $expenses = Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date=?",
            [$date]
        )->fetchColumn();

        $cashFund = Database::query(
            "SELECT balance FROM cash_fund WHERE fund_date=? ORDER BY id DESC LIMIT 1",
            [$date]
        )->fetchColumn();

        $profit = Database::query(
            "SELECT COALESCE(SUM((ti.unit_price-ti.buy_price)*ti.quantity),0)
             FROM transaction_items ti JOIN transactions t ON t.id=ti.transaction_id
             WHERE t.status='completed' AND DATE(t.created_at)=?",
            [$date]
        )->fetchColumn();

        echo json_encode([
            'success'   => true,
            'date'      => $date,
            'summary'   => $summary,
            'top_items' => $topItems,
            'hourly'    => $hourly,
            'expenses'  => $expenses,
            'cash_fund' => $cashFund,
            'profit'    => $profit,
            'net_profit'=> $profit - $expenses,
        ]);
    }

    public static function exportCsv(): void
    {
        Auth::requireAdmin();

        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); return; }

        $rows = json_decode($_POST['rows'] ?? '[]', true);
        if (!is_array($rows) || empty($rows)) { echo "No data"; return; }

        $filename = 'saripos_report_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($out, array_values($row));
        fclose($out);
        exit;
    }
}

// ── UserController ─────────────────────────────────────────
class UserController
{
    public static function list(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        $rows = Database::query("SELECT id,username,email,full_name,role,is_active,last_login,created_at FROM users ORDER BY id")->fetchAll();
        echo json_encode(['success'=>true,'data'=>$rows]);
    }

    public static function save(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed.']); return; }
        $id       = Security::sanitizeInt($_POST['id'] ?? 0);
        $username = Security::sanitizeString($_POST['username'] ?? '');
        $email    = Security::sanitizeString($_POST['email'] ?? '');
        $fullName = Security::sanitizeString($_POST['full_name'] ?? '');
        $role     = Security::sanitizeString($_POST['role'] ?? 'cashier');
        $password = $_POST['password'] ?? '';
        $isActive = Security::sanitizeInt($_POST['is_active'] ?? 1);
        if (empty($username)||empty($email)) { echo json_encode(['success'=>false,'message'=>'Username at email ay kinakailangan.']); return; }
        if (!Security::isEmail($email)) { echo json_encode(['success'=>false,'message'=>'Invalid email.']); return; }
        if (!in_array($role,['admin','cashier'])) { echo json_encode(['success'=>false,'message'=>'Invalid role.']); return; }
        if ($id > 0) {
            if (!empty($password)) {
                if (strlen($password) < 8) { echo json_encode(['success'=>false,'message'=>'Password: minimum 8 characters.']); return; }
                Database::query("UPDATE users SET username=?,email=?,full_name=?,role=?,password=?,is_active=? WHERE id=?",[$username,$email,$fullName,$role,Security::hashPassword($password),$isActive,$id]);
            } else {
                Database::query("UPDATE users SET username=?,email=?,full_name=?,role=?,is_active=? WHERE id=?",[$username,$email,$fullName,$role,$isActive,$id]);
            }
            echo json_encode(['success'=>true,'message'=>'User na-update.']);
        } else {
            if (empty($password)||strlen($password) < 8) { echo json_encode(['success'=>false,'message'=>'Password (min 8 chars) ay kinakailangan.']); return; }
            Database::query("INSERT INTO users (username,email,full_name,password,role) VALUES (?,?,?,?,?)",[$username,$email,$fullName,Security::hashPassword($password),$role]);
            echo json_encode(['success'=>true,'message'=>'User na-create.','id'=>Database::lastId()]);
        }
    }

    public static function delete(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed.']); return; }
        $id = Security::sanitizeInt($_POST['id'] ?? 0);
        if ($id === Auth::id()) { echo json_encode(['success'=>false,'message'=>'Hindi mabura ang sariling account.']); return; }
        Database::query("UPDATE users SET is_active=0 WHERE id=?",[$id]);
        echo json_encode(['success'=>true,'message'=>'User na-deactivate.']);
    }
}

// ── ExpenseController ──────────────────────────────────────
class ExpenseController
{
    public static function list(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $from = Security::sanitizeDate($_GET['date_from'] ?? date('Y-m-01'));
        $to   = Security::sanitizeDate($_GET['date_to']   ?? date('Y-m-d'));
        $rows = Database::query(
            "SELECT e.*, u.username FROM expenses e JOIN users u ON u.id=e.user_id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC",
            [$from, $to]
        )->fetchAll();
        echo json_encode(['success'=>true,'data'=>$rows]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed.']); return; }
        $id     = Security::sanitizeInt($_POST['id'] ?? 0);
        $cat    = Security::sanitizeString($_POST['category']    ?? 'General');
        $desc   = Security::sanitizeString($_POST['description'] ?? '');
        $amount = Security::sanitizeFloat($_POST['amount']       ?? 0);
        $date   = Security::sanitizeDate($_POST['expense_date']  ?? date('Y-m-d'));
        $notes  = Security::sanitizeString($_POST['notes']       ?? '');
        if (empty($desc)||$amount<=0) { echo json_encode(['success'=>false,'message'=>'Detalye at halaga ay kinakailangan.']); return; }
        $userId = Auth::id();
        if ($id > 0) {
            Database::query("UPDATE expenses SET category=?,description=?,amount=?,expense_date=?,notes=? WHERE id=?",[$cat,$desc,$amount,$date,$notes,$id]);
        } else {
            Database::query("INSERT INTO expenses (user_id,category,description,amount,expense_date,notes) VALUES (?,?,?,?,?,?)",[$userId,$cat,$desc,$amount,$date,$notes]);
        }
        echo json_encode(['success'=>true,'message'=>'Gastos na-save.']);
    }

    public static function delete(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed.']); return; }
        $id = Security::sanitizeInt($_POST['id'] ?? 0);
        Database::query("DELETE FROM expenses WHERE id=?",[$id]);
        echo json_encode(['success'=>true,'message'=>'Gastos na-delete.']);
    }
}

// ── CashFundController ─────────────────────────────────────
class CashFundController
{
    public static function get(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $row = Database::query("SELECT * FROM cash_fund ORDER BY id DESC LIMIT 1")->fetch();
        echo json_encode(['success'=>true,'data'=>$row]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed.']); return; }
        $type   = Security::sanitizeString($_POST['type']   ?? 'add');
        $amount = Security::sanitizeFloat($_POST['amount']  ?? 0);
        $notes  = Security::sanitizeString($_POST['notes']  ?? '');
        $userId = Auth::id();
        $current = Database::query("SELECT balance FROM cash_fund ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0;
        $newBal = in_array($type,['add','open']) ? $current + $amount : max(0, $current - $amount);
        Database::query("INSERT INTO cash_fund (user_id,type,amount,balance,notes,fund_date) VALUES (?,?,?,?,?,CURDATE())",[$userId,$type,$amount,$newBal,$notes]);
        (new PusherService())->trigger('dashboard','cash-updated',['balance'=>$newBal]);
        echo json_encode(['success'=>true,'message'=>"Cash fund updated: ₱{$newBal}",'balance'=>$newBal]);
    }
}
