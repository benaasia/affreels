<?php
define('DB_FILE', 'links.db');
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// Bảo mật: Lọc slug (chỉ cho phép chữ và số)
if (empty($slug) || !preg_match('/^[a-zA-Z0-9]{1,10}$/', $slug)) {
    header("Location: index.php");
    exit;
}

try {
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT url FROM links WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $longUrl = $row['url'];

        // Phát hiện Bot (Facebook, Zalo, Telegram...)
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_bot = preg_match('/(facebookexternalhit|Facebot|Twitterbot|Pinterest|Googlebot|bingbot|WhatsApp|TelegramBot|Zalo|Viber|SkypeShell)/i', $ua);

        if ($is_bot) {
            // Lấy cấu hình site cho Meta
            $stmt_s = $db->query("SELECT key, value FROM settings");
            $settings = $stmt_s->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $title = $settings['site_title'] ?? 'ReelsLink Pro';
            $desc = $settings['site_desc'] ?? 'Hỗ trợ chuyển đổi link Shopee Affiliate';
            $logo = $settings['site_logo'] ?? 'image/logo.png';
            
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $og_image = $base_url . "/image/og.jpg"; 
            if (!file_exists(__DIR__ . '/image/og.jpg')) $og_image = $base_url . "/" . $logo;

            ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($desc); ?>" />
    <meta property="og:image" content="<?php echo $og_image; ?>" />
    <meta property="og:url" content="<?php echo $base_url . "/s/" . $slug; ?>" />
    <meta property="og:type" content="website" />
    <meta http-equiv="refresh" content="0;url=<?php echo $longUrl; ?>">
    <script>window.location.href = "<?php echo $longUrl; ?>";</script>
</head>
<body>
    <p>Đang chuyển hướng... <a href="<?php echo $longUrl; ?>">Bấm vào đây nếu trình duyệt không tự chuyển</a></p>
</body>
</html>
            <?php
            exit;
        } else {
            // Cập nhật click cho người thật
            $stmt_update = $db->prepare("UPDATE links SET clicks = clicks + 1 WHERE slug = ?");
            $stmt_update->execute([$slug]);

            header("Location: " . $longUrl, true, 301);
            exit;
        }
    }
} catch (PDOException $e) {}

header("Location: index.php");
exit;
?>
