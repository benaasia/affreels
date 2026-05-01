/** ReelsLink Pro V6.5 - Hardcoded Auth Frontend **/
document.addEventListener("DOMContentLoaded", () => {
    // Theme Toggle Logic
    const themeToggle = document.getElementById('theme-toggle');
    const body = document.body;
    
    function setTheme(theme) {
        if (theme === 'dark') {
            body.classList.add('dark-mode');
        } else {
            body.classList.remove('dark-mode');
        }
    }

    // 1. Kiểm tra localStorage
    let savedTheme = localStorage.getItem('theme');
    
    // 2. Nếu chưa lưu, kiểm tra hệ thống (System Preference)
    if (!savedTheme) {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        savedTheme = prefersDark ? 'dark' : 'light';
    }

    setTheme(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const isNowDark = !body.classList.contains('dark-mode');
            setTheme(isNowDark ? 'dark' : 'light');
            localStorage.setItem('theme', isNowDark ? 'dark' : 'light');
        });
    }

    // Lắng nghe nếu user thay đổi chế độ hệ thống trong lúc đang mở web
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme')) {
            setTheme(e.matches ? 'dark' : 'light');
        }
    });

    const extractBtn = document.getElementById('extract-btn');
    const urlInput = document.getElementById('reels-url');
    const shopeeUrlInput = document.getElementById('shopee-url');
    const shopeeAffIdInput = document.getElementById('shopee-aff-id');
    const shopeeAffOkBtn = document.getElementById('shopee-aff-ok-btn');
    const shopeeAffClearBtn = document.getElementById('shopee-aff-clear-btn');
    const shopeeAffGroup = document.getElementById('shopee-aff-group');
    const shareLinkGroup = document.getElementById('share-link-group');
    const shareUrlDisplay = document.getElementById('share-url-display');
    const copyShareUrlBtn = document.getElementById('copy-share-url-btn');
    const editAffIdBtn = document.getElementById('edit-aff-id-btn');
    const resultSection = document.getElementById('result-section');
    const shopeeResultDetails = document.getElementById('shopee-result-details');
    const cleanLinkSection = document.getElementById('clean-link-section');
    const redirLinkSection = document.getElementById('redir-link-section');
    const longLinkSection = document.getElementById('long-link-section');
    const fallbackSection = document.getElementById('fallback-section');
    const fallbackBtn = document.getElementById('fallback-btn');
    const shortLinkDisplay = document.getElementById('short-link-display');
    const longLinkDisplay = document.getElementById('long-link-display');
    const cleanLinkDisplay = document.getElementById('clean-link-display');
    const redirLinkDisplay = document.getElementById('redir-link-display');
    const copyShortBtn = document.getElementById('copy-short-btn');
    const copyDestBtn = document.getElementById('copy-dest-btn');
    const copyCleanBtn = document.getElementById('copy-clean-btn');
    const copyRedirBtn = document.getElementById('copy-redir-btn');
    const loader = document.getElementById('loader');
    const statusMsg = document.getElementById('status-msg');
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const card = document.querySelector('.extractor-card');
    let activeTab = 'shopee';

    // Dùng Key cứng giống Extension để đảm bảo độ tin cậy tuyệt đối
    const API_KEY = 'ReelsLink-v4-Secure-Key-2026';

    // Hàm trích xuất link Shopee từ text có nhiều nội dung
    function extractShopeeUrl(text) {
        const match = text.match(/https?:\/\/[a-z0-9.\-]*(?:shopee\.vn|shp\.ee|shope\.ee)[^\s"'<>|]*/i);
        return match ? match[0] : null;
    }

    // Hàm trích xuất link Reels từ text
    function extractReelsUrl(text) {
        const match = text.match(/https?:\/\/[a-z0-9.\-]*(?:facebook\.com\/reel|fb\.watch|fb\.com\/reel)[^\s"'<>|]*/i);
        return match ? match[0] : null;
    }

    // Nút Dán link Shopee từ clipboard
    const pasteShopeeBtn = document.getElementById('paste-shopee-url-btn');
    if (pasteShopeeBtn && shopeeUrlInput) {
        pasteShopeeBtn.addEventListener('click', async () => {
            try {
                const text = await navigator.clipboard.readText();
                const cleaned = extractShopeeUrl(text);
                if (cleaned) {
                    shopeeUrlInput.value = cleaned;
                    shopeeUrlInput.focus();
                    pasteShopeeBtn.textContent = '✓ Đã dán';
                    pasteShopeeBtn.style.background = '#10b981';
                    setTimeout(() => {
                        pasteShopeeBtn.textContent = '📋 Dán';
                        pasteShopeeBtn.style.background = '';
                        // Tự động tạo link
                        if (extractBtn) extractBtn.click();
                    }, 1000);
                } else {
                    showStatus('Link trong clipboard không phải link Shopee!', true);
                }
            } catch (err) {
                showStatus('Không thể đọc clipboard. Hãy dán thủ công (Ctrl+V).', true);
            }
        });
    }

    // Nút Dán link Reels từ clipboard
    const pasteReelsBtn = document.getElementById('paste-reels-url-btn');
    if (pasteReelsBtn && urlInput) {
        pasteReelsBtn.addEventListener('click', async () => {
            try {
                const text = await navigator.clipboard.readText();
                const cleaned = extractReelsUrl(text);
                if (cleaned) {
                    urlInput.value = cleaned;
                    urlInput.focus();
                    pasteReelsBtn.textContent = '✓ Đã dán';
                    pasteReelsBtn.style.background = '#10b981';
                    setTimeout(() => {
                        pasteReelsBtn.textContent = '📋 Dán';
                        pasteReelsBtn.style.background = '';
                        // Tự động tạo link
                        if (extractBtn) extractBtn.click();
                    }, 1000);
                } else {
                    showStatus('Link trong clipboard không phải link Reels!', true);
                }
            } catch (err) {
                showStatus('Không thể đọc clipboard. Hãy dán thủ công (Ctrl+V).', true);
            }
        });
    }

    // Khi dán thủ công (Ctrl+V) vào ô, cũng tự lọc link Shopee
    if (shopeeUrlInput) {
        shopeeUrlInput.addEventListener('paste', (e) => {
            setTimeout(() => {
                const cleaned = extractShopeeUrl(shopeeUrlInput.value);
                if (cleaned) {
                    shopeeUrlInput.value = cleaned;
                    // Tự động tạo link sau khi dán thủ công
                    setTimeout(() => {
                        if (extractBtn) extractBtn.click();
                    }, 100);
                } else {
                    showStatus('Nội dung vừa dán không chứa link Shopee hợp lệ!', true);
                }
            }, 0);
        });
    }

    // Khi dán thủ công (Ctrl+V) vào ô Reels, cũng tự lọc link
    if (urlInput) {
        urlInput.addEventListener('paste', (e) => {
            setTimeout(() => {
                const cleaned = extractReelsUrl(urlInput.value);
                if (cleaned) {
                    urlInput.value = cleaned;
                    // Tự động tạo link sau khi dán thủ công
                    setTimeout(() => {
                        if (extractBtn) extractBtn.click();
                    }, 100);
                }
            }, 0);
        });
    }

    function showStatus(msg, isError = false) {
        statusMsg.innerHTML = msg;
        statusMsg.style.display = msg ? 'block' : 'none';
        statusMsg.style.color = isError ? '#f87171' : '#4ade80';
    }

    function handleCopy(btn, textEl) {
        const text = textEl.textContent;
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            btn.classList.add('copied');
            setTimeout(() => {
                btn.classList.remove('copied');
            }, 2000);
        });
    }

    function setLoading(isLoading) {
        const btnText = extractBtn.querySelector('.btn-text');
        if (isLoading) {
            extractBtn.disabled = true;
            btnText.style.display = 'none';
            loader.style.display = 'block';
        } else {
            extractBtn.disabled = false;
            btnText.style.display = 'block';
            loader.style.display = 'none';
        }
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            activeTab = btn.getAttribute('data-tab');
            
            // Cập nhật Hash nhưng phải giữ lại affiliate_id nếu có
            let newHash = activeTab;
            const urlParams = new URLSearchParams(window.location.search);
            const id = shopeeAffIdInput ? shopeeAffIdInput.value.trim() : '';

            if (activeTab === 'shopee') {
                if (!id || id === urlParams.get('affiliate_id')) {
                    newHash = ''; // Mặc định không cần hash cho Shopee
                } else {
                    newHash = `?affiliate_id=${id}`; // Bỏ chữ shopee, chỉ giữ lại tham số
                }
            }
            
            // Cập nhật URL (xóa hash nếu là rỗng)
            if (newHash === '') {
                if (window.location.hash !== '') {
                    history.pushState(null, null, window.location.pathname + window.location.search);
                }
            } else {
                if (window.location.hash.replace('#', '') !== newHash) {
                    window.location.hash = newHash;
                }
            }
            
            if (activeTab === 'setup') {
                extractBtn.style.display = 'none';
            } else {
                extractBtn.style.display = 'block';
            }
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === `${activeTab}-tab`) {
                    content.classList.add('active');
                }
            });
            showStatus('');
            resultSection.style.display = 'none';
            fallbackSection.style.display = 'none';
        });
    });

    // Lắng nghe thay đổi ID để cập nhật URL
    if (shopeeAffIdInput) {
        shopeeAffIdInput.addEventListener('input', () => {
            const id = shopeeAffIdInput.value.trim();
            const urlParams = new URLSearchParams(window.location.search);
            
            // Hiện/ẩn nút xóa
            if (shopeeAffClearBtn) {
                shopeeAffClearBtn.style.display = id ? 'block' : 'none';
            }

            if (activeTab === 'shopee') {
                // Nếu ID trùng với ID trong Query String (?affiliate_id=...) 
                // thì không cần thêm vào Hash nữa để tránh trùng lặp 2 tham số trên URL
                if (id && id === urlParams.get('affiliate_id')) {
                    history.pushState(null, null, window.location.pathname + window.location.search);
                } else {
                    if (id) {
                        window.location.hash = `?affiliate_id=${id}`; // Bỏ chữ shopee
                    } else {
                        history.pushState(null, null, window.location.pathname + window.location.search);
                    }
                }
            }
        });

        // Nút xóa sạch ID - dùng mousedown để chạy trước khi blur
        if (shopeeAffClearBtn) {
            shopeeAffClearBtn.addEventListener('mousedown', (e) => {
                e.preventDefault(); // QUAN TRỌNG: Ngăn input mất focus (blur)
                e.stopPropagation();
                shopeeAffIdInput.value = '';
                // Cập nhật URL về trạng thái sạch
                history.pushState(null, null, window.location.pathname + window.location.search);
                // Ẩn chính nó
                shopeeAffClearBtn.style.display = 'none';
                // Focus lại ô nhập (thực ra nhờ preventDefault nên nó không mất focus)
                shopeeAffIdInput.focus();
                // Nếu đang hiện Link Chia Sẻ thì ẩn đi vì ID đã trống
                if (typeof updateShareUrl === 'function') updateShareUrl('');
            });
        }
        
        // Khi người dùng gõ xong và nhấn ra ngoài, nếu có ID thì ẩn ô nhập đi và hiện link chia sẻ
        shopeeAffIdInput.addEventListener('blur', () => {
            const id = shopeeAffIdInput.value.trim();
            if (id && shopeeAffGroup) {
                shopeeAffGroup.style.display = 'none';
                if (shareLinkGroup) {
                    shareLinkGroup.style.display = 'block';
                    updateShareUrl(id);
                }
            }
        });

        // Nhấn Enter để hoàn tất nhập ID và chuyển sang dán link
        shopeeAffIdInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                shopeeAffIdInput.blur();
                if (shopeeUrlInput) shopeeUrlInput.focus();
            }
        });

        // Nút OK cũng làm nhiệm vụ tương tự
        if (shopeeAffOkBtn) {
            shopeeAffOkBtn.addEventListener('click', () => {
                const id = shopeeAffIdInput.value.trim();
                shopeeAffIdInput.blur();
                if (shopeeUrlInput) shopeeUrlInput.focus();
                if (id && shareLinkGroup) {
                    shareLinkGroup.style.display = 'block';
                    updateShareUrl(id);
                }
            });
        }

        // Sao chép link chia sẻ
        if (copyShareUrlBtn) {
            copyShareUrlBtn.addEventListener('click', () => {
                const url = shareUrlDisplay.textContent;
                if (!url) return;
                navigator.clipboard.writeText(url).then(() => {
                    const orig = copyShareUrlBtn.textContent;
                    copyShareUrlBtn.textContent = 'Đã chép!';
                    copyShareUrlBtn.style.background = '#10b981';
                    setTimeout(() => {
                        copyShareUrlBtn.textContent = orig;
                        copyShareUrlBtn.style.background = '';
                    }, 2000);
                });
            });
        }

        // Nút sửa ID
        if (editAffIdBtn) {
            editAffIdBtn.addEventListener('click', () => {
                if (shareLinkGroup) shareLinkGroup.style.display = 'none';
                shopeeAffGroup.style.display = 'block';
                shopeeAffIdInput.focus();
                if (shopeeAffIdInput.value && shopeeAffClearBtn) {
                    shopeeAffClearBtn.style.display = 'block';
                }
            });
        }
    }

    // Hàm cập nhật link chia sẻ
    function updateShareUrl(id) {
        if (!shareUrlDisplay) return;
        if (!id) {
            if (shareLinkGroup) shareLinkGroup.style.display = 'none';
            return;
        }
        const baseUrl = window.location.origin + window.location.pathname;
        shareUrlDisplay.textContent = baseUrl + '?affiliate_id=' + id;
    }

    // Hàm kích hoạt tab và xử lý ID dựa trên URL/Hash
    function handleHash() {
        const fullHash = window.location.hash.replace('#', '');
        
        // 1. Ưu tiên lấy ID từ Query String (?affiliate_id=123)
        const urlParams = new URLSearchParams(window.location.search);
        let idFromUrl = urlParams.get('affiliate_id');
        
        // 2. Nếu không có, lấy từ Hash (#shopee?affiliate_id=123)
        const parts = fullHash.split('?');
        const hashTab = parts[0];
        const query = parts[1] || '';
        
        if (!idFromUrl && query.includes('affiliate_id=')) {
            const match = query.match(/affiliate_id=([^&]+)/);
            if (match && match[1]) idFromUrl = match[1];
        }

        // Xử lý điền ID và ẩn ô nhập
        if (idFromUrl) {
            if (shopeeAffIdInput) {
                shopeeAffIdInput.value = idFromUrl;
                if (shopeeAffClearBtn) shopeeAffClearBtn.style.display = 'block';
                if (shopeeAffGroup && document.activeElement !== shopeeAffIdInput) {
                    shopeeAffGroup.style.display = 'none';
                    if (shareLinkGroup) {
                        shareLinkGroup.style.display = 'block';
                        updateShareUrl(idFromUrl);
                    }
                }
            }
        } else {
            // Nếu không có ID trong URL, hiện lại ô nhập
            if (shopeeAffIdInput && !shopeeAffIdInput.value) {
                 if (shopeeAffGroup) shopeeAffGroup.style.display = 'block';
                 if (shareLinkGroup) shareLinkGroup.style.display = 'none';
            }
        }

        // Chuyển tab
        if (!hashTab) {
            const urlParams = new URLSearchParams(window.location.search);
            let defaultTab = 'shopee';
            
            // Nếu có tham số extract (từ bookmarklet/extension) thì ưu tiên mở tab FB Reels
            if (urlParams.has('extract')) {
                defaultTab = 'fbreel';
            } else {
                defaultTab = localStorage.getItem('active_tab') || 'shopee';
            }

            const defaultBtn = document.querySelector(`.tab-btn[data-tab="${defaultTab}"]`);
            if (defaultBtn && !defaultBtn.classList.contains('active')) defaultBtn.click();
        } else {
            const targetBtn = document.querySelector(`.tab-btn[data-tab="${hashTab}"]`);
            if (targetBtn && !targetBtn.classList.contains('active')) targetBtn.click();
        }
    }

    // Khởi tạo khi load trang
    handleHash();

    // Lắng nghe khi Hash thay đổi (nút Back/Forward)
    window.addEventListener('hashchange', handleHash);

    if (card.classList.contains('auto-processing')) {
        extractBtn.style.display = 'none';
    }

    extractBtn.addEventListener('click', async () => {
        if (activeTab === 'setup') return;
        const formData = new FormData();
        
        if (activeTab === 'fbreel') {
            const val = urlInput.value.trim();
            if (!val) return showStatus('Vui lòng nhập link Reels!', true);
            
            const cleaned = extractReelsUrl(val);
            if (!cleaned) return showStatus('Link không phải định dạng Reels (facebook.com/reel, fb.watch...)!', true);
            
            formData.append('reels_url', cleaned);
            urlInput.value = cleaned; // Cập nhật lại ô nhập cho sạch
            
            // Gửi source URL (trang FB Reel gốc) nếu có
            const sourceInput = document.getElementById('source-url');
            if (sourceInput && sourceInput.value.trim()) {
                formData.append('source_url', sourceInput.value.trim());
            }
        } else if (activeTab === 'shopee') {
            const val = shopeeUrlInput.value.trim();
            if (!val) return showStatus('Vui lòng nhập link Shopee!', true);
            
            const cleaned = extractShopeeUrl(val);
            if (!cleaned) return showStatus('Link không phải định dạng Shopee (shopee.vn, shp.ee...)!', true);
            
            formData.append('shopee_url', cleaned);
            shopeeUrlInput.value = cleaned; // Cập nhật lại ô nhập cho sạch
            
            const affId = shopeeAffIdInput.value.trim();
            if (affId) {
                formData.append('shopee_aff_id', affId);
            }
        }

        formData.append('api_key', API_KEY);

        showStatus('');
        resultSection.style.display = 'none';
        fallbackSection.style.display = 'none';
        setLoading(true);

        try {
            const response = await fetch('index.php', {
                method: 'POST',
                headers: {
                    'X-API-KEY': API_KEY // Gửi Key qua Header
                },
                body: formData
            });

            if (response.status === 403) throw new Error('Lỗi xác thực: Key không khớp (403).');
            if (!response.ok) throw new Error('Kết nối server thất bại');

            const data = await response.json();

            if (data.success) {
                shortLinkDisplay.textContent = data.short_link;
                longLinkDisplay.textContent = data.full_link || data.link;
                
                const affId = shopeeAffIdInput.value.trim();

                if (activeTab === 'shopee' && data.clean_link) {
                    cleanLinkDisplay.textContent = data.clean_link;
                    redirLinkDisplay.textContent = data.link;
                    shopeeResultDetails.style.display = 'block';
                    longLinkDisplay.textContent = data.full_link || data.link;
                    
                    // Hiện nút toggle chi tiết
                    const toggleBtn = document.getElementById('toggle-details-btn');
                    if (toggleBtn) toggleBtn.style.display = 'block';
                    
                    // Ẩn chi tiết mặc định
                    const detailsSection = document.getElementById('link-details-section');
                    if (detailsSection) detailsSection.style.display = 'none';
                } else {
                    shopeeResultDetails.style.display = 'none';
                    const toggleBtn = document.getElementById('toggle-details-btn');
                    if (toggleBtn) toggleBtn.style.display = 'none';
                    if (longLinkSection) longLinkSection.style.display = 'block';
                }

                resultSection.style.display = 'block';
                showStatus('Đã trích xuất!', false);
                resultSection.scrollIntoView({ behavior: 'smooth' });

                // Thêm trạng thái loading cho nút copy rút gọn
                copyShortBtn.classList.add('loading-scrape');

                // Gọi Facebook Scrape ngầm (Async)
                fetch(`index.php?action=scrape&url=${encodeURIComponent(data.short_link)}`)
                    .then(() => {
                        copyShortBtn.classList.remove('loading-scrape');
                        showStatus('Sẵn sàng! Chia sẻ hoặc Mua hàng để nhận Voucher độc quyền.', false);
                    })
                    .catch(() => {
                        copyShortBtn.classList.remove('loading-scrape');
                    });
            } else {
                showStatus(data.message, true);
                const inputVal = activeTab === 'fbreel' ? urlInput.value.trim() : shopeeUrlInput.value.trim();
                const isFB = inputVal.includes('facebook.com') || inputVal.includes('fb.watch') || inputVal.includes('fb.com');
                if (activeTab === 'fbreel' && isFB) {
                    fallbackBtn.href = inputVal;
                    fallbackSection.style.display = 'block';
                    setTimeout(() => {
                        fallbackSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 100);
                }
            }
        } catch (error) {
            showStatus('Lỗi: ' + error.message, true);
        } finally {
            setLoading(false);
        }
    });

    copyShortBtn.addEventListener('click', () => handleCopy(copyShortBtn, shortLinkDisplay));
    copyDestBtn.addEventListener('click', () => handleCopy(copyDestBtn, longLinkDisplay));
    copyCleanBtn.addEventListener('click', () => handleCopy(copyCleanBtn, cleanLinkDisplay));
    copyRedirBtn.addEventListener('click', () => handleCopy(copyRedirBtn, redirLinkDisplay));

    // Nút Mua hàng (Open new tab)
    const buyButtons = [
        { btn: document.getElementById('buy-short-btn'), display: shortLinkDisplay },
        { btn: document.getElementById('buy-dest-btn'), display: longLinkDisplay },
        { btn: document.getElementById('buy-clean-btn'), display: cleanLinkDisplay },
        { btn: document.getElementById('buy-redir-btn'), display: redirLinkDisplay }
    ];

    buyButtons.forEach(item => {
        if (item.btn) {
            item.btn.addEventListener('click', () => {
                const url = item.display.textContent.trim();
                if (url) window.open(url, '_blank');
            });
        }
    });
});

// Modal Beta
function dismissBetaModal() {
    const modal = document.getElementById('beta-modal');
    if (modal) {
        modal.style.display = 'none';
        localStorage.setItem('beta_notice_last_dismissed', Date.now());
    }
}

// Show modal on load if not dismissed or older than 24h
document.addEventListener('DOMContentLoaded', () => {
    const lastDismissed = localStorage.getItem('beta_notice_last_dismissed');
    const now = Date.now();
    const twentyFourHours = 24 * 60 * 60 * 1000;

    if (!lastDismissed || (now - lastDismissed > twentyFourHours)) {
        const modal = document.getElementById('beta-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }
});
