<?php
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$current_domain = $_SERVER['HTTP_HOST'];
$base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$current_page = $protocol . "://" . $current_domain . $_SERVER['PHP_SELF'];

$site_title = 'FbReels Pro';
$site_favicon = 'image/favicon.png';
$site_logo = 'image/favicon.png';

try {
    $db_path = __DIR__ . '/links.db';
    if (file_exists($db_path)) {
        $db_tmp = new PDO("sqlite:" . $db_path);
        $stmt_tmp = $db_tmp->query("SELECT key, value FROM settings");
        $settings_tmp = $stmt_tmp->fetchAll(PDO::FETCH_KEY_PAIR);
        if (isset($settings_tmp['site_title'])) $site_title = $settings_tmp['site_title'];
        if (isset($settings_tmp['site_logo'])) $site_logo = $settings_tmp['site_logo'];
        if (isset($settings_tmp['site_favicon'])) $site_favicon = $settings_tmp['site_favicon'];
    }
} catch (Exception $e) {}

$chrome_link = "https://1links.cc/reelslink-pro-extractor";
$firefox_link = "https://1links.cc/reelslink-pro-extractor-firefox";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hướng dẫn cài đặt Extension - <?php echo htmlspecialchars($site_title); ?></title>
    <meta name="description" content="Hướng dẫn cài đặt Extension trình trích xuất link Shopee Affiliate từ Facebook Reels tự động.">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($site_favicon); ?>">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .guide-container {
            max-width: 800px;
            margin: 40px auto;
            animation: fadeInUp 0.8s ease-out;
        }
        .browser-detection-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        body.dark-mode .browser-detection-card {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .browser-icon-main {
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 0 15px var(--primary-glow));
            display: flex;
            justify-content: center;
        }
        .browser-icon-main svg {
            width: 100px;
            height: 100px;
        }
        .install-btn-container {
            margin: 2rem 0;
        }
        .install-now-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            background: linear-gradient(135deg, var(--primary), #818cf8);
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 100px;
            font-size: 1.15rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3);
            min-width: 320px;
        }
        .install-now-btn svg {
            width: 32px;
            height: 32px;
            flex-shrink: 0;
        }
        .install-now-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.4);
        }
        .detected-text {
            font-size: 0.9rem;
            color: var(--text-dim);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 3rem;
        }
        .step-item {
            background: var(--input-bg);
            padding: 1.2rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        .step-item:hover {
            background: var(--card-bg);
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        .step-num {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 5px 15px var(--primary-glow);
        }
        .step-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }
        .step-desc {
            font-size: 0.85rem;
            color: var(--text-dim);
            line-height: 1.5;
        }
        .other-browsers {
            margin-top: 4rem;
            text-align: center;
        }
        .other-browsers h3 {
            font-size: 1.1rem;
            color: var(--text-dim);
            margin-bottom: 1.5rem;
            font-weight: 400;
        }
        .browser-list {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .browser-link {
            color: var(--text-dim);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            opacity: 0.6;
        }
        .browser-link:hover {
            opacity: 1;
            color: var(--primary);
            transform: translateY(-3px);
        }
        .browser-link i {
            font-size: 1.8rem;
        }
        .browser-link span {
            font-size: 0.75rem;
            font-weight: 600;
        }
        @media (max-width: 600px) {
            .steps-grid { grid-template-columns: 1fr; }
            .browser-detection-card { padding: 2rem 1rem; }
            .install-now-btn { width: 100%; padding: 1rem 1.5rem; font-size: 1.1rem; }
        }
    </style>
</head>
<body class="admin-body">
    <div class="container guide-container" style="display: block;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; position: relative;">
            <a href="index.php" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; transition: color 0.3s;">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
            <div style="font-size: 0.75rem; color: var(--text-dim); opacity: 0.5; text-transform: uppercase; letter-spacing: 1px;">Hướng dẫn cài đặt</div>
            <button id="theme-toggle" class="theme-toggle-btn" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);" title="Chuyển chế độ Sáng/Tối">
                <span class="theme-icon">🌙</span>
            </button>
        </div>
        
        <div class="hero">
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 12px; color: var(--primary);">
                    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo" style="width: 48px; height: 48px; border-radius: 50%;"> 
                    <span><?php echo htmlspecialchars($site_title); ?></span>
                </a>
            </h1>
            <p>Trình trích xuất Shopee Affiliate tự động</p>
        </div>

        <div class="browser-detection-card" id="detection-card">
            <div class="browser-icon-main" id="browser-icon">
                <i class="fas fa-puzzle-piece"></i>
            </div>
            <h2 id="browser-title-display" style="margin-bottom: 1rem; font-size: 1.8rem; color: var(--text-main);">Cài đặt Extension</h2>
            <p style="color: var(--text-dim); line-height: 1.6; max-width: 500px; margin: 0 auto; font-size: 0.95rem;">
                Sử dụng Extension để lấy link sản phẩm Shopee trực tiếp trên Facebook Reels máy tính chỉ với một cú nhấp chuột.
            </p>

            <div class="install-btn-container">
                <a href="<?php echo $chrome_link; ?>" id="main-install-link" target="_blank" class="install-now-btn">
                    <i class="fab fa-chrome"></i> Cài đặt ngay
                </a>
            </div>

            <div class="other-browsers" style="margin-top: 1rem; margin-bottom: 2rem;">
                <h3 style="font-size: 0.9rem; color: var(--text-dim); margin-bottom: 1rem; font-weight: 400;">Hoặc chọn phiên bản cho trình duyệt khác</h3>
                <div class="browser-list" style="gap: 1.5rem;">
                    <a href="<?php echo $chrome_link; ?>" target="_blank" class="browser-link">
                        <svg viewBox="0 0 48 48" width="22" height="22" style="margin-bottom: 2px;"><circle cx="24" cy="23.9947" r="12" fill="#fff"/><path d="M3.2154,36A24,24,0,1,0,12,3.2154,24,24,0,0,0,3.2154,36ZM34.3923,18A12,12,0,1,1,18,13.6077,12,12,0,0,1,34.3923,18Z" fill="none"/><path d="M24,12H44.7812a23.9939,23.9939,0,0,0-41.5639.0029L13.6079,30l.0093-.0024A11.9852,11.9852,0,0,1,24,12Z" fill="#ea4335"/><circle cx="24" cy="24" r="9.5" fill="#1a73e8"/><path d="M34.3913,30.0029,24.0007,48A23.994,23.994,0,0,0,44.78,12.0031H23.9989l-.0025.0093A11.985,11.985,0,0,1,34.3913,30.0029Z" fill="#fbbc04"/><path d="M13.6086,30.0031,3.218,12.006A23.994,23.994,0,0,0,24.0025,48L34.3931,30.0029l-.0067-.0068a11.9852,11.9852,0,0,1-20.7778.007Z" fill="#34a853"/></svg>
                        <span style="font-size: 0.7rem;">Chrome</span>
                    </a>
                    <a href="<?php echo $chrome_link; ?>" target="_blank" class="browser-link">
                        <svg viewBox="0 0 256 256" width="22" height="22" style="margin-bottom: 2px;"><defs><linearGradient id="edge_a" x1="63.3" y1="84" x2="241.7" y2="84" gradientTransform="matrix(1 0 0 -1 0 266)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#0c59a4"/><stop offset="1" stop-color="#114a8b"/></linearGradient><linearGradient id="edge_c" x1="157.3" y1="161.4" x2="46" y2="40.1" gradientTransform="matrix(1 0 0 -1 0 266)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#1b9de2"/><stop offset="0.2" stop-color="#1595df"/><stop offset="0.7" stop-color="#0680d7"/><stop offset="1" stop-color="#0078d4"/></linearGradient><radialGradient id="edge_e" cx="113.4" cy="570.2" r="202.4" gradientTransform="matrix(-.04 1 2.13 0.08 -1179.5 -106.7)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#35c1f1"/><stop offset="0.7" stop-color="#36c752"/></radialGradient></defs><path d="M235.7 195.5a93.7 93.7 0 0 1-10.6 4.7 101.9 101.9 0 0 1-35.9 6.4c-47.3 0-88.5-32.5-88.5-74.3a31.5 31.5 0 0 1 16.4-27.3c-42.8 1.8-53.8 46.4-53.8 72.5 0 74 68.1 81.4 82.8 81.4 7.9 0 19.8-2.3 27-4.6l1.3-.4a128.3 128.3 0 0 0 66.6-52.8 4 4 0 0 0-5.3-5.6Z" transform="translate(-4.6 -5)" fill="url(#edge_a)"/><path d="M110.3 246.3A79.2 79.2 0 0 1 87.6 225a80.7 80.7 0 0 1 29.5-120c3.2-1.5 8.5-4.1 15.6-4a32.4 32.4 0 0 1 25.7 13 31.9 31.9 0 0 1 6.3 18.7c0-.2 24.5-79.6-80-79.6-43.9 0-80 41.6-80 78.2a130.2 130.2 0 0 0 12.1 56 128 128 0 0 0 156.4 67 75.5 75.5 0 0 1-62.8-8Z" transform="translate(-4.6 -5)" fill="url(#edge_c)"/><path d="M157 153.8c-.9 1-3.4 2.5-3.4 5.6 0 2.6 1.7 5.2 4.8 7.3 14.3 10 41.4 8.6 41.5 8.6a59.6 59.6 0 0 0 30.3-8.3 61.4 61.4 0 0 0 30.4-52.9c.3-22.4-8-37.3-11.3-43.9C228 28.8 182.3 5 132.6 5a128 128 0 0 0-128 126.2c.5-36.5 36.8-66 80-66 3.5 0 23.5.3 42 10a72.6 72.6 0 0 1 30.9 29.3c6.1 10.6 7.2 24.1 7.2 29.5s-2.7 13.3-7.8 19.9Z" transform="translate(-4.6 -5)" fill="url(#edge_e)"/></svg>
                        <span style="font-size: 0.7rem;">Edge</span>
                    </a>
                    <a href="<?php echo $chrome_link; ?>" target="_blank" class="browser-link">
                        <svg width="22" height="22" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M166.3,19.4c-2.6-0.1-5.1-2.2-4.9-4.7c0.2-3.1,3-5.8,6.2-5.6c2.6,0.1,5.1,2.3,4.9,4.9c-0.1,2.1-1.9,4-4,4.2C167.8,18.3,167,18.3,166.3,19.4z" fill="#f16037"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M163,8.5h22.1c2.4,0,4.8,2.4,4.7,4.6c0,2.6-1.3,3.7-2.1,4.4c0,0-0.1,0.1-0.1,0.1L134.6,68.6v0.1c-1.7,1.8-4.6,1.8-6.3,0c-1.4-1.4-1.6-3.4-0.7-5.1c0,0,25.3-47.2,27.3-50.9C157,9,161.3,8.5,163,8.5z M195.7,101.7c0,51.5-41.9,93.3-93.6,93.3c-51.6,0-93.6-41.7-93.6-93.3c0-47,34.9-85.9,80.2-92.3c0.2,0,0.4-0.1,0.6-0.1c0.2,0,0.4-0.1,0.6-0.1c1.5,0.1,2.8,1.1,3.2,2.5l0.1,1.4l3.6,41.9l0.1,1.2c-0.1,1.5-1.2,2.6-2.5,3.1c-0.2,0-0.4,0-0.6,0.1c-19.9,3.9-35.1,21.4-35.1,42.5c0,23.9,19.4,43.3,43.4,43.3c23.9,0,43.3-19.3,43.4-43.1v-0.1v-0.2v-0.2c0.1-1.5,1.1-2.6,2.4-3.1l0.6-0.1l42.1-8.7l1-0.2h0.2c1.6,0,3,1.1,3.4,2.6c0,0.1,0,0.3,0,0.5c0,0.2,0.1,0.4,0.1,0.6C195.5,96.1,195.7,98.9,195.7,101.7z M131.8,101.7c0,16.3-13.2,29.6-29.6,29.6c-16.3,0-29.7-13.2-29.7-29.6c0-16.3,13.2-29.6,29.7-29.6C118.6,72.1,131.8,85.4,131.8,101.7z" fill="url(#coccoc_grad)"/>
                            <path d="M180.3,74.7l-34.3,11.1c-2,0.6-4.1-0.4-4.8-2.4c-0.5-1.6,0.1-3.3,1.3-4.2l28.7-21.8c4.3-3.3,10.6-2.4,13.9,1.9c3.3,4.3,2.4,10.6-1.9,13.8C182.4,73.8,181.4,74.3,180.3,74.7z" fill="#f16037"/>
                            <defs>
                                <linearGradient id="coccoc_grad" x1="13.7" y1="20" x2="26.3" y2="20" gradientUnits="userSpaceOnUse" gradientTransform="scale(5.12)">
                                    <stop stop-color="#97C93D"/><stop offset="0.4" stop-color="#80C44B"/><stop offset="1" stop-color="#4EB969"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <span style="font-size: 0.7rem;">Cốc Cốc</span>
                    </a>
                    <a href="<?php echo $firefox_link; ?>" target="_blank" class="browser-link">
                        <svg viewBox="0 0 512 512" width="22" height="22" style="margin-bottom: 2px;"><defs><linearGradient id="ff_main" x1="87.25%" y1="15.5%" x2="9.4%" y2="93.1%"><stop offset=".05" stop-color="#fff44f"/><stop offset=".37" stop-color="#ff980e"/><stop offset=".53" stop-color="#ff3647"/><stop offset=".7" stop-color="#e31587"/></linearGradient></defs><path d="M478.711 166.353c-10.445-25.124-31.6-52.248-48.212-60.821 13.52 26.505 21.345 53.093 24.335 72.936 0 .039.015.136.047.4C427.706 111.135 381.627 83.823 344 24.355c-1.9-3.007-3.805-6.022-5.661-9.2a73.716 73.716 0 01-2.646-4.972A43.7 43.7 0 01332.1.677a.626.626 0 00-.546-.644.818.818 0 00-.451 0c-.034.012-.084.051-.12.065-.053.021-.12.069-.176.1-9.291 4.428-64.407 91.694 10.298 166.389z" fill="url(#ff_main)"/><path d="M18.223 261.41C36.766 370.988 136.1 454.651 248.855 457.844c104.361 2.954 171.037-57.62 198.576-116.716 17.8-38.2 30.154-100.7 7.479-162.175 8.524 55.661-19.79 109.584-64.051 146.044l-.133.313c-86.245 70.223-168.774 42.368-185.484 30.966-50.282-24.029-71.054-69.838-66.6-109.124-42.457 0-56.934-35.809-56.934-35.809s38.119-27.179 88.358-3.541c46.53 21.893 90.228 3.543 90.233 3.541-.089-1.952-41.917-18.59-58.223-34.656-8.713-8.584-12.85-12.723-16.514-15.828a71.355 71.355 0 00-6.225-4.7 282.929 282.929 0 00-4.981-3.3c-17.528-11.392-52.388-10.765-53.543-10.735h-.111c-9.527-12.067-8.855-51.873-8.312-60.184-.114-.515-7.107 3.63-8.023 4.255a175.073 175.073 0 00-23.486 20.12 210.478 210.478 0 00-22.435 26.916c0 .012-.007.026-.011.038 0-.013.007-.026.011-.038a202.838 202.838 0 00-32.247 72.805c-.115.521-8.65 37.842-4.44 57.199z" fill="url(#ff_main)"/><path d="M148.439 277.443s11.093-41.335 79.432-41.335c7.388 0 28.509-20.615 28.9-26.593s-43.7 18.352-90.233-3.541c-50.239-23.638-88.358 3.541-88.358 3.541s14.477 35.809 56.934 35.809c-4.453 39.286 16.319 85.1 66.6 109.124 1.124.537 2.18 1.124 3.334 1.639-29.348-15.169-53.582-43.834-56.609-78.644z" fill="#fff"/></svg>
                        <span style="font-size: 0.7rem;">Firefox</span>
                    </a>
                </div>
            </div>

            <div class="steps-grid">
                <div class="step-item">
                    <div class="step-num">1</div>
                    <div class="step-title">Thêm vào trình duyệt</div>
                    <div class="step-desc">Nhấn "Add to Chrome" hoặc "Get" tại cửa hàng Extension.</div>
                </div>
                <div class="step-item">
                    <div class="step-num">2</div>
                    <div class="step-title">Ghim Extension</div>
                    <div class="step-desc">Nhấn vào biểu tượng <i class="fas fa-puzzle-piece"></i> và Ghim (Pin) ReelsLink Pro.</div>
                </div>
                <div class="step-item">
                    <div class="step-num">3</div>
                    <div class="step-title">Sử dụng trên FB</div>
                    <div class="step-desc">Mở link Reels và nhấn nút "Get Link" bên cạnh sản phẩm.</div>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 3rem; padding-bottom: 3rem;">
            <a href="index.php" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem; transition: color 0.3s ease;">
                <i class="fas fa-arrow-left"></i> Quay lại trang chủ
            </a>
        </div>
    </div>

    <script>
        const chromeLink = "<?php echo $chrome_link; ?>";
        const firefoxLink = "<?php echo $firefox_link; ?>";

        const themeToggle = document.getElementById('theme-toggle');
        const body = document.body;
        
        function setTheme(theme) {
            if (theme === 'dark') {
                body.classList.add('dark-mode');
            } else {
                body.classList.remove('dark-mode');
            }
        }

        let savedTheme = localStorage.getItem('theme');
        if (!savedTheme) {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            savedTheme = prefersDark ? 'dark' : 'light';
        }
        setTheme(savedTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const isNowDark = !body.classList.contains('dark-mode');
                setTheme(isNowDark ? 'dark' : 'light');
                localStorage.setItem('theme', isNowDark ? 'dark' : 'light');
            });
        }

        async function detectBrowser() {
            const ua = navigator.userAgent;
            const icon = document.getElementById('browser-icon');
            const title = document.getElementById('browser-title-display');
            const mainLink = document.getElementById('main-install-link');

            let browser = "chrome";
            let name = "Chrome";
            let iconCode = '<i class="fab fa-chrome"></i>';
            let targetLink = chromeLink;

            if (ua.includes("Firefox/")) {
                browser = "firefox";
                name = "Firefox";
                targetLink = firefoxLink;
                iconCode = '<i class="fab fa-firefox"></i>';
            } else if (ua.includes("Edg/")) {
                browser = "edge";
                name = "Microsoft Edge";
                iconCode = '<i class="fab fa-edge"></i>';
            } else if (ua.includes("CocCoc/")) {
                browser = "coccoc";
                name = "Cốc Cốc";
                iconCode = '<i class="fas fa-puzzle-piece"></i>';
            }

            title.textContent = `Cài đặt cho ${name}`;
            mainLink.href = targetLink;
            mainLink.innerHTML = `${iconCode} &nbsp; Cài đặt cho ${name}`;
        }

        window.onload = detectBrowser;
    </script>
</body>
</html>
