<?php
require_once dirname(__DIR__) . '/core/functions.php';
header('Content-Type: application/json');

$headers = getallheaders();
$auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401); echo json_encode(['status' => 'error', 'message' => 'Missing or Invalid Token.']); exit;
}

$token = substr($auth, 7);
$stmt = $db->prepare("SELECT * FROM users WHERE api_token = ?"); $stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['plan'] === 'regular') {
    http_response_code(401); echo json_encode(['status' => 'error', 'message' => 'Unauthorized or API plan upgrade required.']); exit;
}

if (($_GET['action'] ?? '') === 'deploy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = $_POST['project_name'] ?? 'API-Project';
    $folder_token = clean_folder_name($project_name) . '-api-' . bin2hex(random_bytes(3));
    $target_dir = STORAGE_DIR . 'user_' . $user['id'] . '/' . $folder_token . '/';
    
    mkdir($target_dir, 0777, true);
    $paths = $_POST['paths'] ?? [];
    
    foreach ($_FILES['files']['name'] as $idx => $name) {
        if ($_FILES['files']['error'][$idx] === UPLOAD_ERR_OK) {
            $rel_path = !empty($paths[$idx]) ? $paths[$idx] : $name;
            $dest = $target_dir . $rel_path;
            if(!is_dir(dirname($dest))) mkdir(dirname($dest), 0777, true);
            move_uploaded_file($_FILES['files']['tmp_name'][$idx], $dest);
        }
    }
    
    $stmt = $db->prepare("INSERT INTO projects (user_id, project_name, folder_name) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $project_name, $folder_token]);
    
    echo json_encode(['status' => 'success', 'message' => 'Deployed via VibeCloud API!', 'url' => 'preview.php?p=' . $folder_token]);
    exit;
}
