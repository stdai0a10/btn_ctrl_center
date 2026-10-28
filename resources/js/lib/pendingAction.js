const key = 'button-action-pending';
export function clearPendingAction() {
    try { window.localStorage.removeItem(key); } catch { /* Storage may be unavailable. */ }
}
export function readPendingAction(userId) {
    try {
        const marker = JSON.parse(window.localStorage.getItem(key));
        if (!marker) return null;
        if (!userId || marker.user_public_id !== userId || typeof marker.request_id !== 'string'
            || !Number.isFinite(marker.created_at) || marker.created_at > Date.now()
            || Date.now() - marker.created_at >= 24 * 60 * 60 * 1000) {
            clearPendingAction();
            return null;
        }
        return marker;
    } catch { clearPendingAction(); return null; }
}
export function savePendingAction(requestId, userId) {
    const marker = { request_id: requestId, user_public_id: userId, created_at: Date.now() };
    try { window.localStorage.setItem(key, JSON.stringify(marker)); } catch { /* Keep the in-memory marker. */ }
    return marker;
}
