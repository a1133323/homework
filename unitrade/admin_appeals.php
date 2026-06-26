<?php
require_once 'db.php';

// 💡 引入 PHPMailer 核心類別與檔案
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// 1. 安全防禦：檢查是否為管理員，非管理員直接踢走
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<h2>🚨 權限不足！此頁面僅限管理員存取。</h2>";
    echo "<a href='index.php'>回首頁</a>";
    exit;
}

$msg = "";
// 運用 Session 防止管理員重新整理網頁時，重複送出審核表單
if (isset($_SESSION['appeal_admin_msg'])) {
    $msg = $_SESSION['appeal_admin_msg'];
    unset($_SESSION['appeal_admin_msg']);
}

// 2. 處理管理員的審核動作 (核准解封 或 駁回維持)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appeal_id = intval($_POST['appeal_id']);
    $target_user_id = intval($_POST['target_user_id']);
    $admin_remark = trim($_POST['admin_remark'] ?? '');

    if ($appeal_id > 0 && $target_user_id > 0) {
        if ($_POST['action'] === 'approve_appeal') {
            // 【核准申訴】: 利用資料庫事務(Transaction)確保兩邊同時成功
            $pdo->beginTransaction();
            try {
                // A. 恢復使用者狀態為正常 active，並清空原本的停權原因
                $stmt1 = $pdo->prepare("UPDATE users SET status = 'active', banned_reason = NULL WHERE id = ?");
                $stmt1->execute([$target_user_id]);

                // B. 將該筆申訴紀錄狀態改為 approved
                $stmt2 = $pdo->prepare("UPDATE appeals SET status = 'approved', admin_remark = ? WHERE id = ?");
                $stmt2->execute([$admin_remark, $appeal_id]);

                // 🔍 在 commit 前，先撈出該學生的 Email 和暱稱以供寄信使用
                $stmt_user_info = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
                $stmt_user_info->execute([$target_user_id]);
                $target_user = $stmt_user_info->fetch();

                $pdo->commit();
                $_SESSION['appeal_admin_msg'] = "✅ 操作成功：已核准申訴！該學生已成功復權，現在可以正常登入。";

                // 💌 調用 PHPMailer 寄發自動解封通知信
                if ($target_user) {
                    $mail = new PHPMailer(true);
                    try {
                        // ⚙️ SMTP 伺服器發信設定
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';                // 學校代管於 Google，故使用 Gmail SMTP
                        $mail->SMTPAuth   = true;
                        $mail->Username   = '';         // 你的發信用信箱
                        $mail->Password   = '';               // Google 16 位元應用程式密碼
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // 啟用安全加密
                        $mail->Port       = 587;                             // TLS 連接埠
                        $mail->CharSet    = 'UTF-8';                         // 避免中文亂碼

                        // 🛠️ 解決本地端 XAMPP 沒有 SSL 憑證導致無法連線外部 SMTP 的問題
                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );

                        // 👥 收寄件人設定（發件信箱必須跟上方 Username 一致）
                        $mail->setFrom('a1133323@mail.nuk.edu.tw', 'UniTrade 校園易管理團隊');
                        $mail->addAddress($target_user['email'], $target_user['username']); 

                        // 📄 郵件內文排版 (HTML 格式)
                        $mail->isHTML(true);
                        $mail->Subject = '【UniTrade 校園易】帳號停權申訴審查結果通知';
                        $mail->Body    = "
                        <div style='font-family: \"Microsoft JhengHei\", sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 600px;'>
                            <h2 style='color: #38a169;'>🎉 您的帳號已成功解封！</h2>
                            <p>親愛的 <strong>" . htmlspecialchars($target_user['username']) . "</strong> 同學，您好：</p>
                            <p>您先前提交的帳號停權申訴案件（案件編號：#{$appeal_id}）已經由管理團隊審查完畢。</p>
                            <hr style='border: none; border-top: 1px solid #edf2f7; margin: 15px 0;'>
                            <div style='background: #f7fafc; padding: 15px; border-radius: 6px; border-left: 4px solid #38a169;'>
                                <strong>💡 審查結果：</strong> 申訴通過，帳號已立即恢復正常權限。<br>
                                <strong>📝 管理員批註：</strong> " . (!empty($admin_remark) ? htmlspecialchars($admin_remark) : "無特殊說明。") . "
                            </div>
                            <hr style='border: none; border-top: 1px solid #edf2f7; margin: 15px 0;'>
                            <p style='font-size: 14px; color: #4a5568;'>您現在已經可以重新登入平台進行交易。請共同珍惜信用評分，並遵守 UniTrade 社群規範，謝謝！</p>
                            <p style='margin-top: 30px; font-size: 12px; color: #a0aec0;'>本信件由系統自動發出，請勿直接回覆。</p>
                        </div>
                        ";

                        $mail->send();
                        $_SESSION['appeal_admin_msg'] .= "（已成功發送通知信至學生信箱！）";
                    } catch (Exception $e) {
                        // 若寄信失敗則在提示訊息後方補上註記，不影響已解封的資料庫狀態
                        $_SESSION['appeal_admin_msg'] .= " ⚠️ 但 Email 發送失敗。錯誤原因: {$mail->ErrorInfo}";
                    }
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['appeal_admin_msg'] = "❌ 操作失敗：" . $e->getMessage();
            }
        } 
        elseif ($_POST['action'] === 'reject_appeal') {
            // 【駁回申訴】: 僅更新申訴狀態為 rejected，不解封學生
            $stmt = $pdo->prepare("UPDATE appeals SET status = 'rejected', admin_remark = ? WHERE id = ?");
            $stmt->execute([$admin_remark, $appeal_id]);
            $_SESSION['appeal_admin_msg'] = "❌ 已駁回該使用者的申訴，該帳號將維持停權狀態。";
        }
    }
    
    // 轉址回原頁面，清空 POST 憑證
    header("Location: admin_appeals.php");
    exit;
}

// 3. 撈取動態小紅點：計算當前未處理（pending）的申訴總數
$stmt_badge = $pdo->query("SELECT COUNT(*) FROM appeals WHERE status = 'pending'");
$pending_appeals_count = $stmt_badge->fetchColumn() ?? 0;

// 4. 撈取所有申訴案件（利用 FIELD 排序：讓「待審核 pending」永遠排在最前面，其餘依時間倒序）
$stmt_appeals = $pdo->query("SELECT * FROM appeals ORDER BY FIELD(status, 'pending', 'approved', 'rejected'), id DESC");
$all_appeals = $stmt_appeals->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>UniTrade 後台 - 申訴案件審核中心</title>
    <style>
        body { font-family: "Microsoft JhengHei", sans-serif; margin: 0; display: flex; background: #f7fafc; color: #2d3748; }
        
        /* 側邊欄導覽樣式（與你的 admin.php 完美對齊） */
        .sidebar { width: 260px; background: #1a202c; color: white; min-height: 100vh; padding: 20px; box-sizing: border-box; position: fixed; }
        .sidebar h2 { text-align: center; font-size: 20px; margin-bottom: 30px; color: #63b3ed; letter-spacing: 1px; }
        .sidebar .admin-info { font-size: 13px; color: #a0aec0; text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #4a5568; }
        .sidebar a { display: block; color: #cbd5e0; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 6px; font-size: 15px; transition: 0.3s; position: relative; }
        .sidebar a:hover, .sidebar a.active { background: #3182ce; color: white; font-weight: bold; }
        
        /* 側邊欄紅色提示泡泡 */
        .badge { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #e53e3e; color: white; border-radius: 10px; padding: 2px 8px; font-size: 11px; font-weight: bold; }

        /* 右側主內容區 */
        .main-content { flex: 1; margin-left: 260px; padding: 40px; box-sizing: border-box; min-width: 800px; }
        
        /* 白底卡片容器 */
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 35px; }
        
        /* 表格樣式 */
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; }
        .admin-table th { background: #feebc8; color: #9c4221; padding: 12px 15px; font-size: 14px; border-bottom: 2px solid #fbd38d; }
        .admin-table td { padding: 15px; border-bottom: 1px solid #edf2f7; font-size: 14px; vertical-align: top; }
        .admin-table tr:hover { background: #fffdfa; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>UniTrade 後台管理</h2>
        <div class="admin-info">👨‍💻 當前管理員：<?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
        
        <a href="admin.php">📊 營運主控台 & 風控</a>
        
        <a href="admin_appeals.php" class="active">
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
        <h1 style="margin-top:0; font-size: 26px;">📝 被停權帳號申訴審核中心</h1>
        
        <?php if(!empty($msg)): ?>
            <p style='color:#2b6cb0; font-weight:bold; background:#ebf8ff; padding:15px; border-radius:6px; border:1px solid #bee3f8; margin-bottom:25px;'>
                ℹ️ <?= htmlspecialchars($msg) ?>
            </p>
        <?php endif; ?>

        <div class="content-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:60px;">案件ID</th>
                        <th style="width:180px;">申訴學生</th>
                        <th>學生陳述申訴理由</th>
                        <th style="width:110px;">當前狀態</th>
                        <th style="width:300px;">管理員批註與審核操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($all_appeals) === 0): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:#a0aec0; padding: 30px;">🎉 目前全站清空，沒有任何申訴案件。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($all_appeals as $ap): ?>
                            <tr>
                                <td>#<?= $ap['id']; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($ap['username']); ?></strong><br>
                                    <span style="font-size:12px; color:#718096;">學號/ID: <?= $ap['user_id']; ?></span><br>
                                    <span style="font-size:12px; color:#4a5568; word-break:break-all;"><?= htmlspecialchars($ap['email']); ?></span>
                                </td>
                                <td style="line-height:1.6; word-break:break-all;">
                                    <div style="background:#f7fafc; padding:12px; border-radius:6px; border:1px solid #edf2f7; white-space: pre-wrap; color: #2d3748;"><span style="color:#718096; font-size:12px; display:block; margin-bottom:5px;">💬 學生自述：</span><?= htmlspecialchars($ap['reason']); ?></div>
                                    <span style="font-size:11px; color:#a0aec0; display:block; margin-top:6px;">⏱️ 提交時間：<?= $ap['created_at']; ?></span>
                                </td>
                                <td>
                                    <?php if($ap['status'] === 'pending'): ?>
                                        <span style="background:#feebc8; color:#9c4221; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:12px;">⏳ 待審核</span>
                                    <?php elseif($ap['status'] === 'approved'): ?>
                                        <span style="background:#c6f6d5; color:#22543d; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:12px;">✅ 已解封</span>
                                    <?php else: ?>
                                        <span style="background:#fed7d7; color:#9b2c2c; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:12px;">❌ 已駁回</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($ap['status'] === 'pending'): ?>
                                        <form action="admin_appeals.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="appeal_id" value="<?= $ap['id']; ?>">
                                            <input type="hidden" name="target_user_id" value="<?= $ap['user_id']; ?>">
                                            
                                            <input type="text" name="admin_remark" placeholder="備註原因（例如：已口頭警告、下不為例）" style="width:100%; padding:8px; border:1px solid #cbd5e0; border-radius:4px; margin-bottom:8px; font-size:13px; box-sizing:border-box;">
                                            
                                            <div style="display:flex; gap:8px;">
                                                <button type="submit" name="action" value="approve_appeal" style="background:#38a169; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:bold; flex:1;" onclick="return confirm('確定要恢復該同學的帳號權限嗎？')">🔓 通過(解封)</button>
                                                <button type="submit" name="action" value="reject_appeal" style="background:#e53e3e; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:bold; flex:1;" onclick="return confirm('確定要駁回這筆申訴，讓該帳號維持停權嗎？')">🛑 駁回維持</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div style="font-size:13px; color:#4a5568; background: #f7fafc; padding: 10px; border-radius: 4px;">
                                            <strong>💼 管理員審查批註：</strong><br>
                                            <span style="color:#4a5568; display:block; margin-top:5px;">
                                                <?= !empty($ap['admin_remark']) ? htmlspecialchars($ap['admin_remark']) : '<span style="color:#cbd5e0;">（當時無填寫備註）</span>'; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>