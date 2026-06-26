<?php
// 開啟 Session（若原本 db.php 沒包含的話，建議補上防錯）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🚨 【新增門禁防禦】如果檢查發現使用者「沒有」登入的 Session 紀錄，就強制跳轉到登入頁
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit(); // 切記一定要加 exit()，否則後面的資料庫查詢和網頁還是會被偷跑
}

require_once 'db.php';

/* ==========================================================================
   📢 【區塊一】全站重要公告邏輯（對象：所有人，查 announcements 表）
   ========================================================================== */
$stmt_ann = $pdo->query("SELECT content FROM announcements ORDER BY id DESC LIMIT 1");
$latest_ann = $stmt_ann->fetch();


/* ==========================================================================
   🚨 【區塊二】個人違規警告邏輯（對象：特定登入使用者，查 notifications 表）
   ========================================================================== */
$violation_noti = null;
if (isset($_SESSION['user_id'])) {
    // 確保留下未讀(is_read=0)且屬於 violation 的處份通知
    $stmt_vio = $pdo->prepare("SELECT id, content FROM notifications 
                               WHERE user_id = ? AND type = 'violation' AND is_read = 0 
                               ORDER BY id DESC LIMIT 1");
    $stmt_vio->execute([$_SESSION['user_id']]);
    $violation_noti = $stmt_vio->fetch();
}

// 處理同學點擊「我知道了，清除警告」的已讀動作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'read_violation') {
    $noti_id = intval($_POST['noti_id']);
    if ($noti_id > 0 && isset($_SESSION['user_id'])) {
        // 安全起見，必須符合當前登入使用者的 user_id 才能將其更新為已讀 1
        $update_noti = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $update_noti->execute([$noti_id, $_SESSION['user_id']]);
        
        // 更新完後重新導向至 index.php 刷新畫面避免重複提交表單
        header("Location: index.php");
        exit(); 
    }
}


/* ==========================================================================
   📦 商品列表主要邏輯
   ========================================================================== */
$sql = "SELECT items.*, users.username FROM items 
        JOIN users ON items.owner_id = users.id 
        WHERE items.item_status = 'available' 
        ORDER BY items.id DESC";
$stmt = $pdo->query($sql);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniTrade 校園易 - 首頁</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        /* ── Reset & Base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Nunito', 'Microsoft JhengHei', sans-serif;
            background: #fdf4ff;
            background-image:
                radial-gradient(circle at 10% 0%, #fce4f388 0%, transparent 40%),
                radial-gradient(circle at 90% 100%, #e3f2fd88 0%, transparent 40%);
            min-height: 100vh;
            color: #3d2c4e;
        }

        /* ── 頂部導覽列 ── */
        .top-nav {
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1.5px solid #f8bbd0;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 62px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .nav-brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #f48fb1, #e91e8c);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            box-shadow: 0 2px 8px #f48fb155;
        }
        .nav-brand-text {
            font-size: 20px;
            font-weight: 900;
            color: #c2185b;
            letter-spacing: .5px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .nav-links a {
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            color: #ad1457;
            padding: 6px 14px;
            border-radius: 20px;
            transition: background .18s, color .18s;
        }
        .nav-links a:hover {
            background: #fce4ec;
            color: #880e4f;
        }
        .nav-links a.nav-admin {
            color: #c62828;
            background: #ffebee;
        }
        .nav-links a.nav-admin:hover { background: #ffcdd2; }
        .nav-links .nav-sep {
            color: #f8bbd0;
            font-size: 14px;
            user-select: none;
        }
        .nav-user-badge {
            font-size: 13px;
            color: #6d4c41;
            font-weight: 600;
            padding: 5px 12px;
            background: #fce4ec;
            border-radius: 20px;
            border: 1.5px solid #f8bbd0;
        }
        .nav-guest {
            font-size: 13px;
            color: #9e9e9e;
        }

        /* ── 主容器 ── */
        .page-wrap {
            max-width: 1240px;
            margin: 0 auto;
            padding: 28px 24px 60px;
        }

        /* ── 違規通知橫幅 ── */
        .violation-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: #fff5f5;
            border: 2px solid #fc8181;
            border-radius: 16px;
            padding: 14px 20px;
            margin-bottom: 18px;
        }
        .violation-inner {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .violation-badge {
            background: #e53e3e;
            color: white;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 800;
            flex-shrink: 0;
            animation: blinker 1.2s linear infinite;
        }
        @keyframes blinker { 50% { opacity: 0.5; } }
        .violation-text {
            font-size: 13.5px;
            color: #c53030;
            font-weight: 700;
            line-height: 1.5;
        }
        .btn-read-violation {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            font-family: inherit;
            white-space: nowrap;
            transition: transform .12s;
        }
        .btn-read-violation:hover { transform: scale(1.04); }

        /* ── 系統公告橫幅 ── */
        .ann-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: rgba(255,253,231,0.95);
            border: 1.5px solid #ffe082;
            border-radius: 16px;
            padding: 13px 20px;
            margin-bottom: 18px;
            transition: opacity .3s ease;
        }
        .ann-inner {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .ann-badge {
            background: #f57c00;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 800;
            white-space: nowrap;
        }
        .ann-text {
            font-size: 13.5px;
            color: #7b341e;
            font-weight: 700;
            line-height: 1.5;
        }
        .btn-ann-close {
            background: transparent;
            border: none;
            color: #f57c00;
            font-size: 22px;
            cursor: pointer;
            opacity: .45;
            padding: 0 4px;
            line-height: 1;
            transition: opacity .2s;
        }
        .btn-ann-close:hover { opacity: 1; }

        /* ── 地圖推廣橫幅 ── */
        .map-promo-banner {
            background: linear-gradient(135deg, #ce93d8 0%, #9c27b0 50%, #7b1fa2 100%);
            border-radius: 20px;
            padding: 22px 28px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: 0 6px 24px #9c27b033;
        }
        .map-promo-text h3 {
            font-size: 17px;
            font-weight: 800;
            color: white;
            margin-bottom: 5px;
        }
        .map-promo-text p {
            font-size: 13px;
            color: rgba(255,255,255,0.88);
            font-weight: 600;
            line-height: 1.5;
        }
        .btn-map-promo {
            background: white;
            color: #7b1fa2;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 24px;
            font-weight: 800;
            font-size: 14px;
            white-space: nowrap;
            box-shadow: 0 3px 10px rgba(0,0,0,0.12);
            transition: transform .12s, box-shadow .12s;
        }
        .btn-map-promo:hover {
            transform: scale(1.04);
            box-shadow: 0 5px 16px rgba(0,0,0,0.18);
        }

        /* ── 區塊標題 ── */
        .section-heading {
            font-size: 20px;
            font-weight: 900;
            color: #6a1b9a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-heading::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(90deg, #ce93d8, transparent);
            border-radius: 2px;
        }

        /* ── 地圖搜尋欄 ── */
        .map-search-bar {
            background: white;
            border: 1.5px solid #e1bee7;
            border-radius: 16px;
            padding: 12px 18px;
            margin-bottom: 12px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .map-search-label {
            font-size: 13px;
            font-weight: 700;
            color: #6a1b9a;
            white-space: nowrap;
        }
        #search_address {
            flex: 1;
            min-width: 220px;
            border: 1.5px solid #e1bee7;
            border-radius: 20px;
            padding: 7px 14px;
            font-family: inherit;
            font-size: 13px;
            color: #3d2c4e;
            outline: none;
            transition: border-color .2s;
        }
        #search_address:focus { border-color: #ab47bc; }
        .btn-map-search {
            background: linear-gradient(135deg, #ab47bc, #8e24aa);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            font-family: inherit;
            transition: transform .12s;
        }
        .btn-map-search:hover { transform: scale(1.04); }
        #search_status {
            font-size: 13px;
            color: #7e57c2;
            font-weight: 600;
        }

        /* ── 地圖容器 ── */
        #map {
            width: 100%;
            height: 420px;
            border: 2px solid #e1bee7;
            border-radius: 18px;
            margin-bottom: 36px;
            box-shadow: 0 4px 20px #9c27b015;
            overflow: hidden;
        }

        /* ── 商品卡片網格 ── */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 20px;
        }
        .item-card {
            background: white;
            border: 1.5px solid #f3e5f5;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 3px 12px #9c27b010;
            transition: transform .18s, box-shadow .18s;
            display: flex;
            flex-direction: column;
        }
        .item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 28px #9c27b022;
        }
        .item-img {
            width: 100%;
            height: 155px;
            object-fit: cover;
            display: block;
        }
        .item-img-placeholder {
            width: 100%;
            height: 155px;
            background: linear-gradient(135deg, #f3e5f5, #fce4ec);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: #ce93d8;
            font-weight: 700;
        }
        .item-body {
            padding: 13px 14px 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .cat-badge {
            display: inline-block;
            font-size: 11.5px;
            color: #8e24aa;
            background: #f3e5f5;
            padding: 2px 9px;
            border-radius: 20px;
            font-weight: 700;
            width: fit-content;
        }
        .item-title {
            font-size: 14.5px;
            font-weight: 800;
            color: #3d2c4e;
            line-height: 1.35;
        }
        .item-meta {
            font-size: 12.5px;
            color: #6d4c41;
            line-height: 1.7;
        }
        .item-type-rent { color: #f57c00; font-weight: 800; }
        .item-type-sale { color: #2e7d32; font-weight: 800; }
        .item-price {
            font-size: 15px;
            font-weight: 900;
            color: #e91e8c;
        }
        .btn-detail {
            display: block;
            margin-top: auto;
            padding: 8px 0;
            background: linear-gradient(135deg, #f48fb1, #e91e8c);
            color: white;
            text-align: center;
            border-radius: 12px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .3px;
            transition: opacity .15s;
        }
        .btn-detail:hover { opacity: .88; }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #ce93d8;
            font-size: 15px;
            font-weight: 700;
        }

        /* ── RWD ── */
        @media (max-width: 640px) {
            .top-nav { padding: 0 16px; }
            .page-wrap { padding: 20px 14px 50px; }
            .map-promo-banner { flex-direction: column; align-items: flex-start; }
            .nav-links { gap: 2px; }
        }
    </style>
</head>
<body>

    <!-- ── 頂部導覽列 ── -->
    <nav class="top-nav">
        <a href="index.php" class="nav-brand">
            <div class="nav-brand-icon">🎓</div>
            <span class="nav-brand-text">UniTrade</span>
        </a>

        <div class="nav-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="nav-user-badge">👋 <?php echo htmlspecialchars($_SESSION['username']); ?></span>

                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php" class="nav-admin">⚙️ 管理後台</a>
                    <span class="nav-sep">·</span>
                    <a href="logout.php">登出</a>
                <?php else: ?>
                    <a href="upload.php">📦 上架物品</a>
                    <span class="nav-sep">·</span>
                    <a href="my_orders.php">📋 個人中心</a>
                    <span class="nav-sep">·</span>
                    <a href="cart.php">🛒 購物車</a>
                    <span class="nav-sep">·</span>
                    <a href="credit_profile.php">🛡️ 信用中心</a>
                    <span class="nav-sep">·</span>
                    <a href="logout.php">登出</a>
                <?php endif; ?>
            <?php else: ?>
                <span class="nav-guest">您尚未登入</span>
                <span class="nav-sep">·</span>
                <a href="auth.php">登入 / 註冊</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="page-wrap">

        <!-- ── 違規通知 ── -->
        <?php if ($violation_noti): ?>
            <div class="violation-banner">
                <div class="violation-inner">
                    <span class="violation-badge">🚨 違規處份通知</span>
                    <span class="violation-text"><?php echo htmlspecialchars($violation_noti['content']); ?></span>
                </div>
                <form action="index.php" method="POST" style="margin:0;flex-shrink:0;">
                    <input type="hidden" name="action" value="read_violation">
                    <input type="hidden" name="noti_id" value="<?php echo $violation_noti['id']; ?>">
                    <button type="submit" class="btn-read-violation">我知道了，清除警告 ✔️</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ── 系統公告 ── -->
        <?php if ($latest_ann): ?>
            <div id="system-announcement" class="ann-banner">
                <div class="ann-inner">
                    <span class="ann-badge">📢 系統公告</span>
                    <span class="ann-text"><?php echo htmlspecialchars($latest_ann['content']); ?></span>
                </div>
                <button type="button" class="btn-ann-close" onclick="closeAnnouncement()" aria-label="關閉公告">&times;</button>
            </div>
            <script>
            function closeAnnouncement() {
                var annBox = document.getElementById('system-announcement');
                if (annBox) {
                    annBox.style.opacity = '0';
                    setTimeout(function() { annBox.style.display = 'none'; }, 300);
                }
            }
            </script>
        <?php endif; ?>

        <!-- ── 地圖推廣橫幅 ── -->
        <div class="map-promo-banner">
            <div class="map-promo-text">
                <h3>🗺️ 想找附近的教科書或生活用品嗎？</h3>
                <p>開啟「智能地理搜尋模式」，一鍵切換分類、設定方圓 500m 範圍，探索離你最近的校園好物！</p>
            </div>
            <a href="map_search.php" class="btn-map-promo">開啟智能地圖搜尋 🚀</a>
        </div>

        <!-- ── 地圖區 ── -->
        <h2 class="section-heading">📍 智能校園地理搜尋地圖</h2>

        <div class="map-search-bar">
            <span class="map-search-label">🔍 快速搜尋校園位置：</span>
            <input type="text" id="search_address" placeholder="輸入地名或地址（例如：高雄大學管理學院）">
            <button type="button" onclick="searchMapLocation()" class="btn-map-search">搜尋定位</button>
            <span id="search_status"></span>
        </div>

        <div id="map"></div>

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            var taiwanBounds = L.latLngBounds(L.latLng(21.5, 119.0), L.latLng(25.5, 122.5));
            var map = L.map('map', {
                center: [22.7306, 120.2857],
                zoom: 16,
                minZoom: 8,
                maxBounds: taiwanBounds,
                maxBoundsViscosity: 1.0
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            function searchMapLocation() {
                var query = document.getElementById('search_address').value.trim();
                var statusText = document.getElementById('search_status');
                if (query === "") { alert("請先輸入想要搜尋的地點或校園大樓名稱！"); return; }
                statusText.innerText = "⏳ 正在尋找地圖位置...";
                statusText.style.color = "#7e57c2";
                var url = "https://nominatim.openstreetmap.org/search?format=json&q=" + encodeURIComponent(query);
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            var result = data[0];
                            map.flyTo([parseFloat(result.lat), parseFloat(result.lon)], 17, { animate: true, duration: 1.5 });
                            statusText.innerText = "✅ 已跳轉至：" + result.display_name.split(',')[0];
                            statusText.style.color = "#2e7d32";
                        } else {
                            statusText.innerText = "❌ 找不到該地點，請嘗試更精確的關鍵字";
                            statusText.style.color = "#c62828";
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        statusText.innerText = "🚨 搜尋連線失敗。";
                        statusText.style.color = "#c62828";
                    });
            }

            const catNames = { textbook: '📘 教科書', daily: '🧼 生活用品', electronics: '🔌 電子產品' };

            <?php
            $stmt_map = $pdo->query("SELECT id, title, price, type, lat, lng, location_name, image_url, category FROM items WHERE item_status = 'available'");
            $map_items = $stmt_map->fetchAll();

            foreach ($map_items as $m_item) {
                if (!empty($m_item['lat']) && !empty($m_item['lng']) && $m_item['lat'] != 0) {
                    $item_type = ($m_item['type'] === 'rent') ? '⏳ 租賃' : '💰 販售';
                    $badge_color = ($m_item['type'] === 'rent') ? '#f57c00' : '#2e7d32';
                    $clean_title = htmlspecialchars($m_item['title'], ENT_QUOTES, 'UTF-8');
                    $clean_loc = htmlspecialchars($m_item['location_name'], ENT_QUOTES, 'UTF-8');
                    $clean_img = htmlspecialchars($m_item['image_url'], ENT_QUOTES, 'UTF-8');
                    $clean_cat = htmlspecialchars($m_item['category'], ENT_QUOTES, 'UTF-8');
                    $price_label = ($m_item['type'] === 'rent') ? '元 / 天' : '元';
            ?>
                    var marker = L.marker([<?php echo $m_item['lat']; ?>, <?php echo $m_item['lng']; ?>]).addTo(map);
                    var popupContent = `
                        <div style="font-family:'Nunito','Microsoft JhengHei',sans-serif;width:210px;text-align:center;">
                            <div style="text-align:left;margin-bottom:5px;">
                                <span style="font-size:11px;color:#8e24aa;background:#f3e5f5;padding:2px 7px;border-radius:10px;font-weight:700;">${catNames['<?php echo $clean_cat; ?>'] || '未分類'}</span>
                            </div>
                            <div style="width:100%;height:120px;overflow:hidden;border-radius:10px;background:#f3e5f5;margin-bottom:9px;display:flex;align-items:center;justify-content:center;border:1px solid #e1bee7;">
                                <?php if (!empty($m_item['image_url'])): ?>
                                    <img src="<?php echo $clean_img; ?>" alt="<?php echo $clean_title; ?>" style="max-width:100%;max-height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <div style="color:#ce93d8;font-size:12px;font-weight:700;">暫無圖片</div>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:13.5px;font-weight:800;color:#3d2c4e;margin-bottom:6px;text-align:left;display:flex;align-items:center;gap:5px;">
                                <span style="background:<?php echo $badge_color; ?>;color:white;font-size:11px;padding:2px 7px;border-radius:6px;white-space:nowrap;font-weight:700;"><?php echo $item_type; ?></span>
                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px;"><?php echo $clean_title; ?></span>
                            </div>
                            <div style="font-size:13px;color:#e91e8c;font-weight:900;text-align:left;margin-bottom:4px;">
                                $<?php echo number_format($m_item['price'], 0); ?> <?php echo $price_label; ?>
                            </div>
                            <div style="font-size:11.5px;color:#7b6a8d;text-align:left;margin-bottom:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                📍 <?php echo $clean_loc; ?>
                            </div>
                            <a href="item_detail.php?id=<?php echo $m_item['id']; ?>" style="display:block;background:linear-gradient(135deg,#f48fb1,#e91e8c);color:white;text-align:center;padding:7px 0;border-radius:10px;text-decoration:none;font-size:12.5px;font-weight:800;">
                                🔍 查看物品詳情
                            </a>
                        </div>
                    `;
                    marker.bindPopup(popupContent);
            <?php
                }
            }
            ?>
        </script>

        <!-- ── 最新上架 ── -->
        <h2 class="section-heading">✨ 最新上架物品</h2>

        <?php if (count($items) === 0): ?>
            <div class="empty-state">
                🌸 目前還沒有任何物品上架，快來成為第一個上架的同學吧！
            </div>
        <?php else: ?>
            <?php
            $cat_display = [
                'textbook'   => '📘 教科書',
                'daily'      => '🧼 生活用品',
                'electronics'=> '🔌 電子產品'
            ];
            ?>
            <div class="items-grid">
                <?php foreach ($items as $item): ?>
                    <div class="item-card">
                        <?php if ($item['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="item-img" alt="<?php echo htmlspecialchars($item['title']); ?>">
                        <?php else: ?>
                            <div class="item-img-placeholder">📷 暫無圖片</div>
                        <?php endif; ?>

                        <div class="item-body">
                            <span class="cat-badge">
                                <?php echo $cat_display[$item['category']] ?? '📦 未分類'; ?>
                            </span>
                            <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="item-price">
                                $<?php echo number_format($item['price'], 0); ?>
                                <?php echo $item['type'] === 'rent' ? ' / 天' : ''; ?>
                            </div>
                            <div class="item-meta">
                                類型：<span class="<?php echo $item['type'] === 'rent' ? 'item-type-rent' : 'item-type-sale'; ?>">
                                    <?php echo $item['type'] === 'rent' ? '【租賃】' : '【販售】'; ?>
                                </span><br>
                                📍 <?php echo htmlspecialchars($item['location_name']); ?><br>
                                👤 <?php echo htmlspecialchars($item['username']); ?>
                            </div>
                            <a href="item_detail.php?id=<?php echo $item['id']; ?>" class="btn-detail">查看詳情 →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div><!-- /.page-wrap -->

</body>
</html>