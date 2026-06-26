<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_items = [];

// 1. 只要購物車 Session 裡有東西，就撈出商品詳情
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $sql = "SELECT items.*, users.username AS seller_name 
            FROM items 
            JOIN users ON items.owner_id = users.id 
            WHERE items.id IN ($placeholders) AND items.item_status = 'available'";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($_SESSION['cart']);
    $cart_items = $stmt->fetchAll();
}

// 2. 【移除功能】處理從購物車中刪除單項商品
if (isset($_GET['action']) && $_GET['action'] === 'remove') {
    $remove_id = intval($_GET['item_id']);
    if (($key = array_search($remove_id, $_SESSION['cart'])) !== false) {
        unset($_SESSION['cart'][$key]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // 重新索引
    }
    header("Location: cart.php");
    exit;
}

// 3. 【分開結帳功能】處理單件商品的預約與結帳
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_checkout'])) {
    $item_id = intval($_POST['item_id']);
    $item_type = $_POST['item_type'];
    
    // 預設日期與金額
    $start_date = null;
    $end_date = null;
    $total_price = floatval($_POST['base_price']); // 預設為單價（買賣模式）

    // 如果是租賃物品，由前端表單傳入使用者選擇的起訖時間，並動態計算天數金額
    if ($item_type === 'rent') {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        if (empty($start_date) || empty($end_date)) {
            echo "<script>alert('請選擇租賃的起始與結束時間！'); history.back();</script>";
            exit;
        }
        if ($start_date > $end_date) {
            echo "<script>alert('起始日期不能大於結束日期！'); history.back();</script>";
            exit;
        }
        
        // 根據起訖日期計算天數（同一天還算 1 天，隔天還算 2 天，以此類推）
        $days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
        $total_price = floatval($_POST['base_price']) * $days;
    }

    $pdo->beginTransaction();
    try {
        // 3-1. 寫入訂單資料表 (orders)
        $sql_order = "INSERT INTO orders (item_id, buyer_id, order_type, start_date, end_date, total_price, payment_status) 
                      VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute([$item_id, $user_id, $item_type, $start_date, $end_date, $total_price]);

        // 3-2. 更新該物品狀態，從 available 變更為 renting 或 sold，防止重複下單
        $new_status = ($item_type === 'rent') ? 'renting' : 'sold';
        $sql_update_item = "UPDATE items SET item_status = ? WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update_item);
        $stmt_update->execute([$new_status, $item_id]);

        // 3-3. 結帳成功後，單獨把這件商品移出購物車 Session
        if (($key = array_search($item_id, $_SESSION['cart'])) !== false) {
            unset($_SESSION['cart'][$key]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }

        $pdo->commit();
        echo "<script>alert('🎉 該物品預約成功！已為您生成獨立訂單，請至個人中心追蹤面交資訊！'); location.href='my_orders.php';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('結帳失敗，錯誤原因：" . $e->getMessage() . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>我的購物車 - UniTrade 校園易</title>
    <style>
        body { font-family: 'Microsoft JhengHei', sans-serif; background: #f7fafc; padding: 40px; margin: 0; }
        .cart-container { max-width: 950px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .cart-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .cart-table th, .cart-table td { padding: 15px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .cart-table th { background: #edf2f7; color: #4a5568; font-weight: bold; }
        .btn-delete { color: #e53e3e; text-decoration: none; font-weight: bold; font-size: 14px; }
        .btn-delete:hover { text-decoration: underline; }
        .btn-single-checkout { background: #3182ce; color: white; border: none; padding: 8px 16px; font-size: 14px; font-weight: bold; border-radius: 4px; cursor: pointer; }
        .btn-single-checkout:hover { background: #2b6cb0; }
        .date-input { padding: 5px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 13px; }
        .price-badge { color: #e53e3e; font-weight: bold; font-size: 16px; }
    </style>
</head>
<body>

<div class="cart-container">
    <h2>🛒 您的校園預約購物車</h2>
    <p><a href="index.php" style="color: #3182ce; text-decoration: none; font-weight: bold;">← 繼續尋寶（回首頁）</a></p>

    <?php if (empty($cart_items)): ?>
        <div style="text-align: center; padding: 50px; color: #a0aec0;">
            <p style="font-size: 60px; margin: 0;">🛒</p>
            <p style="font-size: 16px; margin-top: 10px;">購物車空空如也，快去商品詳情頁面加入吧！</p>
        </div>
    <?php else: ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>商品圖片</th>
                    <th>商品名稱</th>
                    <th>賣家</th>
                    <th>模式</th>
                    <th width="280">選擇租借時間 (僅限租賃)</th>
                    <th>結帳小計</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): ?>
                <tr>
                    <td>
                        <?php if($item['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" width="65" style="border-radius:6px; border:1px solid #edf2f7;">
                        <?php else: ?>
                            <span style="color:#aaa; font-size:12px;">無圖</span>
                        <?php endif; ?>
                    </td>
                    
                    <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                    <td><?php echo htmlspecialchars($item['seller_name']); ?></td>
                    
                    <td>
                        <?php if($item['type'] === 'rent'): ?>
                            <span style="background:#fffaf0; color:#dd6b20; padding:2px 6px; border-radius:4px; font-weight:bold; font-size:13px;">⏳ 租賃</span>
                        <?php else: ?>
                            <span style="background:#e6ffed; color:#52c41a; padding:2px 6px; border-radius:4px; font-weight:bold; font-size:13px;">💰 買賣</span>
                        <?php endif; ?>
                    </td>
                    
                    <form action="cart.php" method="POST" onsubmit="return checkDatesBeforeSubmit(this, '<?php echo $item['type']; ?>');">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="item_type" value="<?php echo $item['type']; ?>">
                        <input type="hidden" name="base_price" value="<?php echo $item['price']; ?>">

                        <td>
                            <?php if($item['type'] === 'rent'): ?>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <div>起：<input type="date" name="start_date" class="date-input" min="<?php echo date('Y-m-d'); ?>" onchange="calculateItemTotal(this)"></div>
                                    <div>迄：<input type="date" name="end_date" class="date-input" min="<?php echo date('Y-m-d'); ?>" onchange="calculateItemTotal(this)"></div>
                                    <div style="font-size: 11px; color:#718096;" class="days-summary">(計費單價: $<?php echo number_format($item['price']); ?> / 天)</div>
                                </div>
                            <?php else: ?>
                                <span style="color:#a0aec0; font-style:italic; font-size:13px;">直接買斷，無需選擇時間</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <span class="price-badge" data-base="<?php echo $item['price']; ?>">$<?php echo number_format($item['price'], 0); ?> 元</span>
                        </td>
                        
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <button type="submit" name="single_checkout" class="btn-single-checkout">🚀 單件預約結帳</button>
                                <a href="cart.php?action=remove&item_id=<?php echo $item['id']; ?>" class="btn-delete" onclick="return confirm('確定要從購物車移除這件物品嗎？')">❌ 移除</a>
                            </div>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// ✨ 前端即時日期租金計算器
function calculateItemTotal(inputElement) {
    // 找到同一行的 tr 元素
    const row = inputElement.closest('tr');
    const startDateInput = row.querySelector('input[name="start_date"]');
    const endDateInput = row.querySelector('input[name="end_date"]');
    const priceBadge = row.querySelector('.price-badge');
    const daysSummary = row.querySelector('.days-summary');
    
    const basePrice = parseFloat(priceBadge.getAttribute('data-base'));
    
    if (startDateInput.value && endDateInput.value) {
        const start = new Date(startDateInput.value);
        const end = new Date(endDateInput.value);
        
        if (start <= end) {
            // 計算相差天數 (同一天算 1 天)
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            const newTotal = basePrice * diffDays;
            // 格式化千分位
            priceBadge.innerText = '$' + newTotal.toLocaleString() + ' 元';
            daysSummary.innerText = '(已選擇 ' + diffDays + ' 天，單價: $' + basePrice + '/天)';
        } else {
            priceBadge.innerText = '$' + basePrice.toLocaleString() + ' 元';
            daysSummary.innerText = '⚠️ 結束日期不得早於起始日期';
        }
    }
}

// 驗證提交
function checkDatesBeforeSubmit(formElement, type) {
    if (type === 'rent') {
        const start = formElement.querySelector('input[name="start_date"]').value;
        const end = formElement.querySelector('input[name="end_date"]').value;
        if (!start || !end) {
            alert('租賃失敗：請完整填寫該商品的租借起始與結束日期！');
            return false;
        }
        if (start > end) {
            alert('租賃失敗：起始日期不能大於結束日期！');
            return false;
        }
    }
    return confirm('確定要單獨預約結帳此商品，並向同學送出訂單嗎？');
}
</script>

</body>
</html>