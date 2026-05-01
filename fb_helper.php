<?php
function smartFacebookScrapeLocal($url) {
    global $site_fb_token;
    return callRemoteAPI('scrape', [
        'url' => $url,
        'fb_access_token' => $site_fb_token
    ]);
}
