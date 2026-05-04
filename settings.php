<?php

define('DB_FILE', 'links.db');
require_once 'remote_api_helper.php';

try {
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT DEFAULT ''
    )");
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Tự động đồng bộ QR từ main site mỗi khi truy cập trang cài đặt
smartCheckAPIStatus();

$message = '';
$message_type = 'success';

$current_key = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_key = isset($_POST['remote_api_key']) ? trim($_POST['remote_api_key']) : '';
    
    try {
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('remote_api_key', ?)");
        $stmt->execute([$current_key]);

        // Đồng bộ lại sau khi lưu
        smartCheckAPIStatus();

        $message = "Cấu hình đã được lưu thành công!";
    } catch (PDOException $e) {
        $message = "Lỗi lưu cấu hình: " . $e->getMessage();
        $message_type = 'error';
    }
} else {
    // Lấy cấu hình hiện tại nếu không phải POST
    $stmt_settings = $db->query("SELECT value FROM settings WHERE key = 'remote_api_key' LIMIT 1");
    $current_key = $stmt_settings->fetchColumn() ?: 'FREE-85C45DDDBF3CEADB';
}

// Lấy toàn bộ settings cho các mục đích khác
$stmt_all = $db->query("SELECT * FROM settings");
$settings = $stmt_all->fetchAll(PDO::FETCH_KEY_PAIR);
$qr_url = isset($settings['master_donate_qr_url']) ? $settings['master_donate_qr_url'] : 'https://qr.sepay.vn/img?bank=Techcombank&acc=7679696999&template=&amount=&des=DonateAffReel';

// --- KIỂM TRA TRẠNG THÁI KEY ---
$api_status = null;
if (!empty($current_key)) {
    $api_status = smartVerifyKey($current_key);
}

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
        .container {
            max-width: 500px;
            margin: 60px auto;
            padding: 20px;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .hero {
            text-align: center;
            margin-bottom: 10px;
        }
        .settings-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            animation: fadeInUp 0.8s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Mobile Optimization */
        @media (max-width: 768px) {
            .settings-card {
                padding: 1.5rem 1rem;
            }
            .hero h1 {
                font-size: 1.8rem;
            }
            .form-group label {
                font-size: 0.85rem;
            }
            .btn-submit {
                padding: 12px;
                font-size: 0.9rem;
            }
        }
        @media (max-width: 480px) {
            .settings-card {
                padding: 1.2rem 0.8rem;
                border-radius: 16px;
            }
            .input-group-mobile {
                flex-direction: column;
            }
            .btn-submit-mobile {
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="dark-mode">
    <div class="container">
        <div class="hero">
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 12px; color: var(--primary);">
                    <img src="image/logo.png" alt="Logo" style="height: 48px; border-radius: 5%;">
                </a>
            </h1>
            <!--
            <p>Cấu hình Remote API cho hệ thống</p>
            -->
        </div>

        <div class="settings-card">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="remote_api_key">API Key (Được cấp bởi AffReel)</label>
                    <div style="display: flex; gap: 8px;" class="input-group-mobile">
                        <div style="position: relative; flex: 1;">
                            <input type="text" id="remote_api_key" name="remote_api_key" class="form-control" value="<?php echo htmlspecialchars($current_key); ?>" placeholder="Dán API Key của bạn vào đây..." style="width: 100%; padding-right: 45px;">
                            <?php if ($api_status): ?>
                                <i class="fas <?php echo $api_status['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>" 
                                   style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: <?php echo $api_status['success'] ? '#10b981' : '#ef4444'; ?>; font-size: 1.1rem;"
                                   title="<?php echo htmlspecialchars($api_status['message']); ?>"></i>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn-submit btn-submit-mobile" style="width: auto; padding: 0 25px; margin: 0; box-shadow: none; white-space: nowrap;">
                            <i class="fas fa-save"></i> Lưu
                        </button>
                    </div>

                    <?php if ($api_status): ?>
                        <div style="margin-top: 15px; padding: 15px; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid <?php echo $api_status['success'] ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)'; ?>;">
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <span style="font-size: 0.9rem; font-weight: 600;">
                                    <i class="fas fa-shield-check" style="color: <?php echo $api_status['success'] ? '#10b981' : '#ef4444'; ?>;"></i> 
                                    Trạng thái API Key: 
                                    <span style="color: <?php echo $api_status['success'] ? '#10b981' : '#ef4444'; ?>;">
                                        <?php echo $api_status['success'] ? 'Hợp lệ' : 'Không hợp lệ'; ?>
                                    </span>
                                </span>
                                <?php if (!$api_status['success']): ?>
                                    <div style="font-size: 0.8rem; color: #ef4444; font-style: italic; background: rgba(239, 68, 68, 0.05); padding: 8px; border-radius: 4px; border-left: 3px solid #ef4444;">
                                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($api_status['message']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <small style="display: block; margin-top: 12px; font-size: 0.8rem;">
                        <i class="fab fa-telegram" style="color: #0088cc;"></i> Chưa có Key? <a href="https://t.me/shortlinkone" target="_blank" style="color: var(--primary); font-weight: 700; text-decoration: underline;">Lấy API miễn phí tại đây</a>
                    </small>
                </div>
            </form>

            <div class="donate-section">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-dim); line-height: 1.5;">☕ Chúng tôi cung cấp công cụ này hoàn toàn miễn phí,<br>hãy mời tác giả một ly cà phê để duy trì hệ thống nhé!</div>
                <?php 
                $qr_display_url = $qr_url;
                $qr_display_url .= (strpos($qr_display_url, '?') !== false ? '&' : '?') . 'v=' . time();
                ?>
                <img src="<?php echo htmlspecialchars($qr_display_url); ?>" alt="QR Donate" class="qr-image">
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
        if (localStorage.getItem('theme') === 'light') {
            document.body.classList.remove('dark-mode');
        }
    </script>
</body>
</html>
