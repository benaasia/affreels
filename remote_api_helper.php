<?php
function callRemoteAPI($endpoint, $data = []) {
    global $remote_api_key, $remote_api_url;

    // Tự động lấy cấu hình nếu biến global bị trống
    if (empty($remote_api_url) || empty($remote_api_key)) {
        try {
            $db_temp = new PDO("sqlite:" . __DIR__ . "/links.db");
            if (empty($remote_api_key)) {
                $st = $db_temp->prepare("SELECT value FROM settings WHERE key = 'remote_api_key' LIMIT 1");
                $st->execute(); $r = $st->fetch();
                $remote_api_key = (!empty($r) && !empty($r['value'])) ? trim($r['value']) : 'FREE-85C45DDDBF3CEADB';
            }
            if (empty($remote_api_url)) {
                $st = $db_temp->prepare("SELECT value FROM settings WHERE key = 'remote_api_url' LIMIT 1");
                $st->execute(); $r = $st->fetch();
                $remote_api_url = (!empty($r) && !empty($r['value'])) ? trim($r['value']) : 'https://tikaff.net/api/v1';
            }
        } catch (Exception $e) {}
    }

    // Đảm bảo cuối cùng không bị trống
    if (empty($remote_api_url)) $remote_api_url = 'https://tikaff.net/api/v1';
    if (empty($remote_api_key)) $remote_api_key = 'FREE-85C45DDDBF3CEADB';

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

function smartExtractShopeeLink($input, $is_html = false) {
    return callRemoteAPI('extract', [
        'input' => $input,
        'is_html' => $is_html ? 1 : 0
    ]);
}

function smartGetFinalUrl($url) {
    $result = callRemoteAPI('expand', ['url' => $url]);
    if (isset($result['success']) && $result['success']) {
        return $result['url'];
    } else {
        $msg = isset($result['message']) ? $result['message'] : 'Không thể giải mã link qua API.';
        return "ERROR: " . $msg;
    }
}

function smartFacebookScrape($url) {
    $client_fb_token = '';
    try {
        $db_local = new PDO("sqlite:" . __DIR__ . "/links.db");
        $st_fb = $db_local->prepare("SELECT value FROM settings WHERE key = 'site_fb_token' LIMIT 1");
        $st_fb->execute();
        $row_fb = $st_fb->fetch(PDO::FETCH_ASSOC);
        if ($row_fb) $client_fb_token = $row_fb['value'];
    } catch (Exception $e) {}

    if (empty($client_fb_token)) return ['success' => false, 'message' => 'Scrape disabled because token is empty.'];
    
    return callRemoteAPI('scrape', [
        'url' => $url,
        'fb_access_token' => $client_fb_token
    ]);
}

function smartCheckAPIStatus() {
    return callRemoteAPI('status');
}
