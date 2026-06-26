<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// 1. 檢查使用者有沒有登入（沒登入不能用購物車）
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('請先登入會員，才能使用購物車功能！'); location.href='auth.php';</script>";
    exit;
}

// 2. 拿到從 item_detail.php 傳過來的商品 ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $item_id = intval($_POST['item_id']);

    // 3. 先檢查資料庫有沒有這個商品，順便撈出狀態
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();

    if (!$item) {
        echo "<script>alert('找不到該商品或已下架！'); location.href='index.php';</script>";
        exit;
    }

    // 4. 初始化 Session 購物車（如果還不存在，就開一個空陣列）
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // 5. 檢查購物車是不是早就放過這個東西了（因為校園二生物品通常只有「一件」）
    if (in_array($item_id, $_SESSION['cart'])) {
        echo "<script>alert('這件商品已經在您的購物車中囉！'); location.href='cart.php';</script>";
        exit;
    }

    // 6. 成功塞進購物車 Session 陣列中！
    $_SESSION['cart'][] = $item_id;

    echo "<script>alert('成功加入購物車！'); location.href='cart.php';</script>";
    exit;

} else {
    // 如果不是用 POST 來或者是亂點進來的，退回首頁
    header("Location: index.php");
    exit;
}