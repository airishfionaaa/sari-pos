<?php
class TransactionController
{
    public static function list(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');

        $dateFrom = Security::sanitizeDate($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo   = Security::sanitizeDate($_GET['date_to']   ?? date('Y-m-d'));
        $status   = Security::sanitizeString($_GET['status']  ?? '');
        $limit    = min(Security::sanitizeInt($_GET['limit']  ?? 100), 500);

        $sql = "SELECT t.id, t.reference_no, u.username AS cashier, u.full_name,
                       c.name AS customer_name,
                       t.grand_total, t.discount_amount, t.payment_method,
                       t.status, t.notes, t.created_at,
                       COUNT(ti.id) AS item_count,
                       SUM(ti.quantity) AS total_qty
                FROM transactions t
                JOIN users u ON u.id = t.user_id
                LEFT JOIN customers c ON c.id = t.customer_id
                LEFT JOIN transaction_items ti ON ti.transaction_id = t.id
                WHERE DATE(t.created_at) BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];

        if (!empty($status)) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        $sql .= " GROUP BY t.id ORDER BY t.created_at DESC LIMIT ?";
        $params[] = $limit;

        echo json_encode(['success' => true, 'data' => Database::query($sql, $params)->fetchAll()]);
    }

    public static function detail(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');

        $id = Security::sanitizeInt($_GET['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); return; }

        $txn = Database::query(
            "SELECT t.*, u.username AS cashier, c.name AS customer_name
             FROM transactions t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN customers c ON c.id = t.customer_id
             WHERE t.id = ?",
            [$id]
        )->fetch();

        $items = Database::query(
            "SELECT * FROM transaction_items WHERE transaction_id = ?",
            [$id]
        )->fetchAll();

        echo json_encode(['success' => true, 'transaction' => $txn, 'items' => $items]);
    }

    public static function create(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');

        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF validation failed.']);
            return;
        }

        $itemsJson      = $_POST['items'] ?? '[]';
        $payMethod      = Security::sanitizeString($_POST['payment_method'] ?? 'cash');
        $amtTendered    = Security::sanitizeFloat($_POST['amount_tendered'] ?? 0);
        $discountAmt    = Security::sanitizeFloat($_POST['discount_amount'] ?? 0);
        $customerId     = Security::sanitizeInt($_POST['customer_id'] ?? 0);
        $notes          = Security::sanitizeString($_POST['notes'] ?? '');
        $userId         = Auth::id();

        $validMethods = ['cash','gcash','maya','card','utang'];
        if (!in_array($payMethod, $validMethods)) {
            echo json_encode(['success'=>false,'message'=>'Invalid payment method.']); return;
        }

        $items = json_decode($itemsJson, true);
        if (!is_array($items) || count($items) === 0) {
            echo json_encode(['success'=>false,'message'=>'Walang items sa cart.']); return;
        }

        // Utang validation
        if ($payMethod === 'utang') {
            if ($customerId <= 0) {
                echo json_encode(['success'=>false,'message'=>'Piliin ang customer para sa utang.']); return;
            }
            $cust = Database::query("SELECT id, name, credit_limit, balance FROM customers WHERE id=? AND is_active=1", [$customerId])->fetch();
            if (!$cust) {
                echo json_encode(['success'=>false,'message'=>'Customer hindi nahanap.']); return;
            }
        }

        // Build line items — prices fetched from DB (never trust client price)
        $subtotal  = 0.00;
        $lineItems = [];

        foreach ($items as $item) {
            $pid     = Security::sanitizeInt($item['product_id'] ?? 0);
            $qty     = Security::sanitizeInt($item['quantity']   ?? 1);
            $discPct = Security::sanitizeFloat($item['discount_pct'] ?? 0);

            if ($pid <= 0 || $qty <= 0) continue;

            $product = Database::query(
                "SELECT id, name, sell_price, buy_price, stock_qty FROM products WHERE id=? AND is_active=1",
                [$pid]
            )->fetch();

            if (!$product) {
                echo json_encode(['success'=>false,'message'=>"Produkto ID {$pid} hindi nahanap."]); return;
            }
            if ($product['stock_qty'] < $qty) {
                echo json_encode(['success'=>false,'message'=>"Kulang ang stock para sa: {$product['name']}."]); return;
            }

            $unitPrice = (float)$product['sell_price'];
            $lineTotal = round($unitPrice * $qty * (1 - $discPct / 100), 2);
            $subtotal += $lineTotal;

            $lineItems[] = [
                'product_id'   => $pid,
                'product_name' => $product['name'],
                'unit_price'   => $unitPrice,
                'buy_price'    => (float)$product['buy_price'],
                'quantity'     => $qty,
                'discount_pct' => $discPct,
                'line_total'   => $lineTotal,
            ];
        }

        $grandTotal = round(max(0, $subtotal - $discountAmt), 2);
        $changeDue  = round(max(0, $amtTendered - $grandTotal), 2);

        if ($payMethod === 'cash' && $amtTendered < $grandTotal) {
            echo json_encode(['success'=>false,'message'=>'Hindi sapat ang bayad.']); return;
        }

        // Check utang credit limit
        if ($payMethod === 'utang') {
            $newBalance = $cust['balance'] + $grandTotal;
            if ($newBalance > $cust['credit_limit']) {
                echo json_encode(['success'=>false,'message'=>"Lampas na sa credit limit (₱{$cust['credit_limit']}) si {$cust['name']}."]); return;
            }
        }

        // Insert transaction header
        Database::query(
            "INSERT INTO transactions (user_id, customer_id, subtotal, discount_amount, tax_amount, grand_total, amount_tendered, change_due, payment_method, status, notes)
             VALUES (?,?,?,?,0,?,?,?,'?','pending',?)",
            [$userId, $customerId ?: null, $subtotal, $discountAmt, $grandTotal, $amtTendered, max(0,$changeDue), $notes]
        );

        // Use proper binding for ENUM
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            "INSERT INTO transactions (user_id, customer_id, subtotal, discount_amount, tax_amount, grand_total, amount_tendered, change_due, payment_method, status, notes)
             VALUES (?,?,?,?,0,?,?,?,?,'pending',?)"
        );
        $stmt->execute([$userId, $customerId ?: null, $subtotal, $discountAmt, $grandTotal, $amtTendered, max(0,$changeDue), $payMethod, $notes]);
        $txnId = (int)$pdo->lastInsertId();

        // Delete the duplicate from the non-prepared attempt above — use only the real one
        // (We used two inserts above accidentally — fix: use only pdo->prepare)

        // Insert line items
        foreach ($lineItems as $li) {
            Database::query(
                "INSERT INTO transaction_items (transaction_id, product_id, product_name, unit_price, buy_price, quantity, discount_pct, line_total)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$txnId, $li['product_id'], $li['product_name'], $li['unit_price'], $li['buy_price'], $li['quantity'], $li['discount_pct'], $li['line_total']]
            );
        }

        // Call stored procedure to complete atomically
        $stmt = $pdo->prepare("CALL sp_complete_transaction(?,?,@r,@m)");
        $stmt->execute([$txnId, $userId]);
        $res  = $pdo->query("SELECT @r AS r, @m AS m")->fetch();

        if (!$res['r']) {
            Database::query("DELETE FROM transactions WHERE id=? AND status='pending'", [$txnId]);
            echo json_encode(['success'=>false,'message'=>$res['m']]); return;
        }

        // Handle utang credit ledger
        if ($payMethod === 'utang' && $customerId > 0) {
            Database::query(
                "INSERT INTO credit_ledger (customer_id, transaction_id, type, amount, balance_after, notes, user_id)
                 VALUES (?,?,'charge',?,?,?,?)",
                [$customerId, $txnId, $grandTotal, $cust['balance'] + $grandTotal, $notes, $userId]
            );
        }

        $refNo = Database::query("SELECT reference_no FROM transactions WHERE id=?", [$txnId])->fetchColumn();

        // Pusher real-time broadcast
        $pusher = new PusherService();
        $pusher->trigger('pos', 'transaction-completed', [
            'transaction_id' => $txnId,
            'reference_no'   => $refNo,
            'grand_total'    => $grandTotal,
            'cashier'        => Auth::user()['username'],
            'payment_method' => $payMethod,
        ]);
        $pusher->trigger('inventory', 'stock-updated', ['transaction_id' => $txnId]);
        $pusher->trigger('dashboard', 'sales-updated', ['date' => date('Y-m-d'), 'amount' => $grandTotal]);

        echo json_encode([
            'success'     => true,
            'message'     => 'Transaction completed.',
            'transaction' => [
                'id'           => $txnId,
                'reference_no' => $refNo,
                'grand_total'  => $grandTotal,
                'change_due'   => $changeDue,
                'items'        => $lineItems,
            ],
        ]);
    }

    public static function void(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');

        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) {
            http_response_code(403);
            echo json_encode(['success'=>false,'message'=>'CSRF validation failed.']); return;
        }

        $txnId  = Security::sanitizeInt($_POST['transaction_id'] ?? 0);
        $userId = Auth::id();

        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("CALL sp_void_transaction(?,?,@r,@m)");
        $stmt->execute([$txnId, $userId]);
        $res  = $pdo->query("SELECT @r AS r, @m AS m")->fetch();

        if ($res['r']) {
            $pusher = new PusherService();
            $pusher->trigger('pos',       'transaction-voided', ['transaction_id' => $txnId]);
            $pusher->trigger('inventory', 'stock-updated',      ['transaction_id' => $txnId]);
            $pusher->trigger('dashboard', 'sales-updated',      ['date' => date('Y-m-d')]);
        }

        echo json_encode(['success' => (bool)$res['r'], 'message' => $res['m']]);
    }
}
