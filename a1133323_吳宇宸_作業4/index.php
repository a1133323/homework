<?php
require_once 'db.php';
require_once 'mailer.php';
$db = new DB();
$message = "";

// 處理動作一：匯入 Email 建構資料庫
if (isset($_POST['action']) && $_POST['action'] === 'import') {
    $rawEmails = $_POST['emails'] ?? '';
    $emailArray = preg_split('/[\s,;]+/', $rawEmails);
    $emailArray = array_filter(array_map('trim', $emailArray));
    $count = (!empty($emailArray)) ? $db->insertEmails($emailArray) : 0;
    $message = "[ 系統通知 ]：成功注入 $count 個目標節點至名單資料庫。";
}
$totalUsers = $db->getTotalCount();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>群發控制台</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="container">
    <div class="header">
        <h1>郵件發送協定</h1>
        <div style="font-size: 0.8rem; color: #008800; letter-spacing: 2px;">加密 SMTP 傳輸協定 // 安全加密連線外殼</div>
    </div>

    <?php if($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="dashboard">
        <div class="module">
            <h2>目標資料庫注入 <span class="status-tag">連線中</span></h2>
            <p>目前掌握目標總數：<span style="color:#fff; font-weight: bold; text-shadow: 0 0 5px #00ff41;"><?php echo $totalUsers; ?></span> 個節點</p>
            <form method="POST">
                <input type="hidden" name="action" value="import">
                <div class="form-group">
                    <label>輸入目標 Email 列表 (支援換行或逗號分隔)：</label>
                    <textarea name="emails" rows="8" placeholder="請在此貼上目標信箱列表...&#10;例如：&#10;test1@mail.com&#10;test2@mail.com, test3@mail.com"></textarea>
                </div>
                <button type="submit" class="btn">執行名單寫入</button>
            </form>
        </div>

        <div class="module">
            <h2>核心酬載與寄送設定 <span class="status-tag">準備就緒</span></h2>
            <form method="POST">
                <input type="hidden" name="action" value="send_mail">
                
                <div class="dashboard" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:0;">
                    <div class="form-group">
                        <label>郵件主旨 (Subject)：</label>
                        <input type="text" name="subject" required value="【系統重要安全通知】">
                    </div>
                    <div class="form-group">
                        <label>設定寄送間隔秒數：</label>
                        <input type="number" name="interval" min="0" value="2">
                    </div>
                </div>

                <div class="form-group">
                    <label>郵件內容 (支援 HTML 原始碼)：</label>
                    <textarea name="content" rows="5" required>偵測到未授權的連線，請立即確認系統狀態。</textarea>
                </div>

                <div class="dashboard" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>發送模式：</label>
                        <select name="send_type" onchange="document.getElementById('rand_field').style.opacity = (this.value=='random'?'1':'0.2')">
                            <option value="all">全體廣播寄送 (ALL)</option>
                            <option value="random">隨機限制筆數 (RANDOM)</option>
                        </select>
                    </div>
                    <div class="form-group" id="rand_field" style="opacity:0.2; transition: opacity 0.3s;">
                        <label>隨機抽取數量：</label>
                        <input type="number" name="random_count" min="1" value="5">
                    </div>
                </div>
                <button type="submit" class="btn" style="background: #002200; border-color: #00ff41;">發動群發序列</button>
            </form>
        </div>

        <?php
        if (isset($_POST['action']) && $_POST['action'] === 'send_mail') {
            $subject = $_POST['subject'] ?? '';
            $content = $_POST['content'] ?? '';
            $sendType = $_POST['send_type'] ?? 'all';
            $randomCount = intval($_POST['random_count'] ?? 0);
            $interval = intval($_POST['interval'] ?? 0);

            $targets = ($sendType === 'random') ? $db->getRandomEmails($randomCount) : $db->getAllEmails();
            $totalTargets = count($targets);

            if ($totalTargets > 0) {
                ?>
                <div class="module full-width">
                    <h2>進度條監控序列 <span id="php_text">0 / <?php echo $totalTargets; ?> 封 (0%)</span></h2>
                    <div class="progress-wrapper">
                        <div class="progress-bar-bg">
                            <div class="progress-bar" id="php_bar"></div>
                        </div>
                    </div>
                    <div class="log-terminal" id="php_log">
                        [ 初始化 ] 正在載入郵件發送協定核心...<br>
                    </div>
                </div>
                <?php
                
                // 強制即時輸出設定
                set_time_limit(0);
                while (ob_get_level() > 0) ob_end_flush();
                ob_implicit_flush(1);

                foreach ($targets as $index => $email) {
                    $current = $index + 1;
                    $percent = round(($current / $totalTargets) * 100);
                    
                    // 執行寄信
                    $result = Mailer::send($email, $subject, $content);
                    
                    // 中文化控制台狀態字樣
                    if ($result === true) {
                        $status = "[ 成功 ]";
                        $color = "#00ff41"; // 螢光綠
                    } else {
                        $status = "[ 失敗: $result ]";
                        $color = "#ff3333"; // 警示紅
                    }
                    $line = "<span style='color:$color'>$status 正在傳送至 -> $email</span><br>";

                    // 即時將進度與中文日誌推播到網頁前端
                    echo "<script>
                        document.getElementById('php_bar').style.width = '{$percent}%';
                        document.getElementById('php_text').innerText = '{$current} / {$totalTargets} 封 ({$percent}%)';
                        document.getElementById('php_log').innerHTML += \"{$line}\";
                        document.getElementById('php_log').scrollTop = document.getElementById('php_log').scrollHeight;
                    </script>";

                    echo str_repeat(' ', 4096);
                    flush();
                    
                    // 寄送間隔延遲
                    if ($current < $totalTargets && $interval > 0) {
                        sleep($interval);
                    }
                }
                echo "<script>document.getElementById('php_log').innerHTML += \"<br><span style='color:#00ff41; font-weight:bold;'>[ 任務結束 ] 所有發送序列執行完畢。連線中斷。</span>\";</script>";
            } else {
                echo "<p style='color:#ff3333; grid-column: 1 / span 2; margin-top:20px; font-weight:bold;'>[ 核心錯誤 ]：未在資料庫中找到任何可發送的目標節點！</p>";
            }
        }
        ?>
    </div>
</div>

</body>
</html>