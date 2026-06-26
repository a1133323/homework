<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// 檢查使用者是否登入
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* ==========================================================
   ⭐️ 核心計算邏輯：計算「我的平均評價星等」
   ========================================================== */
// 根據你的評價系統，撈出所有別人評價目前登入用戶的星星分數
// 這裡假設你的評價表叫 reviews，被評價的人欄位是 reviewee_id，星星分數欄位是 rating
$stmt = $pdo->prepare("SELECT AVG(rating), COUNT(id) FROM reviews WHERE reviewee_id = ?");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_NUM);

$avg_stars = $result[0]; // 平均分數
$total_reviews = $result[1]; // 總評價筆數

// 如果完全沒有收到過任何評價，預設給 5.0 分；有的話四捨五入到小數點第一位
$avg_stars = ($avg_stars !== null) ? round($avg_stars, 1) : 5.0;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>UniTrade 校園易 - 我的信用中心</title>
    <style>
        body { font-family: 'Microsoft JhengHei', sans-serif; background: #f4f6f9; margin: 0; padding: 30px; color: #333; }
        .credit-container { max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; }
        .badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-weight: bold; font-size: 15px; margin-top: 15px; }
        .badge-excellent { background: #e6fffa; color: #319795; }
        .badge-good { background: #ebf8ff; color: #2b6cb0; }
        .badge-warning { background: #fffaf0; color: #dd6b20; }
        .stars-display { font-size: 48px; color: #ffb400; margin: 15px 0 5px 0; }
        .score-text { font-size: 24px; font-weight: bold; color: #2d3748; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #007bff; text-decoration: none; font-weight: bold; float: left; }
    </style>
</head>
<body>

    <div class="credit-container">
        <a href="index.php" class="back-link">← 返回首頁</a>
        <div style="clear: both;"></div>
        
        <h2>🛡️ 您的校園信用紀錄中心</h2>
        <p style="color: gray; font-size: 14px;">本分數由全校同學與您面交交易後評分累積而成。</p>
        
        <div class="stars-display">★</div>
        <div class="score-text"><?php echo $avg_stars; ?> <span style="font-size: 16px; color: gray;">/ 5.0 分</span></div>
        <p style="color: #4a5568; margin: 5px 0;">( 累計收到來自同學的評價：<b><?php echo $total_reviews; ?></b> 筆 )</p>

        <div>
            <?php if ($avg_stars >= 4.5): ?>
                <span class="badge badge-excellent">💎 極優（全校誠信模範生）</span>
            <?php elseif ($avg_stars >= 3.5): ?>
                <span class="badge badge-good">✅ 良好（守時好同學）</span>
            <?php else: ?>
                <span class="badge badge-warning">⚠️ 需注意（歷史面交表現待改善）</span>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>