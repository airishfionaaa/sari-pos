-- ============================================================
--  SARI-POS Complete Database Schema
--  Version: 2.0 — Full Feature Set
--  Engine: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sari_pos`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sari_pos`;

-- ─────────────────────────────────────────────
--  USERS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(60)      NOT NULL,
  `email`      VARCHAR(120)     NOT NULL,
  `password`   VARCHAR(255)     NOT NULL COMMENT 'bcrypt/argon2 hash',
  `full_name`  VARCHAR(120)     DEFAULT NULL,
  `role`       ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
  `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
  `last_login` DATETIME         DEFAULT NULL,
  `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  CATEGORIES
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80)   NOT NULL,
  `color`      VARCHAR(7)    DEFAULT '#3b82f6',
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  PRODUCTS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED     NOT NULL,
  `barcode`     VARCHAR(60)      DEFAULT NULL,
  `name`        VARCHAR(150)     NOT NULL,
  `description` TEXT             DEFAULT NULL,
  `buy_price`   DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
  `sell_price`  DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
  `stock_qty`   INT              NOT NULL DEFAULT 0,
  `low_stock`   INT              NOT NULL DEFAULT 5,
  `expiry_date` DATE             DEFAULT NULL,
  `unit`        VARCHAR(30)      NOT NULL DEFAULT 'pc',
  `is_active`   TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_barcode` (`barcode`),
  KEY `fk_products_category` (`category_id`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  CUSTOMERS (for utang/credit tracking)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `customers` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120)  NOT NULL,
  `phone`       VARCHAR(20)   DEFAULT NULL,
  `address`     VARCHAR(255)  DEFAULT NULL,
  `credit_limit`DECIMAL(12,2) NOT NULL DEFAULT 500.00,
  `balance`     DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'current utang balance',
  `notes`       TEXT          DEFAULT NULL,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  TRANSACTIONS (header)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `reference_no`    VARCHAR(30)      NOT NULL,
  `user_id`         INT UNSIGNED     NOT NULL,
  `customer_id`     INT UNSIGNED     DEFAULT NULL,
  `subtotal`        DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
  `tax_amount`      DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
  `grand_total`     DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
  `amount_tendered` DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
  `change_due`      DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
  `payment_method`  ENUM('cash','gcash','maya','card','utang') NOT NULL DEFAULT 'cash',
  `status`          ENUM('pending','completed','void') NOT NULL DEFAULT 'pending',
  `notes`           TEXT             DEFAULT NULL,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transactions_ref` (`reference_no`),
  KEY `fk_transactions_user` (`user_id`),
  KEY `fk_transactions_customer` (`customer_id`),
  CONSTRAINT `fk_transactions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  TRANSACTION ITEMS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transaction_items` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `transaction_id` INT UNSIGNED  NOT NULL,
  `product_id`     INT UNSIGNED  NOT NULL,
  `product_name`   VARCHAR(150)  NOT NULL,
  `unit_price`     DECIMAL(12,2) NOT NULL,
  `buy_price`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `quantity`       INT           NOT NULL DEFAULT 1,
  `discount_pct`   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `line_total`     DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_items_transaction` (`transaction_id`),
  KEY `fk_items_product`     (`product_id`),
  CONSTRAINT `fk_items_transaction`
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  CREDIT LEDGER (utang records)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `credit_ledger` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `customer_id`    INT UNSIGNED  NOT NULL,
  `transaction_id` INT UNSIGNED  DEFAULT NULL,
  `type`           ENUM('charge','payment') NOT NULL,
  `amount`         DECIMAL(12,2) NOT NULL,
  `balance_after`  DECIMAL(12,2) NOT NULL,
  `notes`          TEXT          DEFAULT NULL,
  `user_id`        INT UNSIGNED  DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cl_customer` (`customer_id`),
  CONSTRAINT `fk_cl_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  STOCK MOVEMENTS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED  NOT NULL,
  `user_id`     INT UNSIGNED  DEFAULT NULL,
  `type`        ENUM('sale','restock','adjustment','void','expired') NOT NULL,
  `qty_change`  INT           NOT NULL,
  `qty_before`  INT           NOT NULL,
  `qty_after`   INT           NOT NULL,
  `reference`   VARCHAR(60)   DEFAULT NULL,
  `note`        TEXT          DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_sm_product` (`product_id`),
  CONSTRAINT `fk_sm_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  EXPENSES
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  NOT NULL,
  `category`    VARCHAR(80)   NOT NULL DEFAULT 'General',
  `description` VARCHAR(255)  NOT NULL,
  `amount`      DECIMAL(12,2) NOT NULL,
  `expense_date`DATE          NOT NULL,
  `notes`       TEXT          DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  CASH FUND (change fund / petty cash log)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cash_fund` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  NOT NULL,
  `type`        ENUM('open','add','remove','close') NOT NULL,
  `amount`      DECIMAL(12,2) NOT NULL,
  `balance`     DECIMAL(12,2) NOT NULL,
  `notes`       TEXT          DEFAULT NULL,
  `fund_date`   DATE          NOT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  CSRF TOKENS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `csrf_tokens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`      VARCHAR(64)  NOT NULL,
  `session_id` VARCHAR(128) NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_csrf_token` (`token`),
  KEY `idx_csrf_session`     (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
--  AUDIT LOG
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(80)  NOT NULL,
  `table_name` VARCHAR(60)  DEFAULT NULL,
  `record_id`  INT UNSIGNED DEFAULT NULL,
  `details`    TEXT         DEFAULT NULL,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- sp_complete_transaction: atomically complete a sale
DROP PROCEDURE IF EXISTS `sp_complete_transaction`$$
CREATE PROCEDURE `sp_complete_transaction`(
  IN  p_transaction_id INT UNSIGNED,
  IN  p_user_id        INT UNSIGNED,
  OUT p_result         TINYINT,
  OUT p_message        VARCHAR(255)
)
proc_block: BEGIN
  DECLARE done         INT DEFAULT FALSE;
  DECLARE v_product_id INT UNSIGNED;
  DECLARE v_qty        INT;
  DECLARE v_stock_now  INT;
  DECLARE v_prod_name  VARCHAR(150);
  DECLARE v_ref        VARCHAR(30);

  DECLARE cur CURSOR FOR
    SELECT ti.product_id, ti.quantity
    FROM   transaction_items ti
    WHERE  ti.transaction_id = p_transaction_id;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_result = 0;
    SET p_message = 'Transaction failed — rolled back.';
  END;

  START TRANSACTION;

  SELECT reference_no INTO v_ref FROM transactions WHERE id = p_transaction_id;

  IF (SELECT COUNT(*) FROM transactions WHERE id = p_transaction_id AND status = 'pending') = 0 THEN
    SET p_result = 0;
    SET p_message = 'Transaction not found or already processed.';
    ROLLBACK;
    LEAVE proc_block;
  END IF;

  OPEN cur;
  stock_loop: LOOP
    FETCH cur INTO v_product_id, v_qty;
    IF done THEN LEAVE stock_loop; END IF;

    SELECT stock_qty, name INTO v_stock_now, v_prod_name
    FROM products WHERE id = v_product_id FOR UPDATE;

    IF v_stock_now < v_qty THEN
      SET p_result = 0;
      SET p_message = CONCAT('Kulang na stock para sa: ', v_prod_name);
      CLOSE cur;
      ROLLBACK;
      LEAVE proc_block;
    END IF;

    UPDATE products SET stock_qty = stock_qty - v_qty WHERE id = v_product_id;

    INSERT INTO stock_movements (product_id, user_id, type, qty_change, qty_before, qty_after, reference)
    VALUES (v_product_id, p_user_id, 'sale', -v_qty, v_stock_now, v_stock_now - v_qty, v_ref);
  END LOOP;
  CLOSE cur;

  -- Handle utang: update customer balance
  UPDATE customers c
  JOIN transactions t ON t.customer_id = c.id
  SET c.balance = c.balance + t.grand_total
  WHERE t.id = p_transaction_id AND t.payment_method = 'utang';

  UPDATE transactions SET status = 'completed' WHERE id = p_transaction_id;

  COMMIT;
  SET p_result = 1;
  SET p_message = 'Transaction completed successfully.';
END proc_block $$

-- sp_void_transaction: void and restore stock
DROP PROCEDURE IF EXISTS `sp_void_transaction`$$
CREATE PROCEDURE `sp_void_transaction`(
  IN  p_transaction_id INT UNSIGNED,
  IN  p_user_id        INT UNSIGNED,
  OUT p_result         TINYINT,
  OUT p_message        VARCHAR(255)
)
proc_block: BEGIN
  DECLARE done         INT DEFAULT FALSE;
  DECLARE v_product_id INT UNSIGNED;
  DECLARE v_qty        INT;
  DECLARE v_stock_now  INT;
  DECLARE v_ref        VARCHAR(30);

  DECLARE cur CURSOR FOR
    SELECT ti.product_id, ti.quantity
    FROM   transaction_items ti
    WHERE  ti.transaction_id = p_transaction_id;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_result = 0;
    SET p_message = 'Void failed.';
  END;

  START TRANSACTION;

  IF (SELECT COUNT(*) FROM transactions WHERE id = p_transaction_id AND status = 'completed') = 0 THEN
    SET p_result = 0;
    SET p_message = 'Only completed transactions can be voided.';
    ROLLBACK;
    LEAVE proc_block;
  END IF;

  SELECT reference_no INTO v_ref FROM transactions WHERE id = p_transaction_id;

  OPEN cur;
  void_loop: LOOP
    FETCH cur INTO v_product_id, v_qty;
    IF done THEN LEAVE void_loop; END IF;

    SELECT stock_qty INTO v_stock_now FROM products WHERE id = v_product_id FOR UPDATE;
    UPDATE products SET stock_qty = stock_qty + v_qty WHERE id = v_product_id;

    INSERT INTO stock_movements (product_id, user_id, type, qty_change, qty_before, qty_after, reference)
    VALUES (v_product_id, p_user_id, 'void', v_qty, v_stock_now, v_stock_now + v_qty, v_ref);
  END LOOP;
  CLOSE cur;

  UPDATE transactions SET status = 'void' WHERE id = p_transaction_id;
  COMMIT;
  SET p_result = 1;
  SET p_message = 'Transaction voided successfully.';
END proc_block $$

-- sp_restock_product
DROP PROCEDURE IF EXISTS `sp_restock_product`$$
CREATE PROCEDURE `sp_restock_product`(
  IN p_product_id INT UNSIGNED,
  IN p_qty        INT,
  IN p_user_id    INT UNSIGNED,
  IN p_note       TEXT
)
BEGIN
  DECLARE v_before INT DEFAULT 0;
  SELECT stock_qty INTO v_before FROM products WHERE id = p_product_id;
  UPDATE products SET stock_qty = stock_qty + p_qty WHERE id = p_product_id;
  INSERT INTO stock_movements (product_id, user_id, type, qty_change, qty_before, qty_after, note)
  VALUES (p_product_id, p_user_id, 'restock', p_qty, v_before, v_before + p_qty, p_note);
  INSERT INTO audit_log (user_id, action, table_name, record_id, details)
  VALUES (p_user_id, 'RESTOCK', 'products', p_product_id, CONCAT('Restocked +', p_qty, ' units. Note: ', IFNULL(p_note,'')));
END $$

-- sp_record_payment: record utang payment
DROP PROCEDURE IF EXISTS `sp_record_payment`$$
CREATE PROCEDURE `sp_record_payment`(
  IN p_customer_id INT UNSIGNED,
  IN p_amount      DECIMAL(12,2),
  IN p_user_id     INT UNSIGNED,
  IN p_notes       TEXT,
  OUT p_result     TINYINT,
  OUT p_message    VARCHAR(255)
)
proc_block: BEGIN
  DECLARE v_balance DECIMAL(12,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_result = 0;
    SET p_message = 'Payment recording failed.';
  END;

  START TRANSACTION;
  SELECT balance INTO v_balance FROM customers WHERE id = p_customer_id FOR UPDATE;

  IF v_balance <= 0 THEN
    SET p_result = 0;
    SET p_message = 'Walang utang ang customer na ito.';
    ROLLBACK;
    LEAVE proc_block;
  END IF;

  IF p_amount > v_balance THEN SET p_amount = v_balance; END IF;

  UPDATE customers SET balance = balance - p_amount WHERE id = p_customer_id;

  INSERT INTO credit_ledger (customer_id, type, amount, balance_after, notes, user_id)
  VALUES (p_customer_id, 'payment', p_amount, v_balance - p_amount, p_notes, p_user_id);

  COMMIT;
  SET p_result = 1;
  SET p_message = CONCAT('Bayad na: ', FORMAT(p_amount,2));
END proc_block $$

-- sp_adhoc_report: flexible sales reporting
DROP PROCEDURE IF EXISTS `sp_adhoc_report`$$
CREATE PROCEDURE `sp_adhoc_report`(
  IN p_date_from   DATE,
  IN p_date_to     DATE,
  IN p_category_id INT,
  IN p_user_id_f   INT,
  IN p_group_by    VARCHAR(20)
)
BEGIN
  CASE p_group_by
    WHEN 'day' THEN
      SELECT DATE(t.created_at) AS period,
             COUNT(DISTINCT t.id)       AS transactions,
             SUM(ti.quantity)           AS units_sold,
             SUM(ti.line_total)         AS gross_sales,
             SUM((ti.unit_price - ti.buy_price)*ti.quantity) AS gross_profit,
             SUM(t.discount_amount)     AS discounts,
             SUM(t.grand_total)         AS net_sales
      FROM transactions t
      JOIN transaction_items ti ON ti.transaction_id = t.id
      WHERE t.status = 'completed'
        AND DATE(t.created_at) BETWEEN p_date_from AND p_date_to
        AND (p_user_id_f = 0 OR t.user_id = p_user_id_f)
        AND (p_category_id = 0 OR ti.product_id IN (SELECT id FROM products WHERE category_id = p_category_id))
      GROUP BY DATE(t.created_at) ORDER BY period;

    WHEN 'week' THEN
      SELECT YEARWEEK(t.created_at,1) AS period,
             MIN(DATE(t.created_at))  AS week_start,
             COUNT(DISTINCT t.id)     AS transactions,
             SUM(ti.quantity)         AS units_sold,
             SUM(t.grand_total)       AS net_sales,
             SUM((ti.unit_price - ti.buy_price)*ti.quantity) AS gross_profit
      FROM transactions t
      JOIN transaction_items ti ON ti.transaction_id = t.id
      WHERE t.status = 'completed'
        AND DATE(t.created_at) BETWEEN p_date_from AND p_date_to
      GROUP BY YEARWEEK(t.created_at,1) ORDER BY period;

    WHEN 'month' THEN
      SELECT DATE_FORMAT(t.created_at,'%Y-%m') AS period,
             COUNT(DISTINCT t.id)  AS transactions,
             SUM(ti.quantity)      AS units_sold,
             SUM(t.grand_total)    AS net_sales,
             SUM((ti.unit_price - ti.buy_price)*ti.quantity) AS gross_profit
      FROM transactions t
      JOIN transaction_items ti ON ti.transaction_id = t.id
      WHERE t.status = 'completed'
        AND DATE(t.created_at) BETWEEN p_date_from AND p_date_to
      GROUP BY DATE_FORMAT(t.created_at,'%Y-%m') ORDER BY period;

    WHEN 'product' THEN
      SELECT p.id, p.name AS product_name, c.name AS category,
             SUM(ti.quantity) AS units_sold,
             SUM(ti.line_total) AS gross_sales,
             SUM((ti.unit_price - ti.buy_price)*ti.quantity) AS gross_profit
      FROM transaction_items ti
      JOIN transactions t ON t.id = ti.transaction_id
      JOIN products p ON p.id = ti.product_id
      JOIN categories c ON c.id = p.category_id
      WHERE t.status = 'completed'
        AND DATE(t.created_at) BETWEEN p_date_from AND p_date_to
        AND (p_category_id = 0 OR p.category_id = p_category_id)
      GROUP BY p.id, p.name, c.name ORDER BY gross_sales DESC;

    WHEN 'category' THEN
      SELECT c.id, c.name AS category,
             SUM(ti.quantity) AS units_sold,
             SUM(ti.line_total) AS gross_sales,
             SUM((ti.unit_price - ti.buy_price)*ti.quantity) AS gross_profit
      FROM transaction_items ti
      JOIN transactions t ON t.id = ti.transaction_id
      JOIN products p ON p.id = ti.product_id
      JOIN categories c ON c.id = p.category_id
      WHERE t.status = 'completed'
        AND DATE(t.created_at) BETWEEN p_date_from AND p_date_to
      GROUP BY c.id, c.name ORDER BY gross_sales DESC;

    WHEN 'cashier' THEN
      SELECT u.id, u.username AS cashier, u.full_name,
             COUNT(DISTINCT t.id) AS transactions,
             SUM(t.grand_total)   AS net_sales
      FROM transactions t JOIN users u ON u.id = t.user_id
      WHERE t.status = 'completed'
        AND DATE(t.created_at) BETWEEN p_date_from AND p_date_to
      GROUP BY u.id, u.username, u.full_name ORDER BY net_sales DESC;

    WHEN 'payment' THEN
      SELECT t.payment_method,
             COUNT(*) AS transactions,
             SUM(t.grand_total) AS total_amount
      FROM transactions t
      WHERE t.status = 'completed'
        AND DATE(t.created_at) BETWEEN p_date_from AND p_date_to
      GROUP BY t.payment_method ORDER BY total_amount DESC;

    ELSE
      SELECT DATE(t.created_at) AS period, SUM(t.grand_total) AS net_sales
      FROM transactions t
      WHERE t.status = 'completed'
        AND DATE(t.created_at) BETWEEN p_date_from AND p_date_to
      GROUP BY DATE(t.created_at) ORDER BY period;
  END CASE;
END $$

-- sp_zreading: end-of-day Z-reading
DROP PROCEDURE IF EXISTS `sp_zreading`$$
CREATE PROCEDURE `sp_zreading`(IN p_date DATE)
BEGIN
  SELECT
    COUNT(CASE WHEN status='completed' THEN 1 END) AS total_transactions,
    COUNT(CASE WHEN status='void' THEN 1 END)      AS void_transactions,
    COALESCE(SUM(CASE WHEN status='completed' THEN subtotal END),0)        AS gross_sales,
    COALESCE(SUM(CASE WHEN status='completed' THEN discount_amount END),0) AS total_discounts,
    COALESCE(SUM(CASE WHEN status='completed' THEN grand_total END),0)     AS net_sales,
    COALESCE(SUM(CASE WHEN status='completed' AND payment_method='cash'   THEN grand_total END),0) AS cash_sales,
    COALESCE(SUM(CASE WHEN status='completed' AND payment_method='gcash'  THEN grand_total END),0) AS gcash_sales,
    COALESCE(SUM(CASE WHEN status='completed' AND payment_method='maya'   THEN grand_total END),0) AS maya_sales,
    COALESCE(SUM(CASE WHEN status='completed' AND payment_method='card'   THEN grand_total END),0) AS card_sales,
    COALESCE(SUM(CASE WHEN status='completed' AND payment_method='utang'  THEN grand_total END),0) AS utang_sales,
    COALESCE(AVG(CASE WHEN status='completed' THEN grand_total END),0)     AS avg_transaction,
    COUNT(DISTINCT user_id) AS cashier_count
  FROM transactions
  WHERE DATE(created_at) = p_date;
END $$

DELIMITER ;

-- ============================================================
--  TRIGGERS
-- ============================================================

DELIMITER $$

-- Trigger 1: Auto-generate reference_no
DROP TRIGGER IF EXISTS `trg_before_insert_transaction`$$
CREATE TRIGGER `trg_before_insert_transaction`
BEFORE INSERT ON `transactions`
FOR EACH ROW
BEGIN
  IF NEW.reference_no = '' OR NEW.reference_no IS NULL THEN
    SET NEW.reference_no = CONCAT('TXN-', DATE_FORMAT(NOW(),'%Y%m%d'), '-', LPAD(FLOOR(RAND()*99999),5,'0'));
  END IF;
END $$

-- Trigger 2: Audit stock changes
DROP TRIGGER IF EXISTS `trg_after_update_product`$$
CREATE TRIGGER `trg_after_update_product`
AFTER UPDATE ON `products`
FOR EACH ROW
BEGIN
  IF OLD.stock_qty <> NEW.stock_qty THEN
    INSERT INTO audit_log (action, table_name, record_id, details)
    VALUES (
      'STOCK_CHANGE', 'products', NEW.id,
      CONCAT('Stock: ', OLD.stock_qty, ' → ', NEW.stock_qty, ' | Product: ', NEW.name)
    );
  END IF;
END $$

-- Trigger 3: Block hard delete of products with sales history
DROP TRIGGER IF EXISTS `trg_before_delete_product`$$
CREATE TRIGGER `trg_before_delete_product`
BEFORE DELETE ON `products`
FOR EACH ROW
BEGIN
  DECLARE cnt INT;
  SELECT COUNT(*) INTO cnt FROM transaction_items ti
  JOIN transactions t ON t.id = ti.transaction_id
  WHERE ti.product_id = OLD.id AND t.status = 'completed';
  IF cnt > 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Hindi mabura: may kasaysayan ng benta ang produktong ito.';
  END IF;
END $$

-- Trigger 4: Update transaction timestamp when items added
DROP TRIGGER IF EXISTS `trg_after_insert_txn_item`$$
CREATE TRIGGER `trg_after_insert_txn_item`
AFTER INSERT ON `transaction_items`
FOR EACH ROW
BEGIN
  UPDATE transactions SET updated_at = NOW() WHERE id = NEW.transaction_id;
END $$

-- Trigger 5: Update customer balance on utang transaction
DROP TRIGGER IF EXISTS `trg_after_update_transaction`$$
CREATE TRIGGER `trg_after_update_transaction`
AFTER UPDATE ON `transactions`
FOR EACH ROW
BEGIN
  IF OLD.status = 'completed' AND NEW.status = 'void' AND OLD.payment_method = 'utang' AND OLD.customer_id IS NOT NULL THEN
    UPDATE customers SET balance = balance - OLD.grand_total WHERE id = OLD.customer_id;
    INSERT INTO audit_log (action, table_name, record_id, details)
    VALUES ('UTANG_VOID', 'transactions', OLD.id, CONCAT('Void utang ₱', OLD.grand_total, ' for customer_id=', OLD.customer_id));
  END IF;
END $$

DELIMITER ;

-- ============================================================
--  SEED DATA
-- ============================================================

-- Admin user: password = Admin@123
INSERT IGNORE INTO users (username, email, password, full_name, role)
VALUES ('admin', 'admin@saripos.local',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Store Admin', 'admin');

-- Cashier user: password = Cashier@123
INSERT IGNORE INTO users (username, email, password, full_name, role)
VALUES ('cashier1', 'cashier@saripos.local',
        '$2y$12$6gMSs1kOZNH6aHJnGF2k4.1pN8Q3xgJQlgZ5BH1iGJi8ZCH3Jua.G',
        'Juan Dela Cruz', 'cashier');

-- Categories
INSERT IGNORE INTO categories (name, color) VALUES
('Inumin',       '#3b82f6'),
('Meryenda',     '#f59e0b'),
('Canned Goods', '#ef4444'),
('Personal Care','#8b5cf6'),
('Household',    '#06b6d4'),
('Condiments',   '#84cc16'),
('Dairy',        '#f97316'),
('Tobacco',      '#6b7280'),
('Others',       '#64748b');

-- Products
INSERT IGNORE INTO products (category_id, barcode, name, buy_price, sell_price, stock_qty, low_stock, unit, expiry_date) VALUES
(1,'4800061094113','Coca-Cola 355ml',      18.00, 22.00, 50, 10, 'pc',  '2025-12-31'),
(1,'4800092393291','Nestea Iced Tea 500ml',20.00, 25.00, 40, 10, 'pc',  '2025-11-30'),
(1,'4800024130017','Bear Brand Milk 300g', 68.00, 85.00, 15,  3, 'pc',  '2025-10-15'),
(2,'4800016070029','Nova Multigrain 78g',  14.00, 18.00, 30,  5, 'pc',  '2025-09-30'),
(2,'4800016162015','Piattos Cheese 85g',   22.00, 28.00, 25,  5, 'pc',  '2025-09-30'),
(3,'4806518320065','Lucky Me Pancit Canton',8.50, 11.00, 60, 10, 'pc',  '2026-06-01'),
(3,'4806518320066','Argentina Corned Beef',32.00, 40.00,  4,  5, 'pc',  '2026-01-01'),
(4,'4800193490074','Safeguard Bar Soap',   18.00, 23.00, 35,  5, 'pc',  NULL),
(5,'4800167100174','Joy Dishwashing 250ml',28.00, 35.00,  3,  5, 'btl', NULL),
(6,'4800459405015','Datu Puti Vinegar',    16.00, 20.00,  2,  5, 'btl', '2025-08-15');

-- Sample customers
INSERT IGNORE INTO customers (name, phone, address, credit_limit) VALUES
('Maria Santos',   '09171234567', 'Brgy. San Roque', 500.00),
('Juan Dela Cruz', '09281234567', 'Brgy. Sta. Cruz',1000.00),
('Elsa Reyes',     '09391234567', 'Brgy. Poblacion', 300.00);

-- Initial cash fund
INSERT IGNORE INTO cash_fund (user_id, type, amount, balance, notes, fund_date)
VALUES (1, 'open', 500.00, 500.00, 'Opening fund', CURDATE());
