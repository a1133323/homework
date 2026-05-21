<?php
// db.php
require_once 'config.php';

class DB {
    private $pdo;

    public function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("資料庫連線失敗，請檢查 config.php 或確認資料庫已建立。詳細原因: " . $e->getMessage());
        }
    }

    public function insertEmails($emailList) {
        $inserted = 0;
        $sql = "INSERT IGNORE INTO users (email) VALUES (:email)";
        $stmt = $this->pdo->prepare($sql);
        foreach ($emailList as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt->execute(['email' => $email]);
                if ($stmt->rowCount() > 0) $inserted++;
            }
        }
        return $inserted;
    }

    public function getAllEmails() {
        $stmt = $this->pdo->query("SELECT email FROM users ORDER BY no ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getRandomEmails($limit) {
        $stmt = $this->pdo->prepare("SELECT email FROM users ORDER BY RAND() LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getTotalCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn();
    }
}