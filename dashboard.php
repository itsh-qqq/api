<?php
require_once 'core/functions.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$user_id = $_SESSION['user_id'];

// --- [ قسم محرك العمليات الفورية AJAX ] ---
if (isset($_GET['api_action'])) {
    header('Content-Type: application/json');
    $api_action = $_GET['api_action'];

    if ($api_action === 'upgrade_plan') {
        $target_plan = $_POST['plan'] ?? 'regular';
        if (array_key_exists($target_plan, $PLANS)) {
            $stmt = $db->prepare("UPDATE users SET plan = ? WHERE id = ?");
            $stmt->execute([$target_plan, $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'تمت ترقية باقة حسابك بنجاح!']);
        }
        exit;
    }

    if ($api_action === 'get_project_files') {
        $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['folder'] ?? '');
        $dir = STORAGE_DIR . 'user_' . $user_id . '/' . $folder . '/';
        $files = [];
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) $files[] = substr($file->getRealPath(), strlen($dir));
            }
        }
        echo json_encode(['status' => 'success', 'files' => $files]);
        exit;
    }

    if ($api_action === 'get_file_content') {
        $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['folder'] ?? '');
        $file = $_GET['file'] ?? '';
        $target = realpath(STORAGE_DIR . 'user_' . $user_id . '/' . $folder . '/' . $file);
        if ($target && strpos($target, realpath(STORAGE_DIR . 'user_' . $user_id . '/' . $folder)) === 0 && file_exists($target)) {
            echo json_encode(['status' => 'success', 'content' => file_get_contents($target)]);
        }
        exit;
    }

    if ($api_action === 'save_file_content') {
        $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder'] ?? '');
        $file = $_POST['file'] ?? '';
        $content = $_POST['content'] ?? '';
        $target = realpath(STORAGE_DIR . 'user_' . $user_id . '/' . $folder . '/' . $file);
        if ($target && strpos($target, realpath(STORAGE_DIR . 'user_' . $user_id . '/' . $folder)) === 0 && file_exists($target)) {
            file_put_contents($target, $content);
            echo json_encode(['status' => 'success', 'message' => 'تم تحديث الشيفرة البرمجية للموقع المستضاف فورا!']);
        }
        exit;
    }
}

// --- [ قسم الرفع السحابي ومعالجة الحذف الكلاسيكي ] ---
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$user_id]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
$my_plan = $PLANS[$me['plan']];
$user_folder = STORAGE_DIR . 'user_' . $user_id . '/';
if(!is_dir($user_folder)) mkdir($user_folder, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files']) && isset($_POST['project_name'])) {
    $p_name = trim($_POST['project_name']);
    $folder_token = clean_folder_name($p_name) . '-' . bin2hex(random_bytes(3));
    $target_dir = $user_folder . $folder_token . '/';

    if ((get_dir_size($user_folder) + $_FILES['files']['size'][0]) > $my_plan['storage']) {
        echo "<script>alert('المساحة المتاحة لباقاتك غير كافية! يرجى الترقية.'); window.location='dashboard.php';</script>";
        exit;
    }

    mkdir($target_dir, 0777, true);
    $paths = $_POST['paths'] ?? [];
    foreach ($_FILES['files']['name'] as $idx => $name) {
        if ($_FILES['files']['error'][$idx] === UPLOAD_ERR_OK) {
            $rel_path = !empty($paths[$idx]) ? $paths[$idx] : $name;
            $dest = $target_dir . $rel_path;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0777, true);
            move_uploaded_file($_FILES['files']['tmp_name'][$idx], $dest);
        }
    }
    $stmt = $db->prepare("INSERT INTO projects (user_id, project_name, folder_name) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $p_name, $folder_token]);
    header('Location: dashboard.php'); exit;
}

if (isset($_GET['delete_project'])) {
    $stmt = $db->prepare("SELECT folder_name FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['delete_project'], $user_id]);
    $folder = $stmt->fetchColumn();
    if ($folder) {
        delete_folder_recursive($user_folder . $folder);
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?"); $stmt->execute([$_GET['delete_project']]);
    }
    header('Location: dashboard.php'); exit;
}

// جلب إحصائيات الاستهلاك الكلية للحساب المفتوح
$current_size = get_dir_size($user_folder);
$max_size = $my_plan['storage'];
$storage_percentage = round(($current_size / $max_size) * 100, 2);

$stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY id DESC"); $stmt->execute([$user_id]);
$my_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إمبراطورية التحكم السحابي | VibeCloud v3</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #060609; color: #f4f4f5; font-family: system-ui, sans-serif; }
        .sidebar { background: #0c0c12; border-left: 1px solid rgba(255,255,255,0.04); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; padding-top: 25px; }
        .main-panel { margin-right: 260px; padding: 40px; }
        .bento-card { background: rgba(16, 16, 24, 0.7); backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 25px; margin-bottom: 24px; }
        .nav-link-custom { display: flex; align-items: center; gap: 15px; color: #a0aec0; padding: 12px 20px; text-decoration: none; border-radius: 12px; margin: 6px 15px; cursor: pointer; transition: 0.3s; }
        .nav-link-custom:hover, .nav-link-custom.active { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: #fff; box-shadow: 0 8px 20px rgba(99,102,241,0.15); }
        .tab-content-pane { display: none; } .tab-content-pane.active { display: block; animation: fadeUp 0.4s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .file-drop-zone { border: 2px dashed rgba(168, 85, 247, 0.3); border-radius: 16px; padding: 40px; text-align: center; cursor: pointer; background: rgba(168, 85, 247, 0.01); }
        .file-drop-zone:hover { border-color: #a855f7; background: rgba(168, 85, 247, 0.03); }
        .project-row { background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.04); border-radius: 14px; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .btn-action { background: rgba(255,255,255,0.04); border: none; color: #fff; width: 40px; height: 40px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .btn-action:hover { background: #6366f1; } .btn-delete:hover { background: #ef4444; }
    </style>
</head>
<body>

    <!-- 🎛️ القائمة الجانبية للتنقل الذكي الفوري -->
    <div class="sidebar">
        <div class="text-center mb-5 px-3">
            <h4 class="fw-bold" style="background: linear-gradient(to right, #6366f1, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><i class="bi bi-clouds-fill"></i> VibeCloud Pro</h4>
            <span class="text-muted small">إصدار المنصة SaaS v3</span>
        </div>
        <div class="nav flex-column">
            <div class="nav-link-custom active" onclick="switchBentoTab('pane-projects', this)"><i class="bi bi-collection-fill"></i> <span>مشاريعي الحية</span></div>
            <div class="nav-link-custom" onclick="switchBentoTab('pane-deploy', this)"><i class="bi bi-rocket-takeoff-fill"></i> <span>نشر مشروع ويب</span></div>
            <div class="nav-link-custom" onclick="switchBentoTab('pane-vip', this)"><i class="bi bi-gem"></i> <span>باقات VIP والملك</span></div>
            <div class="nav-link-custom" onclick="switchBentoTab('pane-api', this)"><i class="bi bi-cpu-fill"></i> <span>بوابة المطورين API</span></div>
            <a href="index.php" class="nav-link-custom mt-5 text-danger"><i class="bi bi-power"></i> <span>تسجيل الخروج</span></a>
        </div>
    </div>

    <!-- 💻 لوحة التحكم وعرض المحتويات -->
    <div class="main-panel">
        
        <!-- التبويب الأول: استعراض وإدارة كود المشاريع النشطة -->
        <div id="pane-projects" class="tab-content-pane active">
            <div class="row">
                <div class="col-xl-8">
                    <div class="bento-card">
                        <h5 class="fw-bold mb-4"><i class="bi bi-folder2-open text-primary me-2"></i> المواقع والمشاريع النشطة على السيرفر</h5>
                        <?php if(empty($my_projects)): ?>
                            <div class="text-center py-5 text-muted"><i class="bi bi-hdd-network fs-1 d-block mb-2 opacity-25"></i> لا توجد مشاريع منشورة، ارفع مشروعك الأول الآن!</div>
                        <?php else: ?>
                            <?php foreach($my_projects as $p): ?>
                                <div class="project-row">
                                    <div>
                                        <h6 class="fw-bold text-white mb-1"><?= htmlspecialchars($p['project_name']) ?></h6>
                                        <small class="text-muted"><i class="bi bi-globe"></i> preview.php?p=<?= $p['folder_name'] ?></small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="preview.php?p=<?= $p['folder_name'] ?>" target="_blank" class="btn-action"><i class="bi bi-box-arrow-up-right"></i></a>
                                        <button onclick="openLiveEditor('<?= $p['folder_name'] ?>')" class="btn-action text-warning"><i class="bi bi-code-slash"></i></button>
                                        <a href="dashboard.php?delete_project=<?= $p['id'] ?>" class="btn-action btn-delete" onclick="return confirm('تأكيد حذف المشروع؟');"><i class="bi bi-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="bento-card text-center">
                        <h6 class="text-muted mb-1">باقة الحساب الحالية:</h6>
                        <h4 class="fw-bold text-warning mb-4"><i class="bi bi-patch-check-fill"></i> <?= $my_plan['name'] ?></h4>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>المساحة: <?= round($current_size/1024/1024, 2) ?> MB</span>
                            <span><?= $storage_percentage ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 6px;"><div class="progress-bar bg-primary" style="width: <?= $storage_percentage ?>%"></div></div>
                    </div>
                </div>
            </div>

            <!-- محرر الأكواد التكتيكي الفوري المتكامل للمشاريع المرفوعة -->
            <div id="editorBoxContainer" class="bento-card d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-success m-0"><i class="bi bi-terminal-fill"></i> محرر الكود المصدري الفوري</h6>
                    <div class="d-flex gap-2">
                        <select id="editorFileSelector" class="form-select form-select-sm bg-dark text-white border-secondary" style="width:240px;" onchange="fetchFileSource()"></select>
                        <button onclick="saveFileSource()" class="btn btn-success btn-sm px-4 rounded-pill">حفظ الكود 💾</button>
                    </div>
                </div>
                <input type="hidden" id="editorTargetFolder">
                <textarea id="editorContentArea" class="form-control" rows="16" style="background:#09090c; color:#a7f3d0; font-family:monospace; font-size:14px; border:1px solid rgba(255,255,255,0.06);"></textarea>
            </div>
        </div>

        <!-- التبويب الثاني: مركز الرفع الهندسي للمجلدات المترابطة -->
        <div id="pane-deploy" class="tab-content-pane">
            <div class="bento-card" style="max-width: 760px;">
                <h5 class="fw-bold mb-3"><i class="bi bi-rocket-takeoff text-primary me-2"></i> نشر مشروع جديد بالكامل</h5>
                <p class="text-muted small">قم باختيار مجلد الويب الخاص بك، وسيتكفل النظام بإنشاء بيئة استضافة متكاملة للملفات الملحقة كـ HTML والـ CSS والـ JS والصور بنفس الهيكلية.</p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="small text-muted mb-2">اسم بيئة الاستضافة</label>
                        <input type="text" name="project_name" class="form-control bg-dark border-secondary text-white p-3" placeholder="my-portfolio-vibe" required>
                    </div>
                    <div class="file-drop-zone" onclick="document.getElementById('hiddenFolderInput').click()">
                        <i class="bi bi-folder-plus fs-1 text-primary mb-2 d-block"></i>
                        <h6>اضغط هنا لاختيار مجلد الموقع المراد نشره</h6>
                    </div>
                    <input type="file" id="hiddenFolderInput" name="files[]" webkitdirectory directory multiple class="d-none" onchange="parseFolderPaths(this)" required>
                    <div id="pathFieldsHolder"></div>
                    <button type="submit" class="btn btn-primary w-100 mt-4 p-3 fw-bold">إطلاق البث السحابي للمشروع 🚀</button>
                </form>
            </div>
        </div>

        <!-- التبويب الثالث: لوحة ترقيات الـ VIP والملك والمدفوعات -->
        <div id="pane-vip" class="tab-content-pane">
            <h4 class="fw-bold mb-4"><i class="bi bi-gem text-warning me-2"></i> ترقية رتب وعضويات الاستضافة</h4>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="bento-card text-center">
                        <h5 class="fw-bold">العادي (Regular)</h5>
                        <h2 class="fw-bold text-primary my-3">مجاني</h2>
                        <p class="small text-muted">سعة 10 MB سحابية<br>دعم ملفات الويب المترابطة<br>لا يدعم الـ API الخارجي</p>
                        <button onclick="requestUpgrade('regular')" class="btn btn-secondary w-100 rounded-pill mt-3" <?= $me['plan']==='regular'?'disabled':'' ?>>الباقة الحالية</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bento-card text-center" style="border-color: rgba(168,85,247,0.4)">
                        <h5 class="fw-bold text-purple">بريميوم (Prime)</h5>
                        <h2 class="fw-bold text-white my-3">$9 <span class="fs-6 text-muted">/ شهر</span></h2>
                        <p class="small text-muted">سعة 100 MB كاملة<br>تفعيل كامل لبوابة الـ API<br>سيرفرات معالجة فائقة السرعة</p>
                        <button onclick="requestUpgrade('premium')" class="btn btn-purple w-100 rounded-pill mt-3" <?= $me['plan']==='premium'?'disabled':'' ?>><?= $me['plan']==='premium'?'نشطة حالياً':'ترقية فورية' ?></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bento-card text-center" style="border-color: rgba(234,179,8,0.4)">
                        <h5 class="fw-bold text-warning"><i class="bi bi-crown-fill"></i> الملكي (VIP King)</h5>
                        <h2 class="fw-bold text-white my-3">$29 <span class="fs-6 text-muted">/ شهر</span></h2>
                        <p class="small text-muted">مساحة تخزين ضخمة 1 GB<br>وصول كامل وغير محدود للـ API<br>دعم فني وحماية إمبراطورية خاصة</p>
                        <button onclick="requestUpgrade('vip')" class="btn btn-warning text-dark fw-bold w-100 rounded-pill mt-3" <?= $me['plan']==='vip'?'disabled':'' ?>><?= $me['plan']==='vip'?'الملك الحالي 👑':'امتلاك رتبة الملك 👑' ?></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- التبويب الرابع: أنظمة الربط وبوابة الـ API للمطورين -->
        <div id="pane-api" class="tab-content-pane">
            <div class="bento-card">
                <h5 class="fw-bold mb-3 text-info"><i class="bi bi-terminal-fill me-2"></i> واجهة الربط البرمجي (REST API)</h5>
                <?php if($me['plan'] === 'regular'): ?>
                    <div class="alert alert-warning border-0 p-3" style="background:rgba(234,179,8,0.1); color:#fde047;"><i class="bi bi-exclamation-triangle-fill me-2"></i> يتطلب استخدام الـ API مفتاح أمان نشط، يرجى ترقية حسابك لباقة أعلى لتفعيله.</div>
                <?php else: ?>
                    <div class="mb-4">
                        <span class="small text-muted d-block mb-1">مفتاح الحماية النشط لحسابك (Bearer Token)</span>
                        <div class="p-3 bg-black rounded border border-secondary font-monospace d-flex justify-content-between align-items-center">
                            <span style="color:#a7f3d0;"><?= $me['api_token'] ?></span>
                            <button class="btn btn-dark btn-sm text-info" onclick="navigator.clipboard.writeText('<?= $me['api_token'] ?>'); alert('تم النسخ')">نسخ التوكين</button>
                        </div>
                    </div>
                    <h6>Endpoint النشر والرفع البرمجي:</h6>
                    <div class="p-3 bg-dark rounded font-monospace small text-muted"><strong>POST</strong> /api/v1.php?action=deploy</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchBentoTab(paneId, element) {
            document.querySelectorAll('.tab-content-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-link-custom').forEach(l => l.classList.remove('active'));
            document.getElementById(paneId).classList.add('active');
            element.classList.add('active');
        }

        function parseFolderPaths(input) {
            const holder = document.getElementById('pathFieldsHolder'); holder.innerHTML = '';
            for (let i = 0; i < input.files.length; i++) {
                const hidden = document.createElement('input'); hidden.type = 'hidden';
                hidden.name = 'paths[]'; hidden.value = input.files[i].webkitRelativePath || input.files[i].name;
                holder.appendChild(hidden);
            }
        }

        function requestUpgrade(plan) {
            if (confirm('تأكيد تفعيل الباقة المختارة والتحول لنظام الفوترة الجديد؟')) {
                const fd = new FormData(); fd.append('plan', plan);
                fetch('dashboard.php?api_action=upgrade_plan', { method: 'POST', body: fd })
                .then(res => res.json()).then(data => { alert(data.message); window.location.reload(); });
            }
        }

        function openLiveEditor(folder) {
            document.getElementById('editorTargetFolder').value = folder;
            fetch(`dashboard.php?api_action=get_project_files&folder=${folder}`)
            .then(res => res.json()).then(data => {
                if (data.status === 'success') {
                    const sel = document.getElementById('editorFileSelector'); sel.innerHTML = '';
                    data.files.forEach(f => { const opt = document.createElement('option'); opt.value = f; opt.innerText = f; sel.appendChild(opt); });
                    document.getElementById('editorBoxContainer').classList.remove('d-none');
                    fetchFileSource();
                }
            });
        }

        function fetchFileSource() {
            const folder = document.getElementById('editorTargetFolder').value;
            const file = document.getElementById('editorFileSelector').value;
            if(!file) return;
            fetch(`dashboard.php?api_action=get_file_content&folder=${folder}&file=${encodeURIComponent(file)}`)
            .then(res => res.json()).then(data => { if(data.status === 'success') document.getElementById('editorContentArea').value = data.content; });
        }

        function saveFileSource() {
            const folder = document.getElementById('editorTargetFolder').value;
            const file = document.getElementById('editorFileSelector').value;
            const content = document.getElementById('editorContentArea').value;
            const fd = new FormData(); fd.append('folder', folder); fd.append('file', file); fd.append('content', content);
            fetch('dashboard.php?api_action=save_file_content', { method: 'POST', body: fd })
            .then(res => res.json()).then(data => alert(data.message));
        }
    </script>
</body>
</html>