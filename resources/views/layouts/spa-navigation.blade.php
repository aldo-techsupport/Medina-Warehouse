{{-- ⚡ WAREHOUSE SEAMLESS SPA NAVIGATION ENGINE --}}
{{-- Mencegah full page reload antar menu agar upload background (GlobalPackingUploader) tetap berjalan lancar tanpa terputus --}}
<style>
    #spaProgressBar {
        position: fixed;
        top: 0;
        left: 0;
        height: 3px;
        background: linear-gradient(90deg, #0284c7 0%, #38bdf8 50%, #22c55e 100%);
        box-shadow: 0 0 8px rgba(56, 189, 248, 0.75);
        z-index: 99999;
        width: 0%;
        opacity: 0;
        transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease;
        pointer-events: none;
    }
</style>
<div id="spaProgressBar"></div>

<script>
    (function() {
        if (window.__WarehouseSpaInitialized) return;
        window.__WarehouseSpaInitialized = true;

        const WarehouseSpa = {
            isNavigating: false,
            currentUrl: window.location.href,
            progressBar: null,
            progressTimer: null,

            init() {
                this.progressBar = document.getElementById('spaProgressBar');
                this.patchDomContentLoaded();
                this.setupLinkInterceptor();
                this.setupFormInterceptor();
                this.setupPopstate();
            },

            // Intercept DOMContentLoaded so dynamically loaded scripts execute reliably
            patchDomContentLoaded() {
                const originalAddEventListener = document.addEventListener.bind(document);
                document.addEventListener = function(type, listener, options) {
                    if (type === 'DOMContentLoaded' && (document.readyState === 'complete' || document.readyState === 'interactive')) {
                        setTimeout(() => {
                            try {
                                listener(new Event('DOMContentLoaded'));
                            } catch(err) {
                                console.error('SPA DOMContentLoaded listener error:', err);
                            }
                        }, 10);
                        return;
                    }
                    return originalAddEventListener(type, listener, options);
                };
            },

            startProgress() {
                if (!this.progressBar) this.progressBar = document.getElementById('spaProgressBar');
                if (!this.progressBar) return;
                clearInterval(this.progressTimer);
                this.progressBar.style.transition = 'width 0.25s ease, opacity 0.2s ease';
                this.progressBar.style.opacity = '1';
                this.progressBar.style.width = '20%';

                let current = 20;
                this.progressTimer = setInterval(() => {
                    if (current < 85) {
                        current += Math.random() * 15;
                        if (current > 85) current = 85;
                        this.progressBar.style.width = `${current}%`;
                    }
                }, 200);
            },

            finishProgress() {
                clearInterval(this.progressTimer);
                if (!this.progressBar) return;
                this.progressBar.style.width = '100%';
                setTimeout(() => {
                    this.progressBar.style.opacity = '0';
                    setTimeout(() => {
                        this.progressBar.style.width = '0%';
                    }, 200);
                }, 150);
            },

            // Stop camera, close modals, and clean artifacts before page change
            cleanupCurrentPage() {
                // 1. Matikan kamera packing jika sedang aktif
                if (typeof window.stopPackingCamera === 'function') {
                    try { window.stopPackingCamera(); } catch(e) {}
                } else if (window.mediaStream) {
                    try {
                        window.mediaStream.getTracks().forEach(track => track.stop());
                        window.mediaStream = null;
                    } catch(e) {}
                }

                // 2. Tutup modal yang sedang terbuka agar backdrop tidak tersisa
                if (typeof $ !== 'undefined') {
                    try {
                        $('.modal').modal('hide');
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');
                    } catch(e) {}
                }

                // 3. Tutup sidebar mobile jika sedang terbuka
                document.body.classList.remove('sidebar-open');
            },

            async navigate(url, options = { pushState: true }) {
                if (this.isNavigating) return;

                // Jangan muat ulang jika URL persis sama (termasuk hash)
                if (url === window.location.href && options.pushState) {
                    return;
                }

                this.isNavigating = true;
                this.startProgress();

                try {
                    this.cleanupCurrentPage();

                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Warehouse-Spa': 'true'
                        },
                        credentials: 'same-origin'
                    });

                    // Jika ada redirect ke login (sesi habis), arahkan langsung
                    if (response.redirected) {
                        window.location.href = response.url;
                        return;
                    }

                    if (!response.ok) {
                        // Jika gagal memuat respons (404/500), fallback ke navigasi normal
                        window.location.href = url;
                        return;
                    }

                    const html = await response.text();
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');

                    // 1. Update Title
                    document.title = newDoc.title || document.title;

                    // 2. Update Browser History State
                    if (options.pushState) {
                        history.pushState({ spa: true, url }, '', url);
                    }
                    this.currentUrl = url;

                    // 3. Swap .content-wrapper (Halaman baru masuk)
                    const currentWrapper = document.querySelector('.content-wrapper');
                    const newWrapper = newDoc.querySelector('.content-wrapper');
                    if (currentWrapper && newWrapper) {
                        currentWrapper.innerHTML = newWrapper.innerHTML;
                        currentWrapper.className = newWrapper.className;
                    }

                    // 4. Update Header Page Title & Actions jika di luar wrapper
                    const currentHeader = document.querySelector('.content-header');
                    const newHeader = newDoc.querySelector('.content-header');
                    if (currentHeader && newHeader) {
                        currentHeader.innerHTML = newHeader.innerHTML;
                    }

                    // 5. Update Sidebar Active State
                    const currentSidebar = document.querySelector('.main-sidebar .nav-sidebar');
                    const newSidebar = newDoc.querySelector('.main-sidebar .nav-sidebar');
                    if (currentSidebar && newSidebar) {
                        currentSidebar.innerHTML = newSidebar.innerHTML;
                    }

                    // 6. Update Mobile Bottom Nav Active State
                    const currentBottomNav = document.querySelector('.mobile-bottom-nav');
                    const newBottomNav = newDoc.querySelector('.mobile-bottom-nav');
                    if (currentBottomNav && newBottomNav) {
                        currentBottomNav.innerHTML = newBottomNav.innerHTML;
                    }

                    // 7. Update Top Navbar Quick Links
                    const currentHeaderNav = document.querySelector('.main-header .navbar-nav');
                    const newHeaderNav = newDoc.querySelector('.main-header .navbar-nav');
                    if (currentHeaderNav && newHeaderNav) {
                        currentHeaderNav.innerHTML = newHeaderNav.innerHTML;
                    }

                    // 8. Swap Page-Specific Styles
                    const currentStyles = document.getElementById('spa-page-styles');
                    const newStyles = newDoc.getElementById('spa-page-styles');
                    if (currentStyles && newStyles) {
                        currentStyles.innerHTML = newStyles.innerHTML;
                    }

                    // 9. Re-execute Page-Specific Scripts
                    const currentScripts = document.getElementById('spa-page-scripts');
                    const newScripts = newDoc.getElementById('spa-page-scripts');
                    if (currentScripts) {
                        currentScripts.innerHTML = '';
                        if (newScripts) {
                            const scripts = newScripts.querySelectorAll('script');
                            scripts.forEach(oldScript => {
                                const newScript = document.createElement('script');
                                Array.from(oldScript.attributes).forEach(attr => {
                                    newScript.setAttribute(attr.name, attr.value);
                                });
                                newScript.textContent = oldScript.textContent;
                                currentScripts.appendChild(newScript);
                            });
                        }
                    }

                    // 10. Re-execute inline scripts within the new content-wrapper
                    if (currentWrapper) {
                        const wrapperScripts = currentWrapper.querySelectorAll('script');
                        wrapperScripts.forEach(s => {
                            const runScript = document.createElement('script');
                            Array.from(s.attributes).forEach(attr => runScript.setAttribute(attr.name, attr.value));
                            runScript.textContent = s.textContent;
                            s.parentNode.replaceChild(runScript, s);
                        });
                    }

                    // 11. Trigger standard events & scroll to top
                    window.scrollTo(0, 0);
                    document.dispatchEvent(new CustomEvent('warehouse:page-loaded', { detail: { url } }));
                    document.dispatchEvent(new Event('DOMContentLoaded'));

                } catch (err) {
                    console.error('SPA navigation error, falling back to full load:', err);
                    window.location.href = url;
                } finally {
                    this.finishProgress();
                    this.isNavigating = false;
                }
            },

            setupLinkInterceptor() {
                document.addEventListener('click', (e) => {
                    const link = e.target.closest('a');
                    if (!link || !link.href) return;

                    // Lewati link eksternal
                    if (link.origin !== window.location.origin) return;

                    // Lewati anchor kosong atau hash murni (#, #tab...)
                    const rawHref = link.getAttribute('href') || '';
                    if (rawHref === '#' || rawHref.startsWith('javascript:') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:')) {
                        return;
                    }

                    // Jika hanya berpindah anchor dalam halaman yang sama
                    const curBase = window.location.href.split('#')[0];
                    const tarBase = link.href.split('#')[0];
                    if (curBase === tarBase && link.href.includes('#')) {
                        return;
                    }

                    // Lewati link download atau target=_blank
                    if (link.hasAttribute('download') || (link.target && link.target !== '_self')) {
                        return;
                    }

                    // Lewati elemen yang eksplisit meminta non-SPA
                    if (link.hasAttribute('data-no-pjax') || link.getAttribute('data-pjax') === 'false') {
                        return;
                    }

                    // Lewati toggle bootstrap / pushmenu / dismiss
                    if (link.getAttribute('data-toggle') || link.getAttribute('data-widget') || link.getAttribute('data-dismiss')) {
                        return;
                    }

                    // Intersep dan navigasikan tanpa reload!
                    e.preventDefault();
                    this.navigate(link.href);
                });
            },

            setupFormInterceptor() {
                document.addEventListener('submit', (e) => {
                    const form = e.target;
                    if (!form || (form.method || '').toUpperCase() !== 'GET') return;
                    if (form.hasAttribute('data-no-pjax') || form.getAttribute('data-pjax') === 'false') return;

                    const action = form.action || window.location.href;
                    const url = new URL(action, window.location.origin);
                    if (url.origin !== window.location.origin) return;

                    e.preventDefault();
                    const formData = new FormData(form);
                    const params = new URLSearchParams(formData).toString();
                    const finalUrl = url.pathname + (params ? '?' + params : '') + url.hash;
                    this.navigate(finalUrl);
                });
            },

            setupPopstate() {
                window.addEventListener('popstate', (e) => {
                    this.navigate(window.location.href, { pushState: false });
                });
            }
        };

        window.WarehouseSpa = WarehouseSpa;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => WarehouseSpa.init());
        } else {
            WarehouseSpa.init();
        }
    })();
</script>
