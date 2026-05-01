<?php
/**
 * Redirect Handler - ReelsLink Pro V6.5
 */

define('DB_FILE', 'links.db');
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// Bảo mật: Lọc slug (chỉ cho phép chữ và số)
if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $slug)) {
    header("HTTP/1.1 404 Not Found");
    echo "<h1>404 Not Found</h1><p>Mã liên kết không hợp lệ.</p>";
    exit;
}

try {
    // Kết nối SQLite
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Phát hiện Bot để bỏ qua click ảo
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $is_bot = preg_match('/(bot|spider|crawl|slurp|facebookexternalhit|whatsapp|telegram|zalo|viber|skype|twitterbot|slackbot|discordbot)/i', $ua);

    // 1. Cập nhật số lượt click (Atomic increment)
    if (!$is_bot) {
        $stmt_update = $db->prepare("UPDATE links SET clicks = clicks + 1 WHERE slug = ?");
        $stmt_update->execute([$slug]);
    }

    // 2. Truy vấn SQL để tìm link gốc
    $stmt = $db->prepare("SELECT url FROM links WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $longUrl = $row['url'];
        // Chuyển hướng 301 (Permanent Redirect)
        header("Location: " . $longUrl, true, 301);
        exit;
    }

} catch (PDOException $e) {
    // Log lỗi nếu cần
}

// Nếu không tìm thấy link trong database
header("HTTP/1.1 404 Not Found");
echo "<h1>404 Not Found</h1><p>Xin lỗi, liên kết này không tồn tại hoặc đã bị xóa.</p>";
?>
