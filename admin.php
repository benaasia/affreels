<?php
session_start();
define('DB_FILE', 'links.db');
define('DEFAULT_PASSWORD', 'admin123');
define('PER_PAGE', 10);
$current_version = '2.0.9';

try {
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $columns = $db->query("PRAGMA table_info(links)")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($columns, 'name');
    if (!in_array('clicks', $col_names))     $db->exec("ALTER TABLE links ADD COLUMN clicks INTEGER DEFAULT 0");
    if (!in_array('source_url', $col_names)) $db->exec("ALTER TABLE links ADD COLUMN source_url TEXT DEFAULT ''");
    if (!in_array('affiliate_id', $col_names)) $db->exec("ALTER TABLE links ADD COLUMN affiliate_id TEXT DEFAULT ''");
    if (!in_array('created_at', $col_names)) $db->exec("ALTER TABLE links ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");

    $db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT DEFAULT '')");

    $pw_row = $db->prepare("SELECT value FROM settings WHERE key = 'admin_password_hash'");
    $pw_row->execute();
    if (!$pw_row->fetch()) {
        $db->prepare("INSERT INTO settings (key, value) VALUES ('admin_password_hash', ?)")->execute([password_hash(DEFAULT_PASSWORD, PASSWORD_BCRYPT)]);
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
function getSetting($db, $key, $default = '') {
    try {
        $s = $db->prepare("SELECT value FROM settings WHERE key = ?"); $s->execute([$key]);
        $r = $s->fetch(PDO::FETCH_ASSOC); return $r ? $r['value'] : $default;
    } catch (Exception $e) { return $default; }
}
function setSetting($db, $key, $value) {
    $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)")->execute([$key, $value]);
}

$remote_api_key = trim(getSetting($db, 'remote_api_key', 'FREE-85C45DDDBF3CEADB'));
$remote_api_url = trim(getSetting($db, 'remote_api_url', 'https://app.affreel.com/v1'));
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (empty($remote_api_key) && !$is_ajax && !isset($_POST['save_branding']) && !isset($_POST['remote_api_key'])) {
    if (basename($_SERVER['PHP_SELF']) !== 'settings.php') {
        header('Location: settings.php');
        exit;
    }
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])
    && !isset($_POST['delete_slug']) && !isset($_POST['edit_slug'])
    && !isset($_POST['save_custom_domain']) && !isset($_POST['change_password'])) {
    $hash = getSetting($db, 'admin_password_hash', '');
    if (password_verify($_POST['admin_password'], $hash)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php'); exit;
    } else {
        $login_error = 'Mật khẩu không chính xác.';
    }
}

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Lấy cấu hình cơ bản (Dùng chung cho cả trang Login và Dashboard)
$site_title = getSetting($db, 'site_title', 'FbReels Pro');
$site_author = getSetting($db, 'site_author', 'ReelsLink');
$site_favicon = getSetting($db, 'site_favicon', 'image/favicon.png');
$site_logo = getSetting($db, 'site_logo', 'image/favicon.png');
$custom_domain = getSetting($db, 'custom_domain');

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_slug'])) {
        header('Content-Type: application/json');
        $slug = $_POST['delete_slug'];
        if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $slug)) { echo json_encode(['success'=>false,'message'=>'Slug không hợp lệ.']); exit; }
        $db->prepare("DELETE FROM links WHERE slug = ?")->execute([$slug]);
        echo json_encode(['success'=>true,'message'=>'Đã xóa link thành công.']); exit;
    }

    if (isset($_POST['bulk_delete_slugs'])) {
        header('Content-Type: application/json');
        $slugs_raw = json_decode($_POST['bulk_delete_slugs'], true);
        if (!is_array($slugs_raw)) { echo json_encode(['success'=>false,'message'=>'Dữ liệu không hợp lệ.']); exit; }
        
        $valid_slugs = [];
        foreach ($slugs_raw as $s) {
            if (preg_match('/^[a-zA-Z0-9]{1,10}$/', $s)) {
                $valid_slugs[] = $s;
            }
        }
        
        if (empty($valid_slugs)) { echo json_encode(['success'=>false,'message'=>'Không có link nào hợp lệ để xóa.']); exit; }
        
        $placeholders = implode(',', array_fill(0, count($valid_slugs), '?'));
        $stmt = $db->prepare("DELETE FROM links WHERE slug IN ($placeholders)");
        $stmt->execute($valid_slugs);
        echo json_encode(['success'=>true,'message'=>'Đã xóa '.count($valid_slugs).' link thành công.']); exit;
    }

    if (isset($_POST['edit_slug']) && isset($_POST['new_url'])) {
        header('Content-Type: application/json');
        $slug = $_POST['edit_slug'];
        $new_url = filter_var(trim($_POST['new_url']), FILTER_VALIDATE_URL);
        if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $slug)) { echo json_encode(['success'=>false,'message'=>'Slug không hợp lệ.']); exit; }
        if (!$new_url) { echo json_encode(['success'=>false,'message'=>'URL không hợp lệ.']); exit; }
        $db->prepare("UPDATE links SET url = ? WHERE slug = ?")->execute([$new_url, $slug]);
        echo json_encode(['success'=>true,'message'=>'Đã cập nhật link.']); exit;
    }

    if (isset($_POST['save_custom_domain'])) {
        header('Content-Type: application/json');
        $domain = trim($_POST['custom_domain'] ?? '');
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        setSetting($db, 'custom_domain', $domain);
        echo json_encode(['success'=>true,'message'=>'Đã lưu tên miền: '.($domain?:'(Chính)')]); exit;
    }

    if (isset($_POST['change_password'])) {
        header('Content-Type: application/json');
        $cur = trim($_POST['current_password'] ?? '');
        $new = trim($_POST['new_password'] ?? '');
        $cfm = trim($_POST['confirm_password'] ?? '');
        $hash = getSetting($db, 'admin_password_hash', '');
        if (!password_verify($cur, $hash)) { echo json_encode(['success'=>false,'message'=>'Mật khẩu hiện tại không đúng.']); exit; }
        if (strlen($new) < 6) { echo json_encode(['success'=>false,'message'=>'Mật khẩu mới tối thiểu 6 ký tự.']); exit; }
        if ($new !== $cfm) { echo json_encode(['success'=>false,'message'=>'Xác nhận mật khẩu không khớp.']); exit; }
        setSetting($db, 'admin_password_hash', password_hash($new, PASSWORD_BCRYPT));
        echo json_encode(['success'=>true,'message'=>'Đổi mật khẩu thành công!']); exit;
    }

    if (isset($_POST['upload_image']) && isset($_FILES['upload_image'])) {
        header('Content-Type: application/json');
        $upload_dir = __DIR__ . '/image/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file = $_FILES['upload_image'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/webp', 'image/svg+xml'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'ico', 'webp', 'svg'];
        if (!in_array($mime, $allowed_mimes) || !in_array($ext, $allowed_exts)) {
            echo json_encode(['success'=>false, 'message'=>'Định dạng file không hợp lệ hoặc bị cấm.']); exit;
        }
        if ($ext !== 'svg') {
            $check = getimagesize($file['tmp_name']);
            if ($check === false) {
                echo json_encode(['success'=>false, 'message'=>'File tải lên không phải là ảnh hợp lệ.']); exit;
            }
        }
        $new_name = 'up_' . time() . '_' . bin2hex(random_bytes(2)) . '.' . $ext;
        $target = $upload_dir . $new_name;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $rel_target = 'image/' . $new_name;
            echo json_encode(['success'=>true, 'url'=>$rel_target]); exit;
        }
        echo json_encode(['success'=>false, 'message'=>'Lỗi khi lưu file trên server.']); exit;
    }

    if (isset($_POST['save_branding'])) {
        header('Content-Type: application/json');
        setSetting($db, 'site_title', trim($_POST['site_title'] ?? ''));
        setSetting($db, 'site_desc', trim($_POST['site_desc'] ?? ''));
        setSetting($db, 'site_keywords', trim($_POST['site_keywords'] ?? ''));
        setSetting($db, 'site_author', trim($_POST['site_author'] ?? ''));
        setSetting($db, 'site_logo', trim($_POST['site_logo'] ?? ''));
        setSetting($db, 'site_favicon', trim($_POST['site_favicon'] ?? ''));
        setSetting($db, 'site_og_image', trim($_POST['site_og_image'] ?? ''));
        setSetting($db, 'site_video_url', trim($_POST['site_video_url'] ?? ''));
        setSetting($db, 'site_fb_token', trim($_POST['site_fb_token'] ?? ''));

        // Modal Settings
        setSetting($db, 'modal_enabled', trim($_POST['modal_enabled'] ?? '0'));
        setSetting($db, 'modal_icon', trim($_POST['modal_icon'] ?? '🧪'));
        setSetting($db, 'modal_title', trim($_POST['modal_title'] ?? ''));
        setSetting($db, 'modal_body', trim($_POST['modal_body'] ?? ''));
        setSetting($db, 'modal_list', trim($_POST['modal_list'] ?? ''));
        setSetting($db, 'modal_note', trim($_POST['modal_note'] ?? ''));
        setSetting($db, 'modal_button', trim($_POST['modal_button'] ?? ''));
        setSetting($db, 'modal_button_url', trim($_POST['modal_button_url'] ?? ''));
        setSetting($db, 'modal_button_new_tab', trim($_POST['modal_button_new_tab'] ?? '0'));
        echo json_encode(['success'=>true,'message'=>'Đã cập nhật cấu hình hệ thống & thông báo.']); exit;
    }
}

// --- Smart Update Handler ---
if (isset($_POST['action']) && $_POST['action'] === 'smart_update' && $is_logged_in) {
    header('Content-Type: application/json');
    $repo_api_url = "https://api.github.com/repos/benaasia/affreels/contents/";
    $raw_base_url = "https://raw.githubusercontent.com/benaasia/affreels/main/";
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: FbReels-Pro-Updater',
                'Accept: application/vnd.github.v3+json'
            ]
        ]
    ];
    $context = stream_context_create($opts);
    
    $remote_files = @file_get_contents($repo_api_url, false, $context);
    if (!$remote_files) {
        echo json_encode(['success' => false, 'message' => 'Không thể kết nối đến GitHub API.']); exit;
    }
    
    $files = json_decode($remote_files, true);
    $updated_files = [];
    $skipped_files = ['links.db', '.htaccess', 'debug.log']; // Bảo vệ các file quan trọng
    
    foreach ($files as $file) {
        if ($file['type'] !== 'file') continue;
        $filename = $file['name'];
        if (in_array($filename, $skipped_files)) continue;
        
        $local_path = __DIR__ . DIRECTORY_SEPARATOR . $filename;
        $remote_sha = $file['sha'];
        
        $should_update = true;
        if (file_exists($local_path)) {
            $local_content = file_get_contents($local_path);
            $local_sha = sha1("blob " . strlen($local_content) . "\0" . $local_content);
            if ($local_sha === $remote_sha) {
                $should_update = false;
            }
        }
        
        if ($should_update) {
            $new_content = @file_get_contents($raw_base_url . $filename, false, $context);
            if ($new_content !== false) {
                if (@file_put_contents($local_path, $new_content)) {
                    $updated_files[] = $filename;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => count($updated_files) > 0 ? 'Đã cập nhật ' . count($updated_files) . ' file.' : 'Tất cả file đã ở phiên bản mới nhất.',
        'updated' => $updated_files
    ]);
    exit;
}

// --- SHA Comparison Checker ---
if (isset($_POST['action']) && $_POST['action'] === 'check_sha_update' && $is_logged_in) {
    header('Content-Type: application/json');
    $remote_files = json_decode($_POST['remote_files'] ?? '[]', true);
    $has_update = false;
    $skipped_files = ['links.db', '.htaccess', 'debug.log'];

    foreach ($remote_files as $file) {
        if ($file['type'] !== 'file') continue;
        $filename = $file['name'];
        if (in_array($filename, $skipped_files)) continue;

        $local_path = __DIR__ . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($local_path)) {
            $has_update = true; break;
        }

        $local_content = file_get_contents($local_path);
        $local_sha = sha1("blob " . strlen($local_content) . "\0" . $local_content);
        
        if ($local_sha !== $file['sha']) {
            $has_update = true; break;
        }
    }

    echo json_encode(['success' => true, 'has_update' => $has_update]);
    exit;
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$stats = [];
$total_links = 0;
$total_clicks = 0;
$custom_domain = '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$total_pages = 1;

if ($is_logged_in) {
    $row = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(clicks),0) as clicks FROM links")->fetch(PDO::FETCH_ASSOC);
    $total_links = $row['total'];
    $total_clicks = $row['clicks'];

    // Kiểm tra trạng thái API khi ở Dashboard
    $api_warning = '';
    if ($tab === 'dashboard' && file_exists(__DIR__ . '/remote_api_helper.php')) {
        require_once __DIR__ . '/remote_api_helper.php';
        $api_res = smartCheckAPIStatus();
        if (isset($api_res['success']) && !$api_res['success']) {
            // Hiển thị mọi thông báo lỗi từ API (hết lượt, bị chặn, lỗi key, v.v.)
            $api_warning = $api_res['message'];
        }
    }

    if ($tab === 'dashboard') {
        $order = 'created_at DESC';
        switch ($sort_by) {
            case 'clicks_desc': $order = 'clicks DESC'; break;
            case 'clicks_asc': $order = 'clicks ASC'; break;
            case 'oldest': $order = 'created_at ASC'; break;
            case 'newest':
            default: 
                $order = 'created_at DESC'; break;
        }

        $count_sql = "SELECT COUNT(*) FROM links";
        $params = [];
        if ($search) {
            $count_sql .= " WHERE slug LIKE ? OR url LIKE ? OR source_url LIKE ? OR affiliate_id LIKE ?";
            $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
        }
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->execute($params);
        $filtered_count = $count_stmt->fetchColumn();
        $total_pages = max(1, ceil($filtered_count / PER_PAGE));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * PER_PAGE;

        $sql = "SELECT slug, url, source_url, affiliate_id, clicks, created_at FROM links";
        if ($search) {
            $sql .= " WHERE slug LIKE ? OR url LIKE ? OR source_url LIKE ? OR affiliate_id LIKE ?";
        }
        $sql .= " ORDER BY $order LIMIT " . PER_PAGE . " OFFSET " . $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$display_base = $custom_domain ? $protocol . "://" . $custom_domain : $base_url;

function buildQuery($overrides = []) {
    global $sort_by, $search, $page, $tab;
    $params = ['tab' => $tab];
    if ($sort_by !== 'clicks_desc') $params['sort'] = $sort_by;
    if ($search) $params['search'] = $search;
    $params = array_merge($params, $overrides);
    if (isset($params['tab']) && $params['tab'] === 'dashboard') unset($params['tab']);
    if (isset($params['sort']) && $params['sort'] === 'clicks_desc') unset($params['sort']);
    if (isset($params['page']) && $params['page'] <= 1) unset($params['page']);
    if (isset($params['search']) && $params['search'] === '') unset($params['search']);
    return $params ? '?' . http_build_query($params) : '';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ReelsLink Pro Admin - Trang quản trị hệ thống thống kê và rút gọn link.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="<?php echo htmlspecialchars($site_author); ?>">
    <title>Admin Panel — <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($site_favicon); ?>">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-body">

<?php if (!$is_logged_in): ?>
<div class="admin-login-wrapper">
    <div class="admin-login-card">
        <div class="admin-login-icon">🔐</div>
        <div class="admin-logo" style="text-align: center; margin-bottom: 1rem;">
            <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo" style="height: 40px; display: block; margin: 0 auto 10px;">
            <span style="font-weight: 700; font-size: 1.2rem;"><?php echo htmlspecialchars($site_title); ?> <span style="font-weight: 300;">Admin</span></span>
        </div>
        <p class="admin-login-subtitle">Admin Control Panel</p>
        <?php if ($login_error): ?>
            <div class="admin-alert admin-alert-error"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="admin-input-group">
                <input type="password" name="admin_password" id="admin-password" placeholder="Nhập mật khẩu quản trị..." autofocus required>
                <div class="admin-input-icon">🔑</div>
            </div>
            <button type="submit" class="admin-login-btn">Đăng nhập</button>
        </form>
        <a href="index.php" class="admin-back-link">← Quay lại trang chính</a>
    </div>
</div>

<?php else: ?>
<div class="admin-dashboard">
    <nav class="admin-topbar">
        <div class="admin-topbar-left">
            <a href="index.php" class="admin-logo-link">
                <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo" class="admin-logo-img">
                <span class="admin-logo-text"><?php echo htmlspecialchars($site_title); ?></span>
            </a>
            <span class="admin-badge">Admin</span>
        </div>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="admin-topbar-right" id="adminSidebar">
            <a href="admin.php" class="admin-nav-link <?php echo $tab==='dashboard'?'active':''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard
            </a>
            <?php if (is_dir(__DIR__ . '/master_api')): ?>
            <a href="master_api/admin_keys.php" class="admin-nav-link" style="color: #facc15; font-weight: 700;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3L15.5 7.5z"></path></svg>
                Quản lý API
            </a>
            <?php endif; ?>
            <a href="admin.php?tab=settings" class="admin-nav-link <?php echo $tab==='settings'?'active':''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Cài đặt
            </a>
            <button class="admin-nav-link" onclick="openPasswordModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Mật khẩu
            </button>
            <a href="index.php" class="admin-nav-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                Trang chủ
            </a>
            <a href="admin.php?logout=1" class="admin-nav-link admin-nav-logout">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Đăng xuất
            </a>
            <div class="admin-nav-link version-display" onclick="manualCheckUpdate()" style="opacity: 0.6; cursor: pointer; border-top: 1px solid rgba(255,255,255,0.05); margin-top: 10px; padding-top: 10px; transition: all 0.3s;" title="Nhấn để kiểm tra cập nhật">
                <span id="version-dot" style="display: inline-block; width: 6px; height: 6px; background: #10b981; border-radius: 50%; margin-right: 6px; vertical-align: middle; box-shadow: 0 0 8px #10b981;"></span>
                <span id="version-text">Phiên bản v<?php echo $current_version; ?></span>
            </div>
        </div>
    </nav>

<?php if ($tab === 'settings'): ?>
<div class="admin-page-content">
    <div class="admin-page-header">
        <h2>⚙️ Cài đặt hệ thống</h2>
    </div>

    <div class="admin-settings-card">
        <div class="admin-settings-card-header">
            <h3>🌐 Tên miền phụ (Custom Domain)</h3>
        </div>
        <p class="admin-settings-desc">
            Thêm tên miền phụ (ví dụ: <code>go.yourdomain.com</code>) để sử dụng cho link rút gọn thay cho tên miền chính.
        </p>
        <div class="admin-settings-row">
            <div class="admin-settings-label">Tên miền phụ</div>
            <input type="text" id="custom-domain-input" value="<?php echo htmlspecialchars($custom_domain); ?>" placeholder="Ví dụ: go.yourdomain.com" class="admin-settings-input">
        </div>
        <div class="admin-settings-actions">
            <button onclick="saveCustomDomain()" class="admin-settings-save">💾 Lưu tên miền</button>
        </div>
    </div>

    <div class="admin-settings-card" style="margin-top: 1.2rem;">
        <div class="admin-settings-card-header">
            <h3>🎨 Thương hiệu & SEO</h3>
        </div>
        <p class="admin-settings-desc">Tùy chỉnh thông tin hiển thị của website, Logo và các thẻ Meta SEO.</p>
        
        <div class="admin-settings-row">
            <div class="admin-settings-label">Tiêu đề trang (Title)</div>
            <input type="text" id="site-title" value="<?php echo htmlspecialchars(getSetting($db, 'site_title', 'FbReels Pro')); ?>" placeholder="Ví dụ: FbReels Pro - Công cụ rút gọn link" class="admin-settings-input">
        </div>
        
        <div class="admin-settings-row">
            <div class="admin-settings-label">Mô tả (Description)</div>
            <textarea id="site-desc" placeholder="Mô tả ngắn về website..." class="admin-settings-input" style="height: 60px; padding: 10px;"><?php echo htmlspecialchars(getSetting($db, 'site_desc', 'Hỗ trợ chuyển đổi link Shopee Affiliate và lấy nhanh link từ Fb Reels.')); ?></textarea>
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Từ khóa (Keywords)</div>
            <input type="text" id="site-keywords" value="<?php echo htmlspecialchars(getSetting($db, 'site_keywords', 'shopee affiliate, fb reels, rút gọn link')); ?>" placeholder="Cách nhau bởi dấu phẩy..." class="admin-settings-input">
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Tác giả (Author)</div>
            <input type="text" id="site-author" value="<?php echo htmlspecialchars(getSetting($db, 'site_author', 'ReelsLink')); ?>" placeholder="Tên tác giả hoặc công ty..." class="admin-settings-input">
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Logo</div>
            <div style="flex: 1; display: flex; gap: 8px;">
                <input type="text" id="site-logo" value="<?php echo htmlspecialchars(getSetting($db, 'site_logo', 'image/favicon.png')); ?>" placeholder="Link ảnh logo..." class="admin-settings-input">
                <button class="admin-action-btn" onclick="triggerUpload('site-logo')" title="Tải ảnh lên server" style="margin:0; height: 100%;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg></button>
            </div>
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Favicon</div>
            <div style="flex: 1; display: flex; gap: 8px;">
                <input type="text" id="site-favicon" value="<?php echo htmlspecialchars(getSetting($db, 'site_favicon', 'image/favicon.png')); ?>" placeholder="Link ảnh favicon..." class="admin-settings-input">
                <button class="admin-action-btn" onclick="triggerUpload('site-favicon')" title="Tải ảnh lên server" style="margin:0; height: 100%;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg></button>
            </div>
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Ảnh OG (Share)</div>
            <div style="flex: 1; display: flex; gap: 8px;">
                <input type="text" id="site-og-image" value="<?php echo htmlspecialchars(getSetting($db, 'site_og_image', 'image/og.jpg')); ?>" placeholder="Link ảnh khi chia sẻ..." class="admin-settings-input">
                <button class="admin-action-btn" onclick="triggerUpload('site-og-image')" title="Tải ảnh lên server" style="margin:0; height: 100%;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg></button>
            </div>
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Video Hướng Dẫn</div>
            <input type="url" id="site-video-url" value="<?php echo htmlspecialchars(getSetting($db, 'site_video_url', 'https://www.youtube.com/shorts/nj7U1OcOaX0')); ?>" placeholder="Link video YouTube..." class="admin-settings-input">
        </div>

        <div class="admin-settings-row" style="background: rgba(59, 130, 246, 0.05); padding: 15px; border-radius: 12px; border: 1px dashed rgba(59, 130, 246, 0.3);">
            <div class="admin-settings-label" style="color: var(--primary); font-weight: 700;">Facebook Access Token</div>
            <div style="flex: 1; display: flex; flex-direction: column; gap: 5px;">
                <input type="text" id="site-fb-token" value="<?php echo htmlspecialchars(getSetting($db, 'site_fb_token', '')); ?>" placeholder="Dán Token Facebook (AppID|AppSecret) vào đây..." class="admin-settings-input">
                <small style="color: var(--text-dim); font-size: 0.75rem; line-height: 1.5; margin-top: 8px; display: block; background: rgba(0,0,0,0.1); padding: 10px; border-radius: 8px;">
                    <strong>🛠️ Cách lấy Access Token:</strong><br>
                    1. Truy cập <a href="https://developers.facebook.com/apps" target="_blank" style="color: var(--primary); font-weight: bold; text-decoration: underline;">Facebook Developers</a> và tạo 1 App.<br>
                    2. Vào <b>Cài đặt (Settings) &gt; Cơ bản (Basic)</b>.<br>
                    3. Copy <b>ID ứng dụng (App ID)</b> và <b>Khóa bí mật (App Secret)</b>.<br>
                    4. Dán vào ô trên theo định dạng: <code>AppID|AppSecret</code>
                </small>
                <small style="color: #ef4444; font-size: 0.65rem; margin-top: 5px; display: block; font-weight: 600;">* Để trống nếu không muốn tự động debug/scrape link lên Facebook.</small>
            </div>
        </div>

        <div class="admin-settings-actions">
            <button onclick="saveBranding()" class="admin-settings-save" style="background: linear-gradient(135deg, #10b981, #059669);">💾 Lưu tất cả cài đặt</button>
        </div>
        
        <input type="file" id="admin-image-uploader" style="display:none;" onchange="handleImageUpload(this)" accept="image/*">
    </div>

    <!-- Beta Modal Notification -->
    <div class="admin-settings-card" style="margin-top: 1.2rem; border-left: 4px solid var(--secondary);">
        <div class="admin-settings-card-header">
            <h3>📢 Thông báo (Beta Modal)</h3>
        </div>
        <p class="admin-settings-desc">Cấu hình cửa sổ thông báo hiện ra khi người dùng truy cập trang chủ.</p>
        
        <div class="admin-settings-row">
            <div class="admin-settings-label">Kích hoạt thông báo</div>
            <div style="flex: 1;">
                <label class="admin-switch">
                    <input type="checkbox" id="modal-enabled" <?php echo getSetting($db, 'modal_enabled', '1') === '1' ? 'checked' : ''; ?>>
                    <span class="admin-slider"></span>
                </label>
            </div>
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Icon</div>
            <input type="text" id="modal-icon" value="<?php echo htmlspecialchars(getSetting($db, 'modal_icon', '🧪')); ?>" placeholder="Ví dụ: 🧪, 🚀, 🎁..." class="admin-settings-input" style="width: 80px;">
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Tiêu đề</div>
            <input type="text" id="modal-title" value="<?php echo htmlspecialchars(getSetting($db, 'modal_title', 'Tăng 300% Chuyển Đổi TikTok')); ?>" placeholder="Tiêu đề thông báo..." class="admin-settings-input">
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Nội dung (Đoạn văn)</div>
            <textarea id="modal-body" placeholder="Nội dung chính của thông báo..." class="admin-settings-input" style="height: 80px; padding: 10px;"><?php echo htmlspecialchars(getSetting($db, 'modal_body', 'Bạn đang mất đơn vì khách hàng phải đăng nhập lại trên trình duyệt? Hãy dùng thử **TikAff.net** - Giải pháp **Deep Link** tối ưu nhất hiện nay')); ?></textarea>
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Danh sách (Mỗi dòng 1 ý)</div>
            <textarea id="modal-list" placeholder="🚀 **Test link:** Xác nhận Fans thấy Voucher 20%..." class="admin-settings-input" style="height: 100px; padding: 10px;"><?php echo htmlspecialchars(getSetting($db, 'modal_list', "🚀 **Mở App Ngay**: Tự động mở thẳng App TikTok\n💰 **Giữ Chân Khách**: Tăng tỷ lệ chuyển đổi.\n📊 **Thống Kê**: Theo dõi click và đơn hàng thời gian thực.")); ?></textarea>
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Ghi chú (Chữ nghiêng)</div>
            <input type="text" id="modal-note" value="<?php echo htmlspecialchars(getSetting($db, 'modal_note', '* Giải pháp hoàn hảo cho KOC/Link Bio TikTok. Miễn phí 100%')); ?>" placeholder="Ghi chú nhỏ phía dưới..." class="admin-settings-input">
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Nhãn nút bấm</div>
            <input type="text" id="modal-button" value="<?php echo htmlspecialchars(getSetting($db, 'modal_button', 'Khám phá TikAff ngay!')); ?>" placeholder="Chữ hiển thị trên nút..." class="admin-settings-input">
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Link nút bấm</div>
            <input type="text" id="modal-button-url" value="<?php echo htmlspecialchars(getSetting($db, 'modal_button_url', 'https://tikaff.net/?ref=rutgon')); ?>" placeholder="https://... (Để trống nếu chỉ muốn đóng modal)" class="admin-settings-input">
        </div>

        <div class="admin-settings-row">
            <div class="admin-settings-label">Mở tab mới</div>
            <div style="flex: 1;">
                <label class="admin-switch">
                    <input type="checkbox" id="modal-button-new-tab" <?php echo getSetting($db, 'modal_button_new_tab', '1') === '1' ? 'checked' : ''; ?>>
                    <span class="admin-slider"></span>
                </label>
            </div>
        </div>

        <div class="admin-settings-actions">
            <button onclick="saveBranding()" class="admin-settings-save" style="background: var(--secondary);">💾 Lưu thông báo</button>
        </div>
    </div>

    <div class="admin-settings-card" style="margin-top: 1.2rem;">
        <div class="admin-settings-card-header">
            <h3>📋 Thông tin hệ thống</h3>
        </div>
        <div class="admin-settings-row">
            <div class="admin-settings-label">Tên miền chính</div>
            <div class="admin-settings-value"><code><?php echo $_SERVER['HTTP_HOST']; ?></code></div>
        </div>
        <div class="admin-settings-row">
            <div class="admin-settings-label">Short URL Base</div>
            <div class="admin-settings-value"><code><?php echo $display_base; ?>/s/</code></div>
        </div>
        <div class="admin-settings-row">
            <div class="admin-settings-label">Tổng link</div>
            <div class="admin-settings-value"><?php echo number_format($total_links); ?></div>
        </div>
        <div class="admin-settings-row">
            <div class="admin-settings-label">Database</div>
            <div class="admin-settings-value"><code><?php echo DB_FILE; ?></code> (<?php echo round(filesize(DB_FILE)/1024, 1); ?> KB)</div>
        </div>
        <div class="admin-settings-row">
            <div class="admin-settings-value">Mã hóa bcrypt · <a href="#" onclick="openPasswordModal(); return false;" style="color: var(--primary);">Đổi mật khẩu</a></div>
        </div>
    </div>
</div>

<?php else: ?>

    <?php if (!empty($api_warning)): ?>
    <div class="admin-settings-card" style="background: linear-gradient(135deg, #fff7ed, #ffedd5); border: 1px solid #fdba74; margin-bottom: 1.5rem; padding: 1.2rem; border-radius: 12px; display: flex; align-items: center; gap: 15px; animation: slideDown 0.4s ease-out;">
        <div style="width: 45px; height: 45px; background: #f97316; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; box-shadow: 0 4px 10px rgba(249, 115, 22, 0.2);">⚠️</div>
        <div style="flex: 1;">
            <h4 style="margin: 0; color: #9a3412; font-size: 1rem;">Cảnh báo hệ thống API</h4>
            <p style="margin: 3px 0 0; color: #c2410c; font-size: 0.85rem;"><?php echo strip_tags($api_warning, '<a>'); ?></p>
        </div>
        <a href="settings.php" class="admin-settings-save" style="background: #f97316; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: bold; color: white;">Gia hạn / Đổi Key</a>
    </div>
    <style> @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } } </style>
    <?php endif; ?>

    <!-- Update Notification (Hidden by default) -->
    <div id="update-banner" class="admin-settings-card" style="display:none; background: linear-gradient(135deg, #1e293b, #0f172a); color: white; border: 1px solid rgba(99, 102, 241, 0.3); margin-bottom: 1.5rem; position: relative; overflow: hidden; padding: 1.5rem; border-radius: 16px;">
        <div style="position: absolute; top: -10px; right: -10px; opacity: 0.1; transform: rotate(15deg);">
            <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
        </div>
        <div style="display: flex; align-items: center; gap: 15px; position: relative; z-index: 1;">
            <div style="width: 45px; height: 45px; background: #6366f1; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);">🚀</div>
            <div style="flex: 1;">
                <h3 style="margin: 0; font-size: 1.1rem; color: #fff;">Đã có phiên bản mới: <span id="new-version-tag" style="background: #ef4444; padding: 2px 8px; border-radius: 6px; font-size: 0.8rem; margin-left: 5px;">v0.0.0</span></h3>
                <p id="update-changelog" style="margin: 5px 0 0; font-size: 0.85rem; color: #94a3b8;">Hệ thống phát hiện bản cập nhật mới trên GitHub. Vui lòng cập nhật để sử dụng các tính năng mới nhất.</p>
            </div>
            <div id="update-actions" style="display: flex; gap: 10px;">
                <button onclick="runSmartUpdate()" id="update-btn" class="admin-settings-save" style="background: #6366f1; text-decoration: none; margin: 0; padding: 0.6rem 1.2rem; display: inline-flex; align-items: center; gap: 8px; font-weight: bold; border-radius: 8px; cursor: pointer; border: none; color: white;">
                    <svg id="update-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span id="update-text">Cập nhật ngay</span>
                </button>
                <a href="https://github.com/benaasia/affreels" target="_blank" style="background: rgba(255,255,255,0.1); text-decoration: none; padding: 0.6rem 1.2rem; display: inline-flex; align-items: center; gap: 8px; font-weight: bold; border-radius: 8px; color: white; border: 1px solid rgba(255,255,255,0.2); font-size: 0.9rem; transition: all 0.3s;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
                    Chi tiết
                </a>
            </div>
        </div>
    </div>

    <div class="admin-stats-grid">
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background: linear-gradient(135deg, #6366f1, #818cf8);">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
            </div>
            <div class="admin-stat-info">
                <span class="admin-stat-value"><?php echo number_format($total_links); ?></span>
                <span class="admin-stat-label">Tổng số link</span>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background: linear-gradient(135deg, #ec4899, #f472b6);">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div class="admin-stat-info">
                <span class="admin-stat-value"><?php echo number_format($total_clicks); ?></span>
                <span class="admin-stat-label">Tổng lượt click</span>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            </div>
            <div class="admin-stat-info">
                <span class="admin-stat-value"><?php echo $total_links > 0 ? number_format($total_clicks / $total_links, 1) : '0'; ?></span>
                <span class="admin-stat-label">TB click/link</span>
            </div>
        </div>
    </div>

    <div class="admin-toolbar">
        <form method="GET" class="admin-search-form">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm kiếm link..." class="admin-search-input">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="admin-search-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <?php if ($sort_by !== 'clicks_desc'): ?>
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
            <?php endif; ?>
        </form>
        <div class="admin-sort-group">
            <button id="bulk-delete-btn" class="admin-action-btn admin-btn-delete" style="display:none; margin-right: 15px; padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.85rem; font-weight: bold; align-items: center; gap: 5px; cursor: pointer; color: white; background-color: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5);" onclick="bulkDelete()">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Xóa đã chọn (<span id="bulk-count">0</span>)
            </button>
            <span class="admin-sort-label">Sắp xếp:</span>
            <a href="<?php echo buildQuery(['sort'=>'clicks_desc','page'=>1]); ?>" class="admin-sort-btn <?php echo $sort_by==='clicks_desc'?'active':''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.292 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path></svg>
                Click ↓
            </a>
            <a href="<?php echo buildQuery(['sort'=>'clicks_asc','page'=>1]); ?>" class="admin-sort-btn <?php echo $sort_by==='clicks_asc'?'active':''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                Click ↑
            </a>
            <a href="<?php echo buildQuery(['sort'=>'newest','page'=>1]); ?>" class="admin-sort-btn <?php echo $sort_by==='newest'?'active':''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Mới nhất
            </a>
            <a href="<?php echo buildQuery(['sort'=>'oldest','page'=>1]); ?>" class="admin-sort-btn <?php echo $sort_by==='oldest'?'active':''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M12 7v5l4 2"></path></svg>
                Cũ nhất
            </a>
        </div>
    </div>

    <div class="admin-table-wrapper">
        <?php if (empty($stats)): ?>
            <div class="admin-empty-state">
                <div class="admin-empty-icon">📭</div>
                <p>Chưa có link nào<?php echo $search ? ' phù hợp với tìm kiếm' : ''; ?>.</p>
            </div>
        <?php else: ?>
            <table class="admin-table" id="admin-links-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="check-all" onclick="toggleCheckAll(this)" style="cursor: pointer; transform: scale(1.2);"></th>
                        <th>#</th>
                        <th>Link rút gọn</th>
                        <th>Nguồn</th>
                        <th>Aff ID</th>
                        <th>URL Shopee đích</th>
                        <th>Clicks</th>
                        <th>Ngày tạo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $start_index = ($page - 1) * PER_PAGE; ?>
                    <?php foreach ($stats as $idx => $link): ?>
                    <tr id="row-<?php echo htmlspecialchars($link['slug']); ?>">
                        <td style="text-align: center;"><input type="checkbox" class="row-checkbox" value="<?php echo htmlspecialchars($link['slug']); ?>" onchange="updateBulkDeleteBtn()" style="cursor: pointer; transform: scale(1.2);"></td>
                        <td class="admin-td-index"><?php echo $start_index + $idx + 1; ?></td>
                        <td class="admin-td-slug">
                            <a href="<?php echo $display_base; ?>/s/<?php echo htmlspecialchars($link['slug']); ?>" target="_blank" class="admin-slug-link">
                                <?php echo htmlspecialchars($display_base); ?>/s/<?php echo htmlspecialchars($link['slug']); ?>
                            </a>
                        </td>
                        <td class="admin-td-source">
                            <?php if (!empty($link['source_url'])): ?>
                                <a href="<?php echo htmlspecialchars($link['source_url']); ?>" target="_blank" class="admin-source-link" title="<?php echo htmlspecialchars($link['source_url']); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: middle;"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                                    <?php echo htmlspecialchars(mb_strimwidth($link['source_url'], 0, 25, '...')); ?>
                                </a>
                            <?php else: ?>
                                <span class="admin-no-source">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="admin-td-affid">
                            <?php if (!empty($link['affiliate_id'])): ?>
                                <code style="font-size: 0.8rem; color: var(--secondary); font-weight: bold;"><?php echo htmlspecialchars($link['affiliate_id']); ?></code>
                            <?php else: ?>
                                <span class="admin-no-source">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="admin-td-url">
                            <div class="admin-url-display" id="url-<?php echo htmlspecialchars($link['slug']); ?>">
                                <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" title="<?php echo htmlspecialchars($link['url']); ?>">
                                    <?php echo htmlspecialchars(mb_strimwidth($link['url'], 0, 45, '...')); ?>
                                </a>
                            </div>
                        </td>
                        <td class="admin-td-clicks">
                            <span class="admin-click-badge <?php echo $link['clicks'] > 10 ? 'hot' : ($link['clicks'] > 0 ? 'warm' : ''); ?>">
                                <?php echo number_format($link['clicks']); ?>
                            </span>
                        </td>
                        <td class="admin-td-date">
                            <?php 
                                if ($link['created_at']) {
                                    $dt = new DateTime($link['created_at'], new DateTimeZone('UTC'));
                                    $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
                                    echo $dt->format('d/m/Y H:i');
                                } else {
                                    echo '—';
                                }
                            ?>
                        </td>
                        <td class="admin-td-actions">
                            <button class="admin-action-btn admin-btn-copy" onclick="copyShortLink('<?php echo htmlspecialchars($link['slug']); ?>')" title="Sao chép">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            </button>
                            <button class="admin-action-btn admin-btn-edit" onclick="editLink('<?php echo htmlspecialchars($link['slug']); ?>')" title="Sửa">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            </button>
                            <button class="admin-action-btn admin-btn-delete" onclick="deleteLink('<?php echo htmlspecialchars($link['slug']); ?>')" title="Xóa">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="admin-pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo buildQuery(['page' => $page - 1]); ?>" class="admin-page-btn">← Trước</a>
                <?php endif; ?>

                <?php
                $range = 2;
                $start_page = max(1, $page - $range);
                $end_page = min($total_pages, $page + $range);

                if ($page > 1) {
                    echo '<a href="' . buildQuery(['page' => $page - 1]) . '" class="admin-page-btn">← Trước</a>';
                }

                if ($start_page > 1) {
                    echo '<a href="' . buildQuery(['page' => 1]) . '" class="admin-page-btn">1</a>';
                    if ($start_page > 2) echo '<span class="admin-page-dots">…</span>';
                }

                for ($p = $start_page; $p <= $end_page; $p++) {
                    $active_class = ($p === $page) ? 'active' : '';
                    echo '<a href="' . buildQuery(['page' => $p]) . '" class="admin-page-btn ' . $active_class . '">' . $p . '</a>';
                }

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span class="admin-page-dots">…</span>';
                    echo '<a href="' . buildQuery(['page' => $total_pages]) . '" class="admin-page-btn">' . $total_pages . '</a>';
                }

                if ($page < $total_pages) {
                    echo '<a href="' . buildQuery(['page' => $page + 1]) . '" class="admin-page-btn">Sau →</a>';
                }
                ?>
                <span class="admin-page-info">Trang <?php echo $page; ?>/<?php echo $total_pages; ?> · <?php echo number_format($filtered_count); ?> link</span>
            </div>
            <?php else: ?>
            <div class="admin-table-footer">
                Hiển thị <strong><?php echo count($stats); ?></strong> link
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<div class="admin-modal-overlay" id="edit-modal" style="display:none;">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3>✏️ Chỉnh sửa URL</h3>
            <button class="admin-modal-close" onclick="closeModal('edit-modal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <label>Slug: <strong id="modal-slug-display"></strong></label>
            <input type="text" id="modal-new-url" placeholder="Nhập URL mới..." class="admin-modal-input">
        </div>
        <div class="admin-modal-footer">
            <button class="admin-modal-btn admin-modal-cancel" onclick="closeModal('edit-modal')">Hủy</button>
            <button class="admin-modal-btn admin-modal-save" id="modal-save-btn">Lưu thay đổi</button>
        </div>
    </div>
</div>

<div class="admin-modal-overlay" id="password-modal" style="display:none;">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3>🔑 Đổi mật khẩu Admin</h3>
            <button class="admin-modal-close" onclick="closeModal('password-modal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <p style="font-size:0.8rem; color: var(--text-dim); margin-bottom: 1rem;">Mật khẩu được mã hóa bcrypt. Tối thiểu 6 ký tự.</p>
            <input type="password" id="pw-current" placeholder="Mật khẩu hiện tại" class="admin-modal-input" style="margin-bottom: 0.7rem;">
            <input type="password" id="pw-new" placeholder="Mật khẩu mới" class="admin-modal-input" style="margin-bottom: 0.7rem;">
            <input type="password" id="pw-confirm" placeholder="Xác nhận mật khẩu mới" class="admin-modal-input">
        </div>
        <div class="admin-modal-footer">
            <button class="admin-modal-btn admin-modal-cancel" onclick="closeModal('password-modal')">Hủy</button>
            <button class="admin-modal-btn admin-modal-save" onclick="changePassword()">🔒 Đổi mật khẩu</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?php echo $display_base; ?>';

function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function openPasswordModal() { openModal('password-modal'); document.getElementById('pw-current').focus(); }

let currentUploadTarget = '';
function triggerUpload(targetId) {
    currentUploadTarget = targetId;
    document.getElementById('admin-image-uploader').click();
}

function handleImageUpload(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    const fd = new FormData();
    fd.append('upload_image', file);
    
    showToast('⏳ Đang tải ảnh lên...', false);
    
    fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById(currentUploadTarget).value = data.url;
                showToast('✅ Đã tải ảnh thành công!');
            } else {
                showToast('❌ ' + data.message, true);
            }
            input.value = '';
        })
        .catch(e => {
            showToast('❌ Lỗi kết nối khi tải ảnh.', true);
            input.value = '';
        });
}

function saveBranding() {
    const fd = new FormData();
    fd.append('save_branding', '1');
    fd.append('site_title', document.getElementById('site-title').value.trim());
    fd.append('site_desc', document.getElementById('site-desc').value.trim());
    fd.append('site_keywords', document.getElementById('site-keywords').value.trim());
    fd.append('site_author', document.getElementById('site-author').value.trim());
    fd.append('site_logo', document.getElementById('site-logo').value.trim());
    fd.append('site_favicon', document.getElementById('site-favicon').value.trim());
    fd.append('site_og_image', document.getElementById('site-og-image').value.trim());
    fd.append('site_video_url', document.getElementById('site-video-url').value.trim());
    fd.append('site_fb_token', document.getElementById('site-fb-token').value.trim());

    // Modal settings
    fd.append('modal_enabled', document.getElementById('modal-enabled').checked ? '1' : '0');
    fd.append('modal_icon', document.getElementById('modal-icon').value.trim());
    fd.append('modal_title', document.getElementById('modal-title').value.trim());
    fd.append('modal_body', document.getElementById('modal-body').value.trim());
    fd.append('modal_list', document.getElementById('modal-list').value.trim());
    fd.append('modal_note', document.getElementById('modal-note').value.trim());
    fd.append('modal_button', document.getElementById('modal-button').value.trim());
    fd.append('modal_button_url', document.getElementById('modal-button-url').value.trim());
    fd.append('modal_button_new_tab', document.getElementById('modal-button-new-tab').checked ? '1' : '0');
    
    fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showToast(data.success ? '✅ ' + data.message : '❌ ' + data.message, !data.success);
            if (data.success) setTimeout(() => location.reload(), 800);
        });
}

function saveCustomDomain() {
    const domain = document.getElementById('custom-domain-input').value.trim();
    const fd = new FormData();
    fd.append('save_custom_domain', '1');
    fd.append('custom_domain', domain);
    fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showToast(data.success ? '✅ ' + data.message : '❌ ' + data.message, !data.success);
            if (data.success) setTimeout(() => location.reload(), 800);
        });
}

function changePassword() {
    const cur = document.getElementById('pw-current').value;
    const np = document.getElementById('pw-new').value;
    const cf = document.getElementById('pw-confirm').value;
    if (!cur || !np || !cf) return showToast('Vui lòng điền đầy đủ!', true);
    const fd = new FormData();
    fd.append('change_password', '1');
    fd.append('current_password', cur);
    fd.append('new_password', np);
    fd.append('confirm_password', cf);
    fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showToast(data.success ? '✅ ' + data.message : '❌ ' + data.message, !data.success);
            if (data.success) { closeModal('password-modal'); document.getElementById('pw-current').value = ''; document.getElementById('pw-new').value = ''; document.getElementById('pw-confirm').value = ''; }
        });
}

function copyShortLink(slug) {
    navigator.clipboard.writeText(BASE_URL + '/s/' + slug).then(() => showToast('Đã sao chép: ' + BASE_URL + '/s/' + slug));
}

function deleteLink(slug) {
    if (!confirm('⚠️ Xóa link /s/' + slug + '? Không thể hoàn tác!')) return;
    const fd = new FormData(); fd.append('delete_slug', slug);
    fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById('row-' + slug);
                if (row) { row.style.transition='all 0.4s'; row.style.opacity='0'; row.style.transform='translateX(50px)'; setTimeout(()=>row.remove(),400); }
                showToast('🗑️ ' + data.message);
            } else showToast('❌ ' + data.message, true);
        });
}

let currentEditSlug = '';

function toggleCheckAll(source) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const btn = document.getElementById('bulk-delete-btn');
    if (btn) {
        if (checked.length > 0) {
            btn.style.display = 'inline-flex';
            document.getElementById('bulk-count').textContent = checked.length;
        } else {
            btn.style.display = 'none';
        }
    }
}

function bulkDelete() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    if (checked.length === 0) return;
    if (!confirm('⚠️ Bạn có chắc muốn xóa ' + checked.length + ' link đã chọn?\nKhông thể hoàn tác!')) return;
    
    const slugs = Array.from(checked).map(cb => cb.value);
    const fd = new FormData();
    fd.append('bulk_delete_slugs', JSON.stringify(slugs));
    
    fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('✅ ' + data.message);
                slugs.forEach(slug => {
                    const row = document.getElementById('row-' + slug);
                    if (row) {
                        row.style.transition='all 0.4s'; 
                        row.style.opacity='0'; 
                        row.style.transform='translateX(50px)'; 
                        setTimeout(()=>row.remove(), 400); 
                    }
                });
                document.getElementById('check-all').checked = false;
                updateBulkDeleteBtn();
                setTimeout(() => location.reload(), 600);
            } else {
                showToast('❌ ' + data.message, true);
            }
        });
}
function editLink(slug) {
    currentEditSlug = slug;
    document.getElementById('modal-slug-display').textContent = '/s/' + slug;
    const urlEl = document.querySelector('#url-' + slug + ' a');
    document.getElementById('modal-new-url').value = urlEl ? urlEl.getAttribute('href') : '';
    openModal('edit-modal');
    document.getElementById('modal-new-url').focus();
}

document.getElementById('modal-save-btn').addEventListener('click', () => {
    const newUrl = document.getElementById('modal-new-url').value.trim();
    if (!newUrl) return showToast('URL không được để trống!', true);
    const fd = new FormData(); fd.append('edit_slug', currentEditSlug); fd.append('new_url', newUrl);
    fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) { showToast('✅ ' + data.message); closeModal('edit-modal'); setTimeout(()=>location.reload(),600); }
            else showToast('❌ ' + data.message, true);
        });
});

function showToast(msg, isError = false) {
    let t = document.getElementById('admin-toast');
    if (!t) { t = document.createElement('div'); t.id = 'admin-toast'; document.body.appendChild(t); }
    t.innerHTML = msg;
    t.className = 'admin-toast show' + (isError ? ' error' : '');
    setTimeout(() => { t.className = 'admin-toast'; }, 3000);
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal('edit-modal'); closeModal('password-modal'); }});
</script>

<?php endif; ?>
    <script>
    function toggleSidebar() {
        document.getElementById('adminSidebar').classList.toggle('active');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }

    // Smart Update Checker for Client (Dựa trên nội dung file)
    async function checkUpdates(isManual = false) {
        const dot = document.getElementById('version-dot');
        if (isManual && dot) {
            dot.style.background = '#f59e0b';
            dot.style.boxShadow = '0 0 8px #f59e0b';
        }

        const currentVersion = "<?php echo $current_version; ?>";
        const repoApiUrl = "https://api.github.com/repos/benaasia/affreels/contents/";
        
        try {
            const res = await fetch(repoApiUrl + "?t=" + Date.now(), {
                headers: { 'Accept': 'application/vnd.github.v3+json' }
            });
            const files = await res.json();
            
            if (!Array.isArray(files)) throw new Error("Invalid API response");

            let hasUpdate = false;
            let updateInfo = { version: currentVersion, changelog: 'Phát hiện thay đổi mã nguồn trên GitHub.' };

            // Thử lấy thông tin version.json trước (nếu có) để lấy changelog
            const versionFile = files.find(f => f.name === 'version.json');
            if (versionFile) {
                try {
                    const vRes = await fetch("https://raw.githubusercontent.com/benaasia/affreels/main/version.json?t=" + Date.now());
                    const vData = await vRes.json();
                    updateInfo.version = vData.version || currentVersion;
                    updateInfo.changelog = vData.changelog || updateInfo.changelog;
                } catch(e) {}
            }

            // Gửi request ngầm về server để so sánh SHA của từng file
            const fd = new FormData();
            fd.append('action', 'check_sha_update');
            fd.append('remote_files', JSON.stringify(files.map(f => ({name: f.name, sha: f.sha, type: f.type}))));

            const checkRes = await fetch('admin.php', { method: 'POST', body: fd });
            const checkData = await checkRes.json();

            if (checkData.success && checkData.has_update) {
                const banner = document.getElementById('update-banner');
                if (banner) {
                    document.getElementById('new-version-tag').textContent = 'v' + updateInfo.version;
                    document.getElementById('update-changelog').textContent = updateInfo.changelog;
                    banner.style.display = 'block';
                    if (isManual) banner.scrollIntoView({ behavior: 'smooth' });
                }
            } else if (isManual) {
                showToast('✅ Hệ thống của bạn đã khớp hoàn toàn với GitHub.');
            }

            if (dot) {
                dot.style.background = '#10b981';
                dot.style.boxShadow = '0 0 8px #10b981';
            }
        } catch (err) {
            console.log("Update check failed:", err);
            if (isManual) showToast('❌ Không thể kết nối đến GitHub.', true);
            if (dot) {
                dot.style.background = '#ef4444';
                dot.style.boxShadow = '0 0 8px #ef4444';
            }
        }
    }

    // Tự động kiểm tra khi load
    checkUpdates();

    // Hàm gọi thủ công từ Sidebar
    function manualCheckUpdate() {
        checkUpdates(true);
    }

    function runSmartUpdate() {
        const btn = document.getElementById('update-btn');
        const text = document.getElementById('update-text');
        const icon = document.getElementById('update-icon');
        
        if (btn.disabled) return;
        
        if (!confirm('Hệ thống sẽ tải và ghi đè các file có thay đổi từ GitHub. Quá trình này không làm mất dữ liệu links. Tiếp tục?')) return;
        
        btn.disabled = true;
        btn.style.opacity = '0.7';
        text.textContent = 'Đang xử lý...';
        icon.style.animation = 'spin 1s linear infinite';
        
        const style = document.createElement('style');
        style.innerHTML = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
        document.head.appendChild(style);
        
        const fd = new FormData();
        fd.append('action', 'smart_update');
        
        fetch('admin.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    text.textContent = 'Hoàn tất!';
                    showToast('✅ ' + data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    text.textContent = 'Thử lại';
                    icon.style.animation = 'none';
                    showToast('❌ ' + data.message, true);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.style.opacity = '1';
                text.textContent = 'Lỗi kết nối';
                icon.style.animation = 'none';
                showToast('❌ Lỗi kết nối server.', true);
            });
    }
    </script>
</body>
</html>
