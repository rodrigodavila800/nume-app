/*
 * Aviso de cookies e consentimento de conteúdo externo (RGPD / LSSI).
 *
 * Só há uma categoria opcional: "conteúdo externo" (players do YouTube e do Vimeo), desligada por
 * padrão. A escolha fica no cookie nume_cookie_consent (12 meses). Sem aceite, o player não carrega:
 * no lugar aparecem "Aceitar e assistir" e um link para ver o vídeo na plataforma de origem.
 *
 * Uso nas telas:
 *  - iframe no HTML: <iframe data-consent-src="https://www.youtube-nocookie.com/embed/ID ...">
 *  - iframe aberto por JS: NumeConsent.loadEmbed(iframe, url)
 *  - botão que reabre as preferências: data-open-cookie-settings
 */
(function () {
    const COOKIE = 'nume_cookie_consent';
    const MAX_AGE = 60 * 60 * 24 * 365;
    const root = document.getElementById('nume-cookie-consent');
    const labels = root ? root.dataset : {};

    function read() {
        const match = document.cookie.split('; ').find((row) => row.startsWith(COOKIE + '='));
        if (!match) return null;
        try {
            const value = JSON.parse(decodeURIComponent(match.split('=').slice(1).join('=')));
            return value && value.v === 1 ? value : null;
        } catch (error) {
            return null;
        }
    }

    function save(external) {
        const value = encodeURIComponent(JSON.stringify({ v: 1, external: !!external, at: new Date().toISOString() }));
        document.cookie = COOKIE + '=' + value + '; Max-Age=' + MAX_AGE + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        hide();
        applyAll();
        document.dispatchEvent(new CustomEvent('nume:consent-changed', { detail: { external: !!external } }));
    }

    function allowsExternal() {
        const consent = read();
        return !!(consent && consent.external);
    }

    function isExternalPlayer(url) {
        try {
            const host = new URL(url, location.href).hostname;
            return /(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be|vimeo\.com)$/.test(host);
        } catch (error) {
            return false;
        }
    }

    function watchUrl(url) {
        const youtube = url.match(/(?:embed\/|v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
        if (/youtu/.test(url) && youtube) return 'https://www.youtube.com/watch?v=' + youtube[1];
        const vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        if (vimeo) return 'https://vimeo.com/' + vimeo[1];
        return url;
    }

    function clearPlaceholder(iframe) {
        const host = iframe.parentElement;
        const placeholder = host && host.querySelector(':scope > .nume-consent-embed');
        if (placeholder) placeholder.remove();
        iframe.classList.remove('nume-consent-hidden');
    }

    function loadEmbed(iframe, url) {
        if (!iframe) return;
        iframe.dataset.consentSrc = url || '';
        clearPlaceholder(iframe);

        if (!url || !isExternalPlayer(url) || allowsExternal()) {
            iframe.src = url || '';
            return;
        }

        iframe.src = '';
        iframe.classList.add('nume-consent-hidden');
        const host = iframe.parentElement;
        host.classList.add('nume-consent-host');

        const box = document.createElement('div');
        box.className = 'nume-consent-embed';
        const text = document.createElement('p');
        text.textContent = labels.embedText || 'Este vídeo é carregado de um serviço externo.';
        const accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'nume-consent-btn nume-consent-btn-primary';
        accept.textContent = labels.embedAccept || 'Aceitar e assistir';
        accept.addEventListener('click', () => save(true));
        const watch = document.createElement('a');
        watch.className = 'nume-consent-link';
        watch.href = watchUrl(url);
        watch.target = '_blank';
        watch.rel = 'noopener noreferrer';
        watch.textContent = /vimeo/.test(url) ? (labels.embedVimeo || 'Ver no Vimeo') : (labels.embedYoutube || 'Ver no YouTube');
        box.append(text, accept, watch);
        host.appendChild(box);
    }

    function applyAll() {
        document.querySelectorAll('iframe[data-consent-src]').forEach((iframe) => {
            if (iframe.dataset.consentSrc) loadEmbed(iframe, iframe.dataset.consentSrc);
        });
    }

    function show(withPreferences) {
        if (!root) return;
        root.hidden = false;
        const panel = root.querySelector('[data-consent-preferences]');
        if (panel) panel.hidden = !withPreferences;
        const toggle = root.querySelector('[data-consent-external]');
        if (toggle) toggle.checked = allowsExternal();
        const focusTarget = root.querySelector(withPreferences ? '[data-consent-external]' : '[data-consent-accept]');
        if (focusTarget) focusTarget.focus();
    }

    function hide() {
        if (root) root.hidden = true;
    }

    window.NumeConsent = { allowsExternal, loadEmbed, open: () => show(true) };

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-open-cookie-settings]')) {
            event.preventDefault();
            show(true);
        }
    });

    if (root) {
        root.querySelector('[data-consent-accept]')?.addEventListener('click', () => save(true));
        root.querySelector('[data-consent-reject]')?.addEventListener('click', () => save(false));
        root.querySelector('[data-consent-configure]')?.addEventListener('click', () => show(true));
        root.querySelector('[data-consent-save]')?.addEventListener('click', () => save(root.querySelector('[data-consent-external]')?.checked));
    }

    const start = () => {
        applyAll();
        if (read() === null) show(false);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
