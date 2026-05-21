<?php
// config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // 請替換成你的資料庫帳號
define('DB_PASS', ''); // 請替換成你的資料庫密碼
define('DB_NAME', 'emailuser');

// SMTP 郵件伺服器設定 (請根據你的郵件服務商調整，例如 Gmail)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // TLS 常用 587，SSL 常用 465
define('SMTP_USER', 'yourgmail'); // 你的發信信箱
define('SMTP_PASS', 'yourpassword');   // 你的應用程式密碼
define('SMTP_FROM', 'yourgmail');
define('SMTP_FROM_NAME', '郵件刺客');

// 啟用錯誤回報（開發階段使用）
error_reporting(E_ALL);
ini_set('display_errors', 1);
