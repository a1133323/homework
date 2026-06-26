<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";

// 處理賣家提交面交資訊
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_meeting') {
    $order_id = intval($_POST['order_id']);
    $meeting_info = trim($_POST['meeting_info']);

    if (!empty($meeting_info)) {
        $stmt = $pdo->prepare("UPDATE orders SET meeting_info = ? WHERE id = ?");
        $stmt->execute([$meeting_info, $order_id]);
        $msg = "面交資訊已成功傳送給買家！";
    }
}

// 👑 1. 買家清單 SQL
$sql_buyer = "SELECT orders.*, items.title, items.location_name AS area_name, users.username AS seller_name,
                     r_out.id AS my_review_id,
                     r_in.rating AS peer_rating, r_in.comment AS peer_comment
              FROM orders 
              JOIN items ON orders.item_id = items.id 
              JOIN users ON items.owner_id = users.id 
              LEFT JOIN reviews r_out ON orders.id = r_out.order_id AND r_out.reviewer_id = ?
              LEFT JOIN reviews r_in  ON orders.id = r_in.order_id  AND r_in.reviewee_id = ?
              WHERE orders.buyer_id = ? 
              ORDER BY orders.id DESC";
$stmt_buyer = $pdo->prepare($sql_buyer);
$stmt_buyer->execute([$user_id, $user_id, $user_id]);
$my_rentals = $stmt_buyer->fetchAll();

// 👑 2. 賣家清單 SQL
$sql_seller = "SELECT orders.*, items.title, items.location_name AS area_name, users.username AS buyer_name,
                     r_out.id AS my_review_id,
                     r_in.rating AS peer_rating, r_in.comment AS peer_comment
               FROM orders 
               JOIN items ON orders.item_id = items.id 
               JOIN users ON orders.buyer_id = users.id 
               LEFT JOIN reviews r_out ON orders.id = r_out.order_id AND r_out.reviewer_id = ?
               LEFT JOIN reviews r_in  ON orders.id = r_in.order_id  AND r_in.reviewee_id = ?
               WHERE items.owner_id = ? 
               ORDER BY orders.id DESC";
$stmt_seller = $pdo->prepare($sql_seller);
$stmt_seller->execute([$user_id, $user_id, $user_id]);
$my_sales = $stmt_seller->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人中心 - 訂單與租賃管理</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Nunito', 'Microsoft JhengHei', sans-serif;
            background: #fdf4ff;
            background-image:
                radial-gradient(circle at 8% 5%, #fce4f388 0%, transparent 38%),
                radial-gradient(circle at 92% 90%, #e3f2fd88 0%, transparent 38%);
            min-height: 100vh;
            color: #3d2c4e;
        }

        /* ── 頂部導覽 ── */
        .top-nav {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1.5px solid #f8bbd0;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-brand {
            display: flex; align-items: center; gap: 9px;
            text-decoration: none;
        }
        .nav-brand-icon {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, #f48fb1, #e91e8c);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px;
            box-shadow: 0 2px 8px #f48fb155;
        }
        .nav-brand-text { font-size: 19px; font-weight: 900; color: #c2185b; }
        .nav-links { display: flex; align-items: center; gap: 8px; }
        .nav-links a {
            font-size: 13px; font-weight: 700; color: #ad1457;
            text-decoration: none;
            padding: 6px 14px; border-radius: 20px;
            background: #fce4ec;
            transition: background .18s;
        }
        .nav-links a:hover { background: #f8bbd0; }

        /* ── 主容器 ── */
        .page-wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px 70px;
        }

        /* ── 頁面標題 ── */
        .page-header {
            margin-bottom: 28px;
        }
        .page-header h1 {
            font-size: 24px; font-weight: 900; color: #c2185b;
            display: flex; align-items: center; gap: 9px;
        }
        .page-header p { font-size: 13.5px; color: #ad1457; margin-top: 5px; font-weight: 600; }

        /* ── 訊息提示 ── */
        .msg-box {
            background: #f0fff4; border: 1.5px solid #9ae6b4;
            border-radius: 14px; padding: 12px 18px;
            color: #276749; font-size: 14px; font-weight: 700;
            margin-bottom: 22px;
        }

        /* ── 區塊 ── */
        .section-block {
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(8px);
            border: 1.5px solid #f3e5f5;
            border-radius: 24px;
            padding: 26px 28px;
            box-shadow: 0 6px 28px #9c27b010;
            margin-bottom: 30px;
        }
        .section-heading {
            font-size: 17px; font-weight: 900; color: #6a1b9a;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .section-heading::after {
            content: ''; flex: 1; height: 1.5px;
            background: linear-gradient(90deg, #ce93d8, transparent);
        }

        /* ── 空狀態 ── */
        .empty-state {
            text-align: center; padding: 36px 20px;
            color: #ce93d8; font-size: 14px; font-weight: 700;
        }

        /* ── 訂單卡片清單 ── */
        .order-list { display: flex; flex-direction: column; gap: 16px; }

        .order-card {
            border: 1.5px solid #f3e5f5;
            border-radius: 18px;
            padding: 18px 20px;
            background: #fdf4ff88;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            transition: box-shadow .18s;
        }
        .order-card:hover { box-shadow: 0 4px 18px #9c27b015; }

        /* 卡片頂部橫跨 */
        .order-card-header {
            grid-column: 1 / -1;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 8px;
            padding-bottom: 12px;
            border-bottom: 1.5px dashed #f3e5f5;
        }
        .order-title { font-size: 15px; font-weight: 900; color: #3d2c4e; }
        .order-badges { display: flex; gap: 7px; flex-wrap: wrap; align-items: center; }

        /* badge 通用 */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 11px; border-radius: 20px;
            font-size: 12px; font-weight: 800;
        }
        .badge-rent   { background: #fff3e0; color: #f57c00; border: 1.5px solid #ffe0b2; }
        .badge-buy    { background: #e8f5e9; color: #2e7d32; border: 1.5px solid #c8e6c9; }
        .badge-pending { background: #fff8e1; color: #f9a825; border: 1.5px solid #ffe082; }
        .badge-paid   { background: #e8f5e9; color: #2e7d32; border: 1.5px solid #c8e6c9; }

        /* 欄位項目 */
        .order-field { display: flex; flex-direction: column; gap: 3px; }
        .order-field-label { font-size: 11.5px; font-weight: 800; color: #9e9e9e; text-transform: uppercase; letter-spacing: .5px; }
        .order-field-value { font-size: 13.5px; font-weight: 700; color: #3d2c4e; }
        .price-buy { color: #2e7d32; font-size: 15px; font-weight: 900; }
        .price-pay { color: #e91e8c; font-size: 15px; font-weight: 900; }

        /* 面交資訊（買家看到的） */
        .meeting-info-box {
            grid-column: 1 / -1;
            background: #fffde7;
            border: 1.5px solid #ffe082;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 13.5px; font-weight: 700; color: #7b5e00;
            line-height: 1.6;
        }
        .meeting-info-pending {
            grid-column: 1 / -1;
            font-size: 13px; color: #bdbdbd; font-style: italic; font-weight: 600;
        }

        /* 面交更新表單（賣家用） */
        .meeting-form {
            grid-column: 1 / -1;
            display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
        }
        .meeting-input {
            flex: 1; min-width: 200px;
            border: 1.5px solid #f3e5f5; border-radius: 12px;
            padding: 8px 14px; font-family: inherit;
            font-size: 13.5px; color: #3d2c4e;
            background: white; outline: none;
            transition: border-color .2s;
        }
        .meeting-input:focus { border-color: #ce93d8; }
        .btn-meeting {
            background: linear-gradient(135deg, #ce93d8, #9c27b0);
            color: white; border: none;
            padding: 9px 18px; border-radius: 12px;
            cursor: pointer; font-family: inherit;
            font-size: 13px; font-weight: 800;
            white-space: nowrap; transition: transform .12s;
        }
        .btn-meeting:hover { transform: scale(1.04); }

        /* 評分區 */
        .review-area {
            grid-column: 1 / -1;
            display: flex; flex-direction: column; gap: 8px;
            padding-top: 12px;
            border-top: 1.5px dashed #f3e5f5;
        }
        .btn-review-buyer {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #f48fb1, #e91e8c);
            color: white; text-decoration: none;
            padding: 9px 18px; border-radius: 12px;
            font-size: 13px; font-weight: 800;
            box-shadow: 0 3px 10px #f4849044;
            transition: transform .12s;
            width: fit-content;
        }
        .btn-review-seller {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #81c784, #388e3c);
            color: white; text-decoration: none;
            padding: 9px 18px; border-radius: 12px;
            font-size: 13px; font-weight: 800;
            box-shadow: 0 3px 10px #38a16944;
            transition: transform .12s;
            width: fit-content;
        }
        .btn-review-buyer:hover, .btn-review-seller:hover { transform: scale(1.04); }
        .reviewed-badge {
            font-size: 13px; font-weight: 800; color: #9e9e9e;
            display: inline-flex; align-items: center; gap: 5px;
        }

        /* 對方給我的評語 */
        .peer-review-box {
            background: #fce4ec;
            border: 1.5px solid #f8bbd0;
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 13px; color: #880e4f;
            line-height: 1.6;
        }
        .peer-review-box.green {
            background: #f1f8e9;
            border-color: #c5e1a5;
            color: #2e7d32;
        }
        .peer-review-stars { font-weight: 900; font-size: 13.5px; margin-bottom: 3px; }
        .peer-review-comment { font-weight: 600; color: #555; margin-top: 3px; }

        /* ── RWD ── */
        @media (max-width: 640px) {
            .order-card { grid-template-columns: 1fr; }
            .top-nav { padding: 0 16px; }
            .page-wrap { padding: 20px 12px 60px; }
            .section-block { padding: 18px 14px; }
        }
    </style>
</head>
<body>

    <!-- ── 頂部導覽 ── -->
    <nav class="top-nav">
        <a href="index.php" class="nav-brand">
            <div class="nav-brand-icon">🎓</div>
            <span class="nav-brand-text">UniTrade</span>
        </a>
        <div class="nav-links">
            <a href="index.php">← 回首頁</a>
            <a href="upload.php">📦 我要上架</a>
        </div>
    </nav>

    <div class="page-wrap">

        <!-- ── 頁面標題 ── -->
        <div class="page-header">
            <h1>📋 個人中心 / 交易追蹤後台</h1>
            <p>管理您的所有借入訂單與出租販售紀錄</p>
        </div>

        <!-- ── 訊息提示 ── -->
        <?php if (!empty($msg)): ?>
            <div class="msg-box">✅ <?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <!-- ════════════════════════════════════
             【借入清單】我向他人預約/購買的物品
             ════════════════════════════════════ -->
        <div class="section-block">
            <div class="section-heading">⏳ 我向他人預約 / 購買的物品（借入清單）</div>

            <?php if (count($my_rentals) === 0): ?>
                <div class="empty-state">🌸 目前沒有借入或購買任何物品</div>
            <?php else: ?>
                <div class="order-list">
                    <?php foreach ($my_rentals as $order): ?>
                        <div class="order-card">

                            <!-- 卡片頭 -->
                            <div class="order-card-header">
                                <span class="order-title"><?php echo htmlspecialchars($order['title']); ?></span>
                                <div class="order-badges">
                                    <?php if ($order['order_type'] === 'rent'): ?>
                                        <span class="badge badge-rent">⏳ 租賃</span>
                                    <?php else: ?>
                                        <span class="badge badge-buy">💰 購買</span>
                                    <?php endif; ?>
                                    <?php if ($order['payment_status'] === 'pending'): ?>
                                        <span class="badge badge-pending">⚠️ 待付款（面交現付）</span>
                                    <?php else: ?>
                                        <span class="badge badge-paid">✅ 已付款</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 欄位 -->
                            <div class="order-field">
                                <span class="order-field-label">出租 / 賣家</span>
                                <span class="order-field-value">👤 <?php echo htmlspecialchars($order['seller_name']); ?></span>
                            </div>
                            <div class="order-field">
                                <span class="order-field-label">總金額</span>
                                <span class="order-field-value price-pay">$<?php echo number_format($order['total_price'], 0); ?></span>
                            </div>

                            <?php if ($order['order_type'] === 'rent'): ?>
                            <div class="order-field">
                                <span class="order-field-label">租借區間</span>
                                <span class="order-field-value"><?php echo $order['start_date']; ?> → <?php echo $order['end_date']; ?></span>
                            </div>
                            <?php endif; ?>

                            <!-- 面交資訊 -->
                            <?php if (!empty($order['meeting_info'])): ?>
                                <div class="meeting-info-box">
                                    📍 賣家面交資訊：<?php echo nl2br(htmlspecialchars($order['meeting_info'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="meeting-info-pending">⏳ 等待賣家輸入具體面交地點...</div>
                            <?php endif; ?>

                            <!-- 評分 & 對方評語 -->
                            <div class="review-area">
                                <?php if (!$order['my_review_id']): ?>
                                    <a href="review.php?order_id=<?php echo $order['id']; ?>" class="btn-review-buyer">
                                        ⭐ 交易結案 / 評分賣家
                                    </a>
                                <?php else: ?>
                                    <span class="reviewed-badge">✅ 您已給予賣家評分</span>
                                <?php endif; ?>

                                <?php if ($order['peer_rating']): ?>
                                    <div class="peer-review-box">
                                        <div class="peer-review-stars">
                                            💬 賣家給您的評語：<?php echo str_repeat('⭐', $order['peer_rating']); ?> (<?php echo $order['peer_rating']; ?>分)
                                        </div>
                                        <div class="peer-review-comment"><?php echo htmlspecialchars($order['peer_comment'] ?? '未填寫詳細說明'); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ════════════════════════════════════
             【出租清單】學生向我申請的物品
             ════════════════════════════════════ -->
        <div class="section-block">
            <div class="section-heading">📥 學生向我申請的物品（出租 / 販售清單）</div>

            <?php if (count($my_sales) === 0): ?>
                <div class="empty-state">🌸 目前沒有人向您租借或購買物品</div>
            <?php else: ?>
                <div class="order-list">
                    <?php foreach ($my_sales as $order): ?>
                        <div class="order-card">

                            <!-- 卡片頭 -->
                            <div class="order-card-header">
                                <span class="order-title"><?php echo htmlspecialchars($order['title']); ?></span>
                                <div class="order-badges">
                                    <?php if ($order['order_type'] === 'rent'): ?>
                                        <span class="badge badge-rent">⏳ 租賃</span>
                                    <?php else: ?>
                                        <span class="badge badge-buy">💰 購買</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 欄位 -->
                            <div class="order-field">
                                <span class="order-field-label">買家</span>
                                <span class="order-field-value">👤 <?php echo htmlspecialchars($order['buyer_name']); ?></span>
                            </div>
                            <div class="order-field">
                                <span class="order-field-label">應收金額</span>
                                <span class="order-field-value price-buy">$<?php echo number_format($order['total_price'], 0); ?></span>
                            </div>

                            <?php if ($order['order_type'] === 'rent'): ?>
                            <div class="order-field">
                                <span class="order-field-label">租借區間</span>
                                <span class="order-field-value"><?php echo $order['start_date']; ?> → <?php echo $order['end_date']; ?></span>
                            </div>
                            <?php endif; ?>

                            <!-- 面交更新表單 -->
                            <form action="my_orders.php" method="POST" class="meeting-form">
                                <input type="hidden" name="action" value="update_meeting">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="text" name="meeting_info" class="meeting-input"
                                       placeholder="例如：中午12點管院面交"
                                       value="<?php echo htmlspecialchars($order['meeting_info'] ?? ''); ?>" required>
                                <button type="submit" class="btn-meeting">更新面交資訊</button>
                            </form>

                            <!-- 評分 & 對方評語 -->
                            <div class="review-area">
                                <?php if (!$order['my_review_id']): ?>
                                    <a href="review.php?order_id=<?php echo $order['id']; ?>" class="btn-review-seller">
                                        🤝 確認歸還 / 評分買家
                                    </a>
                                <?php else: ?>
                                    <span class="reviewed-badge">✅ 您已給予買家評分</span>
                                <?php endif; ?>

                                <?php if ($order['peer_rating']): ?>
                                    <div class="peer-review-box green">
                                        <div class="peer-review-stars">
                                            💬 買家給您的評語：<?php echo str_repeat('⭐', $order['peer_rating']); ?> (<?php echo $order['peer_rating']; ?>分)
                                        </div>
                                        <div class="peer-review-comment"><?php echo htmlspecialchars($order['peer_comment'] ?? '未填寫詳細說明'); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /.page-wrap -->

</body>
</html>