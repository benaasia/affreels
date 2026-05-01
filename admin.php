<?php
/**
 * ReelsLink Pro V6.5 - Admin Panel
 * Dashboard, Settings (tên miền), Pagination, Password Modal (bcrypt)
 */

session_start();
define('DB_FILE', 'links.db');
define('DEFAULT_PASSWORD', 'admin123');
define('PER_PAGE', 10);

// === Database Connection & Migration ===
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

    // Khởi tạo mật khẩu bcrypt lần đầu
    $pw_row = $db->prepare("SELECT value FROM settings WHERE key = 'admin_password_hash'");
    $pw_row->execute();
    if (!$pw_row->fetch()) {
        $db->prepare("INSERT INTO settings (key, value) VALUES ('admin_password_hash', ?)")->execute([password_hash(DEFAULT_PASSWORD, PASSWORD_BCRYPT)]);
    }

    // Cột source_url lưu link Reels hoặc link Shopee rút gọn tùy tab sử dụng

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// === Helpers ===
function getSetting($db, $key, $default = '') {
    try {
        $s = $db->prepare("SELECT value FROM settings WHERE key = ?"); $s->execute([$key]);
        $r = $s->fetch(PDO::FETCH_ASSOC); return $r ? $r['value'] : $default;
    } catch (Exception $e) { return $default; }
}
function setSetting($db, $key, $value) {
    $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)")->execute([$key, $value]);
}

// KIỂM TRA CẤU HÌNH API (Chỉ chạy khi không phải AJAX)
$remote_api_key = trim(getSetting($db, 'remote_api_key', 'FREE-85C45DDDBF3CEADB'));
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (empty($remote_api_key) && !$is_ajax && !isset($_POST['save_branding']) && !isset($_POST['remote_api_key'])) {
    if (basename($_SERVER['PHP_SELF']) !== 'settings.php') {
        header('Location: settings.php');
        exit;
    }
}

// === Auth ===
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

// === AJAX Handlers ===
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

        // Kiểm tra xem có thực sự là ảnh không (trừ SVG là text)
        if ($ext !== 'svg') {
            $check = getimagesize($file['tmp_name']);
            if ($check === false) {
                echo json_encode(['success'=>false, 'message'=>'File tải lên không phải là ảnh hợp lệ.']); exit;
            }
        }
        
        $new_name = 'up_' . time() . '_' . bin2hex(random_bytes(2)) . '.' . $ext;
        $target = $upload_dir . $new_name;
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            // Chuyển URL thành dạng tương đối để gọi trên web dễ hơn
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
        setSetting($db, 'site_fb_token', trim($_POST['site_fb_token'] ?? '')); // Token riêng của Client
        echo json_encode(['success'=>true,'message'=>'Đã cập nhật cấu hình thương hiệu.']); exit;
    }
}

// === Page & Data ===
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
    $custom_domain = getSetting($db, 'custom_domain');

    // Lấy cấu hình Branding
    $site_title = getSetting($db, 'site_title', 'FbReels Pro');
    $site_author = getSetting($db, 'site_author', 'ReelsLink');
    $site_favicon = getSetting($db, 'site_favicon', 'image/favicon.png');
    $site_logo = getSetting($db, 'site_logo', 'image/favicon.png');

    $row = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(clicks),0) as clicks FROM links")->fetch(PDO::FETCH_ASSOC);
    $total_links = $row['total'];
    $total_clicks = $row['clicks'];

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

        // Count for pagination
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

// Build query string helper
function buildQuery($overrides = []) {
    global $sort_by, $search, $page, $tab;
    $params = ['tab' => $tab];
    if ($sort_by !== 'clicks_desc') $params['sort'] = $sort_by;
    if ($search) $params['search'] = $search;
    $params = array_merge($params, $overrides);
    // Remove defaults
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
<!-- =================== LOGIN =================== -->
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
<!-- =================== DASHBOARD =================== -->
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
        </div>
    </nav>

<?php if ($tab === 'settings'): ?>
<!-- =================== SETTINGS PAGE =================== -->
<div class="admin-page-content">
    <div class="admin-page-header">
        <h2>⚙️ Cài đặt hệ thống</h2>
    </div>

    <!-- Custom Domain -->
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

    <!-- Branding & SEO -->
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
                <small style="color: #64748b; font-size: 0.65rem; margin-top: 5px; display: block;">* Để trống nếu muốn dùng Token hệ thống của Server API.</small>
            </div>
        </div>

        <div class="admin-settings-actions">
            <button onclick="saveBranding()" class="admin-settings-save" style="background: linear-gradient(135deg, #10b981, #059669);">💾 Lưu cấu hình thương hiệu</button>
        </div>
        
        <!-- Hidden File Input cho Upload -->
        <input type="file" id="admin-image-uploader" style="display:none;" onchange="handleImageUpload(this)" accept="image/*">
    </div>

    <!-- System Info -->
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
<!-- =================== DASHBOARD TAB =================== -->

    <!-- Stats Cards -->
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

    <!-- Toolbar -->
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

    <!-- Links Table -->
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

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="admin-pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo buildQuery(['page' => $page - 1]); ?>" class="admin-page-btn">← Trước</a>
                <?php endif; ?>

                <?php
                $range = 2;
                $start_page = max(1, $page - $range);
                $end_page = min($total_pages, $page + $range);
                if ($start_page > 1): ?>
                    <a href="<?php echo buildQuery(['page' => 1]); ?>" class="admin-page-btn">1</a>
                    <?php if ($start_page > 2): ?><span class="admin-page-dots">…</span><?php endif; ?>
                <?php endif;

                for ($p = $start_page; $p <= $end_page; $p++): ?>
                    <a href="<?php echo buildQuery(['page' => $p]); ?>" class="admin-page-btn <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
                <?php endfor;

                if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><span class="admin-page-dots">…</span><?php endif; ?>
                    <a href="<?php echo buildQuery(['page' => $total_pages]); ?>" class="admin-page-btn"><?php echo $total_pages; ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo buildQuery(['page' => $page + 1]); ?>" class="admin-page-btn">Sau →</a>
                <?php endif; ?>

                <span class="admin-page-info">Trang <?php echo $page; ?>/<?php echo $total_pages; ?> · <?php echo number_format($filtered_count); ?> link</span>
            </div>
            <?php else: ?>
            <div class="admin-table-footer">
                Hiển thị <strong><?php echo count($stats); ?></strong> link
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; // end dashboard/settings tab ?>
</div>

<!-- ===== EDIT MODAL ===== -->
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

<!-- ===== PASSWORD MODAL ===== -->
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

// === Modal Helpers ===
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
            input.value = ''; // Reset input
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
    
    fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showToast(data.success ? '✅ ' + data.message : '❌ ' + data.message, !data.success);
            if (data.success) setTimeout(() => location.reload(), 800);
        });
}

// === Custom Domain ===
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

// === Change Password ===
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

// === Copy / Delete / Edit ===
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
    if (!confirm('⚠️ Bạn có chắc muốn xóa ' + checked.length + ' link đã chọn?\\nKhông thể hoàn tác!')) return;
    
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
                setTimeout(() => location.reload(), 600); // Reload to fix pagination/counts
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

// === Toast ===
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
    </script>
</body>
</html>
