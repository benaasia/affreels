<?php
define('DB_FILE', 'links.db');
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $slug)) {
    header("HTTP/1.1 404 Not Found");
    echo "<h1>404 Not Found</h1><p>Mã liên kết không hợp lệ.</p>";
    exit;
}

try {
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $is_bot = preg_match('/(bot|spider|crawl|slurp|facebookexternalhit|whatsapp|telegram|zalo|viber|skype|twitterbot|slackbot|discordbot)/i', $ua);

    if (!$is_bot) {
        $stmt_update = $db->prepare("UPDATE links SET clicks = clicks + 1 WHERE slug = ?");
        $stmt_update->execute([$slug]);
    }

    $stmt = $db->prepare("SELECT url FROM links WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $longUrl = $row['url'];
        header("Location: " . $longUrl, true, 301);
        exit;
    }

} catch (PDOException $e) {
}

header("HTTP/1.1 404 Not Found");
echo "<h1>404 Not Found</h1><p>Xin lỗi, liên kết này không tồn tại hoặc đã bị xóa.</p>";
?>
