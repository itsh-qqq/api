<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

define('BASE_DIR', dirname(__DIR__));
define('STORAGE_DIR', BASE_DIR . '/storage/');
define('DB_FILE', BASE_DIR . '/core/vibecloud_ultimate.db');

if (!is_dir(STORAGE_DIR)) mkdir(STORAGE_DIR, 0777, true);

// ميزات وإعدادات باقات الـ VIP والعادي والملك
$PLANS = [
    'regular' => ['name' => 'العادي (Regular)', 'storage' => 10 * 1024 * 1024, 'api' => 0, 'price' => 'مجاني'],
    'premium' => ['name' => 'بريميوم (Prime)', 'storage' => 100 * 1024 * 1024, 'api' => 1, 'price' => '$9 / شهرياً'],
    'vip'     => ['name' => 'الملكي (VIP King)', 'storage' => 1024 * 1024 * 1024, 'api' => 1, 'price' => '$29 / شهرياً']
];

try {
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // إنشاء جدول المستخدمين
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        role TEXT DEFAULT 'user',
        plan TEXT DEFAULT 'regular',
        api_token TEXT UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // إنشاء جدول المشاريع
    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        project_name TEXT,
        folder_name TEXT UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // حساب مسؤول نظام (أدمن) تلقائي عند التشغيل لأول مرة
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $admin_pass = password_hash('admin123', PASSWORD_BCRYPT);
        $api_token = bin2hex(random_bytes(16));
        $db->exec("INSERT INTO users (username, password, role, plan, api_token) VALUES ('admin', '$admin_pass', 'admin', 'vip', '$api_token')");
    }
} catch (PDOException $e) {
    die("فشل في الاتصال بنواة النظام: " . $e->getMessage());
}
