<?php
require_once 'core/functions.php';

if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$error = ''; $message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($action === 'login') {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        } else { $error = "بيانات الدخول غير صحيحة، الفايب مش تمام!"; }
    } elseif ($action === 'register') {
        if (strlen($username) < 3 || strlen($password) < 6) {
            $error = "اسم المستخدم يجب أن يتجاوز حرفين والرمز 5 خانات.";
        } else {
            try {
                $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
                $api_token = bin2hex(random_bytes(16));
                $stmt = $db->prepare("INSERT INTO users (username, password, api_token) VALUES (?, ?, ?)");
                $stmt->execute([$username, $hashed_pass, $api_token]);
                $message = "تم إنشاء إمبراطوريتك بنجاح! سجل دخولك الآن.";
            } catch (PDOException $e) { $error = "اسم المستخدم مسجل مسبقاً!"; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>VibeCloud | بوابة الانطلاق السحابي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: radial-gradient(circle at center, #13122b 0%, #050508 100%); color: #fff; font-family: system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .glass-card { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 24px; padding: 40px; width: 100%; max-width: 440px; box-shadow: 0 30px 60px rgba(0,0,0,0.5); }
        .nav-pills .nav-link { color: #aaa; border-radius: 10px; }
        .nav-pills .nav-link.active { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; }
        .form-control { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: white; border-radius: 12px; padding: 12px; }
        .form-control:focus { background: rgba(255,255,255,0.08); color: #fff; border-color: #6366f1; box-shadow: 0 0 15px rgba(99,102,241,0.3); }
        .btn-neon { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); border: none; color: white; padding: 12px; border-radius: 12px; font-weight: 600; width: 100%; transition: 0.3s; }
        .btn-neon:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(168, 85, 247, 0.4); }
    </style>
</head>
<body>

<div class="glass-card">
    <div class="text-center mb-4">
        <h2 class="fw-bold" style="background: linear-gradient(to right, #6366f1, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">VibeCloud Engine</h2>
        <p class="text-muted small">منصة الاستضافة السحابية للمطورين المحترفين</p>
    </div>

    <ul class="nav nav-pills nav-justified mb-4">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#loginPane" type="button">تسجيل الدخول</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#registerPane" type="button">حساب جديد</button></li>
    </ul>

    <?php if($error): ?> <div class="alert alert-danger py-2 text-center" style="background:rgba(239,68,68,0.1); border:1px solid #ef4444; color:#fc8181;"><?= $error ?></div> <?php endif; ?>
    <?php if($message): ?> <div class="alert alert-success py-2 text-center" style="background:rgba(16,185,129,0.1); border:1px solid #10b981; color:#a7f3d0;"><?= $message ?></div> <?php endif; ?>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="loginPane">
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="mb-3"><label class="text-muted small mb-1">اسم المستخدم</label><input type="text" name="username" class="form-control" required></div>
                <div class="mb-4"><label class="text-muted small mb-1">كلمة المرور</label><input type="password" name="password" class="form-control" required></div>
                <button type="submit" class="btn btn-neon">دخول المنصة</button>
            </form>
        </div>
        <div class="tab-pane fade" id="registerPane">
            <form method="POST">
                <input type="hidden" name="action" value="register">
                <div class="mb-3"><label class="text-muted small mb-1">اسم مستخدم جديد</label><input type="text" name="username" class="form-control" required></div>
                <div class="mb-4"><label class="text-muted small mb-1">كلمة مرور قوية</label><input type="password" name="password" class="form-control" required></div>
                <button type="submit" class="btn btn-neon">إنشاء إمبراطوريتك</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>