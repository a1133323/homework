<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// 1. 取得篩選參數，若無瀏覽器定位則給予校園預設中心點（以下為高雄大學座標範例）
$user_lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 22.7306;
$user_lng = isset($_GET['lng']) ? floatval($_GET['lng']) : 120.2857;
$distance = isset($_GET['distance']) ? intval($_GET['distance']) : 1000; // 預設 1000 公尺
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

// 2. 驗證分類安全（只允許 all 或你規定的那三類）
$allowed_categories = ['textbook', 'daily', 'electronics'];
if ($category !== 'all' && !in_array($category, $allowed_categories)) {
    $category = 'all'; 
}

// 👑 3. 改用標準問號 (?) 防錯機制：動態組合 SQL 語句與參數陣列
$params = [];
$category_clause = "";

// 根據是否篩選分類，來動態決定 SQL 的 WHERE 條件
if ($category !== 'all') {
    $category_clause = " AND category = ? ";
}

// 完美的 Haversine 距離計算公式（MySQL 標準問號版）
$sql = "SELECT *, 
        (6371000 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance 
        FROM items 
        WHERE item_status = 'available' $category_clause
        HAVING distance <= ? 
        ORDER BY distance ASC";

// 👑 嚴格按照問號在 SQL 中出現的順序，將變數壓入陣列中
$params[] = $user_lat; // 第一個問號 (計算公式中的 user_lat)
$params[] = $user_lng; // 第二個問號 (計算公式中的 user_lng)
$params[] = $user_lat; // 第三個問號 (計算公式中的 user_lat)

if ($category !== 'all') {
    $params[] = $category; // 第四個問號 (如果你有選分類的話)
}

$params[] = $distance; // 最後一個問號 (HAVING 距離限制)

// 4. 執行查詢（現在問號跟陣列數量 100% 絕對靈魂對齊，再也不會跳出 HY093 錯誤）
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>智慧校園地圖搜尋</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { font-family: 'Microsoft JhengHei', sans-serif; margin: 20px; background: #f8fafc; }
        #map { height: 550px; width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-top: 15px; }
        .filter-bar { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; gap: 15px; align-items: center; }
        .filter-bar select, .filter-bar button { padding: 8px 12px; font-size: 14px; border-radius: 4px; border: 1px solid #cbd5e0; }
        .filter-bar button { background: #007bff; color: white; border: none; cursor: pointer; font-weight: bold; }
        .filter-bar button:hover { background: #0056b3; }
        .popup-card { text-align: center; }
        .popup-card img { max-width: 90px; height: 70px; object-fit: cover; border-radius: 4px; margin-top: 5px; }
    </style>
</head>
<body>

    <h2>📍 智慧地理搜尋與地圖展示</h2>
    <p><a href="index.php">回首頁列表看全部</a></p>

    <div class="filter-bar">
        <form method="GET" action="" id="searchForm" style="margin:0; display:flex; gap:15px; align-items:center;">
            <input type="hidden" name="lat" id="form_lat" value="<?php echo $user_lat; ?>">
            <input type="hidden" name="lng" id="form_lng" value="<?php echo $user_lng; ?>">

            <div>
                <label>🧭 搜尋範圍：</label>
                <select name="distance">
                    <option value="500" <?php if($distance == 500) echo 'selected'; ?>>方圓 500m 內</option>
                    <option value="1000" <?php if($distance == 1000) echo 'selected'; ?>>方圓 1km 內</option>
                    <option value="2000" <?php if($distance == 2000) echo 'selected'; ?>>方圓 2km 內</option>
                </select>
            </div>

            <div>
                <label>🗂️ 快速切換分類：</label>
                <select name="category">
                    <option value="all" <?php if($category == 'all') echo 'selected'; ?>>全部物品 🌟</option>
                    <option value="textbook" <?php if($category == 'textbook') echo 'selected'; ?>>📘 教科書</option>
                    <option value="daily" <?php if($category == 'daily') echo 'selected'; ?>>🧼 生活用品</option>
                    <option value="electronics" <?php if($category == 'electronics') echo 'selected'; ?>>🔌 電子產品</option>
                </select>
            </div>

            <button type="button" onclick="getLocationAndSubmit()">🚀 開始精準搜尋</button>
        </form>
    </div>

    <div id="map"></div>

    <script>
        // JS 定位功能：取得當前 GPS 並提交
        function getLocationAndSubmit() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.getElementById('form_lat').value = position.coords.latitude;
                    document.getElementById('form_lng').value = position.coords.longitude;
                    document.getElementById('searchForm').submit();
                }, function(error) {
                    alert("無法獲取精確定位，將使用預設校園位置進行篩選！");
                    document.getElementById('searchForm').submit();
                });
            } else {
                document.getElementById('searchForm').submit();
            }
        }

        // 初始化地圖，並以使用者（或學校）座標為中心
        const map = L.map('map').setView([<?php echo $user_lat; ?>, <?php echo $user_lng; ?>], 15);

        // 載入地圖圖層 (OpenStreetMap)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        // 畫出搜尋範圍半透明藍色圓圈
        L.circle([<?php echo $user_lat; ?>, <?php echo $user_lng; ?>], {
            color: '#007bff', fillColor: '#007bff', fillOpacity: 0.08, radius: <?php echo $distance; ?>
        }).addTo(map);

        // 標記「我的位置」大頭針
        L.marker([<?php echo $user_lat; ?>, <?php echo $user_lng; ?>]).addTo(map)
         .bindPopup("<b>📍 您目前的位置 (中心點)</b>").openPopup();

        // 渲染後端 PHP 查出來的物品數據
        const itemsData = <?php echo json_encode($items); ?>;

        // 定義分類名稱對照（前台顯示用）
        const catNames = { textbook: '📘 教科書', daily: '🧼 生活用品', electronics: '🔌 電子產品' };

        itemsData.forEach(item => {
            // 排除掉當前定位點本身（如果是自己發布的或剛好重疊）
            if(parseFloat(item.lat) === <?php echo $user_lat; ?> && parseFloat(item.lng) === <?php echo $user_lng; ?>) return;

            const imgHtml = item.image_url ? `<img src="${item.image_url}" class="popup-card img">` : '<div style="font-size:12px; color:#aaa; margin:5px 0;">無商品圖</div>';
            const typeBadge = item.type === 'rent' ? '⏳ 租賃' : '💰 販售';
            const distMeters = parseFloat(item.distance).toFixed(0);

            const popupContent = `
                <div class="popup-card">
                    <span style="font-size:11px; color:gray; background:#edf2f7; padding:2px 6px; border-radius:10px;">${catNames[item.category] || '未分類'}</span>
                    <h4 style="margin:5px 0 2px 0;">${item.title}</h4>
                    <span style="color:#e53e3e; font-weight:bold; font-size:14px;">$${parseInt(item.price)} 元 (${typeBadge})</span><br>
                    ${imgHtml}
                    <p style="font-size:12px; margin:5px 0; color:#4a5568;">跑 🏃‍♂️ 距離約 ${distMeters} 公尺</p>
                    <a href="item_detail.php?id=${item.id}" target="_blank" style="display:inline-block; background:#2b6cb0; color:white; padding:4px 10px; text-decoration:none; border-radius:4px; font-size:12px; font-weight:bold; margin-top:3px;">查看商品詳情</a>
                </div>
            `;

            // 將物品釘在地圖上
            L.marker([item.lat, item.lng]).addTo(map).bindPopup(popupContent);
        });
    </script>
</body>
</html>