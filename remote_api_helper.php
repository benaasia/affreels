<?php
function callRemoteAPI($endpoint, $data = []) {
    global $remote_api_key, $remote_api_url;

    // Tự động lấy cấu hình nếu biến global bị trống
    if (empty($remote_api_url) || empty($remote_api_key)) {
        $remote_api_url = 'https://app.affreel.com/v1'; // Fix cứng URL
        try {
            $db_temp = new PDO("sqlite:" . __DIR__ . "/links.db");
            if (empty($remote_api_key)) {
                $st = $db_temp->prepare("SELECT value FROM settings WHERE key = 'remote_api_key' LIMIT 1");
                $st->execute(); $r = $st->fetch();
                $remote_api_key = (!empty($r) && !empty($r['value'])) ? trim($r['value']) : 'FREE-85C45DDDBF3CEADB';
            }
        } catch (Exception $e) {}
    }

    // Đảm bảo cuối cùng không bị trống
    if (empty($remote_api_url)) $remote_api_url = 'https://app.affreel.com/v1';
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'Lỗi kết nối API: ' . $error];
    }

    // Xử lý để lấy đúng phần JSON nếu có thông báo rác (PHP Notice/Warning) đính kèm
    $json_start = strpos($response, '{');
    $json_end = strrpos($response, '}');
    if ($json_start !== false && $json_end !== false) {
        $clean_json = substr($response, $json_start, $json_end - $json_start + 1);
        $result = json_decode($clean_json, true);
    } else {
        $result = json_decode($response, true);
    }

    if (!$result) {
        return [
            'success' => false, 
            'message' => 'Phản hồi từ Server không hợp lệ (JSON Error). Nội dung nhận được: ' . substr(strip_tags($response), 0, 100) . '...'
        ];
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

function smartVerifyKey($force_key = null) {
    global $remote_api_key;
    if ($force_key !== null) $remote_api_key = $force_key;
    return callRemoteAPI('check_status');
}

function smartCheckAPIStatus() {
    $res = callRemoteAPI('check');
    
    // Nếu thành công và có link QR từ main site, cập nhật lại vào settings cục bộ
    if (isset($res['success']) && $res['success'] && !empty($res['donate_qr_url'])) {
        try {
            $db = new PDO("sqlite:" . __DIR__ . "/links.db");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('master_donate_qr_url', ?)");
            $stmt->execute([$res['donate_qr_url']]);
            $res['db_update'] = true;
        } catch (Exception $e) {
            $res['db_update'] = false;
            $res['db_error'] = $e->getMessage();
        }
    }
    
    return $res;
}
