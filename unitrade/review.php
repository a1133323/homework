<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? 0;
$msg = "";

// 1. 驗證這筆訂單是否存在，且目前登入的人必須是這筆訂單的買家或賣家
// ✅ 加上 items.id AS item_real_id 確保絕對不會跟 orders.id 混淆
$stmt = $pdo->prepare("SELECT orders.*, items.title, items.owner_id, items.id AS item_real_id FROM orders 
                        JOIN items ON orders.item_id = items.id 
                        WHERE orders.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order || ($order['buyer_id'] != $user_id && $order['owner_id'] != $user_id)) {
    echo "<h2>🚨 找不到該訂單或您無權存取此頁面。</h2>";
    echo "<a href='my_orders.php'>返回個人中心</a>";
    exit;
}

// 判定被評價的人（如果是買家進來，被評人就是賣家；反之亦然）
$reviewee_id = ($user_id == $order['buyer_id']) ? $order['owner_id'] : $order['buyer_id'];

// 2. 處理評價表單送出
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    if ($rating >= 1 && $rating <= 5) {
        try {
            $pdo->beginTransaction();

            // 寫入評價紀錄表 (reviews)
            $stmt_review = $pdo->prepare("INSERT INTO reviews (order_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt_review->execute([$order_id, $user_id, $reviewee_id, $rating, $comment]);

            // 更新訂單與物品狀態（如果是租賃，填入實際歸還日，並把商品放回 available）
            $stmt_order_update = $pdo->prepare("UPDATE orders SET actual_return_date = NOW(), payment_status = 'paid' WHERE id = ?");
            $stmt_order_update->execute([$order_id]);

            // ✅ 根據訂單類型決定物品新狀態：如果是租賃(rent)就回歸上架，如果是購買(buy)就設為已售出(sold)
            $new_item_status = 'sold';

            $stmt_item_update = $pdo->prepare("UPDATE items SET item_status = ? WHERE id = ?");
            $stmt_item_update->execute([$new_item_status, $order['item_real_id']]);

            // 【核心加分點】根據這筆評價，重新計算被評價者的全站平均信用分數 (credit_score)
            // 基礎分 100 分，拿 5 星不變、4 星不變，拿 1~3 星則扣分
            $stmt_avg = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE reviewee_id = ?");
            $stmt_avg->execute([$reviewee_id]);
            $avg_rating = $stmt_avg->fetchColumn();

            // 簡單公式：新信用分數 = 基礎 80 分 + (平均星等 * 4) -> 最低 80 分，最高 100 分
            $new_credit_score = intval(80 + ($avg_rating * 4));
            if ($new_credit_score > 100) $new_credit_score = 100;

            $stmt_user_update = $pdo->prepare("UPDATE users SET credit_score = ? WHERE id = ?");
            $stmt_user_update->execute([$new_credit_score, $reviewee_id]);

            $pdo->commit();
            echo "<script>alert('評價與歸還結案成功！感謝您的評分。'); window.location.href='my_orders.php';</script>";
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "處置失敗: " . $e->getMessage();
        }
    } else {
        $msg = "請選擇 1 至 5 星的評分！";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>UniTrade - 交易結案與信用評分</title>
</head>
<body>
    <h2>🤝 交易結案確認與雙方信用互評</h2>
    <p>您正在針對物品：<strong><?php echo htmlspecialchars($order['title']); ?></strong> 的交易進行結案評分。</p>
    <hr>

    <?php if(!empty($msg)) echo "<p style='color:red;'>$msg</p>"; ?>

    <form action="review.php?order_id=<?php echo $order_id; ?>" method="POST">
        <h3>1. 給予對方的交易評分 (必填)：</h3>
        <input type="radio" name="rating" value="5" checked> ⭐⭐⭐⭐⭐ 5分 (完美準時、物品完好)<br>
        <input type="radio" name="rating" value="4"> ⭐⭐⭐⭐ 4分 (良好)<br>
        <input type="radio" name="rating" value="3"> ⭐⭐⭐ 3分 (普通/有小遲到)<br>
        <input type="radio" name="rating" value="2"> ⭐⭐ 2分 (差勁/逾期或物品有小損壞)<br>
        <input type="radio" name="rating" value="1"> ⭐ 1分 (極糟/惡意破壞或逾期不還)<br><br>

        <h3>2. 填寫詳細交易評價：</h3>
        <textarea name="comment" rows="5" cols="50" placeholder="例如：買家非常準時，歸還時書籍也保護得很好，推薦！"></textarea><br><br>

        <button type="submit" style="background:green; color:white; padding:10px 20px; border:none; cursor:pointer;">
            確認歸還 / 完成交易並送出評價
        </button>
    </form>
    <br>
    <a href="my_orders.php">← 返回個人中心</a>
</body>
</html>