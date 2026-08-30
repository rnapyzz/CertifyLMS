/**
 * TopBar 通知ポップオーバー(resources/views/notifications/_partials/notification-popover.blade.php +
 * resources/views/layouts/_partials/topbar.blade.php のベルアイコン)を動かす JS。
 *
 * - ベルクリックで開閉。開くたびに GET /api/v1/notifications を fetch し直す(キャッシュしない、
 *   スコープ外のポップオーバー内ページネーションもないため常に最新の直近分を出す)。
 * - Sanctum SPA Cookie 認証: 初回のみ GET /sanctum/csrf-cookie で XSRF-TOKEN cookie を取得し、
 *   以後の POST は X-XSRF-TOKEN ヘッダにその値を載せる(Laravel 標準の CSRF 二段防御)。
 * - タブ(全件/未読)はサーバーへ再フェッチせず、直近フェッチ結果をクライアント側でフィルタする。
 * - 未読件数(TopBar バッジ + ポップオーバー内の未読タブバッジ)はサーバーが返す unread_count を
 *   常に正とし、行クリック/全件既読のたびに更新する。
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const API_BASE = '/api/v1/notifications';

function readXsrfTokenCookie() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');
    if (!root) return;

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const badge = root.querySelector('[data-notification-popover-badge]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    if (!trigger || !panel) return;

    const tabButtons = panel.querySelectorAll('[data-notification-popover-tab]');
    const unreadCountEl = panel.querySelector('[data-notification-popover-unread-count]');
    const markAllBtn = panel.querySelector('[data-notification-popover-mark-all]');
    const loadingEl = panel.querySelector('[data-notification-popover-loading]');
    const emptyEl = panel.querySelector('[data-notification-popover-empty]');
    const itemsEl = panel.querySelector('[data-notification-popover-items]');
    const rowTemplate = panel.querySelector('[data-notification-popover-row-template]');
    if (!itemsEl || !rowTemplate) return;

    let notifications = [];
    let activeTab = 'all';
    let isOpen = false;
    let csrfReady = false;
    let closeTimer = null;

    async function ensureCsrfCookie() {
        if (csrfReady) return;
        await fetch(CSRF_COOKIE_URL, { credentials: 'same-origin' });
        csrfReady = true;
    }

    function apiHeaders(extra = {}) {
        return {
            Accept: 'application/json',
            'X-XSRF-TOKEN': readXsrfTokenCookie(),
            ...extra,
        };
    }

    function updateUnreadCount(count) {
        if (badge) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.toggle('hidden', count <= 0);
        }
        trigger.setAttribute('aria-label', `通知 (${count} 件未読)`);
        if (unreadCountEl) {
            unreadCountEl.textContent = count > 99 ? '99+' : String(count);
        }
    }

    function setLoading(loading) {
        loadingEl?.classList.toggle('hidden', !loading);
        if (loading) {
            emptyEl?.classList.add('hidden');
            itemsEl.innerHTML = '';
        }
    }

    function visibleNotifications() {
        return activeTab === 'unread' ? notifications.filter((n) => n.unread) : notifications;
    }

    function renderRow(notification) {
        const node = rowTemplate.content.firstElementChild.cloneNode(true);
        const link = node.querySelector('[data-notification-popover-row]');
        const dot = node.querySelector('[data-notification-popover-row-dot]');
        const titleEl = node.querySelector('[data-notification-popover-row-title]');
        const messageEl = node.querySelector('[data-notification-popover-row-message]');
        const timeEl = node.querySelector('[data-notification-popover-row-time]');

        if (link) {
            link.href = notification.target_url;
            link.setAttribute('aria-data-unread', notification.unread ? 'true' : 'false');
            link.addEventListener('click', (e) => {
                e.preventDefault();
                handleRowClick(notification);
            });
        }
        if (dot) dot.classList.toggle('invisible', !notification.unread);
        if (titleEl) titleEl.textContent = notification.title;
        if (messageEl) messageEl.textContent = notification.message;
        if (timeEl) timeEl.textContent = notification.created_at_human;

        return node;
    }

    function render() {
        const visible = visibleNotifications();
        itemsEl.innerHTML = '';

        if (visible.length === 0) {
            emptyEl?.classList.remove('hidden');
        } else {
            emptyEl?.classList.add('hidden');
            const fragment = document.createDocumentFragment();
            visible.forEach((n) => fragment.appendChild(renderRow(n)));
            itemsEl.appendChild(fragment);
        }
    }

    function setActiveTab(tab) {
        activeTab = tab;
        tabButtons.forEach((btn) => {
            btn.setAttribute('aria-selected', btn.dataset.notificationPopoverTab === tab ? 'true' : 'false');
        });
        render();
    }

    async function fetchNotifications() {
        setLoading(true);
        try {
            const res = await fetch(API_BASE, {
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(`fetch failed: ${res.status}`);
            const data = await res.json();
            notifications = Array.isArray(data.notifications) ? data.notifications : [];
            updateUnreadCount(data.unread_count ?? 0);
            render();
        } catch (e) {
            notifications = [];
            render();
        } finally {
            setLoading(false);
        }
    }

    async function handleRowClick(notification) {
        // 楽観的にローカル状態と未読数を先に反映してから遷移する(既読化 API 呼出の完了は待たない)。
        if (notification.unread) {
            notification.unread = false;
            const current = notifications.filter((n) => n.unread).length;
            updateUnreadCount(current);
        }

        ensureCsrfCookie()
            .then(() => fetch(`${API_BASE}/${notification.id}/read`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
            }))
            .catch(() => {});

        window.location.href = notification.target_url;
    }

    async function handleMarkAll() {
        if (markAllBtn) markAllBtn.disabled = true;
        try {
            await ensureCsrfCookie();
            const res = await fetch(`${API_BASE}/read-all`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            if (res.ok) {
                notifications = notifications.map((n) => ({ ...n, unread: false }));
                updateUnreadCount(0);
                render();
            }
        } catch (e) {
            // no-op: 失敗時は次回ポップオーバーを開いた時に実際の状態で再同期される
        } finally {
            if (markAllBtn) markAllBtn.disabled = false;
        }
    }

    function openPanel() {
        isOpen = true;
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        panel.style.display = 'flex';
        panel.classList.remove('hidden');
        // 次フレームで opacity/translate を外し、CSS transition を発火させる
        requestAnimationFrame(() => {
            panel.classList.remove('opacity-0', '-translate-y-1');
        });
        trigger.setAttribute('aria-expanded', 'true');
        document.addEventListener('click', handleOutsideClick, true);
        document.addEventListener('keydown', handleKeydown);

        ensureCsrfCookie().then(fetchNotifications);
    }

    function closePanel() {
        isOpen = false;
        panel.classList.add('opacity-0', '-translate-y-1');
        trigger.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', handleOutsideClick, true);
        document.removeEventListener('keydown', handleKeydown);
        closeTimer = setTimeout(() => {
            panel.classList.add('hidden');
            panel.style.display = 'none';
        }, 150);
    }

    function togglePanel() {
        if (isOpen) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function handleOutsideClick(e) {
        if (!root.contains(e.target)) closePanel();
    }

    function handleKeydown(e) {
        if (e.key === 'Escape') closePanel();
    }

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePanel();
    });

    tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => setActiveTab(btn.dataset.notificationPopoverTab));
    });

    markAllBtn?.addEventListener('click', handleMarkAll);
}
