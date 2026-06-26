<?php
// 開頭確保有 session_start(); 如果你的 db.php 內有，可不用重複寫
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// ==========================================
// 👑 核心：新增處理學生檢舉表單提交的後端邏輯
// ==========================================
$report_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_report') {
    // 安全防禦：沒登入的遊客不能檢舉
    if (!isset($_SESSION['user_id'])) {
        echo "<script>alert('請先登入系統才能進行檢舉！'); window.location.href='auth.php';</script>";
        exit;
    }

    $reporter_id = intval($_SESSION['user_id']);
    $item_id = intval($_POST['item_id']);
    
    // 判斷檢舉原因：若是選自訂，則抓 text 內容；否則抓 select 的預設字串
    if ($_POST['report_reason_type'] === 'custom') {
        $reason = trim($_POST['custom_reason']);
    } else {
        $reason = trim($_POST['report_reason_type']);
    }

    if ($item_id > 0 && !empty($reason)) {
        // 寫入 reports 資料表
        $stmt_rep = $pdo->prepare("INSERT INTO reports (reporter_id, item_id, reason, status) VALUES (?, ?, ?, 'pending')");
        $stmt_rep->execute([$reporter_id, $item_id, $reason]);

        echo "<script>alert('感謝您的回報！檢舉工單已成功建立，系統管理員將盡速進行審查。');</script>";
    }
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. 修改 SQL：除了撈物品，順便撈出持有人（賣家）的 credit_score
$stmt = $pdo->prepare("SELECT items.*, users.username, users.email, users.credit_score, users.id AS seller_id FROM items 
                        JOIN users ON items.owner_id = users.id 
                        WHERE items.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

// 如果找不到該物品
if (!$item) {
    echo "<h2>找不到該物品，或者已被下架。</h2>";
    echo "<a href='index.php'>回首頁</a>";
    exit;
}

// 2. 升級撈取邏輯：除了歷史評價，更透過 JOIN 順便抓出當時交易物品的名稱與照片
$stmt_reviews = $pdo->prepare("SELECT reviews.*, users.username AS reviewer_name,
                                     past_items.title AS past_item_title,
                                     past_items.image_url AS past_item_img
                               FROM reviews 
                               JOIN users ON reviews.reviewer_id = users.id 
                               JOIN orders ON reviews.order_id = orders.id
                               JOIN items past_items ON orders.item_id = past_items.id
                               WHERE reviews.reviewee_id = ? 
                               ORDER BY reviews.id DESC");
$stmt_reviews->execute([$item['seller_id']]);
$reviews = $stmt_reviews->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?> - 物品詳情</title>
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
            background: rgba(255,255,255,0.88);
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
        .nav-back {
            font-size: 13px; font-weight: 700; color: #ad1457;
            text-decoration: none;
            background: #fce4ec;
            padding: 6px 14px;
            border-radius: 20px;
            transition: background .18s;
        }
        .nav-back:hover { background: #f8bbd0; }

        /* ── 主容器 ── */
        .page-wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 36px 24px 70px;
        }

        /* ── 物品詳情主區塊 ── */
        .detail-card {
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(8px);
            border: 1.5px solid #f3e5f5;
            border-radius: 26px;
            padding: 32px;
            box-shadow: 0 6px 28px #9c27b010;
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 36px;
            margin-bottom: 32px;
        }

        /* ── 左欄：圖片 ── */
        .item-image-wrap {
            border-radius: 18px;
            overflow: hidden;
            border: 1.5px solid #f3e5f5;
            background: linear-gradient(135deg, #f3e5f5, #fce4ec);
            aspect-ratio: 1;
            display: flex; align-items: center; justify-content: center;
        }
        .item-image-wrap img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        .item-image-placeholder {
            font-size: 14px; color: #ce93d8; font-weight: 700;
        }

        /* ── 右欄：資訊 ── */
        .item-info { display: flex; flex-direction: column; gap: 14px; }
        .item-title {
            font-size: 24px; font-weight: 900; color: #3d2c4e;
            line-height: 1.3;
        }
        .info-divider {
            height: 1.5px;
            background: linear-gradient(90deg, #f3e5f5, transparent);
        }

        .info-row {
            display: flex; align-items: center; gap: 10px;
            font-size: 14px; color: #6d4c41; font-weight: 600;
        }
        .info-row .label { color: #9e9e9e; font-size: 12.5px; font-weight: 700; }

        .badge-type {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 20px;
            font-size: 13px; font-weight: 800;
        }
        .badge-rent  { background: #fff3e0; color: #f57c00; border: 1.5px solid #ffe0b2; }
        .badge-sell  { background: #e8f5e9; color: #2e7d32; border: 1.5px solid #c8e6c9; }
        .badge-status { background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 20px; font-size: 12.5px; font-weight: 800; border: 1.5px solid #c8e6c9; }

        .price-big {
            font-size: 30px; font-weight: 900; color: #e91e8c;
            display: flex; align-items: baseline; gap: 4px;
        }
        .price-big .unit { font-size: 14px; color: #ad1457; font-weight: 700; }

        .seller-box {
            background: #fdf4ff;
            border: 1.5px solid #e1bee7;
            border-radius: 14px;
            padding: 13px 16px;
            display: flex; flex-direction: column; gap: 6px;
        }
        .seller-name { font-size: 14px; font-weight: 800; color: #3d2c4e; }
        .seller-email { font-size: 12.5px; color: #9e9e9e; font-weight: 600; }
        .credit-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #e1f5fe; color: #0288d1;
            border: 1.5px solid #b3e5fc;
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 13.5px; font-weight: 800;
            width: fit-content;
        }

        .desc-box {
            background: #fdf4ff;
            border-left: 4px solid #ce93d8;
            border-radius: 0 12px 12px 0;
            padding: 14px 16px;
            font-size: 14px; color: #4a3860; line-height: 1.7;
        }

        /* ── 動作按鈕區 ── */
        .action-row { display: flex; gap: 12px; align-items: center; margin-top: 4px; }
        .btn-cart {
            flex: 1;
            background: linear-gradient(135deg, #f48fb1, #e91e8c);
            color: white; border: none;
            padding: 13px 20px;
            border-radius: 16px; cursor: pointer;
            font-family: inherit; font-size: 15px; font-weight: 800;
            letter-spacing: .3px;
            box-shadow: 0 4px 16px #f4849055;
            transition: transform .14s, box-shadow .14s;
        }
        .btn-cart:hover { transform: scale(1.03); box-shadow: 0 6px 20px #f4849077; }

        .btn-report {
            background: #fff5f5; color: #e53e3e;
            border: 1.5px solid #fc8181;
            padding: 13px 18px;
            border-radius: 16px; cursor: pointer;
            font-family: inherit; font-size: 14px; font-weight: 800;
            transition: background .18s;
            white-space: nowrap;
        }
        .btn-report:hover { background: #ffebee; }

        .owner-notice {
            background: #f5f5f5; color: #757575;
            border-radius: 12px; padding: 12px 16px;
            font-size: 13.5px; font-weight: 700;
        }
        .owner-upload-link {
            display: inline-block; margin-top: 8px;
            color: #e91e8c; font-weight: 800; text-decoration: none;
            font-size: 13.5px;
        }
        .owner-upload-link:hover { text-decoration: underline; }

        /* ── 評價區 ── */
        .reviews-section {
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(8px);
            border: 1.5px solid #f3e5f5;
            border-radius: 26px;
            padding: 28px 32px;
            box-shadow: 0 6px 28px #9c27b010;
        }
        .section-heading {
            font-size: 17px; font-weight: 900; color: #6a1b9a;
            margin-bottom: 18px;
            display: flex; align-items: center; gap: 8px;
        }
        .section-heading::after {
            content: ''; flex: 1; height: 1.5px;
            background: linear-gradient(90deg, #ce93d8, transparent);
        }
        .reviews-scroll {
            max-height: 520px; overflow-y: auto;
            display: flex; flex-direction: column; gap: 14px;
            padding-right: 4px;
        }
        .reviews-scroll::-webkit-scrollbar { width: 5px; }
        .reviews-scroll::-webkit-scrollbar-thumb { background: #e1bee7; border-radius: 10px; }

        .review-card {
            background: #fdf4ff;
            border: 1.5px solid #f3e5f5;
            border-radius: 16px;
            padding: 15px 18px;
        }
        .review-item-ref {
            display: flex; align-items: center; gap: 12px;
            background: white; border: 1.5px solid #f3e5f5;
            border-radius: 12px; padding: 9px 12px;
            margin-bottom: 12px;
        }
        .review-item-thumb {
            width: 46px; height: 46px;
            border-radius: 8px; overflow: hidden;
            background: linear-gradient(135deg, #f3e5f5, #fce4ec);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; border: 1px solid #f3e5f5;
        }
        .review-item-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .review-item-thumb-empty { font-size: 11px; color: #ce93d8; font-weight: 700; }
        .review-item-label { font-size: 11px; color: #9e9e9e; font-weight: 700; }
        .review-item-title { font-size: 13.5px; font-weight: 800; color: #3d2c4e; margin-top: 2px; }

        .review-meta {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 9px; font-size: 13px;
        }
        .review-author { font-weight: 800; color: #6a1b9a; }
        .review-stars { color: #f57c00; font-weight: 800; font-size: 13.5px; }

        .review-comment {
            font-size: 13.5px; color: #4a3860; line-height: 1.65;
            border-left: 3px solid #ce93d8;
            padding-left: 12px; margin-left: 4px;
        }
        .review-empty { font-size: 13px; color: #ce93d8; font-style: italic; font-weight: 600; padding: 20px 0; text-align: center; }

        /* ── 檢舉 Modal ── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 9999;
            justify-content: center; align-items: center;
        }
        .modal-box {
            background: white;
            border-radius: 22px;
            padding: 30px;
            width: 100%; max-width: 460px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
            position: relative;
        }
        .modal-title {
            font-size: 17px; font-weight: 900; color: #c62828;
            margin-bottom: 6px; display: flex; align-items: center; gap: 7px;
        }
        .modal-desc {
            font-size: 13px; color: #9e9e9e; font-weight: 600;
            margin-bottom: 20px; line-height: 1.6;
        }
        .modal-divider { height: 1.5px; background: #ffcdd2; margin-bottom: 20px; }
        .modal-label { font-size: 13px; font-weight: 800; color: #4a3860; display: block; margin-bottom: 7px; }
        .modal-select, .modal-textarea {
            width: 100%; border: 1.5px solid #f3e5f5;
            border-radius: 12px; padding: 10px 14px;
            font-family: inherit; font-size: 13.5px; color: #3d2c4e;
            margin-bottom: 14px; outline: none;
            transition: border-color .2s;
        }
        .modal-select:focus, .modal-textarea:focus { border-color: #fc8181; }
        .modal-textarea { resize: none; height: 85px; line-height: 1.6; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px; }
        .btn-modal-cancel {
            background: #f5f5f5; color: #757575;
            border: none; padding: 10px 18px;
            border-radius: 12px; cursor: pointer;
            font-family: inherit; font-size: 13.5px; font-weight: 700;
            transition: background .18s;
        }
        .btn-modal-cancel:hover { background: #eeeeee; }
        .btn-modal-submit {
            background: linear-gradient(135deg, #ef5350, #c62828);
            color: white; border: none; padding: 10px 22px;
            border-radius: 12px; cursor: pointer;
            font-family: inherit; font-size: 13.5px; font-weight: 800;
            transition: transform .12s;
        }
        .btn-modal-submit:hover { transform: scale(1.04); }

        /* ── RWD ── */
        @media (max-width: 780px) {
            .detail-card { grid-template-columns: 1fr; }
            .item-image-wrap { aspect-ratio: 16/9; }
            .top-nav { padding: 0 16px; }
            .page-wrap { padding: 22px 14px 60px; }
            .reviews-section, .detail-card { padding: 20px 16px; }
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
        <a href="index.php" class="nav-back">← 返回物品列表</a>
    </nav>

    <div class="page-wrap">

        <!-- ── 物品詳情卡片 ── -->
        <div class="detail-card">

            <!-- 左：圖片 -->
            <div class="item-image-wrap">
                <?php if ($item['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                <?php else: ?>
                    <span class="item-image-placeholder">📷 暫無圖片</span>
                <?php endif; ?>
            </div>

            <!-- 右：資訊 -->
            <div class="item-info">

                <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                <div class="info-divider"></div>

                <!-- 模式 & 狀態 -->
                <div class="info-row">
                    <span class="label">交易模式</span>
                    <?php if ($item['type'] === 'rent'): ?>
                        <span class="badge-type badge-rent">⏳ 校園租賃</span>
                    <?php else: ?>
                        <span class="badge-type badge-sell">💰 直接販售</span>
                    <?php endif; ?>
                    <span class="badge-status"><?php echo htmlspecialchars($item['item_status']); ?></span>
                </div>

                <!-- 價格 -->
                <div class="price-big">
                    $<?php echo number_format($item['price'], 0); ?>
                    <span class="unit"><?php echo $item['type'] === 'rent' ? '元 / 天' : '元'; ?></span>
                </div>

                <!-- 面交地點 -->
                <div class="info-row">
                    <span class="label">📍 面交地點</span>
                    <span><?php echo htmlspecialchars($item['location_name']); ?></span>
                </div>

                <!-- 賣家資訊 -->
                <div class="seller-box">
                    <div class="seller-name">👤 <?php echo htmlspecialchars($item['username']); ?></div>
                    <div class="seller-email">✉️ <?php echo htmlspecialchars($item['email']); ?></div>
                    <div class="credit-badge">
                        🛡️ 信用評分：<?php echo isset($item['credit_score']) ? $item['credit_score'] : 100; ?> / 100 分
                    </div>
                </div>

                <!-- 物品描述 -->
                <?php if (!empty($item['description'])): ?>
                <div>
                    <div style="font-size:12.5px;font-weight:800;color:#9e9e9e;margin-bottom:7px;">📝 物品詳細描述</div>
                    <div class="desc-box">
                        <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 動作按鈕 -->
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $item['owner_id']): ?>
                    <div class="owner-notice">
                        📢 這是您上架的物品（無法預約自己倉庫的東西）
                        <br>
                        <a href="upload.php" class="owner-upload-link">再去上架其他物品 →</a>
                    </div>
                <?php else: ?>
                    <div class="action-row">
                        <form action="add_to_cart.php" method="POST" style="flex:1;margin:0;">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="btn-cart" style="width:100%;">
                                🛒 加入購物車 / 預約商品
                            </button>
                        </form>
                        <button id="reportBtn" class="btn-report">⚠️ 檢舉</button>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- ── 評價區 ── -->
        <div class="reviews-section">
            <div class="section-heading">💬 同學對賣家的歷史評價（<?php echo count($reviews); ?> 筆）</div>

            <?php if (count($reviews) === 0): ?>
                <div class="review-empty">🌸 該同學目前尚無面交評價紀錄</div>
            <?php else: ?>
                <div class="reviews-scroll">
                    <?php foreach ($reviews as $rev): ?>
                        <div class="review-card">

                            <!-- 當時交易物品縮圖 -->
                            <div class="review-item-ref">
                                <div class="review-item-thumb">
                                    <?php if (!empty($rev['past_item_img'])): ?>
                                        <img src="<?php echo htmlspecialchars($rev['past_item_img']); ?>" alt="">
                                    <?php else: ?>
                                        <span class="review-item-thumb-empty">無圖</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="review-item-label">📦 當時交易物品</div>
                                    <div class="review-item-title"><?php echo htmlspecialchars($rev['past_item_title']); ?></div>
                                </div>
                            </div>

                            <!-- 評分者 & 星等 -->
                            <div class="review-meta">
                                <span class="review-author">👤 <?php echo htmlspecialchars($rev['reviewer_name']); ?> 同學</span>
                                <span class="review-stars">
                                    <?php echo str_repeat('⭐', $rev['rating']); ?> <?php echo $rev['rating']; ?>分
                                </span>
                            </div>

                            <!-- 評語 -->
                            <div class="review-comment">
                                <?php if (!empty($rev['comment'])): ?>
                                    <?php echo nl2br(htmlspecialchars($rev['comment'])); ?>
                                <?php else: ?>
                                    <span style="color:#bdbdbd;font-style:italic;">未填寫詳細評語</span>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /.page-wrap -->

    <!-- ── 檢舉 Modal ── -->
    <div id="reportModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title">⚠️ 檢舉違規商品</div>
            <div class="modal-divider"></div>
            <p class="modal-desc">請選擇或輸入您檢舉此商品的具體原因，管理員將盡快審查並在必要時下架商品。</p>

            <form action="" method="POST" id="reportForm">
                <input type="hidden" name="action" value="submit_report">
                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">

                <label class="modal-label">請選擇違規類型：</label>
                <select name="report_reason_type" id="reasonType" class="modal-select" onchange="toggleCustomReason()">
                    <option value="商品含有校園違禁品（菸、酒、槍、毒）">🚫 商品含有校園違禁品（菸、酒、槍、毒等）</option>
                    <option value="詐騙行為或不實商品資訊">❌ 詐騙行為或不實商品資訊</option>
                    <option value="類別錯誤或惡意洗版">📁 類別錯誤或惡意洗版</option>
                    <option value="custom">✍️ 其他原因（自行填寫描述）</option>
                </select>

                <div id="customReasonWrapper" style="display:none;">
                    <label class="modal-label">請輸入具體原因：</label>
                    <textarea name="custom_reason" id="customReason" class="modal-textarea" placeholder="請詳細說明違規事由..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" id="closeReportBtn" class="btn-modal-cancel">取消</button>
                    <button type="submit" class="btn-modal-submit">送出檢舉</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const reportBtn = document.getElementById('reportBtn');
    const reportModal = document.getElementById('reportModal');
    const closeReportBtn = document.getElementById('closeReportBtn');
    const reasonType = document.getElementById('reasonType');
    const customReasonWrapper = document.getElementById('customReasonWrapper');
    const customReason = document.getElementById('customReason');

    // 如果檢舉按鈕存在（代表非擁有者），則綁定點擊事件
    if (reportBtn) {
        reportBtn.addEventListener('click', () => {
            reportModal.style.display = 'flex';
        });
    }

    // 關閉彈窗
    if (closeReportBtn) {
        closeReportBtn.addEventListener('click', () => {
            reportModal.style.display = 'none';
        });
    }

    // 點擊視窗外面黑背景也可以關閉
    window.addEventListener('click', (e) => {
        if (e.target === reportModal) {
            reportModal.style.display = 'none';
        }
    });

    // 動態判斷：選擇「其他原因」時才秀出輸入框
    function toggleCustomReason() {
        if (reasonType.value === 'custom') {
            customReasonWrapper.style.display = 'block';
            customReason.required = true;
        } else {
            customReasonWrapper.style.display = 'none';
            customReason.required = false;
        }
    }
    </script>

</body>
</html>