<?php
require_once 'core/config.php';

$folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['p'] ?? '');
if (empty($folder)) die("المشروع غير محدد.");

// التحقق من مالك المشروع
$stmt = $db->prepare("SELECT user_id FROM projects WHERE folder_name = ?");
$stmt->execute([$folder]);
$user_id = $stmt->fetchColumn();

if (!$user_id) die("الموقع المطلوب غير موجود على خوادمنا.");

$base_path = STORAGE_DIR . 'user_' . $user_id . '/' . $folder . '/';
$request_file = $_GET['file'] ?? 'index.html';
$target_file = realpath($base_path . $request_file);

// الحماية العليا لمنع الاختراق العكسي للمجلدات Path Traversal
if (!$target_file || strpos($target_file, realpath($base_path)) !== 0) {
    http_response_code(403); die("وصول غير مصرح به خارج البيئة المعزولة لموقعك.");
}

if (!file_exists($target_file)) {
    http_response_code(404); die("الملف المطلوبة استعراضه غير موجود.");
}

$ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
$mimes = [
    'html' => 'text/html', 'css' => 'text/css', 'js' => 'application/javascript',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'json' => 'application/json'
];

header("Content-Type: " . ($mimes[$ext] ?? 'text/plain'));
readfile($target_file);