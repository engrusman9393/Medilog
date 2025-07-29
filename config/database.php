<?php
class Database {
    private $servername = "localhost";
    private $username = "medicgeg_user";
    private $password = "SalamAsif123";
    private $dbname = "medicgeg_medilog_app_db";
    private $conn;

    public function __construct() {
        try {
            $this->conn = new PDO("mysql:host=$this->servername;dbname=$this->dbname", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->conn;
    }
    
    // Get user setting with default fallback
    public function getUserSetting($userId, $settingKey, $defaultValue = null) {
        try {
            $stmt = $this->conn->prepare("SELECT setting_value FROM user_settings_tbl WHERE user_id = ? AND setting_key = ?");
            $stmt->execute([$userId, $settingKey]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['setting_value'] : $defaultValue;
        } catch (Exception $e) {
            return $defaultValue;
        }
    }
    
    // Set user setting
    public function setUserSetting($userId, $settingKey, $settingValue) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO user_settings_tbl (user_id, setting_key, setting_value, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            return $stmt->execute([$userId, $settingKey, $settingValue, $settingValue]);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>