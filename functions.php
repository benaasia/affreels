<?php
/**
 * AffReel Core Functions - Client Version
 * Chứa các hàm tiện ích cơ bản. Các hàm xử lý lõi đã được chuyển lên Server API để bảo mật.
 */

/**
 * Hàm tạo link rút gọn lưu vào database cục bộ của Client
 */
function generateShortLink($longUrl, $db, $sourceUrl = '', $affiliateId = '') {
    // 1. Kiểm tra xem link này đã tồn tại chưa
    $stmt = $db->prepare("SELECT slug FROM links WHERE url = ? LIMIT 1");
    $stmt->execute([$longUrl]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $slug = $row['slug'];
        // Cập nhật source_url nếu trước đó chưa có
        if ($sourceUrl) {
            $db->prepare("UPDATE links SET source_url = ?, affiliate_id = ? WHERE slug = ? AND (source_url IS NULL OR source_url = '')")->execute([$sourceUrl, $affiliateId, $slug]);
        }
    } else {
        // 2. Nếu chưa có, tạo slug mới ngẫu nhiên 6 ký tự
        $slug = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $stmt_insert = $db->prepare("INSERT INTO links (slug, url, clicks, source_url, affiliate_id) VALUES (?, ?, 0, ?, ?)");
        $stmt_insert->execute([$slug, $longUrl, $sourceUrl, $affiliateId]);
    }

    // 3. Xây dựng URL rút gọn hoàn chỉnh
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
        // Lấy thư mục hiện tại của file chạy
        $currentDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $baseUrl = $protocol . "://" . $host . $currentDir;
    }
    
    // Đảm bảo không bị lặp /s/s/
    $baseUrl = rtrim($baseUrl, '/');
    return $baseUrl . "/s/" . $slug;
}
