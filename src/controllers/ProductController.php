<?php
class ProductController
{
    public static function list(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');

        $search = Security::sanitizeString($_GET['q'] ?? '');
        $catId  = Security::sanitizeInt($_GET['category_id'] ?? 0);
        $lowOnly= Security::sanitizeInt($_GET['low_only'] ?? 0);
        $expiry = Security::sanitizeInt($_GET['expiry_days'] ?? 0);

        $sql = "SELECT p.id, p.barcode, p.name, p.description, p.buy_price, p.sell_price,
                       p.stock_qty, p.low_stock, p.expiry_date, p.unit, p.is_active,
                       c.name AS category, c.id AS category_id, c.color AS cat_color
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR c.name LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($catId > 0) {
            $sql .= " AND p.category_id = ?";
            $params[] = $catId;
        }
        if ($lowOnly) {
            $sql .= " AND p.stock_qty <= p.low_stock AND p.is_active = 1";
        } else {
            $sql .= " AND p.is_active = 1";
        }
        if ($expiry > 0) {
            $sql .= " AND p.expiry_date IS NOT NULL AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
            $params[] = $expiry;
        }
        $sql .= " ORDER BY p.name LIMIT 500";

        $products = Database::query($sql, $params)->fetchAll();
        echo json_encode(['success' => true, 'data' => $products]);
    }

    public static function save(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');

        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF validation failed.']);
            return;
        }

        $id          = Security::sanitizeInt($_POST['id'] ?? 0);
        $categoryId  = Security::sanitizeInt($_POST['category_id'] ?? 0);
        $barcode     = Security::sanitizeString($_POST['barcode'] ?? '');
        $name        = Security::sanitizeString($_POST['name'] ?? '');
        $description = Security::sanitizeString($_POST['description'] ?? '');
        $buyPrice    = Security::sanitizeFloat($_POST['buy_price'] ?? 0);
        $sellPrice   = Security::sanitizeFloat($_POST['sell_price'] ?? 0);
        $stockQty    = Security::sanitizeInt($_POST['stock_qty'] ?? 0);
        $lowStock    = Security::sanitizeInt($_POST['low_stock'] ?? 5);
        $unit        = Security::sanitizeString($_POST['unit'] ?? 'pc');
        $expiryDate  = Security::sanitizeString($_POST['expiry_date'] ?? '');

        if (empty($name))       { echo json_encode(['success'=>false,'message'=>'Pangalan ng produkto ay kinakailangan.']); return; }
        if ($categoryId <= 0)   { echo json_encode(['success'=>false,'message'=>'Kategorya ay kinakailangan.']); return; }
        if ($sellPrice <= 0)    { echo json_encode(['success'=>false,'message'=>'Presyo ay dapat higit sa 0.']); return; }

        $expiry = (!empty($expiryDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) ? $expiryDate : null;

        if ($id > 0) {
            Database::query(
                "UPDATE products SET category_id=?,barcode=?,name=?,description=?,buy_price=?,sell_price=?,low_stock=?,unit=?,expiry_date=? WHERE id=?",
                [$categoryId, $barcode ?: null, $name, $description, $buyPrice, $sellPrice, $lowStock, $unit, $expiry, $id]
            );
            $msg = 'Produkto na-update.';
        } else {
            Database::query(
                "INSERT INTO products (category_id,barcode,name,description,buy_price,sell_price,stock_qty,low_stock,unit,expiry_date) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$categoryId, $barcode ?: null, $name, $description, $buyPrice, $sellPrice, $stockQty, $lowStock, $unit, $expiry]
            );
            $id = (int)Database::lastId();
            $msg = 'Produkto na-add.';
        }

        (new PusherService())->trigger('inventory', 'product-updated', ['product_id' => $id]);
        echo json_encode(['success' => true, 'message' => $msg, 'id' => $id]);
    }

    public static function delete(): void
    {
        Auth::requireAdmin();
        header('Content-Type: application/json');

        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF validation failed.']);
            return;
        }

        $id = Security::sanitizeInt($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); return; }

        try {
            Database::query("UPDATE products SET is_active=0 WHERE id=?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Produkto na-deactivate.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => Security::e($e->getMessage())]);
        }
    }

    public static function restock(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');

        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF validation failed.']);
            return;
        }

        $productId = Security::sanitizeInt($_POST['product_id'] ?? 0);
        $qty       = Security::sanitizeInt($_POST['qty'] ?? 0);
        $note      = Security::sanitizeString($_POST['note'] ?? '');
        $userId    = Auth::id();

        if ($qty <= 0) { echo json_encode(['success'=>false,'message'=>'Qty ay dapat higit sa 0.']); return; }
        if ($productId <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid product.']); return; }

        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("CALL sp_restock_product(?,?,?,?)");
        $stmt->execute([$productId, $qty, $userId, $note]);

        (new PusherService())->trigger('inventory', 'stock-updated', ['product_id' => $productId, 'added' => $qty]);

        echo json_encode(['success' => true, 'message' => "Nadagdag: +{$qty} units."]);
    }
}
