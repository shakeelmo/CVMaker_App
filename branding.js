// cvmaker.ink - Dynamic Branding
(function() {
    function applyBranding(settings) {
        if (settings.site_favicon) {
            let favicon = document.querySelector("link[rel~='icon']");
            if (!favicon) {
                favicon = document.createElement('link');
                favicon.rel = 'icon';
                document.head.appendChild(favicon);
            }
            favicon.href = settings.site_favicon + '?t=' + Date.now();
            favicon.type = settings.site_favicon.endsWith('.svg') ? 'image/svg+xml' :
                           settings.site_favicon.endsWith('.ico') ? 'image/x-icon' :
                           settings.site_favicon.endsWith('.png') ? 'image/png' : '';
        }

        if (settings.site_name && settings.site_name !== 'cvmaker.ink') {
            document.title = document.title.replace('cvmaker.ink', settings.site_name).replace('CV Maker', settings.site_name).replace('cvmaker.ink', settings.site_name);
        }

        if (settings.site_logo) {
            const candidates = document.querySelectorAll('a[href="/"], a[href="/index.html"], a[href="index.html"]');
            candidates.forEach(link => {
                if (link.querySelector('img.site-logo')) return;

                const linkText = (link.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
                const hasBrandText = linkText.includes('cvmaker.ink') || linkText.includes('cvmaker.ink') || linkText.includes('cvmaker.ink') || linkText.includes('cv maker') || linkText === 'fs';
                const hasBrandIcon = !!link.querySelector('i.fa-file-alt');
                if (!hasBrandText && !hasBrandIcon) return;

                link.style.display = 'inline-flex';
                link.style.alignItems = 'center';
                link.style.gap = '0';
                link.style.lineHeight = '1';

                const img = document.createElement('img');
                img.src = settings.site_logo + '?t=' + Date.now();
                img.alt = settings.site_name || 'cvmaker.ink';
                img.className = 'site-logo';
                img.style.height = '74px';
                img.style.width = 'auto';
                img.style.maxHeight = '84px';
                img.style.maxWidth = '380px';
                img.style.objectFit = 'contain';
                img.style.display = 'block';

                link.innerHTML = '';
                link.appendChild(img);
            });
        }
    }

    fetch('/api/settings/public')
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (data) applyBranding(data);
        })
        .catch(() => {});
})();
