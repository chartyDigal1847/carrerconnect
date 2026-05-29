/**
 * CareerConnect WebSocket client (Laravel Echo + Reverb)
 * Falls back to HTTP polling when Reverb is unavailable.
 */
window.CareerConnectRealtime = (function () {
    let echo = null;
    let connected = false;
    let userId = null;

    function getToken() {
        return window.DEORIS_API_TOKEN || null;
    }

    function getCsrf() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute("content") : "";
    }

    function init(config, facultyUserId) {
        userId = facultyUserId;

        if (typeof window.Echo === "undefined" || typeof window.Pusher === "undefined") {
            console.warn("[realtime] Echo/Pusher not loaded — using poll fallback only");
            return false;
        }

        if (!config.key) {
            return false;
        }

        window.Pusher.logToConsole = false;

        try {
        echo = new window.Echo({
            broadcaster: "reverb",
            key: config.key,
            wsHost: config.host,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: config.scheme === "https",
            enabledTransports: ["ws", "wss"],
            authEndpoint: config.authEndpoint,
            auth: {
                headers: {
                    Authorization: "Bearer " + (getToken() || ""),
                    "X-CSRF-TOKEN": getCsrf(),
                    Accept: "application/json",
                },
            },
        });

        connected = true;
        subscribeChannels(config);
        return true;
        } catch (e) {
            console.warn("[realtime] Echo init failed:", e.message);
            return false;
        }
    }

    function subscribeChannels() {
        if (!echo || !userId) return;

        echo.channel("announcements")
            .listen(".announcement.published", (payload) => {
                window.dispatchEvent(new CustomEvent("careerconnect:announcement", { detail: payload }));
            });

        echo.private("notifications." + userId)
            .listen(".notification.created", (payload) => {
                window.dispatchEvent(new CustomEvent("careerconnect:notification", { detail: payload }));
            });
    }

    function subscribeBoard(boardId, onPost) {
        if (!echo || !boardId) return;
        echo.channel("boards." + boardId)
            .listen(".post.created", (payload) => {
                if (typeof onPost === "function") onPost(payload);
                window.dispatchEvent(new CustomEvent("careerconnect:board-post", { detail: payload }));
            });
    }

    function subscribeThread(threadId, onMessage) {
        if (!echo || !threadId) return;
        echo.private("messages.thread." + threadId)
            .listen(".message.sent", (payload) => {
                if (typeof onMessage === "function") onMessage(payload);
            });
    }

    function disconnect() {
        if (echo) {
            echo.disconnect();
            echo = null;
        }
        connected = false;
    }

    return {
        init,
        subscribeBoard,
        subscribeThread,
        disconnect,
        isConnected: () => connected,
    };
})();
