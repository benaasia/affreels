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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hướng dẫn lấy link Shopee - <?php echo htmlspecialchars($site_title); ?></title>
    <meta name="description" content="Hướng dẫn cách lấy link Shopee chuẩn để chuyển đổi sang link Affiliate thành công 100%.">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($site_favicon); ?>">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .guide-container {
            max-width: 800px;
            margin: 40px auto;
            animation: fadeInUp 0.8s ease-out;
        }
        .guide-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        body.dark-mode .guide-card {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .steps-container {
            text-align: left;
            margin-top: 3rem;
        }
        .step-row {
            display: flex;
            gap: 20px;
            margin-bottom: 2.5rem;
            align-items: flex-start;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
            box-shadow: 0 5px 15px var(--primary-glow);
        }
        .step-content h3 {
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
            color: var(--text-main);
        }
        .step-content p {
            font-size: 0.95rem;
            color: var(--text-dim);
            line-height: 1.6;
        }
        .step-image {
            margin-top: 1rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            max-width: 100%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .highlight {
            color: var(--primary);
            font-weight: 600;
        }
        @media (max-width: 600px) {
            .guide-container { margin: 20px auto; padding: 0 15px; }
            .guide-card { padding: 1.5rem 1rem; }
            .step-row { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body class="admin-body">
    <div class="container guide-container" style="display: block;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; position: relative;">
            <a href="index.php" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; transition: color 0.3s;">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
            <div style="font-size: 0.75rem; color: var(--text-dim); opacity: 0.5; text-transform: uppercase; letter-spacing: 1px;">Hướng dẫn sử dụng</div>
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
            <p>Hướng dẫn chuyển link Shopee Affiliate</p>
        </div>

        <div class="guide-card">
            <h2 style="margin-bottom: 1.5rem; font-size: 1.8rem; color: var(--text-main);">3 Bước tạo link cực nhanh</h2>
            
            <div class="steps-container">
                <div class="step-row">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Thiết lập Affiliate ID</h3>
                        <p>Nhập mã <span class="highlight">Shopee Affiliate ID</span> của bạn vào ô đầu tiên và nhấn <span class="highlight">OK</span>. ID này thường là một dãy số (ví dụ: 123456789). Bạn chỉ cần nhập một lần, hệ thống sẽ tự ghi nhớ.</p>
                        <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 8px;">
                            <a href="https://affiliate.shopee.vn/account_setting" target="_blank" style="font-size: 0.85rem; color: var(--secondary); text-decoration: none; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-external-link-alt"></i> <b>Lấy Affiliate ID của bạn tại đây</b>
                            </a>
                            <a href="https://s.shopee.vn/9zuEFicMMj" target="_blank" style="font-size: 0.85rem; color: var(--text-dim); text-decoration: none; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-user-plus"></i> Chưa có tài khoản? <b>Đăng ký Shopee Affiliate</b>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="step-row">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Dán link sản phẩm Shopee</h3>
                        <p>Sao chép link sản phẩm từ ứng dụng Shopee hoặc trình duyệt, sau đó nhấn nút <span class="highlight">Dán link</span> hoặc dán trực tiếp vào ô nhập liệu.</p>
                    </div>
                </div>

                <div class="step-row">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Tạo link và Chia sẻ</h3>
                        <p>Nhấn nút <span class="highlight">Tạo link Shopee</span>. Hệ thống sẽ ngay lập tức tạo ra link rút gọn đã gắn mã của bạn. Bạn có thể nhấn <span class="highlight">Mua hàng</span> để kiểm tra hoặc <span class="highlight">Sao chép</span> để đi chia sẻ.</p>
                    </div>
                </div>
            </div>

            <div style="margin-top: 1rem; padding: 1.5rem; background: var(--input-bg); border-radius: 20px; text-align: left; border: 1px dashed var(--border-color);">
                <h4 style="color: var(--primary); margin-bottom: 0.5rem;"><i class="fas fa-info-circle"></i> Tại sao nên dùng Tool này?</h4>
                <ul style="color: var(--text-dim); font-size: 0.9rem; padding-left: 1.2rem; line-height: 1.6;">
                    <li>Giúp chuyển đổi link Shopee thông thường sang link Affiliate có hoa hồng.</li>
                    <li>Tự động gắn mã Voucher độc quyền dành cho khách hàng từ Facebook.</li>
                    <li>Link ngắn gọn, chuyên nghiệp, tỷ lệ click cao hơn.</li>
                </ul>
            </div>
        </div>

        <div style="text-align: center; margin-top: 3rem; padding-bottom: 3rem;">
            <a href="index.php" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem; transition: color 0.3s ease;">
                <i class="fas fa-arrow-left"></i> Quay lại trang chủ
            </a>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>
