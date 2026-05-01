<?php
/**
 * Facebook Scrape Helper - ReelsLink Pro V6.6
 * Tự động gọi Facebook Debugger để cập nhật Open Graph
 */

function facebookScrape($url) {
    if (empty($url)) return;

    try {
        // --- Rate Limiter ---
        // Giới hạn tối đa 150 lần/giờ để tránh bị Facebook chặn
        $temp_dir = __DIR__ . DIRECTORY_SEPARATOR . 'temp';
        if (!is_dir($temp_dir)) {
            @mkdir($temp_dir, 0777, true);
        }
        $rate_file = $temp_dir . DIRECTORY_SEPARATOR . 'fb_scrape_rate.json';
        $current_hour = date('Y-m-d-H');
        $max_per_hour = 150;
        
        $rate_data = ['hour' => $current_hour, 'count' => 0];
        if (file_exists($rate_file)) {
            $raw = @file_get_contents($rate_file);
            $parsed = @json_decode($raw, true);
            if ($parsed && isset($parsed['hour']) && $parsed['hour'] === $current_hour) {
                $rate_data = $parsed;
            }
        }
        
        if ($rate_data['count'] >= $max_per_hour) {
            error_log("[FB Scrape] RATE LIMITED - Đã đạt {$max_per_hour} lần/giờ, bỏ qua: {$url}");
            return;
        }
        
        $rate_data['count']++;
        file_put_contents($rate_file, json_encode($rate_data), LOCK_EX);
        
        // --- Facebook Graph API Call ---
        // Token lấy từ app Facebook (ReelsLink Pro / Deep dự phòng)
        $access_token = '4072494859561769|a1d26260e785327f97cc776d6262603b';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/?id=' . urlencode($url) . '&scrape=true&access_token=' . $access_token);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Ghi log để debug nếu cần
        // error_log("[FB Scrape] URL: {$url} | HTTP: {$httpCode} | Resp: " . substr($response, 0, 100));

    } catch (Exception $e) {
        error_log("[FB Scrape Error] : " . $e->getMessage());
    }
}
