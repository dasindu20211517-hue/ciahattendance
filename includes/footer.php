            </div>
        </main>
    </div>
    <div class="toast-container" id="toastContainer"></div>
    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    }

    function requestBrowserNotifications() {
        if (!('Notification' in window)) return Promise.resolve('unsupported');
        if (Notification.permission === 'granted') return Promise.resolve('granted');
        if (Notification.permission === 'denied') return Promise.resolve('denied');
        return Notification.requestPermission().then(function(permission) {
            return permission;
        });
    }

    function triggerNativeNotification(title, body) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        var icon = 'assets/icons/icon-192.png';
        new Notification(title, {
            body: body,
            icon: icon,
            tag: 'ciah-hris-notification'
        });
    }

    function showToast(message, type) {
        type = type || 'info';
        var container = document.getElementById('toastContainer');
        var toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.textContent = message;
        container.appendChild(toast);
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                triggerNativeNotification('CIAH Attendance', message);
            } catch (e) {
                console.warn('Native notification failed:', e);
            }
        }
        setTimeout(function() {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(function() { toast.remove(); }, 300);
        }, 3000);
    }
    </script>
    <script src="assets/whatsapp-pdf.js?v=<?php echo filemtime(__DIR__ . '/../assets/whatsapp-pdf.js'); ?>"></script>
    <script src="assets/app.js?v=<?php echo filemtime(__DIR__ . '/../assets/app.js'); ?>"></script>
    <script>
    // ---- PWA: service worker registration ----
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js').catch(function (err) {
                console.warn('Service worker registration failed:', err);
            });
        });
    }

    // ---- PWA: install prompt ----
    var deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        var btn = document.getElementById('installBtn');
        if (btn) btn.style.display = 'flex';
    });
    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        var btn = document.getElementById('installBtn');
        if (btn) btn.style.display = 'none';
        requestBrowserNotifications();
        showToast('App installed successfully.', 'success');
    });

    function installPWA() {
        if (!deferredPrompt) {
            // iOS / non-Chrome fallback: instruct the user to add to home screen
            showToast('On your phone, open the browser menu and tap "Add to Home Screen".', 'info');
            return;
        }
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (choice) {
            if (choice.outcome === 'accepted') {
                requestBrowserNotifications();
                showToast('App installing...', 'success');
            }
            deferredPrompt = null;
            var btn = document.getElementById('installBtn');
            if (btn) btn.style.display = 'none';
        });
    }

    window.addEventListener('load', function () {
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        if (isStandalone && 'Notification' in window && Notification.permission === 'default') {
            requestBrowserNotifications();
        }
    });
    </script>
</body>
</html>
