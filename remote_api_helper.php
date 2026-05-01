<?php
/**
 * Remote API Helper for FbReels Pro - Client Version
 * Dàn xếp mọi yêu cầu qua Server trung tâm.
 */

function callRemoteAPI($endpoint, $data = []) {
    global $remote_api_key, $remote_api_url;

    if (empty($remote_api_key)) {
        return ['success' => false, 'message' => 'Hệ thống yêu cầu API Key để hoạt động. Vui lòng cấu hình trong phần cài đặt.'];
    }

    $url = rtrim($remote_api_url, '/') . '/' . ltrim($endpoint, '/');
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $current_domain = $protocol . "://" . $_SERVER['HTTP_HOST'];

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $remote_api_key,
        'X-DOMAIN: ' . $current_domain,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'Lỗi kết nối API: ' . $error];
    }

    $result = json_decode($response, true);
    if (!$result) {
        return ['success' => false, 'message' => 'Phản hồi từ Server không hợp lệ (JSON Error).'];
    }

    return $result;
}

/**
 * BUỘC trích xuất link Shopee qua API
 */
function smartExtractShopeeLink($input, $is_html = false) {
    return callRemoteAPI('extract', [
        'input' => $input,
        'is_html' => $is_html ? 1 : 0
    ]);
}

/**
 * BUỘC lấy URL cuối cùng qua API
 */
function smartGetFinalUrl($url) {
    $result = callRemoteAPI('expand', ['url' => $url]);
    if (isset($result['success']) && $result['success']) {
        return $result['url'];
    } else {
        $msg = isset($result['message']) ? $result['message'] : 'Không thể giải mã link qua API.';
        return "ERROR: " . $msg;
    }
}

/**
 * BUỘC Facebook Scrape qua API
 */
function smartFacebookScrape($url) {
    // Lấy Token riêng của Client từ DB cục bộ nếu có
    $client_fb_token = '';
    try {
        $db_local = new PDO("sqlite:" . __DIR__ . "/links.db");
        $st_fb = $db_local->prepare("SELECT value FROM settings WHERE key = 'site_fb_token' LIMIT 1");
        $st_fb->execute();
        $row_fb = $st_fb->fetch(PDO::FETCH_ASSOC);
        if ($row_fb) $client_fb_token = $row_fb['value'];
    } catch (Exception $e) {}

    return callRemoteAPI('scrape', [
        'url' => $url,
        'fb_access_token' => $client_fb_token // Gửi token này lên Server
    ]);
}
