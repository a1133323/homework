<?php
// cron_send_reminder.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php'; 

// 手動引入 PHPMailer 核心檔案
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    // 1. 計算明天的日期（格式：YYYY-MM-DD）
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    // 2. 撈出符合條件的租賃訂單
    $sql = "SELECT orders.id AS order_id, orders.end_date, orders.total_price, orders.buyer_id,
                   items.title AS item_title,
                   buyer.username AS buyer_name, buyer.email AS buyer_email,
                   seller.username AS seller_name
            FROM orders
            JOIN items ON orders.item_id = items.id
            JOIN users buyer ON orders.buyer_id = buyer.id
            JOIN users seller ON items.owner_id = seller.id
            WHERE orders.order_type = 'rent' 
              AND orders.end_date = ? 
              AND items.item_status = 'renting'
              AND orders.actual_return_date IS NULL"; //

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tomorrow]);
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($reminders)) {
        echo "[" . date('Y-m-d H:i:s') . "] 目前沒有明天（{$tomorrow}）需要歸還的物品。\n";
        exit;
    }

    // 3. 初始化 PHPMailer 設定
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';                        
    $mail->SMTPAuth   = true;                                    
    $mail->Username   = '';         
    $mail->Password   = '';            
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;          
    $mail->Port       = 587;                                     
    $mail->CharSet    = 'UTF-8';                                 
    $mail->setFrom('', 'UniTrade 校園易平台');

    // 👑 修正重點：將 type 欄位的值綁定為 'reminder'，完美符合資料庫的 enum 規定！
    $noti_sql = "INSERT INTO notifications (user_id, content, type, is_read) VALUES (?, ?, 'reminder', 0)"; //
    $noti_stmt = $pdo->prepare($noti_sql);

    // 4. 跑迴圈發送
    foreach ($reminders as $row) {
        $buyer_id = $row['buyer_id'];
        $item_title = $row['item_title'];
        $end_date = $row['end_date'];
        
        // 站內通知文字
        $msg_content = "⏰ 歸還提醒：您租借的「{$item_title}」將於明天（{$end_date}）到期，請記得主動聯繫賣家進行面交歸還喔！";
        
        try {
            // 寫入通知表
            $noti_stmt->execute([$buyer_id, $msg_content]); 
            echo "[🔔 站內通知] 已成功寫入買家 ID: {$buyer_id} 的通知中心。\n";
        } catch (PDOException $ex) {
            echo "[❌ 站內通知失敗] 無法寫入資料庫: " . $ex->getMessage() . "\n";
        }

        // 發送 Email
        try {
            $mail->clearAddresses(); 
            $mail->addAddress($row['buyer_email'], $row['buyer_name']);

            $mail->isHTML(true);
            $mail->Subject = "【UniTrade 歸還提醒】⏰ 提醒同學，您租借的「{$item_title}」明天到期囉！";
            $mail->Body = "
                <div style='font-family: \"Microsoft JhengHei\", sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #3182ce;'>📚 UniTrade 校園物品歸還提醒</h2>
                    <p>親愛的 <strong>{$row['buyer_name']}</strong> 同學您好：</p>
                    <p>您在平台上向 <strong>{$row['seller_name']}</strong> 同學租借的物品即將到期，特此發信提醒您記得按時面交歸還。</p>
                    <div style='background-color: #f7fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #3182ce;'>
                        <ul style='list-style: none; padding: 0; margin: 0; line-height: 1.8;'>
                            <li><strong>📦 租借物品：</strong> {$item_title}</li>
                            <li><strong>⏳ 應歸還日期：</strong> <span style='color: #e53e3e; font-weight: bold;'>{$end_date}</span> (明天)</li>
                            <li><strong>💰 租金總計：</strong> \${$row['total_price']} 元</li>
                        </ul>
                    </div>
                    <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #a0aec0; text-align: center;'>本信件由 UniTrade 系統自動發出，請勿直接回覆。</p>
                </div>
            ";

            $mail->send();
            echo "[✉️ Email 成功] 已寄送電子郵件給：{$row['buyer_name']}\n\n";

        } catch (Exception $e) {
            echo "[❌ Email 失敗] 無法寄信給 {$row['buyer_name']}。原因: {$mail->ErrorInfo}\n\n";
        }
    }

} catch (PDOException $e) {
    echo "資料庫查詢錯誤: " . $e->getMessage() . "\n";
}