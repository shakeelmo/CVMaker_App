/**
 * Tracking Code Loader - cvmaker.ink
 * Dynamically loads Google Analytics and search engine verification codes
 * from admin settings via API
 */
(function() {
    'use strict';
    
    // Prevent double-loading
    if (window.__trackingLoaded) return;
    window.__trackingLoaded = true;
    
    async function loadTracking() {
        try {
            const response = await fetch('/api/tracking.php');
            if (!response.ok) return;
            
            const data = await response.json();
            if (!data.success || !data.tracking) return;
            
            const tracking = data.tracking;
            
            // Google Analytics 4
            if (tracking.google_analytics?.enabled && tracking.google_analytics?.measurement_id) {
                const gaId = tracking.google_analytics.measurement_id;
                
                // Load gtag script
                const script = document.createElement('script');
                script.async = true;
                script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
                document.head.appendChild(script);
                
                // Initialize dataLayer and gtag
                window.dataLayer = window.dataLayer || [];
                function gtag(){ dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', gaId);
            }
            
            // Google Search Console verification
            if (tracking.google_search_console?.verification_code) {
                let code = tracking.google_search_console.verification_code;
                // Remove HTML tags if present, extract just the content value
                const contentMatch = code.match(/content=["']([^"']+)["']/);
                if (contentMatch) {
                    code = contentMatch[1];
                }
                // Remove any prefix like "google-site-verification="
                code = code.replace(/^google-site-verification=/, '');
                // Remove any escaping
                code = code.replace(/\\/g, '');
                addMetaTag('google-site-verification', code);
            } else if (tracking.google_search_console?.verification_id) {
                addMetaTag('google-site-verification', tracking.google_search_console.verification_id);
            }
            
            // Bing Webmaster Tools verification
            if (tracking.bing_webmaster?.verification_code) {
                addMetaTag('msvalidate.01', tracking.bing_webmaster.verification_code);
            }
            
            // Yandex Webmaster verification
            if (tracking.yandex_webmaster?.verification_code) {
                addMetaTag('yandex-verification', tracking.yandex_webmaster.verification_code);
            }
            
        } catch (e) {
            // Silent fail - don't break page if tracking fails
        }
    }
    
    function addMetaTag(name, content) {
        // Check if already exists
        if (document.querySelector('meta[name="' + name + '"]')) return;
        
        const meta = document.createElement('meta');
        meta.name = name;
        meta.content = content;
        document.head.appendChild(meta);
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadTracking);
    } else {
        loadTracking();
    }
})();
