<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// 安全防禦：僅限管理員
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<h2>🚨 權限不足！</h2>";
    exit;
}

$msg = "";

// 處理下架與發送違規通知邏輯
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ban_item') {
    $item_id = intval($_POST['item_id']);
    $report_id = intval($_POST['report_id']);
    $owner_id = intval($_POST['owner_id']);
    $item_title = trim($_POST['item_title']);

    if ($item_id > 0) {
        // 1. 將物品狀態改為 hidden
        $update_item = $pdo->prepare("UPDATE items SET item_status = 'hidden' WHERE id = ?");
        $update_item->execute([$item_id]);

        // 2. 更新檢舉工單狀態為已處置 (resolved)
        $update_report = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
        $update_report->execute([$report_id]);

        // 3. 寫入站內通知，對齊你的 type='violation' 限制！
        $noti_msg = "🚨 違規下架通知：您上架的物品「{$item_title}」因遭受檢舉且違反校園租賃規範，已被管理員強制下架。";
        $ins_noti = $pdo->prepare("INSERT INTO notifications (user_id, content, type, is_read) VALUES (?, ?, 'violation', 0)");
        $ins_noti->execute([$owner_id, $noti_msg]);

        $msg = "商品「{$item_title}」已強制下架，並已發送違規通知給該學生！";
    }
}

// 撈出所有狀態為 'pending' (待處理) 的檢舉工單
$sql = "SELECT r.id AS report_id, r.reason, r.status, i.id AS item_id, i.title AS item_title, i.owner_id
        FROM reports r
        JOIN items i ON r.item_id = i.id
        WHERE r.status = 'pending'
        ORDER BY r.id DESC";
$reports = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>UniTrade 內容審核中心 - 管理員後台</title>
    <style>
        body { font-family: "Microsoft JhengHei", sans-serif; margin: 0; display: flex; background: #f7fafc; color: #2d3748; }
        .sidebar { width: 260px; background: #1a202c; color: white; min-height: 100vh; padding: 20px; box-sizing: border-box; position: fixed; }
        .sidebar h2 { text-align: center; font-size: 20px; margin-bottom: 30px; color: #63b3ed; }
        .sidebar .admin-info { font-size: 13px; color: #a0aec0; text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #4a5568; }
        .sidebar a { display: block; color: #cbd5e0; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 6px; font-size: 15px; }
        .sidebar a:hover, .sidebar a.active { background: #3182ce; color: white; font-weight: bold; }
        .main-content { flex: 1; margin-left: 260px; padding: 40px; box-sizing: border-box; min-width: 800px; }
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .content-card h3 { margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px; }
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; }
        .admin-table th { background: #feb2b2; color: #9b2c2c; padding: 12px 15px; }
        .admin-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>UniTrade 後台管理</h2>
        <div class="admin-info">👨‍💻 當前管理員：<?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
        <a href="admin.php">📊 營運主控台 & 風控</a>
        <a href="admin_reviews.php" class="active">🔍 全站內容與檢舉審核</a>
        <a href="admin_announcements.php">📢 系統配置與全站公告</a>
        <a href="index.php" style="margin-top: 60px; background: #e53e3e; color: white; text-align: center;">🔙 返回平台首頁</a>
    </div>

    <div class="main-content">
        <h2>🔍 全站內容審核與檢舉中心</h2>

        <?php if(!empty($msg)): ?>
            <p style='color:#2f855a; font-weight:bold; background:#f0fff4; padding:15px; border-radius:6px; border:1px solid #c6f6d5; margin-bottom:25px;'>✅ <?= $msg ?></p>
        <?php endif; ?>

        <div class="content-card">
            <h3>⚠️ 待處理的檢舉商品清單</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>商品 ID</th>
                        <th>被檢舉商品名稱</th>
                        <th>檢舉原因描述</th>
                        <th>處置操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?= $r['item_id']; ?></td>
                            <td><strong><?= htmlspecialchars($r['item_title']); ?></strong></td>
                            <td style="color:#e53e3e;"><?= htmlspecialchars($r['reason']); ?></td>
                            <td>
                                <form action="admin_reviews.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="ban_item">
                                    <input type="hidden" name="item_id" value="<?= $r['item_id']; ?>">
                                    <input type="hidden" name="report_id" value="<?= $r['report_id']; ?>">
                                    <input type="hidden" name="owner_id" value="<?= $r['owner_id']; ?>">
                                    <input type="hidden" name="item_title" value="<?= htmlspecialchars($r['item_title']); ?>">
                                    <button type="submit" onclick="return confirm('確定要強制下架此违规商品嗎？')" style="background:#e53e3e; color:white; border:none; border-radius:4px; cursor:pointer; padding:6px 12px; font-size:13px;">❌ 強制下架並發送警告</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($reports)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:#718096; padding:30px;">目前全站乾淨溜溜，沒有任何待處理的檢舉。👍</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>