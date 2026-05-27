<?php
require_once __DIR__ . '/config.php';

// حساب الحجم الكلي للملفات داخل مجلد المستخدم
function get_dir_size($dir) {
    $size = 0;
    if (!is_dir($dir)) return $size;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

// دالة الحذف التراجعي الذكي للمجلدات والملفات الفرعية
function delete_folder_recursive($target) {
    if (!is_dir($target)) return @unlink($target);
    $it = new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) {
        if ($file->isDir()) rmdir($file->getRealPath()); else unlink($file->getRealPath());
    }
    return rmdir($target);
}

// تنظيف وتأمين أسماء المجلدات البرمجية
function clean_folder_name($name) {
    $cleaned = preg_replace('/[^a-zA-Z0-9_]/', '-', $name);
    return strtolower(trim($cleaned, '-'));
}