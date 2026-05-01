<?php
/**
 * ReelsLink Pro V6.5
 */

// Cấu hình bảo mật và Database
define('LOCAL_API_KEY', 'ReelsLink-v4-Secure-Key-2026'); // Key cho Extension/Bookmarklet gọi vào dashboard này
define('DB_FILE', 'links.db');

// Cấu hình Remote API (Dành cho bản thương mại bán code)
// Mặc định chạy Local. Nếu có REMOTE_API_KEY thì sẽ gọi về Server trung tâm của bạn.
$remote_api_key = ''; 
$remote_api_url = ''; 



try {
    // Khởi tạo kết nối SQLite
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tạo bảng nếu chưa tồn tại
    $db->exec("CREATE TABLE IF NOT EXISTS links (
        slug TEXT PRIMARY KEY,
        url TEXT NOT NULL,
        clicks INTEGER DEFAULT 0,
        source_url TEXT DEFAULT '',
        affiliate_id TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tạo bảng khảo sát gỡ cài đặt
    $db->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reason TEXT,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: Kiểm tra và thêm cột nếu đang ở version cũ
    $check_column = $db->query("PRAGMA table_info(links)")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($check_column, 'name');
    if (!in_array('clicks', $col_names)) {
        $db->exec("ALTER TABLE links ADD COLUMN clicks INTEGER DEFAULT 0");
    }
    if (!in_array('source_url', $col_names)) {
        $db->exec("ALTER TABLE links ADD COLUMN source_url TEXT DEFAULT ''");
    }
    if (!in_array('affiliate_id', $col_names)) {
        $db->exec("ALTER TABLE links ADD COLUMN affiliate_id TEXT DEFAULT ''");
    }

    // Tạo bảng settings nếu chưa có
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT DEFAULT ''
    )");

    // Tự động di chuyển dữ liệu từ JSON sang SQLite (Chỉ chạy 1 lần)
    $json_file = 'links.json';
    if (file_exists($json_file)) {
        $json_data = json_decode(file_get_contents($json_file), true);
        if ($json_data && is_array($json_data)) {
            $stmt_migrate = $db->prepare("INSERT OR IGNORE INTO links (slug, url, clicks) VALUES (?, ?, 0)");
            foreach ($json_data as $s => $u) {
                $stmt_migrate->execute([$s, $u]);
            }
            rename($json_file, 'links.json.bak');
        }
    }
    // Lấy cấu hình từ bảng settings
    $stmt_settings = $db->query("SELECT * FROM settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $remote_api_key = isset($settings['remote_api_key']) ? trim($settings['remote_api_key']) : 'FREE-85C45DDDBF3CEADB';
    $remote_api_url = 'https://app.affreel.com/v1'; // Fix cứng URL Server API

    // CHUYỂN HƯỚNG NẾU CHƯA CÓ API KEY
    if (empty($remote_api_key) && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
        header('Location: settings.php');
        exit;
    }

    // Lấy cấu hình Branding
    $site_title = isset($settings['site_title']) ? $settings['site_title'] : 'FbReels Pro';
    $site_desc = isset($settings['site_desc']) ? $settings['site_desc'] : 'Hỗ trợ chuyển đổi link Shopee Affiliate và lấy nhanh link từ Fb Reels.';
    $site_keywords = isset($settings['site_keywords']) ? $settings['site_keywords'] : 'shopee affiliate, fb reels, rút gọn link';
    $site_author = isset($settings['site_author']) ? $settings['site_author'] : 'ReelsLink';
    $site_logo = isset($settings['site_logo']) ? $settings['site_logo'] : 'image/favicon.png';
    $site_favicon = isset($settings['site_favicon']) ? $settings['site_favicon'] : 'image/favicon.png';

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

define('API_KEY', LOCAL_API_KEY); // Giữ nguyên alias để các thành phần khác không lỗi

require_once __DIR__ . '/remote_api_helper.php';



// Xử lý Facebook Scrape riêng lẻ (AJAX từ Frontend)
if (isset($_GET['action']) && $_GET['action'] === 'scrape' && isset($_GET['url'])) {
    require_once __DIR__ . '/fb_helper.php';
    smartFacebookScrape($_GET['url']);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Xử lý POST (API & AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $received_key = isset($_POST['api_key']) ? $_POST['api_key'] : '';
    if (empty($received_key)) {
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $received_key = $_SERVER['HTTP_X_API_KEY'];
        } else {
            $all_headers = function_exists('getallheaders') ? getallheaders() : [];
            foreach ($all_headers as $name => $value) {
                if (strcasecmp($name, 'X-API-KEY') === 0) { $received_key = $value; break; }
            }
        }
    }
    
    header('Access-Control-Allow-Origin: *'); 
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-API-KEY');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
    header('Content-Type: application/json');

    if ($received_key !== API_KEY) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Lỗi xác thực: API Key không đúng.']);
        exit;
    }
    
    $raw_input = isset($_POST['reels_url']) ? $_POST['reels_url'] : '';
    $shopee_input = isset($_POST['shopee_url']) ? trim($_POST['shopee_url']) : '';
    $provided_aff_id = isset($_POST['shopee_aff_id']) ? trim($_POST['shopee_aff_id']) : '';

    if (!empty($shopee_input)) {
        $full_url = smartGetFinalUrl($shopee_input);
        
        // Nếu API trả về lỗi (Ví dụ: Key bị chặn)
        if (strpos($full_url, 'ERROR: ') === 0) {
            echo json_encode(['success' => false, 'message' => substr($full_url, 7)]);
            exit;
        }

        // Kiểm tra nếu link vẫn là link rút gọn (chưa expand thành công)
        $is_short = (stripos($full_url, 's.shopee.vn') !== false || stripos($full_url, 'shp.ee') !== false || stripos($full_url, 'shope.ee') !== false);
        if ($is_short && stripos($full_url, 'origin_link') === false) {
             $full_url = smartGetFinalUrl($full_url);
             if (strpos($full_url, 'ERROR: ') === 0) {
                echo json_encode(['success' => false, 'message' => substr($full_url, 7)]);
                exit;
             }
        }

        $aff_id = '';
        $clean_url = $full_url;

        if (!empty($provided_aff_id)) {
            // Logic khi có ID cung cấp: Dùng link sạch (Origin)
            $aff_id = $provided_aff_id;
            $clean_url = strtok($full_url, '?');
            $redir_link = "https://s.shopee.vn/an_redir?origin_link=" . urlencode($clean_url) . "&sm=fb_partner&affiliate_id=" . $aff_id . "&content_source=fb&channel_type=fb&content_type=REELS";
        } else {
            // Logic cũ: Trích xuất Affiliate ID từ link đầy đủ
            if (preg_match('/(?:an_|affiliate_id=|aff_id=)([a-zA-Z0-9_-]+)/i', urldecode($full_url), $matches)) {
                $aff_id = $matches[1];
            }
            $clean_url = strtok($full_url, '?');
            $redir_link = "https://s.shopee.vn/an_redir?origin_link=" . urlencode($full_url) . "&sm=fb_partner&affiliate_id=" . $aff_id . "&content_source=fb&channel_type=fb&content_type=REELS";
        }
        
        $result = [
            'success' => true,
            'link' => $redir_link,
            'full_link' => $full_url,
            'clean_link' => $clean_url,
            'affiliate_id' => $aff_id
        ];
        
        $short_link = generateShortLink($redir_link, $db, $shopee_input, $aff_id);
        $result['short_link'] = $short_link;
        
        echo json_encode($result);
        exit;
    }
    
    $input = stripslashes(str_replace('\\/', '/', $raw_input));
    
    if (empty($input)) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu đầu vào trống.']);
        exit;
    }

    $is_direct_shopee = (stripos($input, 'shopee.vn') !== false || stripos($input, 'shp.ee') !== false || stripos($input, 'shope.ee') !== false);
    if ($is_direct_shopee) {
        $result = smartExtractShopeeLink($input);
    } else {
        $html = fetchHTMLFromURL($input);
        if (!$html) {
            $result = smartExtractShopeeLink($input);
            if (!$result['success']) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy link. FB có thể đã chặn bot.']);
                exit;
            }
        } else {
            $result = smartExtractShopeeLink($html, true);
        }
    }

    if ($result['success']) {
        // Ưu tiên source_url gửi rõ ràng từ bookmarklet/extension
        $explicit_source = isset($_POST['source_url']) ? trim($_POST['source_url']) : '';
        if (empty($explicit_source)) {
            // Fallback: nếu input gốc là link FB/ngoài thì dùng nó làm source
            $decoded_input = rawurldecode(urldecode($raw_input));
            $contains_shopee = (stripos($decoded_input, 'shopee.vn') !== false
                || stripos($decoded_input, 'shp.ee') !== false
                || stripos($decoded_input, 'shope.ee') !== false);
            $explicit_source = $contains_shopee ? '' : $raw_input;
        }
        $result['short_link'] = generateShortLink($result['link'], $db, $explicit_source, $result['affiliate_id'] ?? '');
    }
    echo json_encode($result);
    exit;
}

/**
 * Hàm tạo link rút gọn cục bộ tại Client (Sau khi đã có kết quả từ API)
 */
function generateShortLink($longUrl, $db, $sourceUrl = '', $affId = '') {
    $stmt = $db->prepare("SELECT slug FROM links WHERE url = ? LIMIT 1");
    $stmt->execute([$longUrl]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $slug = $row['slug'];
        if ($sourceUrl || $affId) {
            $db->prepare("UPDATE links SET 
                source_url = CASE WHEN source_url IS NULL OR source_url = '' THEN ? ELSE source_url END,
                affiliate_id = CASE WHEN affiliate_id IS NULL OR affiliate_id = '' THEN ? ELSE affiliate_id END
                WHERE slug = ?")->execute([$sourceUrl, $affId, $slug]);
        }
    } else {
        $slug = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $stmt_insert = $db->prepare("INSERT INTO links (slug, url, clicks, source_url, affiliate_id) VALUES (?, ?, 0, ?, ?)");
        $stmt_insert->execute([$slug, $longUrl, $sourceUrl, $affId]);
    }

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $custom_domain = '';
    try {
        $s = $db->prepare("SELECT value FROM settings WHERE key = 'custom_domain'");
        $s->execute();
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['value'])) $custom_domain = $r['value'];
    } catch (Exception $e) {}

    if ($custom_domain) {
        $baseUrl = $protocol . "://" . $custom_domain;
    } else {
        $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    }
    return rtrim($baseUrl, '/') . "/s/" . $slug;
}

$auto_link = isset($_GET['extract']) ? $_GET['extract'] : '';
$auto_source = isset($_GET['source']) ? $_GET['source'] : '';

// Cấu hình URL hiện tại
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'];

// Lấy cấu hình từ database cha
$site_title = 'FbReels Pro';
$site_desc = 'Hỗ trợ chuyển đổi link Shopee Affiliate và lấy nhanh link từ Fb Reels.';
$site_keywords = 'shopee affiliate, fb reels, rút gọn link';
$site_author = 'ReelsLink';
$site_logo = '../image/favicon.png';
$site_favicon = '../image/favicon.png';

try {
    $db_path = __DIR__ . '/../links.db';
    if (file_exists($db_path)) {
        $db_tmp = new PDO("sqlite:" . $db_path);
        $stmt_tmp = $db_tmp->query("SELECT key, value FROM settings");
        $settings_tmp = $stmt_tmp->fetchAll(PDO::FETCH_KEY_PAIR);
        if (isset($settings_tmp['site_title'])) $site_title = $settings_tmp['site_title'];
        if (isset($settings_tmp['site_desc'])) $site_desc = $settings_tmp['site_desc'];
        if (isset($settings_tmp['site_keywords'])) $site_keywords = $settings_tmp['site_keywords'];
        if (isset($settings_tmp['site_author'])) $site_author = $settings_tmp['site_author'];
        if (isset($settings_tmp['site_logo'])) $site_logo = '../' . $settings_tmp['site_logo'];
        if (isset($settings_tmp['site_favicon'])) $site_favicon = '../' . $settings_tmp['site_favicon'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($site_desc); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($site_keywords); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($site_author); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($site_favicon); ?>">
    
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XX8CW4JJHN"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-XX8CW4JJHN');
    </script>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $base_url; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($site_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($site_desc); ?>">
    <meta property="og:image" content="<?php echo $base_url; ?>/image/og.jpg">

    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>const REELSLINK_API_KEY = "<?php echo API_KEY; ?>";</script>
</head>
<body>
    <div class="container">
        <div class="hero">
            <button id="theme-toggle" class="theme-toggle-btn" title="Chuyển chế độ Sáng/Tối">
                <span class="theme-icon">🌙</span>
            </button>
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                <a href="../index.php" class="logo" style="text-decoration: none; display: flex; align-items: center; gap: 12px; color: var(--primary);">
                    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo" style="width: 48px; height: 48px; border-radius: 50%;"> 
                    <span><?php echo htmlspecialchars($site_title); ?></span>
                </a>
            </h1>
            <p>Trích xuất Shopee Affiliate từ Facebook Reels!</p>
        </div>

        <div class="extractor-card <?php echo $auto_link ? 'auto-processing' : ''; ?>">
            <div class="tabs">
                <button class="tab-btn active" data-tab="shopee">Link Shopee</button>
                <button class="tab-btn" data-tab="fbreel">FB Reels</button>
                <button class="tab-btn" data-tab="setup">Cài đặt ⚙️</button>
            </div>

            <div id="shopee-tab" class="tab-content active">
                <div style="text-align: right; margin-bottom: 0.8rem;">
                    <a href="shopee_guide.php" target="_blank" style="font-size: 0.75rem; color: var(--primary); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fas fa-question-circle"></i> Hướng dẫn?
                    </a>
                </div>
                <div class="input-group" id="shopee-aff-group">
                    <div style="display: flex; gap: 8px;">
                        <div style="position: relative; flex: 1; display: flex; align-items: center;">
                            <input type="text" id="shopee-aff-id" placeholder="Nhập Shopee Affiliate ID (ví dụ: 123)..." style="flex: 1; padding-right: 40px;">
                            <button type="button" id="shopee-aff-clear-btn" style="position: absolute; right: 10px; background: none; border: none; color: var(--text-dim); cursor: pointer; display: none; font-size: 1.2rem; padding: 5px; z-index: 10;">✕</button>
                        </div>
                        <button type="button" id="shopee-aff-ok-btn" style="width: auto; padding: 0 1.5rem; background: var(--secondary); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">OK</button>
                    </div>
                    <div style="margin-top: -5px; text-align: left; padding-left: 5px;">
                        <a href="https://affiliate.shopee.vn/account_setting" target="_blank" style="font-size: 0.7rem; color: var(--secondary); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; opacity: 0.9; transition: opacity 0.2s;">
                            <i class="fas fa-hand-point-right" style="font-size: 0.8rem;"></i> <b>Lấy Affiliate ID của bạn tại đây</b>
                        </a>
                    </div>
                </div>

                <div class="input-group" style="position: relative;">
                    <textarea id="shopee-url" rows="2" placeholder="Dán link Shopee vào đây..." style="width: 100%; padding: 12px 15px 35px 15px; border-radius: 16px; resize: none; border: 1px solid var(--border-color); background: rgba(255,255,255,0.03); color: var(--text-main); font-size: 0.95rem; line-height: 1.5;"></textarea>
                    <button type="button" id="paste-shopee-url-btn" style="position: absolute; bottom: 12px; right: 12px; background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); padding: 8px 14px; border-radius: 10px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s;">
                        <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                        Dán link
                    </button>
                </div>

                <!-- Link Chia Sẻ (Hiện sau khi có ID) -->
                <div id="share-link-group" class="input-group" style="display: none; background: rgba(236, 72, 153, 0.05); border: 1px dashed var(--secondary); padding: 12px; border-radius: 14px; margin-top: 1rem;">
                    <div style="font-size: 0.75rem; color: var(--secondary); font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Link chia sẻ (đã kèm ID):</div>
                    <div style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 8px;">💡 Chia sẻ link này cho bạn bè để chuyển đổi link Shopee!</div>
                    <div id="share-url-display" style="width: 100%; font-size: 0.8rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); padding: 8px 10px; border-radius: 8px; color: var(--text-main); font-family: monospace; margin-bottom: 8px; word-break: break-all; user-select: all; cursor: text;"></div>
                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                        <button type="button" id="edit-aff-id-btn" style="background: rgba(255,255,255,0.1); color: var(--text-dim); border: none; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; cursor: pointer;" title="Đổi ID">✎ Đổi ID</button>
                        <button type="button" id="copy-share-url-btn" style="background: var(--secondary); color: white; border: none; padding: 6px 16px; border-radius: 8px; font-size: 0.75rem; cursor: pointer; font-weight: 600;">📋 Sao chép</button>
                    </div>
                </div>
            </div>

            <div id="fbreel-tab" class="tab-content">
                <div class="input-group" style="position: relative;">
                    <textarea id="reels-url" rows="2" placeholder="Dán link Reels URL vào đây..." style="width: 100%; padding: 12px 15px 35px 15px; border-radius: 16px; resize: none; border: 1px solid var(--border-color); background: rgba(255,255,255,0.03); color: var(--text-main); font-size: 0.95rem; line-height: 1.5;"><?php echo htmlspecialchars($auto_link); ?></textarea>
                    <button type="button" id="paste-reels-url-btn" style="position: absolute; bottom: 12px; right: 12px; background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); padding: 8px 14px; border-radius: 10px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s;">
                        <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                        Dán link
                    </button>
                    <input type="hidden" id="source-url" value="<?php echo htmlspecialchars($auto_source); ?>">
                </div>
            </div>

            <div id="setup-tab" class="tab-content">
                <div class="bookmarklet-setup" style="text-align:center;">
                    <div class="setup-grid">
                        <!-- Cách 1: Extension -->
                        <div class="setup-item">
                            <div style="font-size: 0.75rem; font-weight: 700; color: var(--primary); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.8;">
                                Cách 1: Extension (Khuyên dùng)
                            </div>
                            <a href="extension_guide.php" class="ext-install-btn">
                                🧩 Cài đặt Extension
                            </a>
                            <p style="font-size:0.8rem; color:#60a5fa; margin-top:0.8rem; font-weight:600;">💻 Lấy link 1-Click trên FB máy tính.</p>
                        </div>

                        <!-- Cách 2: Bookmarklet -->
                        <div class="setup-item">
                            <div style="font-size: 0.75rem; font-weight: 700; color: var(--secondary); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.8;">
                                Cách 2: Bookmarklet (Dự phòng)
                            </div>
                            <a href="bookmarklet_guide.php" class="magic-link-btn mini">
                                <span class="stars">✨</span> Magic Link V6.5 <span class="stars">✨</span>
                            </a>
                            <!--
                            <a href="javascript:(function(){var h=document.documentElement.innerHTML;var m=h.match(/[a-z0-9.\\\%:_\/-]*(?:shopee\.vn|shp\.ee|shope\.ee)[^&quot;\'\s<>|]*/i);if(m){var url='<?php echo $current_page; ?>?extract='+encodeURIComponent(m[0])+'&source='+encodeURIComponent(window.location.href);window.open(url,'_blank');}else{alert('Không tìm thấy link!');}})();" class="magic-link-btn mini">
                                <span class="stars">✨</span> Magic Link V6.5 <span class="stars">✨</span>
                            </a>
                            <p style="font-size:0.75rem; color:var(--text-dim); margin-top:0.8rem;">🖱️ Kéo vào thanh dấu trang.</p>
                            -->
                            <a href="bookmarklet_guide.php" style="font-size:0.75rem; color:var(--secondary); text-decoration:none; margin-top:0.5rem; font-weight:600;">📖 Xem hướng dẫn chi tiết</a>
                        </div>
                    </div>

                    <img src="image/affreel.gif" alt="Hướng dẫn setup" style="max-width: 85%; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
                    
                    <div style="margin-top: 1.2rem; border-top: 1px dashed var(--border-color); padding-top: 1.5rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-dim); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.8; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-shield-alt"></i> Quản lý Bản quyền & API
                        </div>
                        <a href="settings.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 0.8rem 1.5rem; background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 12px; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                            <i class="fas fa-key" style="color: var(--primary);"></i> Cấu hình API Key
                        </a>
                        <p style="font-size: 0.75rem; color: var(--text-dim); margin-top: 0.8rem;">Dành cho chủ sở hữu mã nguồn FbReels Pro.</p>
                    </div>
                </div>
            </div>
            
            <button id="extract-btn" class="extractor-btn">
                <span class="btn-text">Tạo link ngay ⚡</span>
                <div class="loader" id="loader"></div>
            </button>
            <div class="status-msg" id="status-msg"></div>

            <div id="fallback-section" class="fallback-section" style="display:none;">
                <p>Bot không thể xem Reel này. Hãy thử <a href="extension_guide.php" target="_blank" style="color: #ff9900; font-weight: 600; text-decoration: none;">cài đặt Extension</a> hoặc kéo <a href="javascript:(function(){var h=document.documentElement.innerHTML;var m=h.match(/[a-z0-9.\\\%:_\/-]*(?:shopee\.vn|shp\.ee|shope\.ee)[^&quot;\'\s<>|]*/i);if(m){var url='<?php echo $current_page; ?>?extract='+encodeURIComponent(m[0])+'&source='+encodeURIComponent(window.location.href);window.open(url,'_blank');}else{alert('Không tìm thấy link!');}})();" class="magic-link-btn mini"><span class="stars">✨</span> Magic Link <span class="stars">✨</span></a> vào thanh dấu trang (<a href="bookmarklet_guide.php" target="_blank" style="color: var(--secondary); text-decoration: none; font-weight: 600;">Xem hướng dẫn</a>) để lấy link tự động nhé.</p>
                <a href="#" id="fallback-btn" target="_blank" class="fb-btn">Mở Reel này bằng trình duyệt để sử dụng Bookmarklet hoặc Extension</a>
            </div>

            <div class="result-section" id="result-section">
                <div class="result-card">
                    <!-- 1. Link mã Voucher độc quyền FB (Chỉ hiện khi là Shopee) -->
                    <div id="shopee-result-details" style="display:none;">
                        <div id="redir-link-section">
                            <div class="result-label">Link mã Voucher độc quyền FB:</div>
                            <div class="link-row" style="position: relative; display: block;">
                                <div class="link-display" id="redir-link-display" style="font-size:0.7rem; color:#facc15; padding: 6px 10px 35px 10px;"></div>
                                <div style="position: absolute; right: 6px; bottom: 6px; display: flex; gap: 6px;">
                                    <button class="copy-icon-btn buy-btn" id="buy-redir-btn" title="Mở link mua hàng" style="width: auto; height: 26px; padding: 0 8px; font-size: 0.65rem; display: flex; align-items: center; gap: 3px; border-radius: 5px; background: rgba(238, 77, 45, 0.1); border-color: rgba(238, 77, 45, 0.2); color: #ee4d2d;">
                                        <span>🛒 Mua hàng</span>
                                    </button>
                                    <button class="copy-icon-btn" id="copy-redir-btn" title="Sao chép link redirection" style="width: auto; height: 26px; padding: 0 8px; font-size: 0.65rem; display: flex; align-items: center; gap: 3px; border-radius: 5px;">
                                        <span class="copy-text">Sao chép</span>
                                        <svg class="icon-copy" style="width:10px; height:10px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        <svg class="icon-check" style="width:10px; height:10px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Link rút gọn chính -->
                    <div class="result-label" id="short-link-label" style="margin-top: 1rem">Link rút gọn (có mã Voucher độc quyền FB):</div>
                    <div class="link-row" style="position: relative; display: block;">
                        <div class="link-display" id="short-link-display" style="font-size:1.1rem; color: var(--primary); padding: 8px 12px 38px 12px;"></div>
                        <div style="position: absolute; right: 6px; bottom: 6px; display: flex; gap: 6px;">
                            <button class="copy-icon-btn buy-btn" id="buy-short-btn" title="Mở link mua hàng" style="width: auto; height: 28px; padding: 0 10px; font-size: 0.7rem; display: flex; align-items: center; gap: 4px; border-radius: 6px; background: rgba(238, 77, 45, 0.1); border-color: rgba(238, 77, 45, 0.2); color: #ee4d2d;">
                                <span>🛒 Mua hàng</span>
                            </button>
                            <button class="copy-icon-btn" id="copy-short-btn" title="Sao chép link rút gọn" style="width: auto; height: 28px; padding: 0 10px; font-size: 0.7rem; display: flex; align-items: center; gap: 4px; border-radius: 6px;">
                                <span class="copy-text">Sao chép</span>
                                <svg class="icon-copy" style="width:12px; height:12px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <svg class="icon-check" style="width:12px; height:12px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Nút toggle xem thêm chi tiết -->
                    <div id="toggle-details-btn" style="display:none; text-align:center; margin-top: 0.8rem;">
                        <button type="button" onclick="toggleLinkDetails()" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-dim); padding: 6px 16px; border-radius: 10px; font-size: 0.8rem; cursor: pointer; transition: all 0.3s;">
                            ▼ Xem thêm chi tiết link
                        </button>
                    </div>

                    <!-- Chi tiết link (ẩn mặc định) -->
                    <div id="link-details-section" style="display:none;">
                        <div id="clean-link-section">
                            <div class="result-label" style="margin-top: 1rem">Link sạch (Origin) <span style="font-weight: normal; font-size: 0.7rem; color: var(--text-dim);">(Link gốc - Không có ID Affiliate, không có hoa hồng)</span>:</div>
                            <div class="link-row" style="position: relative; display: block;">
                                <div class="link-display" id="clean-link-display" style="font-size:0.7rem; color:var(--secondary); padding: 6px 10px 35px 10px;"></div>
                                <div style="position: absolute; right: 6px; bottom: 6px; display: flex; gap: 6px;">
                                    <!--
                                    <button class="copy-icon-btn buy-btn" id="buy-clean-btn" title="Mở link mua hàng" style="width: auto; height: 26px; padding: 0 8px; font-size: 0.65rem; display: flex; align-items: center; gap: 3px; border-radius: 5px; background: rgba(238, 77, 45, 0.1); border-color: rgba(238, 77, 45, 0.2); color: #ee4d2d;">
                                        <span>🛒 Mua hàng</span>
                                    </button>
                                    -->
                                    <button class="copy-icon-btn" id="copy-clean-btn" title="Sao chép link sạch" style="width: auto; height: 26px; padding: 0 8px; font-size: 0.65rem; display: flex; align-items: center; gap: 3px; border-radius: 5px;">
                                        <span class="copy-text">Sao chép</span>
                                        <svg class="icon-copy" style="width:10px; height:10px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        <svg class="icon-check" style="width:10px; height:10px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="long-link-section">
                            <div class="result-label" style="margin-top: 1rem">Link Shopee đích (Full):</div>
                            <div class="link-row" style="position: relative; display: block;">
                                <div class="link-display" id="long-link-display" style="font-size:0.7rem; color:var(--text-dim); padding: 6px 10px 35px 10px;"></div>
                                <div style="position: absolute; right: 6px; bottom: 6px; display: flex; gap: 6px;">
                                    <button class="copy-icon-btn buy-btn" id="buy-dest-btn" title="Mở link mua hàng" style="width: auto; height: 26px; padding: 0 8px; font-size: 0.65rem; display: flex; align-items: center; gap: 3px; border-radius: 5px; background: rgba(238, 77, 45, 0.1); border-color: rgba(238, 77, 45, 0.2); color: #ee4d2d;">
                                        <span>🛒 Mua hàng</span>
                                    </button>
                                    <button class="copy-icon-btn" id="copy-dest-btn" title="Sao chép link Shopee đích" style="width: auto; height: 26px; padding: 0 8px; font-size: 0.65rem; display: flex; align-items: center; gap: 3px; border-radius: 5px;">
                                        <span class="copy-text">Sao chép</span>
                                        <svg class="icon-copy" style="width:10px; height:10px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        <svg class="icon-check" style="width:10px; height:10px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if($auto_link): ?>
                    <a href="index.php" style="display:block; text-align:center; margin-top:1.5rem; font-size:0.8rem; color:var(--primary); text-decoration:none;">← Quay lại</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Beta Testing Modal -->
    <div id="beta-modal" class="modal-backdrop">
        <div class="modal-card">
            <div class="modal-icon">🧪</div>
            <h2 class="modal-title">Thông Báo Thử Nghiệm</h2>
            <div class="modal-body">
                <p>AffReel <strong>"Lách quy định Fb Reels của Shpe"</strong> đang thử nghiệm. Để đảm bảo quyền lợi, vui lòng hỗ trợ AffReel:</p>
                <ul class="modal-list">
                    <li>🚀 <strong>Test link:</strong> Xác nhận Fans thấy <strong>Voucher 20%</strong>.</li>
                    <li>💰 <strong>Mua thử:</strong> Kiểm tra mức <strong>Hoa hồng X3</strong>.</li>
                    <li>🎁 <strong>Cam kết:</strong> Miễn phí 100% trọn đời.</li>
                </ul>
                <p style="font-size: 0.85rem; color: #94a3b8; font-style: italic; margin-top: 1rem;">* Vui lòng báo lỗi & góp ý cho Admin để hoàn thiện AffReel. Cảm ơn bạn!</p>
            </div>
            <button class="modal-btn" onclick="dismissBetaModal()">Tôi đã hiểu & Bắt đầu!</button>
        </div>
    </div>

    <script>
    function toggleLinkDetails() {
        const details = document.getElementById('link-details-section');
        const btn = document.querySelector('#toggle-details-btn button');
        if (details.style.display === 'none') {
            details.style.display = 'block';
            btn.textContent = '▲ Ẩn chi tiết link';
        } else {
            details.style.display = 'none';
            btn.textContent = '▼ Xem thêm chi tiết link';
        }
    }
    </script>
    <script src="app.js?v=<?php echo time(); ?>"></script>
    <?php if($auto_link): ?><script>window.onload=function(){document.getElementById('extract-btn').click();};</script><?php endif; ?>
</body>
</html>
