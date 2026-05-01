<?php
/**
 * Settings Page for FbReels Pro
 * Allows customers to configure their Remote API Key.
 */

// Đưa logic xử lý DB vào đây hoặc include từ index.php
define('DB_FILE', 'links.db');

try {
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Tạo bảng settings nếu chưa có
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT DEFAULT ''
    )");
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_key = isset($_POST['remote_api_key']) ? trim($_POST['remote_api_key']) : '';
    try {
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('remote_api_key', ?)");
        $stmt->execute([$api_key]);

        $message = "Cấu hình đã được lưu thành công!";
    } catch (PDOException $e) {
        $message = "Lỗi lưu cấu hình: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Lấy cấu hình hiện tại
$stmt_settings = $db->query("SELECT * FROM settings");
$settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

$current_key = isset($settings['remote_api_key']) ? $settings['remote_api_key'] : 'FREE-85C45DDDBF3CEADB';
$current_url = 'https://app.affreel.com/v1'; // Fix cứng URL Server API

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt API - FbReels Pro</title>
    <link rel="icon" type="image/png" href="image/favicon.png">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            animation: fadeInUp 1s ease-out;
        }
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dim);
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-main);
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), #dc2626);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 20px var(--primary-glow);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px var(--primary-glow);
        }
        .donate-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px dashed var(--border-color);
            text-align: center;
        }
        .qr-image {
            width: 180px;
            height: 180px;
            border-radius: 16px;
            margin: 1rem auto;
            border: 4px solid var(--input-bg);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="dark-mode">
    <div class="container">
        <div class="hero">
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 12px; color: var(--primary);">
                    <img src="image/favicon.png" alt="Logo" style="width: 48px; height: 48px; border-radius: 50%;"> 
                    FbReels <span style="font-weight: 300; color: var(--text-main);">Pro</span>
                </a>
            </h1>
            <p>Cấu hình Remote API cho hệ thống</p>
        </div>

        <div class="settings-card">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="remote_api_key">API Key (Được cấp miễn phí bởi AffReel.com)</label>
                    <input type="text" id="remote_api_key" name="remote_api_key" class="form-control" value="<?php echo htmlspecialchars($current_key); ?>" placeholder="Dán API Key của bạn vào đây...">
                    <small style="display: block; margin-top: 8px; font-size: 0.8rem;">
                        <i class="fab fa-telegram" style="color: #0088cc;"></i> Chưa có Key? <a href="https://t.me/shortlinkone" target="_blank" style="color: var(--primary); font-weight: 700; text-decoration: underline;">Lấy API miễn phí tại đây</a>
                    </small>
                </div>



                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Lưu cấu hình
                </button>
            </form>

            <div class="donate-section">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-dim);">☕ Ủng hộ tác giả phát triển</div>
                <img src="https://qr.sepay.vn/img?bank=Techcombank&acc=7679696999&template=&amount=&des=DonateAffReel" alt="QR Donate" class="qr-image">
                <div style="font-size: 0.75rem; color: var(--text-dim);">Cảm ơn bạn đã tin dùng AffReel Pro!</div>
            </div>
            
            <div style="margin-top: 2rem; text-align: center;">
                <a href="index.php" style="color: var(--text-dim); text-decoration: none; font-size: 0.9rem; font-weight: 500;">
                    <i class="fas fa-arrow-left"></i> Quay lại Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        // Tự động nhận diện dark mode từ localStorage nếu index.php có lưu
        if (localStorage.getItem('theme') === 'light') {
            document.body.classList.remove('dark-mode');
        }
    </script>
</body>
</html>
