<?php
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: auth.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];
$item_ids = $_POST['item_ids'] ?? [];
$start_dates = $_POST['start_date'] ?? [];
$end_dates = $_POST['end_date'] ?? [];
$total_prices = $_POST['total_price'] ?? [];

if (!empty($item_ids)) {
    try {
        // 開啟 MySQL 交易機制，確保所有資料同時寫入成功，若有一筆失敗則全部撤回
        $pdo->beginTransaction();

        foreach ($item_ids as $item_id) {
            // 先查詢該物品目前的類型
            $stmtItem = $pdo->prepare("SELECT type FROM items WHERE id = ?");
            $stmtItem->execute([$item_id]);
            $itemInfo = $stmtItem->fetch();
            
            $order_type = $itemInfo['type'];
            $start_date = isset($start_dates[$item_id]) ? $start_dates[$item_id] : null;
            $end_date = isset($end_dates[$item_id]) ? $end_dates[$item_id] : null;
            $total_price = isset($total_prices[$item_id]) ? floatval($total_prices[$item_id]) : 0;

            // 1. 寫入 orders 資料表
            $sqlOrder = "INSERT INTO orders (item_id, buyer_id, order_type, start_date, end_date, total_price, payment_status) 
                         VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $stmtOrder = $pdo->prepare($sqlOrder);
            $stmtOrder->execute([$item_id, $buyer_id, $order_type, $start_date, $end_date, $total_price]);

            // 2. 更新 items 狀態為 reserved (已被預約)
            $stmtUpdateItem = $pdo->prepare("UPDATE items SET item_status = 'reserved' WHERE id = ?");
            $stmtUpdateItem->execute([$item_id]);

            // 3. 從購物車刪除
            $stmtDeleteCart = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND item_id = ?");
            $stmtDeleteCart = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND item_id = ?");
            $stmtDeleteCart->execute([$buyer_id, $item_id]);
        }

        // 提交交易
        $pdo->commit();
        echo "<script>alert('交易申請已送出！請至個人中心確認面交資訊。'); window.location.href='my_orders.php';</script>";
        exit;

    } catch (Exception $e) {
        // 發生錯誤，復原所有變更
        $pdo->rollBack();
        echo "結帳失敗: " . $e->getMessage();
    }
} else {
    header("Location: view_cart.php");
    exit;
}