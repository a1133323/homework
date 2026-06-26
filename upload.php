<?php
require_once 'db.php';

// 檢查是否登入，未登入則踢回登入頁
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $price = $_POST['price'];
    $type = $_POST['type']; // rent 或 sell 
    $location_name = trim($_POST['location_name']);
    $description = trim($_POST['description']);
    $owner_id = $_SESSION['user_id'];
    
    // 👑 【新增】接收前端傳來的分類，若為空則給予預設值
    $category = (!empty($_POST['category'])) ? $_POST['category'] : 'textbook';
    
    // 【核心修改】接收前端傳來的經緯度，若為空值則給 null
    $lat = (!empty($_POST['lat'])) ? $_POST['lat'] : null;
    $lng = (!empty($_POST['lng'])) ? $_POST['lng'] : null;
    
    $image_url = null;

    // 處理圖片上傳
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['item_image']['tmp_name'];
        $fileName = $_FILES['item_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // 限制附檔名
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileExtension, $allowedExtensions)) {
            // 重新命名檔案避免重複
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadFileDir = './uploads/';
            $dest_path = $uploadFileDir . $newFileName;

            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $image_url = $dest_path; // 儲存相對路徑
            } else {
                $msg = "圖片移動失敗，請檢查 uploads 資料夾權限。";
            }
        } else {
            $msg = "不支援的圖片格式！僅限 JPG, PNG, GIF。";
        }
    }

    // 寫入資料庫
    if (!empty($title) && !empty($price) && $image_url !== null) {
        // 👑 【修改】將 category 欄位與對應的問號 (?) 補進 SQL 語句中
        $stmt = $pdo->prepare("INSERT INTO items (owner_id, type, title, description, price, image_url, location_name, item_status, lat, lng, category) VALUES (?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, ?)");
        
        // 👑 【修改】執行時按順序傳入 $category 變數
        $stmt->execute([$owner_id, $type, $title, $description, $price, $image_url, $location_name, $lat, $lng, $category]);
        $msg = "物品上架成功！";
    } elseif(empty($msg)) {
        $msg = "請填寫必填欄位並上傳物品圖片！";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的倉庫 - 上架新物品</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Nunito', 'Microsoft JhengHei', sans-serif;
            background: #fdf4ff;
            background-image:
                radial-gradient(circle at 8% 5%, #fce4f388 0%, transparent 38%),
                radial-gradient(circle at 92% 95%, #e3f2fd88 0%, transparent 38%);
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
            z-index: 200;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 9px;
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
        .nav-brand-text {
            font-size: 19px;
            font-weight: 900;
            color: #c2185b;
        }
        .nav-user {
            font-size: 13px;
            color: #6d4c41;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-user a {
            color: #ad1457;
            text-decoration: none;
            background: #fce4ec;
            padding: 5px 13px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            transition: background .18s;
        }
        .nav-user a:hover { background: #f8bbd0; }

        /* ── 主容器 ── */
        .page-wrap {
            max-width: 780px;
            margin: 0 auto;
            padding: 36px 24px 70px;
        }

        /* ── 頁面標題 ── */
        .page-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .page-header .title-icon {
            font-size: 44px;
            display: block;
            margin-bottom: 8px;
            filter: drop-shadow(0 4px 8px #f48fb155);
        }
        .page-header h1 {
            font-size: 26px;
            font-weight: 900;
            color: #c2185b;
            letter-spacing: .5px;
        }
        .page-header p {
            font-size: 13.5px;
            color: #ad1457;
            margin-top: 5px;
            font-weight: 600;
        }

        /* ── 訊息提示 ── */
        .msg-box {
            background: #f0fff4;
            border: 1.5px solid #9ae6b4;
            border-radius: 14px;
            padding: 13px 18px;
            color: #276749;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 24px;
            text-align: center;
        }
        .msg-box.error {
            background: #fff5f5;
            border-color: #fc8181;
            color: #c53030;
        }

        /* ── 表單卡片 ── */
        .form-card {
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1.5px solid #f3e5f5;
            border-radius: 24px;
            padding: 32px 36px;
            box-shadow: 0 6px 28px #9c27b010;
        }

        /* ── 區塊分組 ── */
        .form-section {
            margin-bottom: 26px;
        }
        .form-section-title {
            font-size: 13px;
            font-weight: 800;
            color: #8e24aa;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .form-section-title::after {
            content: '';
            flex: 1;
            height: 1.5px;
            background: linear-gradient(90deg, #e1bee7, transparent);
            border-radius: 2px;
        }

        /* ── 欄位行 ── */
        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .field-row.single { grid-template-columns: 1fr; }
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .field-label {
            font-size: 12.5px;
            font-weight: 800;
            color: #ad1457;
        }
        .field-required {
            color: #e91e8c;
            margin-left: 2px;
        }

        /* ── 輸入元件 ── */
        .field-input,
        .field-select,
        .field-textarea {
            width: 100%;
            border: 1.5px solid #f3e5f5;
            border-radius: 14px;
            padding: 10px 15px;
            font-family: inherit;
            font-size: 13.5px;
            color: #3d2c4e;
            background: #fdf4ff88;
            transition: border-color .2s, background .2s;
            outline: none;
        }
        .field-input:focus,
        .field-select:focus,
        .field-textarea:focus {
            border-color: #ce93d8;
            background: #fff;
        }
        .field-select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23ce93d8' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-color: #fdf4ff88;
            padding-right: 36px;
            cursor: pointer;
        }
        .field-textarea { resize: vertical; line-height: 1.6; min-height: 100px; }

        /* ── 價格行 ── */
        .price-wrap {
            position: relative;
        }
        .price-wrap .field-input {
            padding-right: 36px;
        }
        .price-unit {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: #ab47bc;
            font-weight: 700;
            pointer-events: none;
        }

        /* ── Radio 模式選擇 ── */
        .radio-group {
            display: flex;
            gap: 12px;
        }
        .radio-option {
            flex: 1;
            position: relative;
        }
        .radio-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0; height: 0;
        }
        .radio-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 14px;
            border: 2px solid #f3e5f5;
            border-radius: 14px;
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 700;
            color: #9e9e9e;
            transition: all .18s;
            background: #fdf4ff44;
        }
        .radio-option input[type="radio"]:checked + .radio-label {
            border-color: #e91e8c;
            color: #c2185b;
            background: #fce4ec;
        }
        .radio-label:hover {
            border-color: #f48fb1;
            color: #ad1457;
        }

        /* ── 地圖區 ── */
        .location-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .location-row .field-input { flex: 1; }
        .btn-search-loc {
            background: linear-gradient(135deg, #ce93d8, #9c27b0);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 14px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            transition: transform .12s;
        }
        .btn-search-loc:hover { transform: scale(1.04); }

        #upload_map {
            width: 100%;
            height: 300px;
            border: 2px solid #e1bee7;
            border-radius: 18px;
            margin-bottom: 10px;
            box-shadow: 0 4px 16px #9c27b012;
            overflow: hidden;
        }
        .map-hint {
            font-size: 12.5px;
            color: #9e9e9e;
            font-weight: 600;
            margin-bottom: 4px;
        }
        #geo_status {
            font-size: 13px;
            font-weight: 700;
            min-height: 20px;
            margin-top: 4px;
        }

        /* ── 圖片上傳 ── */
        .file-upload-wrap {
            border: 2px dashed #e1bee7;
            border-radius: 16px;
            padding: 22px;
            text-align: center;
            background: #fdf4ff44;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }
        .file-upload-wrap:hover {
            border-color: #ce93d8;
            background: #f3e5f566;
        }
        .file-upload-wrap input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .file-upload-icon { font-size: 32px; margin-bottom: 6px; display: block; }
        .file-upload-text {
            font-size: 13.5px;
            font-weight: 700;
            color: #ab47bc;
        }
        .file-upload-hint {
            font-size: 12px;
            color: #bdbdbd;
            margin-top: 4px;
        }

        /* ── 送出按鈕 ── */
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #f48fb1, #e91e8c);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 20px;
            cursor: pointer;
            font-family: inherit;
            font-size: 16px;
            font-weight: 900;
            letter-spacing: .5px;
            box-shadow: 0 4px 18px #f4849055;
            transition: transform .15s, box-shadow .15s;
            margin-top: 8px;
        }
        .btn-submit:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 24px #f4849077;
        }

        /* ── RWD ── */
        @media (max-width: 560px) {
            .form-card { padding: 22px 18px; }
            .field-row { grid-template-columns: 1fr; }
            .top-nav { padding: 0 16px; }
            .page-wrap { padding: 24px 14px 60px; }
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
        <div class="nav-user">
            <span>👋 <?php echo htmlspecialchars($_SESSION['username'] ?? '使用者'); ?></span>
            <a href="index.php">← 回首頁</a>
        </div>
    </nav>

    <div class="page-wrap">

        <!-- ── 頁面標題 ── -->
        <div class="page-header">
            <span class="title-icon">📦</span>
            <h1>上架新物品</h1>
            <p>把用不到的好物分享給校園同學，讓寶貝找到新主人 🌸</p>
        </div>

        <!-- ── 訊息提示 ── -->
        <?php if (!empty($msg)): ?>
            <div class="msg-box <?php echo strpos($msg, '成功') !== false ? '' : 'error'; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- ── 主表單 ── -->
        <div class="form-card">
            <form action="upload.php" method="POST" enctype="multipart/form-data">

                <!-- 基本資訊 -->
                <div class="form-section">
                    <div class="form-section-title">✏️ 基本資訊</div>

                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label">物品名稱 <span class="field-required">*</span></label>
                            <input type="text" name="title" class="field-input" placeholder="例如：微積分課本第三版" required>
                        </div>
                        <div class="field-group">
                            <label class="field-label">物品分類 <span class="field-required">*</span></label>
                            <select name="category" class="field-select" required>
                                <option value="textbook">📘 教科書</option>
                                <option value="daily">🧼 生活用品</option>
                                <option value="electronics">🔌 電子產品</option>
                            </select>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label">上架模式 <span class="field-required">*</span></label>
                            <div class="radio-group">
                                <div class="radio-option">
                                    <input type="radio" name="type" value="rent" id="type_rent" checked>
                                    <label for="type_rent" class="radio-label">⏳ 租賃（按天計費）</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" name="type" value="sell" id="type_sell">
                                    <label for="type_sell" class="radio-label">💰 販售</label>
                                </div>
                            </div>
                        </div>
                        <div class="field-group">
                            <label class="field-label">金額 / 價格 <span class="field-required">*</span></label>
                            <div class="price-wrap">
                                <input type="number" name="price" step="0.1" class="field-input" placeholder="0" required>
                                <span class="price-unit">元</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 面交地點 -->
                <div class="form-section">
                    <div class="form-section-title">📍 面交地點</div>

                    <div class="location-row">
                        <input type="text" id="location_name" name="location_name"
                            class="field-input"
                            placeholder="在地圖上點擊定位，或輸入地址後按搜尋" required>
                        <button type="button" onclick="searchAddress()" class="btn-search-loc">🔍 搜尋定位</button>
                    </div>

                    <div id="upload_map"></div>
                    <p class="map-hint">💡 直接在地圖上點擊任意位置，大頭針會自動定位並帶入座標！</p>
                    <p id="geo_status"></p>

                    <input type="hidden" id="lat" name="lat" value="">
                    <input type="hidden" id="lng" name="lng" value="">
                </div>

                <!-- 圖片 & 介紹 -->
                <div class="form-section">
                    <div class="form-section-title">🖼️ 圖片與介紹</div>

                    <div class="field-row single" style="margin-bottom:16px;">
                        <div class="field-group">
                            <label class="field-label">物品圖片 <span class="field-required">*</span></label>
                            <div class="file-upload-wrap">
                                <input type="file" name="item_image" accept="image/*" required>
                                <span class="file-upload-icon">📷</span>
                                <div class="file-upload-text">點擊或拖曳上傳圖片</div>
                                <div class="file-upload-hint">支援 JPG、PNG、GIF 格式</div>
                            </div>
                        </div>
                    </div>

                    <div class="field-row single">
                        <div class="field-group">
                            <label class="field-label">物品詳細介紹</label>
                            <textarea name="description" class="field-textarea" placeholder="補充說明物品狀況、使用程度、附件包含哪些等等..."></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">🚀 確認上架物品</button>

            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    // 1. 初始化上架小地圖，預設中心點定在高雄大學
    var taiwanBounds = L.latLngBounds(L.latLng(21.5, 119.0), L.latLng(25.5, 122.5));
    var uploadMap = L.map('upload_map', {
        center: [22.7306, 120.2857],
        zoom: 16,
        minZoom: 10,
        maxBounds: taiwanBounds,
        maxBoundsViscosity: 1.0
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(uploadMap);

    // 2. 事先準備一個空的預設大頭針變數
    var currentMarker = null;

    // 3. 功能 A：監聽地圖上的點擊事件（滑鼠手動插針）
    uploadMap.on('click', function(e) {
        var clickedLat = e.latlng.lat;
        var clickedLng = e.latlng.lng;

        // 更新隱藏表單欄位的值
        document.getElementById('lat').value = clickedLat;
        document.getElementById('lng').value = clickedLng;
        
        var statusText = document.getElementById('geo_status');
        statusText.innerText = "✅ 已透過地圖插針鎖定座標：(" + clickedLat.toFixed(5) + ", " + clickedLng.toFixed(5) + ")";
        statusText.style.color = "#276749";

        // 移動或新增大頭針
        if (currentMarker) {
            currentMarker.setLatLng(e.latlng);
        } else {
            currentMarker = L.marker(e.latlng).addTo(uploadMap);
        }

        // 自動反向地理編碼：依據座標反查地名並填入輸入框
        var reverseUrl = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" + clickedLat + "&lon=" + clickedLng;
        fetch(reverseUrl)
            .then(response => response.json())
            .then(data => {
                if (data && data.display_name) {
                    var placeName = data.display_name.split(',')[0];
                    document.getElementById('location_name').value = "高雄大學 " + placeName;
                }
            });
    });

    // 4. 功能 B：輸入文字地址搜尋函數（融合地圖與大頭針連動）
    function searchAddress() {
        var address = document.getElementById('location_name').value.trim();
        var statusText = document.getElementById('geo_status');
        
        if (address === "") {
            alert("請先輸入面交地點名稱（例如：高雄大學管理學院）");
            return;
        }

        statusText.innerText = "⏳ 正在尋找地理座標中，請稍候...";
        statusText.style.color = "#7e57c2";

        var url = "https://nominatim.openstreetmap.org/search?format=json&q=" + encodeURIComponent(address);

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    var result = data[0];
                    var lat = parseFloat(result.lat);
                    var lng = parseFloat(result.lon);

                    // 將算出來的經緯度寫入隱藏欄位
                    document.getElementById('lat').value = lat;
                    document.getElementById('lng').value = lng;

                    statusText.innerText = "✅ 定位成功！座標已鎖定 (" + lat.toFixed(5) + ", " + lng.toFixed(5) + ")";
                    statusText.style.color = "#276749";

                    // 讓小地圖自動平移過去，並在該處移動/釘上大頭針
                    var newLatLng = new L.LatLng(lat, lng);
                    uploadMap.flyTo(newLatLng, 17);
                    
                    if (currentMarker) {
                        currentMarker.setLatLng(newLatLng);
                    } else {
                        currentMarker = L.marker(newLatLng).addTo(uploadMap);
                    }
                } else {
                    statusText.innerText = "❌ 找不到此地點的座標，請直接在下方地圖上點擊插針選點。";
                    statusText.style.color = "#c62828";
                    document.getElementById('lat').value = "";
                    document.getElementById('lng').value = "";
                }
            })
            .catch(error => {
                console.error("Error:", error);
                statusText.innerText = "🚨 連線錯誤，無法完成自動定位。";
                statusText.style.color = "#c62828";
            });
    }
    </script>

</body>
</html>