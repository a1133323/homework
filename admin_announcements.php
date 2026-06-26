<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<h2>🚨 權限不足！</h2>";
    exit;
}

$msg = "";

// 處理新增公告
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_ann') {
    $content = trim($_POST['ann_content']);
    
    if (!empty($content)) {
        // 為了簡單實用，我們可以直接擴充一個公告資料表，或者簡化存在系統參數表。
        // 這裡假設你有在第一步建立 `announcements` 表格
        $stmt = $pdo->prepare("INSERT INTO announcements (content, created_by) VALUES (?, ?)");
        $stmt->execute([$content, $_SESSION['user_id']]);
        $msg = "全站重要公告已成功發布！";
    }
}

// 撈出歷史公告紀錄
// 如果你還沒建 announcements 表格，此頁面最下方列表會先噴錯，可以先執行 SQL：
// CREATE TABLE announcements (id INT AUTO_INCREMENT PRIMARY KEY, content TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, created_by INT);
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>系統參數與公告配置 - 管理員後台</title>
    <style>
        body { font-family: "Microsoft JhengHei", sans-serif; margin: 0; display: flex; background: #f7fafc; color: #2d3748; }
        .sidebar { width: 260px; background: #1a202c; color: white; min-height: 100vh; padding: 20px; box-sizing: border-box; position: fixed; }
        .sidebar h2 { text-align: center; font-size: 20px; margin-bottom: 30px; color: #63b3ed; }
        .sidebar .admin-info { font-size: 13px; color: #a0aec0; text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #4a5568; }
        .sidebar a { display: block; color: #cbd5e0; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 6px; font-size: 15px; }
        .sidebar a:hover, .sidebar a.active { background: #3182ce; color: white; font-weight: bold; }
        .main-content { flex: 1; margin-left: 260px; padding: 40px; box-sizing: border-box; min-width: 800px; }
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 25px; }
        .content-card h3 { margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px; }
        textarea { width: 100%; height: 100px; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; resize: none; box-sizing: border-box; font-size: 14px; }
        .btn-submit { background: #3182ce; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>UniTrade 後台管理</h2>
        <div class="admin-info">👨‍💻 當前管理員：<?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
        <a href="admin.php">📊 營運主控台 & 風控</a>
        <a href="admin_reviews.php">🔍 全站內容與檢舉審核</a>
        <a href="admin_announcements.php" class="active">📢 系統配置與全站公告</a>
        <a href="index.php" style="margin-top: 60px; background: #e53e3e; color: white; text-align: center;">🔙 返回平台首頁</a>
    </div>

    <div class="main-content">
        <h2>📢 系統參數配置 & 全站公告發布</h2>

        <?php if(!empty($msg)): ?>
            <p style='color:#2f855a; font-weight:bold; background:#f0fff4; padding:15px; border-radius:6px; border:1px solid #c6f6d5; margin-bottom:25px;'>📢 <?= $msg ?></p>
        <?php endif; ?>

        <div class="content-card">
            <h3>✍️ 發布全新全站公告</h3>
            <form action="admin_announcements.php" method="POST">
                <input type="hidden" name="action" value="add_ann">
                <textarea name="ann_content" placeholder="請輸入欲推播給全站學生的公告內容...（例如：端午連假期間，請同學注意歸還時間彈性調整）" required></textarea>
                <button type="submit" class="btn-submit">🚀 確認發布公告</button>
            </form>
        </div>

        <div class="content-card">
            <h3>📜 歷史發布紀錄 (最新 5 筆)</h3>
            <ul style="padding-left: 20px; line-height: 1.8;">
                <?php foreach ($announcements as $ann): ?>
                    <li style="margin-bottom: 12px; border-bottom: 1px dashed #edf2f7; padding-bottom: 8px;">
                        <span style="color:#718096; font-size: 12px;">🕒 發布時間：<?= $ann['created_at']; ?></span><br>
                        <strong><?= htmlspecialchars($ann['content']); ?></strong>
                    </li>
                <?php endforeach; ?>
                <?php if(empty($announcements)) echo "<p style='color:#a0aec0;'>目前暫無公告紀錄。</p>"; ?>
            </ul>
        </div>
    </div>

</body>
</html>