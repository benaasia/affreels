<?php
function get_db_connection() {
    try {
        $db_path = __DIR__ . '/../links.db';
        if (!file_exists($db_path)) return null;
        $db = new PDO("sqlite:" . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        return null;
    }
}

function generateShortLink($longUrl, $db, $sourceUrl = '', $affiliateId = '') {
    $stmt = $db->prepare("SELECT slug FROM links WHERE url = ? LIMIT 1");
    $stmt->execute([$longUrl]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $slug = $row['slug'];
        if ($sourceUrl) {
            $db->prepare("UPDATE links SET source_url = ?, affiliate_id = ? WHERE slug = ? AND (source_url IS NULL OR source_url = '')")->execute([$sourceUrl, $affiliateId, $slug]);
        }
    } else {
        $slug = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $stmt_insert = $db->prepare("INSERT INTO links (slug, url, clicks, source_url, affiliate_id) VALUES (?, ?, 0, ?, ?)");
        $stmt_insert->execute([$slug, $longUrl, $sourceUrl, $affiliateId]);
    }

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $custom_domain = '';
    try {
        $s = $db->prepare("SELECT value FROM settings WHERE key = 'custom_domain'");
        $s->execute();
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['value'])) $custom_domain = $r['value'];
    } catch (Exception $e) {}

    if ($custom_domain) {
        $baseUrl = $protocol . "://" . $custom_domain;
    } else {
        $host = $_SERVER['HTTP_HOST'] ?? 'affreel.com'; 
        $currentDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $baseUrl = $protocol . "://" . $host . $currentDir;
    }
    
    $baseUrl = rtrim($baseUrl, '/');
    return $baseUrl . "/s/" . $slug;
}
