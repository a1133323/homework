<?php
// 開啟 Session 功能，登入與狀態追蹤必備
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$host = 'sql309.infinityfree.com';
$db   = 'if0_42221376_unitrade'; // 請改成你的資料庫名稱
$user = 'if0_42221376';      // 請改成你的資料庫帳號
$pass = 'iuk2DUw2rWM3US';          // 請改成你的資料庫密碼
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// =========================================================================
// 🛡️ 核心風控：全站停權即時攔截器 (優化版：完美解決背景畫面外洩問題)
// =========================================================================
if (isset($_SESSION['user_id'])) {
    
    // 每次網頁載入，都去資料庫撈取該登入用戶的最新狀態
    $stmt_check_ban = $pdo->prepare("SELECT status, banned_reason FROM users WHERE id = ?");
    $stmt_check_ban->execute([$_SESSION['user_id']]);
    $current_user_info = $stmt_check_ban->fetch();

    // 如果在資料庫中該用戶已被標記為停權 (banned)
    if ($current_user_info && $current_user_info['status'] === 'banned') {
        
        // 抓取停權原因，如果沒寫就給予預設文字
        $reason = !empty($current_user_info['banned_reason']) 
                  ? $current_user_info['banned_reason'] 
                  : "違反社群規範（如：逾期未還物品、惡意破壞或遭檢舉過多）";

        // 1. 將停權原因暫存到全新的 Session 變數中，用來帶去登入頁面顯示
        $_SESSION['kick_reason'] = $reason;

        // 2. 清除該用戶原本的登入狀態與憑證
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['role']);
        
        // 3. 【關鍵改動】使用 PHP 原生 Header 轉址，連首頁的邊都摸不到就直接被送去 auth.php
        header("Location: auth.php"); 
        
        // 4. 立即中斷後續所有 PHP 程式碼與 HTML 的渲染，確保完全不留畫面痕跡
        exit();
    }
}
?>