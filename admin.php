<?php
require_once 'db.php';

// 1. 安全防禦：檢查是否登入，且身份必須是管理員
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<h2>🚨 權限不足！此頁面僅限管理員存取。</h2>";
    echo "<a href='index.php'>回首頁</a>";
    exit;
}

// 運用 Session 來傳遞操作訊息，防止重新整理網頁時重複提交表單
$msg = "";
if (isset($_SESSION['admin_msg'])) {
    $msg = $_SESSION['admin_msg'];
    unset($_SESSION['admin_msg']); // 顯示一次後清除
}

// 2. 處理管理員執行的【停權處分】
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ban_user') {
    $ban_user_id = intval($_POST['user_id']);
    $banned_reason = trim($_POST['banned_reason']);

    if ($ban_user_id > 0 && !empty($banned_reason)) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'banned', banned_reason = ? WHERE id = ?");
        $stmt->execute([$banned_reason, $ban_user_id]);
        $_SESSION['admin_msg'] = "成功：用戶 ID: {$ban_user_id} 已執行停權處分！";
    } else {
        $_SESSION['admin_msg'] = "錯誤：停權必須輸入原因！";
    }
    header("Location: admin.php");
    exit;
}

// 3. 處理管理員執行的【解除停權】
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unban_user') {
    $unban_user_id = intval($_POST['user_id']);
    if ($unban_user_id > 0) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active', banned_reason = NULL WHERE id = ?");
        $stmt->execute([$unban_user_id]);
        $_SESSION['admin_msg'] = "成功：用戶 ID: {$unban_user_id} 已恢復正常權限。";
    }
    header("Location: admin.php");
    exit;
}

// 📊 營運統計：計算全站總交易額
$stmt_revenue = $pdo->query("SELECT SUM(total_price) FROM orders");
$total_revenue = $stmt_revenue->fetchColumn() ?? 0;

// 📊 營運統計：計算總訂單數
$stmt_orders_count = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $stmt_orders_count->fetchColumn() ?? 0;

// 📊 營運統計：地理熱點數據撈取
$stmt_geo = $pdo->query("
    SELECT items.location_name, COUNT(orders.id) as tx_count 
    FROM orders 
    JOIN items ON orders.item_id = items.id 
    GROUP BY items.location_name 
    ORDER BY tx_count DESC 
    LIMIT 5
");
$geo_data = $stmt_geo->fetchAll(PDO::FETCH_ASSOC);

// 🔍 【新增】撈取當前未處理（pending）的申訴總數，用來顯示在側邊欄提醒
$stmt_pending_appeals = $pdo->query("SELECT COUNT(*) FROM appeals WHERE status = 'pending'");
$pending_appeals_count = $stmt_pending_appeals->fetchColumn() ?? 0;

// 👥 撈出全站學生清單（調整排序：被停權者 active => banned 優先排到前面，方便關注）
$stmt_users = $pdo->query("SELECT id, username, email, role, credit_score, status, banned_reason FROM users WHERE role = 'student' ORDER BY status DESC, id DESC");
$all_students = $stmt_users->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>UniTrade 營運維運端 - 管理員後台</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: "Microsoft JhengHei", sans-serif; margin: 0; display: flex; background: #f7fafc; color: #2d3748; }
        /* 側邊欄導覽樣式 */
        .sidebar { width: 260px; background: #1a202c; color: white; min-height: 100vh; padding: 20px; box-sizing: border-box; position: fixed; }
        .sidebar h2 { text-align: center; font-size: 20px; margin-bottom: 30px; color: #63b3ed; letter-spacing: 1px; }
        .sidebar .admin-info { font-size: 13px; color: #a0aec0; text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #4a5568; }
        .sidebar a { display: block; color: #cbd5e0; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 6px; font-size: 15px; transition: 0.3s; position: relative; }
        .sidebar a:hover, .sidebar a.active { background: #3182ce; color: white; font-weight: bold; }
        
        /* 紅色提示泡泡樣式 */
        .badge { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #e53e3e; color: white; border-radius: 10px; padding: 2px 8px; font-size: 11px; font-weight: bold; }

        /* 右側主內容區 */
        .main-content { flex: 1; margin-left: 260px; padding: 40px; box-sizing: border-box; min-width: 800px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        /* 統計卡片組合 */
        .stats-container { display: flex; gap: 20px; margin-bottom: 35px; }
        .stat-card { background: white; padding: 20px 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); flex: 1; border-left: 5px solid #4299e1; }
        .stat-card.revenue { border-left-color: #48bb78; }
        .stat-card .title { font-size: 14px; color: #718096; margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: bold; color: #1a202c; }
        
        /* 區塊白底卡片 */
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 35px; }
        .content-card h3 { margin-top: 0; margin-bottom: 20px; font-size: 18px; color: #2d3748; border-bottom: 2px solid #edf2f7; padding-bottom: 10px; }
        
        /* 表格優化樣式 */
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; }
        .admin-table th { background: #ebf8ff; color: #2b6cb0; padding: 12px 15px; font-size: 14px; }
        .admin-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; font-size: 14px; vertical-align: middle; }
        .admin-table tr:hover { background: #f7fafc; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>UniTrade 後台管理</h2>
        <div class="admin-info">👨‍💻 當前管理員：<?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
        
        <a href="admin.php" class="active">📊 營運主控台 & 風控</a>
        
        <a href="admin_appeals.php">
            📝 帳號申訴審核中心
            <?php if ($pending_appeals_count > 0): ?>
                <span class="badge"><?= $pending_appeals_count; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="admin_reviews.php">🔍 全站內容與檢舉審核</a>
        <a href="admin_announcements.php">📢 系統配置與全站公告</a>
        <a href="index.php" style="margin-top: 60px; background: #e53e3e; color: white; text-align: center;">🔙 返回平台首頁</a>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1 style="margin:0; font-size: 26px;">📊 營運主控台 & 學生風控中心</h1>
        </div>

        <?php if(!empty($msg)): ?>
            <p style='color:#c53030; font-weight:bold; background:#fff5f5; padding:15px; border-radius:6px; border:1px solid #feb2b2; margin-bottom:25px;'>ℹ️ <?= htmlspecialchars($msg) ?></p>
        <?php endif; ?>

        <div class="stats-container">
            <div class="stat-card revenue">
                <div class="title">全站累積交易總金額</div>
                <div class="value">$<?= number_format($total_revenue, 0); ?> 元</div>
            </div>
            <div class="stat-card">
                <div class="title">全站媒合成功訂單數</div>
                <div class="value"><?= $total_orders; ?> 筆</div>
            </div>
        </div>

        <div class="content-card">
            <h3>📍 各校區區域面交活躍度統計 (地理熱點)</h3>
            <div style="width: 100%; max-width: 650px; margin: 0 auto;">
                <canvas id="geoChart"></canvas>
            </div>
        </div>

        <div class="content-card">
            <h3>🛡️ 學生權限與信用監控名單</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">學生 ID</th>
                        <th>學生姓名</th>
                        <th>學校信箱</th>
                        <th style="width: 100px;">信用評分</th>
                        <th style="width: 110px;">當前狀態</th>
                        <th>停權原因 (若有)</th>
                        <th style="width: 320px;">權限管制操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_students) === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #a0aec0; padding: 20px;">目前沒有任何學生資料。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($all_students as $student): ?>
                            <tr>
                                <td><?= $student['id']; ?></td>
                                <td><strong><?= htmlspecialchars($student['username']); ?></strong></td>
                                <td><?= htmlspecialchars($student['email']); ?></td>
                                <td style="font-weight:bold; color: <?= $student['credit_score'] < 80 ? '#e53e3e' : '#48bb78'; ?>;">
                                    <?= $student['credit_score']; ?> 分
                                </td>
                                <td>
                                    <?= $student['status'] === 'active' ? '<span style="color:#38a169; font-weight:bold;">● 正常</span>' : '<span style="color:#e53e3e; font-weight:bold;">❌ 已停權</span>' ?>
                                </td>
                                <td style="color:#9c4221; max-width:180px; word-break: break-all; font-size: 13px;">
                                    <?= $student['banned_reason'] ? htmlspecialchars($student['banned_reason']) : '<span style="color:#cbd5e0;">-</span>'; ?>
                                </td>
                                <td>
                                    <?php if ($student['status'] === 'active'): ?>
                                        <form action="admin.php" method="POST" style="margin:0; display: flex; gap: 6px; align-items: center;" onsubmit="return confirm('⚠️ 確定要將學生【<?= htmlspecialchars($student['username']); ?>】列入黑名單並停權嗎？\n停權後他將無法進行任何 platform 操作。')">
                                            <input type="hidden" name="action" value="ban_user">
                                            <input type="hidden" name="user_id" value="<?= $student['id']; ?>">
                                            <input type="text" name="banned_reason" placeholder="請輸入停權原因" required style="padding: 6px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 12px; flex: 1;">
                                            <button type="submit" style="background:#e53e3e; color:white; border:none; border-radius:4px; cursor:pointer; padding:6px 12px; font-size:12px; font-weight:bold; white-space:nowrap;">執行停權</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="admin.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="action" value="unban_user">
                                            <input type="hidden" name="user_id" value="<?= $student['id']; ?>">
                                            <button type="submit" style="background:#38a169; color:white; border:none; border-radius:4px; cursor:pointer; padding:6px 15px; font-size:12px; font-weight:bold;">🔓 解除限制</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const geoData = <?php echo json_encode($geo_data); ?>;
        const labels = geoData.map(item => item.location_name || '未指定地點');
        const counts = geoData.map(item => item.tx_count);

        const ctx = document.getElementById('geoChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '交易活絡次數 (筆)',
                    data: counts,
                    backgroundColor: 'rgba(66, 153, 225, 0.85)',
                    borderColor: 'rgba(66, 153, 225, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    </script>
</body>
</html>