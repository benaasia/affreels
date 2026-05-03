<?php
$main_server_url = "https://affreel.com"; 

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$landing_url = $protocol . "://" . $_SERVER['HTTP_HOST'];

$branding = [
    'site_title' => 'FbReels Pro',
    'site_desc' => 'Chuyển đổi link Shopee Affiliate và lấy link từ Facebook Reels tự động chuyên nghiệp.',
    'site_keywords' => 'shopee affiliate, facebook reels, chuyển đổi link shopee, tạo link shopee, fb reels pro, affiliate marketing',
    'site_author' => 'FbReels Pro',
    'site_logo' => $landing_url . '/image/logo.png',
    'site_favicon' => $landing_url . '/image/favicon.png',
    'site_og_image' => $landing_url . '/image/og.jpg',
    'site_video_url' => 'https://www.youtube.com/shorts/nj7U1OcOaX0'
];

$loaded = false;

$func_path = __DIR__ . '/../functions.php';
if (file_exists($func_path)) {
    require_once $func_path;
    try {
        $db = get_db_connection();
        if ($db) {
            $stmt = $db->query("SELECT key, value FROM settings");
            $settings_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            if (isset($settings_data['site_title']) && $settings_data['site_title'] !== '') $branding['site_title'] = $settings_data['site_title'];
            if (isset($settings_data['site_desc']) && $settings_data['site_desc'] !== '') $branding['site_desc'] = $settings_data['site_desc'];
            if (isset($settings_data['site_keywords']) && $settings_data['site_keywords'] !== '') $branding['site_keywords'] = $settings_data['site_keywords'];
            if (isset($settings_data['site_author']) && $settings_data['site_author'] !== '') $branding['site_author'] = $settings_data['site_author'];
            if (isset($settings_data['site_logo']) && $settings_data['site_logo'] !== '') $branding['site_logo'] = $settings_data['site_logo'];
            if (isset($settings_data['site_favicon']) && $settings_data['site_favicon'] !== '') $branding['site_favicon'] = $settings_data['site_favicon'];
            if (isset($settings_data['site_og_image']) && $settings_data['site_og_image'] !== '') $branding['site_og_image'] = $settings_data['site_og_image'];
            if (isset($settings_data['site_video_url']) && $settings_data['site_video_url'] !== '') $branding['site_video_url'] = $settings_data['site_video_url'];
            if (isset($settings_data['site_gtag_id'])) $branding['site_gtag_id'] = $settings_data['site_gtag_id'];
            
            $loaded = true;
        }
    } catch (Exception $e) {}
}

$site_title = $branding['site_title'];
$site_desc = $branding['site_desc'];
$site_keywords = $branding['site_keywords'];
$site_author = $branding['site_author'];

$site_logo = (strpos($branding['site_logo'], 'http') === 0) ? $branding['site_logo'] : '../' . ltrim($branding['site_logo'], '/');
$site_favicon = (strpos($branding['site_favicon'], 'http') === 0) ? $branding['site_favicon'] : '../' . ltrim($branding['site_favicon'], '/');
$site_og_image = (strpos($branding['site_og_image'], 'http') === 0) ? $branding['site_og_image'] : $landing_url . '/' . ltrim($branding['site_og_image'], '/');
$site_video_url = $branding['site_video_url'];
$site_gtag_id = $branding['site_gtag_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($site_desc); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($site_keywords); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($site_author); ?>">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $base_url; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($site_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($site_desc); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_path); ?>">

    <title><?php echo htmlspecialchars($site_title); ?></title>
    
    <?php if (!empty($site_gtag_id)): ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($site_gtag_id); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', '<?php echo htmlspecialchars($site_gtag_id); ?>');
    </script>
    <?php endif; ?>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($site_favicon); ?>">
    
    <style>
        :root {
            --bg: #09090b;
            --surface: #18181b;
            --surface-hover: #27272a;
            --border: rgba(255, 255, 255, 0.1);
            --border-glow: rgba(59, 130, 246, 0.5);
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.3);
            --accent: #ef4444;
            --accent-glow: rgba(239, 68, 68, 0.3);
            --text: #f4f4f5;
            --text-dim: #a1a1aa;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(59,130,246,0.1) 0%, transparent 50%),
                radial-gradient(circle at 85% 30%, rgba(239,68,68,0.1) 0%, transparent 50%);
        }

        .glass {
            background: rgba(24, 24, 27, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border);
        }

        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 70px;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 5%;
            transition: all 0.3s ease;
        }

        .logo {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 1.5rem;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo img {
            height: 36px;
            width: auto;
            border-radius: 6px;
            background: white;
            padding: 3px;
        }

        .nav-links a {
            color: var(--text-dim);
            text-decoration: none;
            margin-left: 24px;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .nav-links a:hover { color: white; }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #dc2626);
            color: white;
            padding: 10px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 14px var(--accent-glow);
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--accent-glow);
        }

        .btn-outline {
            background: transparent;
            color: white;
            padding: 10px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }

        .btn-outline:hover {
            border-color: var(--text-dim);
            background: rgba(255,255,255,0.05);
        }

        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 120px 20px 60px;
        }

        .shimmer-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            background: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 24px;
            animation: fadeIn 1s ease-out;
        }

        .hero h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 24px;
            max-width: 900px;
            animation: fadeInUp 0.8s ease-out;
        }

        .text-gradient {
            background: linear-gradient(135deg, #f8fafc, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: clamp(1.1rem, 2vw, 1.25rem);
            color: var(--text-dim);
            max-width: 600px;
            margin-bottom: 40px;
            animation: fadeInUp 1s ease-out;
        }

        .cta-group {
            display: flex;
            gap: 16px;
            animation: fadeInUp 1.2s ease-out;
        }

        .dashboard-mockup {
            margin-top: 60px;
            max-width: 1000px;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            animation: slideUpFade 1.4s ease-out;
        }

        .features {
            padding: 100px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 60px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .card {
            padding: 32px;
            border-radius: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border-color: var(--border-glow);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .card h3 {
            font-size: 1.25rem;
            margin-bottom: 12px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .card p {
            color: var(--text-dim);
            font-size: 0.95rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(60px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .cta-group { flex-direction: column; }
            .hero { padding: 100px 15px 40px; }
            .hero h1 { margin-bottom: 16px; }
            .dashboard-mockup { margin-top: 40px; }
            .features { padding: 60px 15px; }
            .section-title { font-size: 2rem; margin-bottom: 30px; }
            .card { padding: 24px; }
        }
    </style>
</head>
<body>
    <nav class="glass" id="navbar">
        <a href="#" class="logo">
            <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo">
            <!--
            <span><?php echo htmlspecialchars($site_title); ?></span>
            -->
        </a>
        <div class="nav-links">
            <a href="../index.php">Ứng dụng</a>
            <a href="<?php echo htmlspecialchars($site_video_url); ?>" target="_blank">Hướng dẫn</a>
        </div>
    </nav>

    <section class="hero">
        <div class="shimmer-badge">✨ V6.5 Premium Update</div>
        <h1 class="text-gradient">Chuyển đổi Link Shopee<br>& Lấy Link Facebook Reels</h1>
        <p>Hỗ trợ chuyển đổi link Shopee Affiliate và lấy nhanh link từ Fb Reels. Magic Link & Chrome Extension giúp bạn thực hiện mọi thứ chỉ với 1 chạm.</p>
        
        <div class="cta-group">
            <a href="../index.php" class="btn-primary">Truy Cập Ứng Dụng Ngay</a>
            <a href="<?php echo htmlspecialchars($site_video_url); ?>" target="_blank" class="btn-outline">▶ Xem Video Hướng Dẫn</a>
        </div>

        <div class="dashboard-mockup glass">
            <div style="background: rgba(0,0,0,0.4); padding: 12px 20px; display: flex; gap: 8px; border-bottom: 1px solid var(--border);">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #ef4444;"></div>
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #eab308;"></div>
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #22c55e;"></div>
            </div>
            <div style="padding: 40px; text-align: left;">
                <h2 style="font-family: 'Plus Jakarta Sans'; margin-bottom: 20px;">Trải Nghiệm Khác Biệt</h2>
                <div class="mockup-features" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px; background: rgba(59, 130, 246, 0.1); padding: 30px; border-radius: 12px; border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div style="font-size: 2rem; margin-bottom: 10px;">⚡</div>
                        <h3 style="margin-bottom: 10px;">Thao Tác Siêu Nhanh</h3>
                        <p style="color: var(--text-dim); font-size: 0.9rem;">Lấy link tức thì ngay trên trang mà không cần tải lại video hay tìm kiếm phức tạp. Chỉ 1 click là có liền.</p>
                    </div>
                    <div style="flex: 1; min-width: 250px; background: rgba(239, 68, 68, 0.1); padding: 30px; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.2);">
                        <div style="font-size: 2rem; margin-bottom: 10px;">🛡️</div>
                        <h3 style="margin-bottom: 10px;">Bảo Vệ Đường Link</h3>
                        <p style="color: var(--text-dim); font-size: 0.9rem;">Bộ lọc thông minh giúp loại bỏ các lượt tương tác ảo bị quét bởi mạng xã hội để tăng tính an toàn.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <section id="install" class="features" style="padding-top: 20px;">
        <h2 class="section-title text-gradient">Cài Đặt Dễ Dàng</h2>
        <div class="grid install-grid">
            <div class="card glass" style="text-align: center;">
                <div class="card-icon" style="margin: 0 auto 20px;">⭐</div>
                <h3>1. Nút Trích Xuất Nhanh (Bookmarklet)</h3>
                <p style="margin-bottom: 24px;">Kéo thả nút Magic Link lên thanh dấu trang (Bookmarks Bar) của trình duyệt. Mỗi khi xem Reels, chỉ cần bật thanh dấu trang và click bấm vào nút này!</p>
                <div style="margin-top: 24px;">
                    <img src="../image/affreel.gif" alt="Hướng dẫn" style="width: 80%; border-radius: 8px;">
                </div>
            </div>
            <div class="card glass" style="text-align: center;">
                <div class="card-icon" style="margin: 0 auto 20px;">🧩</div>
                <h3>2. Tiện Ích Mở Rộng Trình Duyệt</h3>
                <p style="margin-bottom: 24px;">Cài đặt tiện ích mở rộng chính thức cho Chrome/Cốc Cốc trực tiếp từ Cửa hàng ứng dụng để nhấp lấy link siêu tốc tuyệt đối an toàn.</p>
                <a href="https://1links.cc/reelslink-pro-extractor" target="_blank" class="btn-outline" style="display: inline-block; border-color: var(--primary); color: #60a5fa; background: rgba(59, 130, 246, 0.1);">Cài Đặt Từ Chrome Web Store</a>
                <div style="margin-top: 24px; text-align: left; background: rgba(255,255,255,0.05); padding: 20px; border-radius: 8px; font-size: 0.9rem; color: var(--text-dim);">
                    <strong style="color: white; margin-bottom: 8px; display: block;">Cách cài đặt:</strong>
                    1. Bấm vào nút màu xanh ở phía trên.<br>
                    2. Trình duyệt sẽ mở ra trang <b>Cửa Hàng Chrome Trực Tuyến</b>.<br>
                    3. Bấm vào nút màu xanh dương <b>Thêm vào Chrome</b> (Add to Chrome).<br>
                    4. Mở Fb Reel hoặc trang Shopee và nhấp vào biểu tượng <b>FbReels Pro</b> trên thanh công cụ để tự động xử lý link.
                </div>
            </div>
        </div>
    </section>

    <footer style="border-top: 1px solid var(--border); padding: 40px 20px; text-align: center; color: var(--text-dim); font-size: 0.9rem;">
        <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;">
            <img src="<?php echo htmlspecialchars($site_logo); ?>" style="height: 42px; width: auto; background: white; padding: 5px; border-radius: 6px;"> 
            <!--
            <b style="color:white;">FbReels Pro</b>
            -->
        </div>
        <p>&copy; <?php echo date('Y'); ?> Công cụ hỗ trợ Shopee Affiliate & Facebook Reels.</p>
    </footer>

    <script>
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 50) {
                nav.style.background = 'rgba(24, 24, 27, 0.9)';
            } else {
                nav.style.background = 'rgba(24, 24, 27, 0.7)';
            }
        });

        (function() {
            const urlParams = new URLSearchParams(window.location.search);
            const affId = urlParams.get('affiliate_id');
            if (!affId) return;

            document.querySelectorAll('a[href*="index.php"]').forEach(link => {
                const url = new URL(link.href, window.location.href);
                url.searchParams.set('affiliate_id', affId);
                link.href = url.toString();
            });
        })();
    </script>
</body>
</html>
