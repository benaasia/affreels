<?php
define('DB_FILE', 'links.db');
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// Bảo mật: Lọc slug (chỉ cho phép chữ và số)
if (empty($slug) || !preg_match('/^[a-zA-Z0-9]{1,10}$/', $slug)) {
    try {
        $db_tmp = new PDO("sqlite:" . DB_FILE);
        $st = $db_tmp->prepare("SELECT value FROM settings WHERE key = 'site_404_redirect' LIMIT 1");
        $st->execute();
        $r404 = $st->fetchColumn();
        if (empty($r404)) $r404 = 'https://affreel.com';
        header("Location: " . $r404);
        exit;
    } catch (Exception $e) {
        header("Location: https://affreel.com");
        exit;
    }
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

// Nếu không tìm thấy link trong database
try {
    $stmt_404 = $db->prepare("SELECT value FROM settings WHERE key = 'site_404_redirect' LIMIT 1");
    $stmt_404->execute();
    $redirect_404 = $stmt_404->fetchColumn();
    if (empty($redirect_404)) $redirect_404 = 'https://affreel.com';
    
    header("Location: " . $redirect_404);
    exit;
} catch (Exception $e) {
    header("Location: https://affreel.com");
    exit;
}
?>
