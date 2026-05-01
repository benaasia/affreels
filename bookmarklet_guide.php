<?php
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$current_domain = $_SERVER['HTTP_HOST'];
$base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$app_url = $protocol . "://" . $current_domain . $base_path . '/index.php';
$current_page = $protocol . "://" . $current_domain . $_SERVER['PHP_SELF'];

$bookmarklet_js = 'javascript:(function(){var h=document.documentElement.innerHTML;var m=h.match(/[a-z0-9.\\\-]*?(?:shopee\\.vn|shp\\.ee|shope\\.ee)[^\\s"\'<>|]*/i);var t=m?m[0]:"";if(!t){var l=document.getElementsByTagName("a");for(var i=0;i<l.length;i++){var r=l[i].href;if(r.indexOf("shopee.vn")!==-1||r.indexOf("shp.ee")!==-1||r.indexOf("shope.ee")!==-1){t=r;break;}}}if(!t)t=window.location.href;if(t.indexOf("shopee.vn")===-1&&t.indexOf("shp.ee")===-1&&t.indexOf("shope.ee")===-1){alert("Không tìm thấy link Shopee!");return;}window.open("' . $app_url . '?extract="+encodeURIComponent(t)+"&source="+encodeURIComponent(window.location.href),"_blank");})();';

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
    <title>Hướng dẫn Bookmarklet - <?php echo htmlspecialchars($site_title); ?></title>
    <meta name="description" content="Hướng dẫn sử dụng Bookmarklet Magic Link để lấy link Shopee Affiliate từ Facebook Reels.">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($site_favicon); ?>">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .guide-container {
            max-width: 800px;
            margin: 40px auto;
            animation: fadeInUp 0.8s ease-out;
        }
        .bookmarklet-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        body.dark-mode .bookmarklet-card {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .magic-btn-container {
            margin: 2.5rem 0;
            padding: 2rem;
            background: var(--input-bg);
            border-radius: 20px;
            border: 1px dashed var(--border-color);
        }
        .drag-instruction {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .steps-container {
            text-align: left;
            margin-top: 3rem;
        }
        .step-row {
            display: flex;
            gap: 20px;
            margin-bottom: 2rem;
            align-items: flex-start;
        }
        .step-number {
            width: 36px;
            height: 36px;
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
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }
        .step-content p {
            font-size: 0.9rem;
            color: var(--text-dim);
            line-height: 1.6;
        }
        .device-toggle {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 2rem;
        }
        .toggle-btn {
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-dim);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .toggle-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .mobile-instructions {
            display: none;
        }
        @media (max-width: 600px) {
            .guide-container { margin: 20px auto; padding: 0 10px; }
            .bookmarklet-card { padding: 1.5rem 1rem; }
            .step-row { flex-direction: column; gap: 10px; }
            .magic-btn-container { padding: 1.5rem 1rem; }
        }
    </style>
</head>
<body class="admin-body">
    <div class="container guide-container" style="display: block;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; position: relative;">
            <a href="index.php" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; transition: color 0.3s;">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
            <div style="font-size: 0.75rem; color: var(--text-dim); opacity: 0.5; text-transform: uppercase; letter-spacing: 1px;">Hướng dẫn Bookmarklet</div>
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
            <p>Cách 2: Sử dụng Bookmarklet (Dự phòng)</p>
        </div>

        <div class="device-toggle">
            <button class="toggle-btn active" onclick="showTab('desktop')"><i class="fas fa-desktop"></i> Máy tính</button>
            <button class="toggle-btn" onclick="showTab('mobile')"><i class="fas fa-mobile-alt"></i> Điện thoại</button>
        </div>

        <div id="desktop-guide" class="bookmarklet-card">
            <h2 style="margin-bottom: 1rem; font-size: 1.8rem; color: var(--text-main);">Cài đặt trên Máy tính</h2>
            <p style="color: var(--text-dim); line-height: 1.6; max-width: 600px; margin: 0 auto; font-size: 0.95rem;">
                Bookmarklet là một "nút bấm thông minh" nằm trên thanh dấu trang của trình duyệt. Nó giúp bạn trích xuất link nhanh mà không cần cài đặt Extension.
            </p>

            <div class="magic-btn-container">
                <div class="drag-instruction">
                    <i class="fas fa-mouse-pointer"></i> Kéo nút này vào thanh dấu trang (Bookmarks Bar)
                </div>
                <a href="<?php echo htmlspecialchars($bookmarklet_js); ?>" class="magic-link-btn" onclick="return false;" style="cursor: grab;">
                    <span class="stars">✨</span> Magic Link V6.5 <span class="stars">✨</span>
                </a>
                <p style="font-size: 0.8rem; color: var(--text-dim); margin-top: 1.5rem;">
                    (Nếu không thấy thanh dấu trang, nhấn <b>Ctrl + Shift + B</b>)
                </p>
            </div>

            <div class="steps-container">
                <div class="step-row">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Kéo và Thả</h3>
                        <p>Dùng chuột kéo nút <b>Magic Link</b> ở trên thả vào thanh dấu trang của trình duyệt.</p>
                    </div>
                </div>
                <div class="step-row">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Mở Facebook Reels</h3>
                        <p>Mở video Reels bất kỳ trên Facebook mà bạn muốn lấy link sản phẩm.</p>
                    </div>
                </div>
                <div class="step-row">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Kích hoạt</h3>
                        <p>Nhấn vào nút <b>Magic Link</b> trên thanh dấu trang. Hệ thống sẽ tự động tìm link Shopee và đưa bạn về trang rút gọn.</p>
                    </div>
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <img src="image/affreel.gif" alt="Hướng dẫn" style="max-width: 80%; border-radius: 12px; border: 1px solid var(--border-color);">
            </div>
        </div>

        <div id="mobile-guide" class="bookmarklet-card mobile-instructions">
            <h2 style="margin-bottom: 1rem; font-size: 1.8rem; color: var(--text-main);">Cài đặt trên Điện thoại</h2>
            <p style="color: var(--text-dim); line-height: 1.6; max-width: 600px; margin: 0 auto; font-size: 0.95rem;">
                Trên điện thoại (Chrome/Safari), bạn cần thêm Bookmark thủ công và dán mã code vào phần URL.
            </p>

            <div class="magic-btn-container">
                <button class="copy-btn" onclick="copyBookmarklet()" style="width:auto; padding: 0.8rem 2rem; border-radius: 50px; background: var(--primary); color:white; border:none; font-weight:700; cursor:pointer;">
                    <i class="fas fa-copy"></i> Sao chép mã Magic Link
                </button>
                <textarea id="bookmarklet-code" style="display:none;"><?php echo $bookmarklet_js; ?></textarea>
                <div id="copy-status" style="margin-top: 10px; font-size: 0.8rem; color: #4ade80; display:none;">Đã sao chép!</div>
            </div>

            <div class="steps-container">
                <div class="step-row">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Lưu trang hiện tại</h3>
                        <p>Nhấn vào biểu tượng <b>Chia sẻ</b> hoặc <b>Dấu ba chấm</b>, chọn <b>Thêm vào dấu trang</b> (Add to Bookmarks).</p>
                    </div>
                </div>
                <div class="step-row">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Sửa dấu trang</h3>
                        <p>Vào danh sách Dấu trang, tìm trang vừa lưu và chọn <b>Chỉnh sửa</b>.</p>
                    </div>
                </div>
                <div class="step-row">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Dán mã Magic Link</h3>
                        <p>Đổi tên thành <code>✨ Magic Link</code> và dán đoạn mã vừa copy ở trên vào phần <b>Địa chỉ/URL</b>.</p>
                    </div>
                </div>
                <div class="step-row">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Sử dụng</h3>
                        <p>Khi đang xem Reels bằng trình duyệt, gõ chữ <code>Magic</code> vào thanh địa chỉ và chọn dấu trang tương ứng để chạy.</p>
                    </div>
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

        function showTab(type) {
            document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            if (type === 'desktop') {
                document.getElementById('desktop-guide').style.display = 'block';
                document.getElementById('mobile-guide').style.display = 'none';
            } else {
                document.getElementById('desktop-guide').style.display = 'none';
                document.getElementById('mobile-guide').style.display = 'block';
            }
        }

        function copyBookmarklet() {
            const code = document.getElementById('bookmarklet-code');
            code.style.display = 'block';
            code.select();
            document.execCommand('copy');
            code.style.display = 'none';
            
            const status = document.getElementById('copy-status');
            status.style.display = 'block';
            setTimeout(() => { status.style.display = 'none'; }, 2000);
        }
    </script>
</body>
</html>
