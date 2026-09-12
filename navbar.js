/**
 * Shared Navbar Component Loader
 * Loads the navbar HTML and initializes it
 */

// Guards: the navbar HTML must inject exactly once per page load.
// The boot code below can invoke loadNavbar() up to 3 times (immediate +
// IIFE + DOMContentLoaded); without this guard every call wipes and
// re-creates the navbar DOM, resetting badges/username and causing the
// visible multiple-reload flicker.
let __navbarLoadingPromise = null;
let __navbarInjected = false;

// Load navbar HTML (async, non-blocking)
async function loadNavbar() {
    const navbarContainer = document.getElementById('navbar-container');
    if (!navbarContainer) {
        console.error('Navbar container not found');
        return;
    }
    if (__navbarInjected) return; // already live - never wipe it
    if (__navbarLoadingPromise) return __navbarLoadingPromise;
    __navbarLoadingPromise = doLoadNavbar(navbarContainer)
        .then((ok) => { if (ok !== false) __navbarInjected = true; })
        .finally(() => { __navbarLoadingPromise = null; });
    return __navbarLoadingPromise;
}

// Actual navbar fetch + init (runs at most once per page load)
async function doLoadNavbar(navbarContainer) {
    try {
        const resp = await fetch('navbar.html', { cache: 'no-cache' });
        if (!resp.ok) throw new Error(`Failed to load navbar: ${resp.status}`);
        const html = await resp.text();
        navbarContainer.innerHTML = html;
        
        // Paint cached user instantly (before any network round-trip) so the
        // navbar never flashes the "User" empty state on refresh.
        // loadUserData() below still verifies against the server.
        paintCachedUser();
        
        initializeNavbar();
        window.dispatchEvent(new Event('navbarLoaded'));
        
        // Load all badges immediately (no delays) - similar to dashboard.html and cart.html
        // NOTE: cart uses the alias because pages may declare their own
        // global updateCartCount() which would shadow window.updateCartCount
        (async () => {
            // Load cart count immediately
            await window.__navbarUpdateCartCount();
            
            // Load order count immediately
            updateOrderCount();
            
            // Load notifications immediately (badge only, fast display)
            loadNotificationBadgeFast();
            
            // Load full notifications in background (for dropdown)
            loadNotifications();
        })();

        // Refresh notifications when dropdown is opened, and mark as seen
        // so the badge clears once the user has viewed them
        document.addEventListener('shown.bs.dropdown', (event) => {
            if (event.target && event.target.id === 'navNotifications') {
                markNotificationsSeen();
                loadNotifications(true);
        }
        });
        return true;
    } catch (error) {
        console.error('Error loading navbar:', error);
        return false;
    }
}

// Initialize navbar functionality
function initializeNavbar() {
    // Navbar styling is handled by navbar.css - no JavaScript manipulation needed
    
    // Set active nav item based on current page
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.html';
    
    // Remove active class from all nav items
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Add active class to current page
    if (currentPage === 'dashboard.html' || currentPage === 'index.html') {
        const homeLink = document.getElementById('navHome');
        if (homeLink) homeLink.classList.add('active');
    } else if (currentPage === 'cart.html') {
        const cartLink = document.getElementById('navCart');
        if (cartLink) cartLink.classList.add('active');
    } else if (currentPage === 'orders.html') {
        const ordersLink = document.getElementById('navOrders');
        if (ordersLink) ordersLink.classList.add('active');
    }
    
    // Load user data (via alias: pages may declare their own global
    // loadUserData() which would shadow the bare name)
    window.__navbarLoadUserData();
    
    // Badges are now loaded immediately in loadNavbar() - no delays needed here
}

// Paint the last-known user from localStorage synchronously.
// Called immediately after navbar HTML injection so refreshes show the
// real username instantly instead of flashing the "User" empty state.
function paintCachedUser() {
    let cached = null;
    try {
        cached = JSON.parse(localStorage.getItem('loggedInUser') || localStorage.getItem('userData') || 'null');
    } catch (_) {
        cached = null;
    }
    if (cached && (cached.username || cached.first_name)) {
        const usernameDisplay = document.getElementById('usernameDisplay');
        if (usernameDisplay) {
            usernameDisplay.textContent = cached.username || cached.first_name;
        }
        const navNotificationsWrapper = document.getElementById('navNotificationsWrapper');
        if (navNotificationsWrapper) {
            navNotificationsWrapper.style.display = '';
        }
    }
}

// Pages that require a live session. A 401 here (e.g. PHP sessions wiped
// by a redeploy) sends the user to login instead of leaving them stranded
// on a stale, half-logged-out page. Network errors never redirect - the
// server may simply be mid-deploy and unreachable.
const AQUA_PROTECTED_PAGES = ['dashboard.html', 'cart.html', 'orders.html', 'payment.html', 'profile.html', 'recent_orders.html'];

function redirectToLoginIfProtected() {
    try {
        const page = (window.location.pathname.split('/').pop() || '').toLowerCase();
        if (AQUA_PROTECTED_PAGES.includes(page)) {
            window.location.href = 'login.html';
        }
    } catch (_) {}
}

// Load user data for navbar
function loadUserData() {
    // Optimistic paint first - server response below will verify/correct it
    paintCachedUser();
    // Try to fetch from server first, then fallback to localStorage
    fetch('api/get_current_user.php')
        .then(response => {
            // If 401, user is not logged in - clear localStorage and hide user data
            if (response.status === 401) {
                // Clear user data from localStorage
                localStorage.removeItem('loggedInUser');
                localStorage.removeItem('userData');
                sessionStorage.removeItem('userData');
                
                // Hide user dropdown or show default "User"
                const usernameDisplay = document.getElementById('usernameDisplay');
                if (usernameDisplay) {
                    usernameDisplay.textContent = 'User';
                }
                
                // Hide user-specific elements (notifications, orders, etc.)
                const navNotificationsWrapper = document.getElementById('navNotificationsWrapper');
                if (navNotificationsWrapper) {
                    navNotificationsWrapper.style.display = 'none';
                }
                
                // Dead session on a protected page (e.g. after a redeploy
                // wiped server sessions) - go to login instead of lingering
                redirectToLoginIfProtected();
                
                return null; // Don't proceed with JSON parsing
            }
            
            if (!response.ok) {
                throw new Error('Failed to fetch user data');
            }
            return response.json();
        })
        .then(data => {
            // If we got null (401 case), we already handled it above
            if (!data) return;
            
            if (data.success && data.user) {
                const userData = data.user;
                // Save to localStorage
                localStorage.setItem('loggedInUser', JSON.stringify(userData));
                localStorage.setItem('userData', JSON.stringify(userData));
                
                // Restore server-persisted notification state (survives logout/login)
                mergeServerNotifState(userData);
                
                const usernameDisplay = document.getElementById('usernameDisplay');
                if (usernameDisplay) {
                    usernameDisplay.textContent = userData.username || 'User';
                }
                
                // Show user-specific elements
                const navNotificationsWrapper = document.getElementById('navNotificationsWrapper');
                if (navNotificationsWrapper) {
                    navNotificationsWrapper.style.display = '';
                }
            } else {
                // No user data from server - clear localStorage and show default
                localStorage.removeItem('loggedInUser');
                localStorage.removeItem('userData');
                sessionStorage.removeItem('userData');
                
                const usernameDisplay = document.getElementById('usernameDisplay');
                if (usernameDisplay) {
                    usernameDisplay.textContent = 'User';
                }
                
                // Hide user-specific elements
                const navNotificationsWrapper = document.getElementById('navNotificationsWrapper');
                if (navNotificationsWrapper) {
                    navNotificationsWrapper.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error loading user data:', error);
            // On network error, check if we have valid session data
            // Only use localStorage if we're sure user is logged in (check session cookie or try API again)
            // For now, clear localStorage to be safe - user should log in again
            localStorage.removeItem('loggedInUser');
            localStorage.removeItem('userData');
            sessionStorage.removeItem('userData');
            
            const usernameDisplay = document.getElementById('usernameDisplay');
            if (usernameDisplay) {
                usernameDisplay.textContent = 'User';
            }
            
            // Hide user-specific elements
            const navNotificationsWrapper = document.getElementById('navNotificationsWrapper');
            if (navNotificationsWrapper) {
                navNotificationsWrapper.style.display = 'none';
            }
        });
}

// Shadowing-proof alias (see initializeNavbar note): pages must call
// window.__navbarLoadUserData() instead of relying on the bare name.
window.__navbarLoadUserData = loadUserData;

// Paint a count badge only when something actually changed, so duplicate
// paint calls from different sources never cause a visible flicker/reload
function paintCountBadge(id, count) {
    const el = document.getElementById(id);
    if (!el) return;
    const text = String(count);
    const display = count > 0 ? 'flex' : 'none';
    if (el.textContent !== text) el.textContent = text;
    if (el.style.display !== display) el.style.display = display;
}

// Paint the cart badge instantly from the local cart (no network wait)
function paintCartBadge(totalItems) {
    paintCountBadge('cartCount', totalItems);
}

function getLocalCartCount() {
    try {
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        return cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
    } catch (_) {
        return 0;
    }
}

// Single shared sync so parallel callers (navbar + page scripts) reuse
// one request instead of each firing their own fetch-and-paint cycle
let __cartSyncPromise = null;

// Update cart count in navbar
async function updateCartCount() {
    // Instant paint first so the badge never appears late
    paintCartBadge(getLocalCartCount());
    if (__cartSyncPromise) return __cartSyncPromise;
    __cartSyncPromise = (async () => {
        try {
            // Use UserState if available, otherwise fallback to localStorage
            let cart = [];
            if (typeof UserState !== 'undefined') {
                await UserState.loadState();
                cart = UserState.getCart();
            } else {
                cart = JSON.parse(localStorage.getItem('cart') || '[]');
            }
            paintCartBadge(cart.reduce((sum, item) => sum + (item.quantity || 0), 0));
        } catch (_) {
            // Keep the instantly-painted local value on error
        }
    })().finally(() => { __cartSyncPromise = null; });
    return __cartSyncPromise;
}

// Make updateCartCount available globally so pages can call it.
// The alias is shadowing-proof: pages that declare their own global
// updateCartCount() overwrite window.updateCartCount, so page scripts
// must call window.__navbarUpdateCartCount() instead.
window.updateCartCount = updateCartCount;
window.__navbarUpdateCartCount = updateCartCount;

// Update order count in navbar
function updateOrderCount() {
    // Try to get badge element - retry if not found (navbar might still be loading)
    const ordersCountEl = document.getElementById('ordersCount');
    if (!ordersCountEl) {
        // Retry immediately if element doesn't exist yet (navbar still loading)
        // Use requestAnimationFrame for smooth retry without blocking
        requestAnimationFrame(() => {
            if (document.getElementById('ordersCount')) {
                updateOrderCount();
            } else {
                // Fallback: retry after a very short delay if still not found
                setTimeout(updateOrderCount, 50);
            }
        });
        return;
    }
    
    // FAST PATH: Use cached orders when available (e.g., My Orders page just fetched)
    // The cache should already be filtered (no delivered/cancelled orders)
    if (Array.isArray(window.__ordersCache)) {
        // Filter out delivered and cancelled orders to match My Orders page behavior
        const filteredOrders = window.__ordersCache.filter(o => {
            const status = (o.status || '').toLowerCase();
            return status !== 'cancelled' && status !== 'delivered';
        });
        const orderCount = filteredOrders.length;
        paintCountBadge('ordersCount', orderCount);
        // Still fetch in background to sync, but don't wait for it
        fetchOrdersInBackground();
        return;
    }
    
    // FAST PATH: Try localStorage cache first (instant display)
    try {
        const cachedCount = localStorage.getItem('ordersCount');
        const cacheTimestamp = localStorage.getItem('ordersCountTimestamp');
        const now = Date.now();
        // Use cache if it's less than 30 seconds old
        if (cachedCount !== null && cacheTimestamp && (now - parseInt(cacheTimestamp)) < 30000) {
            const orderCount = parseInt(cachedCount, 10);
            paintCountBadge('ordersCount', orderCount);
            // Fetch in background to sync, but don't wait for it
            fetchOrdersInBackground();
            return;
        }
    } catch (e) {
        // Ignore localStorage errors
    }
    
    // SLOW PATH: Fetch orders from API (only if no cache available)
    fetchOrdersInBackground();
}

// Shared in-flight guard: parallel callers (navbar + page scripts) reuse one
// request instead of each firing their own fetch-and-paint cycle
let __ordersFetchPromise = null;

// Fetch orders in background and update cache
function fetchOrdersInBackground() {
    if (__ordersFetchPromise) return __ordersFetchPromise;
    __ordersFetchPromise = doFetchOrders().finally(() => { __ordersFetchPromise = null; });
    return __ordersFetchPromise;
}

function doFetchOrders() {
    return fetch('api/get_orders.php?limit=1000')
        .then(response => {
            if (!response.ok) {
                // If 401, user is not logged in - hide badge
                if (response.status === 401) {
                    const badge401 = document.getElementById('ordersCount');
                    if (badge401) badge401.style.display = 'none';
                    return null;
                }
                throw new Error('Failed to fetch orders: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (!data) return; // Handled 401 case above
            
            if (data.success && Array.isArray(data.orders)) {
                // Filter out delivered and cancelled orders (same as My Orders page)
                const filteredOrders = data.orders.filter(o => {
                    const status = (o.status || '').toLowerCase();
                    return status !== 'cancelled' && status !== 'delivered';
                });
                
                const orderCount = filteredOrders.length;
                
                // Cache the count in localStorage for fast loading next time
                try {
                    localStorage.setItem('ordersCount', String(orderCount));
                    localStorage.setItem('ordersCountTimestamp', String(Date.now()));
                } catch (e) {
                    // Ignore localStorage errors
                }
                
                const badgeEl = document.getElementById('ordersCount');
                if (badgeEl) {
                    badgeEl.textContent = orderCount;
                    // Show badge when count > 0, hide when 0
                    badgeEl.style.display = orderCount > 0 ? 'flex' : 'none';
                }
            } else {
                // No orders or invalid response
                const badgeEl = document.getElementById('ordersCount');
                if (badgeEl) {
                    badgeEl.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching order count:', error);
            // Don't hide badge on network error - might be temporary
        });
}

// Make updateOrderCount available globally so pages can call it
window.updateOrderCount = updateOrderCount;

// Make notification functions available globally
window.loadNotifications = loadNotifications;
window.clearNotificationBadge = clearNotificationBadge;
window.paginateNotifications = paginateNotifications;
window.markNotificationsSeen = markNotificationsSeen;
window.openNotificationOrder = openNotificationOrder;

// ---------------- Notifications ----------------
let __notifData = [];
let __notifPage = 1;
const __notifPageSize = 4;
const NOTIF_CLEARED_KEY = 'notifClearedAt';
const NOTIF_CLEARED_COOKIE = 'notifClearedAtCookie';

function setNotifCleared(ts) {
    try {
        localStorage.setItem(NOTIF_CLEARED_KEY, String(ts));
    } catch (_) {}
    try {
        document.cookie = `${NOTIF_CLEARED_COOKIE}=${ts}; path=/; max-age=${60 * 60 * 24 * 30}`;
    } catch (_) {}
    persistNotifStateToServer();
}

// Fire-and-forget persist of notification state so seen/cleared survives
// logout/login and redeploys (stored per-user in the database)
function persistNotifStateToServer() {
    try {
        const seen = parseInt(localStorage.getItem(NOTIF_SEEN_KEY) || '0', 10) || 0;
        const cleared = getNotifCleared();
        if (!seen && !cleared) return;
        fetch('api/save_notification_state.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seen_at: seen, cleared_at: cleared })
        }).catch(() => {});
    } catch (_) {}
}

// Restore server-persisted state on login (takes the newer of each side)
function mergeServerNotifState(userData) {
    try {
        const serverSeen = parseInt((userData && userData.notif_seen_at) || '0', 10) || 0;
        const serverCleared = parseInt((userData && userData.notif_cleared_at) || '0', 10) || 0;
        const localSeen = getNotifSeen();
        if (serverSeen > localSeen) {
            try { localStorage.setItem(NOTIF_SEEN_KEY, String(serverSeen)); } catch (_) {}
        }
        if (serverCleared > getNotifCleared()) {
            try { localStorage.setItem(NOTIF_CLEARED_KEY, String(serverCleared)); } catch (_) {}
        }
    } catch (_) {}
}

function getNotifCleared() {
    let ts = parseInt(localStorage.getItem(NOTIF_CLEARED_KEY) || '0', 10) || 0;
    try {
        const cookie = document.cookie.split(';').map(c => c.trim()).find(c => c.startsWith(`${NOTIF_CLEARED_COOKIE}=`));
        if (cookie) {
            const val = parseInt(cookie.split('=')[1] || '0', 10) || 0;
            if (val > ts) ts = val;
        }
    } catch (_) {}
    return ts;
}

const NOTIF_SEEN_KEY = 'notifSeenAt';

function setNotifSeen(ts) {
    try { localStorage.setItem(NOTIF_SEEN_KEY, String(ts)); } catch (_) {}
    persistNotifStateToServer();
}

function getNotifSeen() {
    try { return parseInt(localStorage.getItem(NOTIF_SEEN_KEY) || '0', 10) || 0; }
    catch (_) { return 0; }
}

// Opening the bell marks everything as seen: badge clears, list stays visible
function markNotificationsSeen() {
    setNotifSeen(Date.now());
    paintCountBadge('notificationCount', 0);
}

// Clicking a notification marks seen and takes the user to the right page:
// delivered / completed / cancelled orders live on recent orders,
// everything else (active orders) lives on the orders page
function openNotificationOrder(orderId, status) {
    markNotificationsSeen();
    const s = String(status || '').toLowerCase();
    const id = parseInt(orderId, 10) || 0;
    if ((s === 'delivered' || s === 'completed' || s === 'cancelled') && id > 0) {
        window.location.href = 'recent_orders.html?order=' + id;
    } else {
        window.location.href = 'orders.html';
    }
}

function clearNotificationBadge() {
    paintCountBadge('notificationCount', 0);
    
    // Clear cache when user manually clears notifications
    try {
        localStorage.setItem('notificationCount', '0');
        localStorage.setItem('notificationCountTimestamp', String(Date.now()));
    } catch (e) {
        // Ignore localStorage errors
    }
    
    __notifData = [];
    __notifPage = 1;
    // Clear saved page from localStorage
    try {
        localStorage.removeItem('notificationPage');
    } catch (e) {
        // Ignore localStorage errors
    }
    renderNotificationPage();
    setNotifCleared(Date.now());
}

function getNotificationMessage(order) {
    const status = (order.status || '').toLowerCase();
    const payment = (order.payment_method || order.paymentMethod || '').toLowerCase();

    if (status === 'pending') {
        return {
            title: 'Order Placed',
            desc: 'Your order has been placed! Kindly wait for admin approval.',
            color: 'linear-gradient(135deg, #2383B5, #155A7A)',
            icon: 'fas fa-check-circle'
        };
    }
    if (status === 'preparing') {
        return {
            title: 'Preparing',
            desc: 'Your order has been approved and is now being prepared.',
            color: 'linear-gradient(135deg, #6366f1, #4338ca)',
            icon: 'fas fa-box'
        };
    }
    if (status === 'shipped') {
        return {
            title: 'Shipped',
            desc: 'Your order has been shipped and is now in transit. Please await our next update regarding delivery.',
            color: 'linear-gradient(135deg, #5BC0EB, #2383B5)',
            icon: 'fas fa-shipping-fast'
        };
    }
    if (status === 'out_for_delivery') {
        const isCod = payment === 'cod';
        return {
            title: 'Out for Delivery',
            desc: isCod
                ? 'Your order is now out for delivery. Please ensure the corresponding payment is prepared.'
                : 'Your order is now out for delivery and will reach you soon.',
            color: 'linear-gradient(135deg, #22c55e, #16a34a)',
            icon: 'fas fa-truck'
        };
    }
    if (status === 'delivered') {
        return {
            title: 'Delivered',
            desc: 'Your order has been successfully delivered.',
            color: 'linear-gradient(135deg, #10b981, #059669)',
            icon: 'fas fa-check-circle'
        };
    }
    if (status === 'cancellation_requested') {
        const isCod = payment === 'cod';
        return {
            title: 'Cancellation Requested',
            desc: isCod
                ? 'Your cancellation request is being reviewed by our admin. Please wait for approval.'
                : 'Your cancellation request is being reviewed by our admin. Please wait for approval.',
            color: 'linear-gradient(135deg, #fbbf24, #f59e0b)',
            icon: 'fas fa-clock'
        };
    }
    if (status === 'cancelled') {
        const isCod = payment === 'cod';
        return {
            title: 'Order Cancelled',
            desc: isCod
                ? 'The cancellation of your order has been completed successfully.'
                : 'Your order has been successfully cancelled. Your payment has been refunded to your GCash account.',
            color: 'linear-gradient(135deg, #ef4444, #dc2626)',
            icon: 'fas fa-ban'
        };
    }
    return null;
}

// Fast badge-only loading (for instant display)
function loadNotificationBadgeFast() {
    const badge = document.getElementById('notificationCount');
    if (!badge) {
        // Retry immediately if element doesn't exist yet
        requestAnimationFrame(() => {
            if (document.getElementById('notificationCount')) {
                loadNotificationBadgeFast();
            } else {
                setTimeout(loadNotificationBadgeFast, 50);
            }
        });
        return;
    }

    // FAST PATH: Try localStorage cache first (instant display)
    try {
        const cachedCount = localStorage.getItem('notificationCount');
        const cacheTimestamp = localStorage.getItem('notificationCountTimestamp');
        const now = Date.now();
        // Use cache if it's less than 30 seconds old
        if (cachedCount !== null && cacheTimestamp && (now - parseInt(cacheTimestamp)) < 30000) {
            const notifCount = parseInt(cachedCount, 10);
            paintCountBadge('notificationCount', notifCount);
            return; // Badge updated, full load will happen in background
        }
    } catch (e) {
        // Ignore localStorage errors
    }
    
    // If no cache, badge will be updated when loadNotifications() completes
    // For now, ensure badge is visible if it should be (will be updated by API call)
}

function loadNotifications(force = false) {
    const badge = document.getElementById('notificationCount');
    const list = document.getElementById('notificationList');
    const wrapper = document.getElementById('navNotificationsWrapper');
    if (!list || !wrapper) {
        // Retry immediately if elements don't exist yet (navbar still loading)
        requestAnimationFrame(() => {
            if (document.getElementById('notificationList') && document.getElementById('navNotificationsWrapper')) {
                loadNotifications(force);
            } else {
                // Fallback: retry after a very short delay if still not found
                setTimeout(() => loadNotifications(force), 50);
            }
        });
        return;
    }

    // FAST PATH: Try localStorage cache first (instant display) - only if not forcing refresh
    if (!force) {
        try {
            const cachedCount = localStorage.getItem('notificationCount');
            const cacheTimestamp = localStorage.getItem('notificationCountTimestamp');
            const now = Date.now();
            // Use cache if it's less than 30 seconds old
            if (cachedCount !== null && cacheTimestamp && (now - parseInt(cacheTimestamp)) < 30000) {
                const notifCount = parseInt(cachedCount, 10);
                paintCountBadge('notificationCount', notifCount);
                // Fetch in background to sync, but don't wait for it
                fetchNotificationsInBackground();
                return;
            }
        } catch (e) {
            // Ignore localStorage errors
        }
    }

    // SLOW PATH: Fetch notifications from API
    fetchNotificationsInBackground();
}

// Shared in-flight guard: parallel callers reuse one request instead of
// each firing their own fetch-and-paint cycle
let __notifFetchPromise = null;

// Fetch notifications in background and update cache
function fetchNotificationsInBackground() {
    if (__notifFetchPromise) return __notifFetchPromise;
    __notifFetchPromise = doFetchNotifications().finally(() => { __notifFetchPromise = null; });
    return __notifFetchPromise;
}

function doFetchNotifications() {
    const badge = document.getElementById('notificationCount');
    const list = document.getElementById('notificationList');
    const wrapper = document.getElementById('navNotificationsWrapper');
    
    return fetch('api/get_notifications.php?limit=200')
        .then(resp => {
            if (!resp.ok) {
                if (resp.status === 401) {
                    if (wrapper) wrapper.style.display = 'none';
                    return null;
                }
                throw new Error('Failed to fetch notifications');
            }
            return resp.json();
        })
        .then(data => {
            if (!data) return;

            if (!data.success || !Array.isArray(data.notifications)) {
                if (list) {
                    list.innerHTML = `
                        <div class="notification-empty">
                            <i class="fas fa-inbox"></i>
                            <p>No notifications yet.</p>
                        </div>`;
                }
                
                // Cache the count in localStorage for fast loading next time
                try {
                    localStorage.setItem('notificationCount', '0');
                    localStorage.setItem('notificationCountTimestamp', String(Date.now()));
                } catch (e) {
                    // Ignore localStorage errors
                }
                
                paintCountBadge('notificationCount', 0);
                return;
            }

            const clearedAt = getNotifCleared();
            const notifications = [];
            data.notifications.forEach(row => {
                const msg = getNotificationMessage({
                    status: row.status,
                    payment_method: row.payment_method
                });
                if (msg) {
                    const ts = parseToManilaDate(row.created_at || '').getTime() || 0;
                    if (clearedAt && ts <= clearedAt) return;
                    notifications.push({
                        ...msg,
                        orderId: row.order_id,
                        status: (row.status || '').toLowerCase(),
                        when: row.created_at
                    });
                }
            });

            __notifData = notifications;
            
            // Restore saved page from localStorage, or default to 1
            let savedPage = 1;
            try {
                const saved = localStorage.getItem('notificationPage');
                if (saved) {
                    savedPage = parseInt(saved, 10);
                    if (isNaN(savedPage) || savedPage < 1) {
                        savedPage = 1;
                    }
                }
            } catch (e) {
                // Ignore localStorage errors
            }
            
            // Validate saved page against total pages
            const totalPages = Math.max(1, Math.ceil(notifications.length / __notifPageSize));
            __notifPage = Math.min(savedPage, totalPages);
            
            if (list) {
                renderNotificationPage();
            }

            // Always update badge, even if list doesn't exist yet
            // Get badge fresh each time to ensure we have the latest element
            const badgeEl = document.getElementById('notificationCount');
            if (badgeEl) {
                // Badge shows UNSEEN count: items created after the user last
                // opened the bell. Falls back to API total on first ever load.
                const apiTotal = data.pagination?.total;
                const seenAt = getNotifSeen();
                let totalCount;
                if (clearedAt <= 0 && seenAt <= 0 && typeof apiTotal === 'number' && apiTotal >= 0) {
                    totalCount = apiTotal;
                } else {
                    totalCount = notifications.filter(n => {
                        const ts = parseToManilaDate(n.when || '').getTime() || 0;
                        return ts <= 0 || ts > seenAt;
                    }).length;
                }
                
                // Cache the count in localStorage for fast loading next time
                try {
                    localStorage.setItem('notificationCount', String(totalCount));
                    localStorage.setItem('notificationCountTimestamp', String(Date.now()));
                } catch (e) {
                    // Ignore localStorage errors
                }
                
                paintCountBadge('notificationCount', totalCount);
                
                // Force visibility - ensure badge is shown
                if (totalCount > 0) {
                    badgeEl.style.visibility = 'visible';
                    badgeEl.style.opacity = '1';
                }
            }
        })
        .catch(err => {
            console.error('Notifications error:', err);
        });
}

function paginateNotifications(delta) {
    const totalPages = Math.max(1, Math.ceil((__notifData || []).length / __notifPageSize));
    const nextPage = Math.min(totalPages, Math.max(1, __notifPage + delta));
    if (nextPage === __notifPage) return;
    __notifPage = nextPage;
    // Store current page in localStorage for persistence
    try {
        localStorage.setItem('notificationPage', String(__notifPage));
    } catch (e) {
        // Ignore localStorage errors
    }
    renderNotificationPage();
}

function renderNotificationPage() {
    const list = document.getElementById('notificationList');
    const pageInfo = document.getElementById('notifPageInfo');
    const prevBtn = document.getElementById('notifPrevBtn');
    const nextBtn = document.getElementById('notifNextBtn');
    if (!list) return;

    if (!__notifData || __notifData.length === 0) {
        list.innerHTML = `
            <div class="notification-empty">
                <i class="fas fa-inbox"></i>
                <p>No notifications yet.</p>
            </div>`;
        if (pageInfo) pageInfo.textContent = 'Page 1 of 1';
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        return;
    }

    const totalPages = Math.max(1, Math.ceil(__notifData.length / __notifPageSize));
    __notifPage = Math.min(__notifPage, totalPages);
    // Save current page to localStorage
    try {
        localStorage.setItem('notificationPage', String(__notifPage));
    } catch (e) {
        // Ignore localStorage errors
    }
    const start = (__notifPage - 1) * __notifPageSize;
    const current = __notifData.slice(start, start + __notifPageSize);

    list.innerHTML = current.map(n => `
        <div class="notification-item" onclick="openNotificationOrder(${parseInt(n.orderId, 10) || 0}, '${String(n.status || '').replace(/[^a-z_]/g, '')}')" title="View order">
            <div class="notification-icon" style="background:${n.color};">
                <i class="${n.icon}"></i>
            </div>
            <div class="notification-content">
                <div>
                    <div class="notification-title">${n.title}</div>
                    <p class="notification-desc">${n.desc}</p>
                    <div class="notification-meta">Order #${n.orderId}${formatNotifTime(n.when)}</div>
                </div>
            </div>
        </div>
    `).join('');

    if (pageInfo) pageInfo.textContent = `Page ${__notifPage} of ${totalPages}`;
    if (prevBtn) prevBtn.disabled = __notifPage <= 1;
    if (nextBtn) nextBtn.disabled = __notifPage >= totalPages;
}

function formatNotifTime(ts) {
    if (!ts) return '';
    const parsed = parseToManilaDate(ts);
    const formatted = parsed.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
    return ` • ${formatted}`;
}

function parseToManilaDate(ts) {
    // Normalize to treat timestamp as UTC then render Asia/Manila
    if (typeof ts === 'number') return new Date(ts);
    let iso = ts;
    if (ts && typeof ts === 'string' && !ts.includes('T')) {
        iso = ts.replace(' ', 'T') + 'Z';
    }
    return new Date(iso);
}

// Prevent dropdown from closing when paginating/clearing
document.addEventListener('click', (e) => {
    const target = e.target;
    if (!target) return;
    if (target.id === 'notifPrevBtn' || target.id === 'notifNextBtn' || target.id === 'notifClearBtn' || target.closest('#notifPrevBtn') || target.closest('#notifNextBtn') || target.closest('#notifClearBtn')) {
        e.preventDefault();
        e.stopPropagation();
    }
});

// Load navbar immediately (before DOMContentLoaded to prevent lag)
// This ensures navbar appears instantly without delay
if (document.readyState === 'loading') {
    // If still loading, wait for DOM but load immediately
    document.addEventListener('DOMContentLoaded', function() {
        loadNavbar();
    });
    // Also try to load immediately if container exists
    if (document.getElementById('navbar-container')) {
        loadNavbar();
    }
} else {
    // DOM already loaded, load immediately
    loadNavbar();
}

// Also try loading immediately on script execution
(function() {
    const container = document.getElementById('navbar-container');
    if (container && !container.innerHTML.trim()) {
        loadNavbar();
    }
})();

