(function () {
    let lastUnreadCount = null;
    let knownUnreadIds = new Set();
    let lastLatestNotificationKey = null;
    let isInitialized = false;
    let isPolling = false;
    let pollTimeoutId = null;

    function showBrowserNotification(unreadCount) {
        if (!("Notification" in window)) {
            return;
        }

        if (document.visibilityState === "visible") {
            return;
        }

        if (Notification.permission === "granted") {
            new Notification("New CRM Notification", {
                body: "You have " + unreadCount + " unread notification(s).",
                tag: "crm-nova-notification",
            });
            return;
        }

        if (Notification.permission === "default") {
            Notification.requestPermission().catch(function () {
                // Ignore permission request errors.
            });
        }
    }

    function extractNotificationItems(payload) {
        const candidates = [
            payload.notifications,
            payload.notifications && payload.notifications.data,
            payload.resources,
            payload.data,
        ];
        for (const candidate of candidates) {
            if (Array.isArray(candidate)) {
                return candidate;
            }
        }

        return [];
    }

    function extractLatestNotificationKey(items) {
        if (!Array.isArray(items) || items.length === 0) {
            return null;
        }

        const latest = items[0];

        if (!latest || typeof latest !== "object") {
            return null;
        }

        if (latest.id !== undefined && latest.id !== null) {
            return String(latest.id);
        }

        if (latest.created_at !== undefined && latest.created_at !== null) {
            return String(latest.created_at);
        }

        return null;
    }

    function extractUnreadIds(items) {
        const unreadIds = new Set();

        if (!Array.isArray(items)) {
            return unreadIds;
        }

        for (const item of items) {
            if (!item || typeof item !== "object") {
                continue;
            }

            const readAt = item.read_at ?? item.readAt ?? null;
            const isRead = item.read ?? item.is_read;
            const id = item.id ?? item.created_at ?? item.createdAt;

            if (id === undefined || id === null) {
                continue;
            }

            if (isRead === true) {
                continue;
            }

            if (readAt !== null && readAt !== undefined && readAt !== "") {
                continue;
            }

            unreadIds.add(String(id));
        }

        return unreadIds;
    }

    function hasNewUnread(previousUnreadIds, currentUnreadIds) {
        for (const unreadId of currentUnreadIds) {
            if (!previousUnreadIds.has(unreadId)) {
                return true;
            }
        }

        return false;
    }

    function scheduleNextPoll(delayMs) {
        if (pollTimeoutId !== null) {
            clearTimeout(pollTimeoutId);
        }

        pollTimeoutId = window.setTimeout(function () {
            void pollNotifications();
        }, delayMs);
    }

    async function pollNotifications() {
        if (isPolling) {
            return;
        }

        isPolling = true;

        try {
            const response = await fetch("/nova-api/notifications?_=" + Date.now(), {
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const unreadCount = Number(payload.unread || 0);
            const items = extractNotificationItems(payload);
            const currentUnreadIds = extractUnreadIds(items);
            const latestNotificationKey = extractLatestNotificationKey(items);

            if (!isInitialized) {
                lastUnreadCount = unreadCount;
                knownUnreadIds = currentUnreadIds;
                lastLatestNotificationKey = latestNotificationKey;
                isInitialized = true;
                return;
            }

            const hasUnreadIncrease = unreadCount > lastUnreadCount;
            const hasNewUnreadNotification = hasNewUnread(knownUnreadIds, currentUnreadIds);
            const hasNewLatestNotification = latestNotificationKey !== null &&
                latestNotificationKey !== lastLatestNotificationKey;

            if (hasUnreadIncrease || hasNewUnreadNotification || hasNewLatestNotification) {
                showBrowserNotification(unreadCount);
            }

            lastUnreadCount = unreadCount;
            knownUnreadIds = currentUnreadIds;
            lastLatestNotificationKey = latestNotificationKey;
        } catch (error) {
            // Keep polling even if one request fails.
        } finally {
            isPolling = false;
            scheduleNextPoll(document.visibilityState === "visible" ? 1000 : 2500);
        }
    }

    document.addEventListener("visibilitychange", function () {
        if (document.visibilityState === "visible") {
            void pollNotifications();
        }
    });
    window.addEventListener("focus", function () {
        void pollNotifications();
    });
    window.addEventListener("pageshow", function () {
        void pollNotifications();
    });

    void pollNotifications();
})();
