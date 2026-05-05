<?php
/**
 * Shopee Link Converter - AffReel Client
 * Chuyển đổi link Shopee thường sang link Affiliate chuẩn an_redir
 */

define('LOCAL_API_KEY', 'ReelsLink-v4-Secure-Key-2026');
define('DB_FILE', 'links.db');

$remote_api_key = ''; 
$remote_api_url = ''; 

try {
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_settings = $db->query("SELECT * FROM settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $remote_api_key = isset($settings['remote_api_key']) ? trim($settings['remote_api_key']) : 'FREE-85C45DDDBF3CEADB';
    $remote_api_url = 'https://api.affreel.com/v1';

    $site_title = isset($settings['site_title']) ? $settings['site_title'] : 'FbReels Pro';
    $site_desc = isset($settings['site_desc']) ? $settings['site_desc'] : 'Công cụ chuyển đổi link Shopee Affiliate nhanh chóng và an toàn.';
    $site_keywords = isset($settings['site_keywords']) ? $settings['site_keywords'] : 'shopee, affiliate, link converter';
    $site_author = isset($settings['site_author']) ? $settings['site_author'] : 'ReelsLink';
    $site_logo = isset($settings['site_logo']) ? $settings['site_logo'] : 'image/favicon.png';
    $site_favicon = isset($settings['site_favicon']) ? $settings['site_favicon'] : 'image/favicon.png';
    $shopee_aff_id = isset($settings['shopee_aff_id']) ? $settings['shopee_aff_id'] : '';
    $shopee_post_url = isset($settings['shopee_post_url']) ? $settings['shopee_post_url'] : '#';

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

require_once __DIR__ . '/remote_api_helper.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$current_page = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

// Xử lý AJAX Convert
if (isset($_GET['action']) && $_GET['action'] === 'convert' && isset($_POST['url'])) {
    header('Content-Type: application/json');
    $input_url = trim($_POST['url']);
    
    if (preg_match('/(https?:\/\/[^\s]+shopee[^\s]+|https?:\/\/shp\.ee\/[^\s]+)/i', $input_url, $matches)) {
        $input_url = $matches[1];
    }
    
    if (empty($input_url)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng dán link Shopee.']);
        exit;
    }

    if (strpos($input_url, 'shopee') === false && strpos($input_url, 'shp.ee') === false) {
        echo json_encode(['success' => false, 'message' => 'Link không hợp lệ. Chỉ chấp nhận link Shopee!']);
        exit;
    }

    $api_res = smartExtractShopeeLink($input_url);
    
    if (!$api_res['success']) {
        echo json_encode(['success' => false, 'message' => $api_res['message'] ?? 'Lỗi API']);
        exit;
    }

    $final_dest = $api_res['link'] ?? $api_res['url'] ?? '';
    $api_title = $api_res['title'] ?? '';

    if (empty($final_dest)) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy link Shopee.']);
        exit;
    }

    $clean_url = strtok($final_dest, '?');
    
    $product_name = 'Sản phẩm Shopee';
    if (!empty($api_title) && strtolower($api_title) !== 'shopee') {
        $product_name = $api_title;
    } else {
        $path = parse_url($clean_url, PHP_URL_PATH);
        $found_name = '';
        if ($path) {
            $parts = explode('/', trim($path, '/'));
            if (count($parts) > 0 && strpos($parts[0], '-') !== false && strpos($parts[0], 'i.') === false && strtolower($parts[0]) !== 'opaanlp') {
                $found_name = $parts[0];
            }
        }
        
        if (empty($found_name)) {
            $path_in = parse_url($input_url, PHP_URL_PATH);
            if ($path_in) {
                $parts_in = explode('/', trim($path_in, '/'));
                if (count($parts_in) > 0 && strpos($parts_in[0], '-') !== false && strpos($parts_in[0], 'i.') === false) {
                    $found_name = $parts_in[0];
                }
            }
        }

        if (!empty($found_name)) {
            $product_name = str_replace('-', ' ', $found_name);
            $product_name = mb_convert_case($product_name, MB_CASE_TITLE, "UTF-8");
        }
    }

    if (preg_match('/i\.(\d+)\.(\d+)/', $clean_url, $matches)) {
        $clean_url = "https://shopee.vn/opaanlp/{$matches[1]}/{$matches[2]}";
    } elseif (preg_match('/\/(\d+)\/(\d+)/', $clean_url, $matches)) {
        $clean_url = "https://shopee.vn/opaanlp/{$matches[1]}/{$matches[2]}";
    }

    $aff_id = $shopee_aff_id ?: '17374450024'; 
    $encoded_origin = urlencode($clean_url);
    $final_aff_link = "https://s.shopee.vn/an_redir?origin_link={$encoded_origin}&share_channel_code=4&affiliate_id={$aff_id}&sub_id=fb&deep_and_deferred=1";
    
    echo json_encode([
        'success' => true,
        'product_name' => $product_name,
        'clean_url' => $clean_url,
        'aff_link' => $final_aff_link,
        'affiliate_id' => $aff_id
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi" data-theme="<?php echo htmlspecialchars(isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'dark'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chuyển đổi Link Shopee - <?php echo htmlspecialchars($site_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($site_desc); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($site_favicon); ?>">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        @media (max-width: 992px) {
            .app-sidebar { 
                position: fixed;
                bottom: 0;
                left: 0;
                top: auto;
                width: 100%; 
                height: 65px; 
                flex-direction: row; 
                padding: 0 10px; 
                margin: 0;
                overflow: visible;
                border-right: none;
                border-top: 1px solid var(--border-color);
                background: rgba(15, 23, 42, 0.85);
                backdrop-filter: blur(30px);
                -webkit-backdrop-filter: blur(30px);
                z-index: 1000;
                justify-content: space-around;
                align-items: center;
            }
            .sidebar-brand, .sidebar-footer, .nav-label { display: none !important; }
            .sidebar-nav { display: flex; width: 100%; height: 100%; justify-content: space-around; align-items: center; gap: 10px; }
            .nav-btn { 
                flex: 1;
                height: 50px;
                flex-direction: column !important; 
                justify-content: center !important; 
                align-items: center !important;
                gap: 4px !important;
                padding: 0 2px !important; 
                margin: 0 !important;
                font-size: 0.6rem !important; 
                border-radius: 12px;
                background: transparent !important;
                box-shadow: none !important;
            }
            .nav-btn[data-tab="setup"] { display: none !important; }
            .nav-btn span { display: inline-block !important; margin: 0 !important; opacity: 1; white-space: normal; line-height: 1.1; text-align: center; max-width: 100%; }
            .nav-btn.active { background: var(--accent-gradient) !important; color: white !important; box-shadow: 0 4px 12px var(--primary-glow) !important; }
            .nav-btn.active span { font-weight: 700; }
            .nav-btn i, .nav-icon-svg { font-size: 1.2rem !important; width: auto !important; margin-bottom: 2px; }
            .main-content-scroll { padding: 70px 1rem 80px 1rem !important; }
            .hide-on-mobile { display: none !important; }
            .show-on-mobile { display: inline-block !important; }
            
            .mobile-header { 
                display: flex !important; 
                justify-content: space-between !important; 
                align-items: center !important; 
                padding: 15px 15px 0 15px !important; 
                background: transparent !important;
            }
            .mobile-logo {
                display: flex;
                align-items: center;
                height: 100%;
            }
            .mobile-logo img {
                height: 35px !important;
                width: auto !important;
                object-fit: contain !important;
                max-width: 150px !important;
                display: block !important;
            }
            .mobile-header-right {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .mobile-settings-btn {
                background: rgba(255,255,255,0.1) !important;
                border: none !important;
                color: white !important;
                width: 36px !important;
                height: 36px !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer;
            }
        }
        .mobile-header { display: none; }
        .show-on-mobile { display: none; }

        /* Custom Converter Styles integrated with Dashboard Theme */
        *:not(i):not([class*="fa-"]) {
            font-family: 'Inter', 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
        }
        input, button, textarea {
            font-family: inherit;
        }
        
        .converter-container {
            max-width: 550px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .header h1 {
            color: var(--shopee-orange, #ee4d2d);
            font-size: 1.8rem;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            letter-spacing: 1px;
        }
        html[data-theme="dark"] .header h1 {
            color: #ff734d;
        }
        .header p {
            color: var(--text-dim);
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        .voucher-badge {
            background: rgba(238, 77, 45, 0.1);
            color: var(--shopee-orange, #ee4d2d);
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
        }
        html[data-theme="dark"] .voucher-badge {
            color: #ff734d;
        }
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 1.2rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
        }
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-wrapper input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            height: 54px;
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-main);
            font-size: 0.95rem;
        }
        .input-wrapper input:focus {
            border-color: var(--shopee-orange, #ee4d2d);
            outline: none;
            box-shadow: 0 0 0 3px rgba(238, 77, 45, 0.1);
        }
        .paste-btn {
            position: absolute;
            right: 10px;
            background: rgba(238, 77, 45, 0.1);
            border: none;
            color: var(--shopee-orange, #ee4d2d);
            width: 34px;
            height: 34px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        html[data-theme="dark"] .paste-btn {
            color: #ff734d;
        }
        .paste-btn:hover {
            background: var(--shopee-orange, #ee4d2d);
            color: white;
        }
        .convert-btn {
            width: 100%;
            height: 54px;
            background: linear-gradient(135deg, #ff734d, #ee4d2d);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(238, 77, 45, 0.3);
            text-transform: uppercase;
        }
        .convert-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(238, 77, 45, 0.4);
        }
        .convert-btn.success {
            background: #27ae60;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        .info-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-dim);
            margin-top: 1rem;
        }
        .info-bar i {
            color: #27ae60;
        }

        #result-section { display: none; animation: slideUp 0.4s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .actions-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 1.5rem;
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            margin-top: 1.5rem;
        }
        .action-btn {
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: 1.5px solid transparent;
            font-size: 1rem;
            white-space: nowrap;
        }
        .btn-copy { background: rgba(255, 87, 34, 0.1); color: #ff5722; border: 1.5px solid #ff5722; }
        .btn-copy:hover { background: #ff5722; color: white; }
        .btn-visit { background: #1877f2; color: white; }
        .btn-visit:hover { background: #1565c0; transform: translateY(-2px); }

        .instruction-card {
            background: rgba(255, 87, 34, 0.05);
            padding: 0;
            border-radius: 20px;
            border: 1.5px dashed #ff5722;
            overflow: hidden;
            margin-top: 1.5rem;
        }
        .instruction-header {
            background: #ff5722;
            color: white;
            padding: 12px 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.05rem;
        }
        .instruction-body { padding: 1.5rem; color: var(--text-main); }
        .instruction-list { list-style: none; padding-left: 0; margin: 0; }
        .instruction-list li { margin-bottom: 12px; font-size: 0.95rem; line-height: 1.6; }
        .instruction-list li b { color: #ff5722; }
        .guide-btn-link { display: inline-flex; align-items: center; gap: 5px; color: #1877f2; text-decoration: none; font-weight: 700; margin-left: 20px; margin-top: 5px; }
        
        .loader-spin { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Mobile specific fixes */
        @media (max-width: 480px) {
            .actions-row { grid-template-columns: 1fr 1fr; gap: 6px; padding: 0.8rem 0.5rem; margin-top: 1rem; }
            .action-btn { height: 44px; font-size: 0.8rem; gap: 4px; padding: 0 4px; }
            .header h1 { font-size: 1.4rem; }
        }
        
        /* Hide mobile header stuff if we share the layout */
        .mobile-header { display: none; }
        @media (max-width: 768px) { .mobile-header { display: flex; } }
    </style>
</head>
<body>
    <div class="glass-bg-circles">
        <div class="circle circle-1"></div>
        <div class="circle circle-2"></div>
        <div class="circle circle-3"></div>
    </div>
    
    <div class="app-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo">
                    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo">
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-label">Công cụ chính</div>
                <a href="index.php?tab=shopee" class="nav-btn">
                    <svg class="nav-icon-svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19.14 7.42h-3.41V6.15c0-2.07-1.68-3.75-3.75-3.75S8.23 4.08 8.23 6.15v1.27H4.82c-1.12 0-2.03.91-2.03 2.03l-1.01 10.14c0 1.12.91 2.03 2.03 2.03h16.22c1.12 0 2.03-.91 2.03-2.03l-1.01-10.14c0-1.12-.91-2.03-2.03-2.03zm-8.88-1.27c0-.96.78-1.74 1.74-1.74s1.74.78 1.74 1.74v1.27h-3.48V6.15zm5.17 11.23c0 .33-.06.66-.19.95-.12.3-.3.56-.54.79-.23.23-.5.42-.81.56-.3.14-.64.21-.99.21-.48 0-.91-.12-1.3-.35s-.71-.56-.95-1l.92-.53c.16.3.36.52.62.67.25.15.53.22.84.22.18 0 .34-.03.5-.1.15-.07.28-.16.39-.28s.2-.26.26-.42c.06-.17.09-.35.09-.54 0-.17-.03-.33-.1-.47s-.17-.27-.3-.38-.28-.21-.46-.3-.38-.17-.61-.26c-.34-.12-.66-.26-.95-.42s-.54-.35-.74-.58c-.19-.23-.34-.49-.44-.79s-.15-.65-.15-1.05c0-.33.06-.64.19-.94s.3-.56.53-.78c.22-.22.49-.4.8-.52s.64-.19 1-.19c.4 0 .76.08 1.09.25s.61.39.84.67.4.59.5 1l-.9.42c-.12-.34-.28-.59-.5-.75-.21-.16-.48-.25-.8-.25-.15 0-.29.03-.43.08-.13.05-.24.13-.34.22s-.17.21-.23.34c-.05.13-.08.27-.08.43 0 .14.03.26.09.37s.14.21.25.3.23.18.38.25c.15.07.31.13.48.19.4.15.75.31 1.04.49s.54.39.73.63.32.51.41.81c.09.3.13.63.13.99z"></path></svg>
                    <span><span class="hide-on-mobile">Link Shopee</span></span>
                </a>
                <a href="index.php?tab=fbreel" class="nav-btn">
                    <i class="fab fa-facebook"></i> 
                    <span><span class="hide-on-mobile">Facebook Reels</span></span>
                </a>
                <a href="convert.php" class="nav-btn active">
                    <i class="fas fa-exchange-alt"></i> <span><span class="hide-on-mobile">Chuyển đổi Link</span></span>
                </a>
                
                <div class="nav-label">Hệ thống</div>
                <a href="index.php?tab=setup#setup" class="nav-btn hide-on-mobile">
                    <i class="fas fa-cog"></i> <span>Cài đặt</span>
                </a>
                <a href="extension_guide.php" class="nav-btn hide-on-mobile">
                    <i class="fas fa-puzzle-piece"></i> <span>Hướng dẫn Extension</span>
                </a>
                <a href="bookmarklet_guide.php" class="nav-btn hide-on-mobile">
                    <i class="fas fa-magic"></i> <span>Hướng dẫn Bookmark</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <button class="theme-toggle-inline theme-toggle-btn" title="Đổi màu">
                    <span class="theme-icon">🌙</span>
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="app-main">
            <!-- Mobile Top Header -->
            <div class="mobile-header">
                <div class="mobile-logo">
                    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo">
                </div>
                <div class="mobile-header-right">
                    <button class="mobile-settings-btn theme-toggle-btn" title="Đổi màu">
                        <span class="theme-icon">🌙</span>
                    </button>
                    <a href="index.php?tab=setup" class="mobile-settings-btn" title="Cài đặt" style="text-decoration: none;">
                        <i class="fas fa-cog"></i>
                    </a>
                </div>
            </div>

            <div class="main-content-scroll">
                <div class="tool-container">
                    
                    <div class="converter-container" style="margin-top: 2rem;">
                        <div class="header">
                            <h1>CHUYỂN ĐỔI LINK SHOPEE</h1>
                            <p>Nhận <span class="voucher-badge">Voucher độc quyền</span> giảm 20%-25%</p>
                        </div>

                        <!-- Form nhập liệu -->
                        <div class="card">
                            <div class="input-wrapper">
                                <input type="text" id="shopee-url" placeholder="Dán link sản phẩm Shopee vào đây..." 
                                    onpaste="setTimeout(handleConvert, 100)"
                                    autocomplete="off">
                                <button class="paste-btn" onclick="pasteFromClipboard()" title="Dán từ Clipboard">
                                    <i class="far fa-clipboard"></i>
                                </button>
                            </div>
                            
                            <button class="convert-btn" id="btn-main" onclick="handleConvert()">
                                <i class="fas fa-link"></i>
                                <span>CHUYỂN LINK</span>
                            </button>

                            <div class="info-bar">
                                <i class="fas fa-check-square"></i> Miễn phí • An toàn 100% • Không cần đăng nhập
                            </div>
                        </div>

                        <!-- Kết quả -->
                        <div id="result-section">
                            <div id="product-info" class="card" style="display: none; padding: 12px; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div id="product-img-box" style="width: 64px; height: 64px; background: rgba(0,0,0,0.05); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #ccc; font-size: 1.5rem; flex-shrink: 0; overflow: hidden;">
                                        <span id="img-placeholder">SP</span>
                                        <img id="product-actual-img" src="" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div style="flex: 1; text-align: left;">
                                        <div id="product-name-text" style="font-weight: 700; color: var(--text-main); font-size: 1.05rem; margin-bottom: 4px; line-height: 1.3;">Sản phẩm Shopee</div>
                                        <div style="display: inline-flex; align-items: center; gap: 5px; background: rgba(46, 125, 50, 0.1); color: #2e7d32; padding: 2px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                                            <i class="fas fa-check-circle" style="font-size: 0.85rem;"></i> Đã chuyển đổi xong
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="actions-row">
                                <button class="action-btn btn-copy" onclick="copyResult()">
                                    <i class="fas fa-link"></i> Sao chép link
                                </button>
                                <a href="<?php echo htmlspecialchars($shopee_post_url); ?>" id="visit-post-btn" target="_blank" class="action-btn btn-visit">
                                    <i class="fab fa-facebook-f"></i> Đến bài đăng
                                </a>
                            </div>

                            <!-- Hướng dẫn -->
                            <div class="card instruction-card" id="instruction-section" style="display: none;">
                                <div class="instruction-header">
                                    <i class="far fa-lightbulb"></i> Hướng dẫn nhận mã
                                </div>
                                <div class="instruction-body">
                                    <ul class="instruction-list">
                                        <li>1. Bạn hãy nhấn "🔗 <b>Sao chép link</b>" (Màu cam) ở trên</li>
                                        <li>2. Dán link dưới bình luận bài đăng này:</li>
                                    </ul>
                                    <a href="<?php echo htmlspecialchars($shopee_post_url); ?>" id="guide-link" target="_blank" class="guide-btn-link">
                                        👉 Nhấn vào đây để đến bài đăng
                                    </a>
                                    <ul class="instruction-list" style="margin-top: 12px;">
                                        <li>3. Click vào link để mở Shopee sẽ nhận được mã.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <footer style="text-align: center; margin-top: 1.5rem; padding-bottom: 2rem; color: #999; font-size: 0.85rem;">
                    Designed by <a href="https://affreel.com" target="_blank" style="color: var(--shopee-orange); text-decoration: none; font-weight: 600;">Affreel.com</a>
                </footer>
            </div>
        </main>
    </div>

    <!-- Toast Notification -->
    <div id="toast" style="position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:#333; color:white; padding:12px 24px; border-radius:50px; z-index:9999; font-size:0.9rem; transition:all 0.3s; opacity:0; pointer-events:none; box-shadow:0 10px 30px rgba(0,0,0,0.2);"></div>

<script>
    let convertedLink = '';

    window.onload = () => {
        const params = new URLSearchParams(window.location.search);
        const urlParam = params.get('url');
        if (urlParam) {
            document.getElementById('shopee-url').value = urlParam;
            handleConvert();
        }
    };

    async function pasteFromClipboard() {
        try {
            const text = await navigator.clipboard.readText();
            if (text) {
                document.getElementById('shopee-url').value = text;
                showToast('📋 Đã dán link!');
                handleConvert();
            }
        } catch (err) {
            console.error('Không thể truy cập clipboard: ', err);
        }
    }

    function handleConvert() {
        let urlInput = document.getElementById('shopee-url').value.trim();
        
        const urlRegex = /(https?:\/\/[^\s]*shopee[^\s]*|https?:\/\/shp\.ee\/[^\s]*)/i;
        const match = urlInput.match(urlRegex);
        if (match) {
            urlInput = match[1];
            document.getElementById('shopee-url').value = urlInput;
        }

        if (!urlInput) {
            showToast('❌ Vui lòng nhập link Shopee!', true);
            return;
        }

        if (!urlInput.toLowerCase().includes('shopee') && !urlInput.toLowerCase().includes('shp.ee')) {
            showToast('❌ Link không hợp lệ. Chỉ chấp nhận link Shopee!', true);
            return;
        }

        const btn = document.getElementById('btn-main');
        const btnText = btn.querySelector('span');
        const btnIcon = btn.querySelector('i');
        
        btn.disabled = true;
        btnText.textContent = 'ĐANG XỬ LÝ...';
        btnIcon.className = 'fas fa-spinner loader-spin';
        
        const fd = new FormData();
        fd.append('url', urlInput);

        fetch('convert.php?action=convert', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                convertedLink = data.aff_link;
                
                btnText.textContent = 'ĐÃ CHUYỂN LINK';
                btnIcon.className = 'fas fa-check-circle';
                btn.classList.add('success');
                
                document.getElementById('product-name-text').innerText = data.product_name || 'Sản phẩm Shopee';
                
                document.getElementById('product-info').style.display = 'block';
                document.getElementById('result-section').style.display = 'block';
                document.getElementById('instruction-section').style.display = 'block';
                
                showToast('✅ Chuyển đổi thành công!');
                document.getElementById('result-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                showToast('❌ ' + data.message, true);
                resetButton();
            }
        })
        .catch(err => {
            showToast('❌ Lỗi kết nối server!', true);
            resetButton();
        });
    }

    function resetButton() {
        const btn = document.getElementById('btn-main');
        btn.disabled = false;
        btn.querySelector('span').textContent = 'CHUYỂN LINK';
        btn.querySelector('i').className = 'fas fa-link';
        btn.classList.remove('success');
    }

    function copyResult() {
        if (!convertedLink) return;
        navigator.clipboard.writeText(convertedLink).then(() => {
            showToast('📋 Đã sao chép link Affiliate!');
            
            const copyBtn = document.querySelector('.btn-copy');
            const originalHtml = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i> ĐÃ SAO CHÉP';
            copyBtn.style.background = '#27ae60';
            copyBtn.style.color = 'white';
            
            setTimeout(() => {
                copyBtn.innerHTML = originalHtml;
                copyBtn.style.background = '';
                copyBtn.style.color = '';
            }, 2000);
        });
    }

    function showToast(msg, isError = false) {
        let t = document.getElementById('toast');
        t.textContent = msg;
        t.style.background = isError ? '#e74c3c' : '#333';
        t.style.opacity = '1';
        t.style.bottom = '40px';
        
        setTimeout(() => {
            t.style.opacity = '0';
            t.style.bottom = '30px';
        }, 3000);
    }

    // Theme Toggle Logic
    const themeBtn = document.querySelector('.theme-toggle-btn');
    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            document.cookie = `theme=${newTheme};path=/;max-age=31536000`;
        });
    }
</script>
</body>
</html>
