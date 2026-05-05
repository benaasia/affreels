<?php
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Chính sách quyền riêng tư của ReelsLink Pro.">
    <meta name="robots" content="noindex, follow">
    <title>Privacy Policy - ReelsLink Pro</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../image/favicon.png">
    
    <style>
        :root {
            --bg: #09090b;
            --surface: #18181b;
            --border: rgba(255, 255, 255, 0.1);
            --primary: #3b82f6;
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
                radial-gradient(circle at 50% 0%, rgba(59,130,246,0.1) 0%, transparent 50%);
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
            width: 36px;
            height: 36px;
            border-radius: 50%;
        }

        .nav-links a {
            color: var(--text-dim);
            text-decoration: none;
            margin-left: 24px;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .nav-links a:hover { color: white; }

        /* Hero */
        .hero {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 140px 20px 60px;
        }

        .hero h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 3rem;
            line-height: 1.1;
            font-weight: 800;
            margin-bottom: 24px;
        }

        .text-gradient {
            background: linear-gradient(135deg, #f8fafc, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .card {
            padding: 40px;
            border-radius: 20px;
            max-width: 800px;
            margin: 0 auto 100px;
            text-align: left;
        }

        .card h3 {
            font-size: 1.25rem;
            margin-bottom: 15px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: white;
            margin-top: 30px;
        }

        .card h3:first-child { margin-top: 0; }

        .card p, .card ul {
            color: var(--text-dim);
            font-size: 1rem;
            margin-bottom: 15px;
        }
        
        .card ul {
            padding-left: 20px;
            line-height: 1.8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .card { padding: 24px; margin: 0 15px 60px; }
            .hero h1 { font-size: 2.2rem; }
            .hero { padding: 100px 15px 40px; }
        }
    </style>
</head>
<body>

    <nav class="glass" id="navbar">
        <a href="index.php" class="logo">
            <img src="../image/favicon.png" alt="ReelsLink Pro Logo">
            <span>ReelsLink Pro</span>
        </a>
        <div class="nav-links">
            <a href="index.php">Quay lại trang chủ</a>
        </div>
    </nav>

    <section class="hero">
        <h1 class="text-gradient">Chính Sách Quyền Riêng Tư</h1>
        <p style="color: var(--text-dim); max-width: 600px;">Cập nhật lần cuối: Năm 2026. Chức năng bảo mật và quyền riêng tư của bạn là ưu tiên hàng đầu. Tiện ích ReelsLink Pro Extractor được thiết kế với tiêu chuẩn an toàn cao nhất.</p>
    </section>

    <div class="card glass">
        <h3>1. Thông tin chúng tôi thu thập</h3>
        <p>ReelsLink Pro Extractor ("Tiện ích") chỉ truy cập đường dẫn mạng (URL) của Tab đang mở để trích xuất đường link Shopee Affiliate hoặc Tracking ID. Chúng tôi <strong>tuyệt đối không</strong> thu thập, lưu trữ hay truyền tải dữ liệu cá nhân cá nhân, thông tin đăng nhập Facebook hay lịch sử duyệt web của bạn tới bất kỳ máy chủ bên thứ ba nào.</p>
        
        <h3>2. Cách chúng tôi sử dụng thông tin</h3>
        <p>Các đường link Affiliate trích xuất được sẽ gửi trực tiếp về máy chủ nội bộ cấu hình riêng của bạn (Ví dụ: app.affreel.com) để tạo rút gọn tự động. Quá trình này tuyệt đối chỉ dùng cho mục đích rút gọn và thống kê Click nội bộ dưới sự cho phép cấp quyền của bạn.</p>

        <h3>3. Truy cập từ bên thứ ba</h3>
        <p>Chúng tôi không bán kiếm lời, không trao đổi thương mại hay vận chuyển bất kỳ dữ liệu cá nhân nào ra bên ngoài. Các luồng dữ liệu chỉ diễn ra độc quyền và hoàn toàn khép kín giữa Trình duyệt web của bạn và Máy chủ nội bộ mà bạn khai báo.</p>

        <h3>4. Việc sử dụng các phân quyền (Permissions)</h3>
        <ul>
            <li><strong>activeTab (Tab đang mở):</strong> Thiết yếu để hệ thống xác định URL của Video Facebook Reel đang phát, nhằm mục đích bắt gói tin Affiliate chính xác cho bạn.</li>
            <li><strong>scripting (Chèn bộ thu thập):</strong> Thiết yếu để hệ thống có thể xử lý Data JSON nguyên gốc sâu bên trong mã nguồn Reels nhằm đảm bảo tốc độ cực nhanh mà không lưu trữ DOM của FB.</li>
            <li><strong>storage (Bộ nhớ cục bộ):</strong> Sử dụng để lưu trữ các tuỳ chọn trên thanh điều khiển của Tiện ích nội bộ ngay trên chính trình duyệt thiết bị của bạn.</li>
        </ul>

        <h3>5. Liên hệ</h3>
        <p>Nếu bạn có bất cứ thắc mắc gì liên quan tới chính sách truy cập bảo mật và nền tảng trích xuất, bạn có thể liên hệ thông qua ban quản trị của máy chủ nội bộ tại: app.affreel.com.</p>
    </div>

    <!-- Footer -->
    <footer style="border-top: 1px solid var(--border); padding: 40px 20px; text-align: center; color: var(--text-dim); font-size: 0.9rem;">
        <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;">
            <img src="../image/favicon.png" style="width: 24px; border-radius: 50%;"> <b style="color:white; font-family:'Plus Jakarta Sans'">ReelsLink Pro</b>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Trình trích xuất Shopee Affiliate nội quyền.</p>
    </footer>

    <script>
        // Navbar blur effect on scroll
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 50) {
                nav.style.background = 'rgba(24, 24, 27, 0.9)';
                nav.style.boxShadow = '0 4px 30px rgba(0, 0, 0, 0.5)';
            } else {
                nav.style.background = 'rgba(24, 24, 27, 0.7)';
                nav.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>
