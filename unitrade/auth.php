<?php
require_once 'db.php';
$msg = "";
$show_appeal_form = false; // 是否顯示申訴表單
$appeal_user_id = 0;       // 暫存要申訴的使用者 ID
$appeal_username = "";
$appeal_email = "";

// 🛡️ 偵測是否是被系統從 db.php 即時攔截踢過來的
if (isset($_SESSION['kick_reason'])) {
    $msg = "🚨 您的帳號已被系統限制！原因：" . $_SESSION['kick_reason'];
    unset($_SESSION['kick_reason']); // 顯示一次後清除
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 【註冊邏輯】
    if ($action === 'register') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = isset($_POST['role']) ? $_POST['role'] : 'student'; // 預設為學生 

        if (!empty($username) && !empty($email) && !empty($password)) {
            // 加密密碼
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash, $role]);
                $msg = "註冊成功！請登入。";
            } catch (PDOException $e) {
                $msg = "註冊失敗，該 Email 可能已被註冊。";
            }
        } else {
            $msg = "請填寫所有欄位！";
        }
    }
    
    // 【登入邏輯】
    if ($action === 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 驗證密碼與帳號狀態
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // 🛡️ 核心風控：在登入階段直接擋下已被停權的用戶
            if ($user['status'] === 'banned') {
                $reason = !empty($user['banned_reason']) ? $user['banned_reason'] : "違反社群規範";
                $msg = "🚨 登入失敗：您的帳號目前已被系統限制！原因：{$reason}";
                
                // 密碼正確但被停權，開啟申訴表單，並自動帶入該用戶資料（確保是本人申訴）
                $show_appeal_form = true;
                $appeal_user_id = $user['id'];
                $appeal_username = $user['username'];
                $appeal_email = $user['email'];
            } else {
                // 狀態正常，紀錄 Session 資訊
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; // 重要：必須存入 role (student 或 admin)
                
                // 根據角色，將使用者分流到不同的頁面
                if ($user['role'] === 'admin') {
                    // 如果是管理員，直接跳轉到管理員後台
                    header("Location: admin.php");
                    exit;
                } else {
                    // 如果是普通學生，跳轉到前台首頁
                    header("Location: index.php");
                    exit;
                }
            }
        } else {
            $msg = "密碼錯誤，或該帳號尚未註冊！";
        }
    }

    // 🛡️ 【處理學生送出的申訴表單】
    if ($action === 'submit_appeal') {
        $user_id = intval($_POST['appeal_user_id']);
        $username = trim($_POST['appeal_username']);
        $email = trim($_POST['appeal_email']);
        $reason = trim($_POST['appeal_reason']);

        if ($user_id > 0 && !empty($reason)) {
            // 檢查是否已有「審核中」的申訴，避免重複洗板
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM appeals WHERE user_id = ? AND status = 'pending'");
            $stmt_check->execute([$user_id]);
            if ($stmt_check->fetchColumn() > 0) {
                $msg = "⚠️ 您已有申訴案件正在審核中，請耐心等待管理團隊處理，切勿重複發送。";
            } else {
                // 寫入申訴資料表 (請確保你已建立 appeals 資料表)
                $stmt_insert = $pdo->prepare("INSERT INTO appeals (user_id, username, email, reason) VALUES (?, ?, ?, ?)");
                $stmt_insert->execute([$user_id, $username, $email, $reason]);
                $msg = "✅ 申訴提交成功！管理團隊將會盡速審查您的案件。";
            }
        } else {
            $msg = "❌ 申訴失敗：請填寫具體的申訴理由！";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniTrade - 登入與註冊</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Nunito', 'Microsoft JhengHei', sans-serif;
            min-height: 100vh;
            background: #fce4ec;
            background-image:
                radial-gradient(circle at 15% 20%, #fce4f3cc 0%, transparent 45%),
                radial-gradient(circle at 85% 75%, #e3f2fdcc 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, #fffde7aa 0%, transparent 60%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* ── 頁首 ── */
        .page-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .logo-row {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .logo-icon {
            width: 52px; height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f48fb1, #ec407a);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            box-shadow: 0 4px 14px #f48fb166;
        }
        .logo-text h1 {
            font-size: 30px;
            font-weight: 800;
            color: #c2185b;
            letter-spacing: 1px;
            line-height: 1.1;
        }
        .logo-text p {
            font-size: 13px;
            color: #e91e8c;
            font-weight: 600;
            margin-top: 2px;
        }
        .deco-row {
            font-size: 22px;
            letter-spacing: 10px;
            margin-top: 6px;
        }

        /* ── 訊息提示 ── */
        .msg-box {
            width: 100%;
            max-width: 700px;
            background: #fff0f6;
            border: 1.5px solid #f48fb1;
            border-radius: 16px;
            padding: 14px 18px;
            color: #ad1457;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        /* ── 申訴表單 ── */
        .appeal-card {
            width: 100%;
            max-width: 700px;
            background: rgba(255, 253, 231, 0.92);
            border: 1.5px solid #ffe082;
            border-radius: 20px;
            padding: 22px 26px;
            margin-bottom: 20px;
        }
        .appeal-card h3 {
            font-size: 16px;
            font-weight: 800;
            color: #f57f17;
            margin-bottom: 6px;
        }
        .appeal-card .desc {
            font-size: 13px;
            color: #795548;
            margin-bottom: 14px;
            line-height: 1.6;
        }
        .appeal-meta {
            font-size: 13px;
            color: #6d4c41;
            margin-bottom: 12px;
            background: #fff8e1;
            border-radius: 10px;
            padding: 8px 12px;
            border: 1px solid #ffe082;
        }
        .appeal-card label {
            font-size: 13px;
            font-weight: 700;
            color: #5d4037;
            display: block;
            margin-bottom: 6px;
        }
        .appeal-card textarea {
            width: 100%;
            border: 1.5px solid #ffe082;
            border-radius: 12px;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 13px;
            resize: vertical;
            color: #4e342e;
            background: #fffbf0;
            transition: border-color .2s;
            line-height: 1.6;
        }
        .appeal-card textarea:focus {
            outline: none;
            border-color: #ffb300;
            background: #fff;
        }
        .btn-appeal {
            margin-top: 12px;
            background: linear-gradient(135deg, #f57c00, #ff8f00);
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13.5px;
            font-family: inherit;
            box-shadow: 0 3px 10px #f57c0055;
            transition: transform .12s;
        }
        .btn-appeal:hover { transform: scale(1.04); }

        /* ── 主卡片 ── */
        .main-card {
            width: 100%;
            max-width: 700px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1.5px solid #f8bbd0;
            border-radius: 28px;
            padding: 32px 32px 26px;
            box-shadow: 0 8px 32px #f8bbd040;
        }

        /* ── 兩欄佈局 ── */
        .form-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .form-col {
            padding: 0 24px;
        }
        .form-col:first-child {
            padding-left: 0;
            border-right: 2px dashed #f8bbd0;
        }
        .form-col:last-child {
            padding-right: 0;
        }
        .form-col h3 {
            font-size: 16px;
            font-weight: 800;
            color: #c2185b;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .form-col h3 .icon {
            font-size: 20px;
        }

        /* ── 表單元素 ── */
        .field-label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: #ad1457;
            margin-bottom: 5px;
            margin-top: 2px;
        }
        .field-input {
            width: 100%;
            border: 1.5px solid #f8bbd0;
            border-radius: 22px;
            padding: 9px 16px;
            font-family: inherit;
            font-size: 13.5px;
            color: #4a148c;
            background: rgba(252, 228, 236, 0.15);
            margin-bottom: 14px;
            transition: border-color .2s, background .2s;
        }
        .field-input:focus {
            outline: none;
            border-color: #f06292;
            background: #fff;
        }
        select.field-input {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23f06292' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
            cursor: pointer;
        }

        /* ── 按鈕 ── */
        .btn-login {
            background: linear-gradient(135deg, #f06292, #e91e8c);
            color: white;
            border: none;
            padding: 10px 26px;
            border-radius: 22px;
            cursor: pointer;
            font-weight: 800;
            font-size: 14px;
            font-family: inherit;
            letter-spacing: .5px;
            box-shadow: 0 3px 12px #f4849066;
            transition: transform .12s;
        }
        .btn-register {
            background: linear-gradient(135deg, #4fc3f7, #0288d1);
            color: white;
            border: none;
            padding: 10px 26px;
            border-radius: 22px;
            cursor: pointer;
            font-weight: 800;
            font-size: 14px;
            font-family: inherit;
            letter-spacing: .5px;
            box-shadow: 0 3px 12px #29b6f666;
            transition: transform .12s;
        }
        .btn-login:hover, .btn-register:hover { transform: scale(1.05); }

        /* ── 分隔線與頁尾 ── */
        .divider {
            height: 1.5px;
            background: linear-gradient(90deg, transparent, #f8bbd0 30%, #f8bbd0 70%, transparent);
            margin: 24px 0 16px;
        }
        .page-footer {
            text-align: center;
            font-size: 12.5px;
            color: #f48fb1;
            font-weight: 600;
            letter-spacing: .5px;
        }

        /* ── RWD ── */
        @media (max-width: 560px) {
            .form-cols { grid-template-columns: 1fr; }
            .form-col:first-child { border-right: none; border-bottom: 2px dashed #f8bbd0; padding: 0 0 22px; }
            .form-col:last-child { padding: 22px 0 0; }
            .main-card { padding: 24px 20px 20px; }
        }
    </style>
</head>
<body>

    <!-- ── 頁首 ── -->
    <div class="page-header">
        <div class="logo-row">
            <div class="logo-icon">🎓</div>
            <div class="logo-text">
                <h1>UniTrade</h1>
                <p>校園易平台 — 安全 · 友善 · 輕鬆交易</p>
            </div>
        </div>
        <div class="deco-row">📚 💖 🌸 ✨ 🎀</div>
    </div>

    <!-- ── 訊息提示 ── -->
    <?php if (!empty($msg)): ?>
        <div class="msg-box">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- ── 申訴表單 ── -->
    <?php if ($show_appeal_form): ?>
        <div class="appeal-card">
            <h3>📝 帳號停權申訴管道</h3>
            <p class="desc">如果您認為這是一場誤會，或想針對停權原因進行說明，請填寫下方申訴理由，管理團隊將盡速協助您！</p>
            
            <form action="auth.php" method="POST">
                <input type="hidden" name="action" value="submit_appeal">
                <input type="hidden" name="appeal_user_id" value="<?= $appeal_user_id; ?>">
                <input type="hidden" name="appeal_username" value="<?= htmlspecialchars($appeal_username); ?>">
                <input type="hidden" name="appeal_email" value="<?= htmlspecialchars($appeal_email); ?>">
                
                <div class="appeal-meta">
                    <strong>申訴帳號：</strong><?= htmlspecialchars($appeal_username); ?> (<?= htmlspecialchars($appeal_email); ?>)
                </div>
                
                <label>請輸入您的申訴理由或改進意願：</label>
                <textarea name="appeal_reason" rows="4" required placeholder="例如：我已與該同學取得聯絡並歸還物品、這是一場誤會...等等"></textarea>
                
                <button type="submit" class="btn-appeal">送出審查申訴</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ── 主卡片：登入 + 註冊 ── -->
    <div class="main-card">
        <div class="form-cols">

            <!-- 登入 -->
            <div class="form-col">
                <h3><span class="icon">🔑</span> 會員登入</h3>
                <form action="auth.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    
                    <label class="field-label">學校 Email</label>
                    <input type="email" name="email" required placeholder="your@school.edu.tw" class="field-input">
                    
                    <label class="field-label">密碼</label>
                    <input type="password" name="password" required placeholder="••••••••" class="field-input">
                    
                    <button type="submit" class="btn-login">登入</button>
                </form>
            </div>

            <!-- 註冊 -->
            <div class="form-col">
                <h3><span class="icon">🌸</span> 新用戶註冊</h3>
                <form action="auth.php" method="POST">
                    <input type="hidden" name="action" value="register">
                    
                    <label class="field-label">暱稱 / 姓名</label>
                    <input type="text" name="username" required placeholder="你的名字或暱稱" class="field-input">
                    
                    <label class="field-label">學校信箱</label>
                    <input type="email" name="email" required placeholder="your@school.edu.tw" class="field-input">
                    
                    <label class="field-label">密碼</label>
                    <input type="password" name="password" required placeholder="••••••••" class="field-input">
                    
                    <input type="hidden" name="role" value="student">
                    
                    <button type="submit" class="btn-register">加入我們</button>
                </form>
            </div>

        </div>

        <div class="divider"></div>
        <div class="page-footer">💌 UniTrade — 讓校園交易更簡單、更安心</div>
    </div>

</body>
</html>