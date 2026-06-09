// Auto-refresh of the admin: subscribes to the Mercure hub (SSE) and reloads
// the page when a notification changes (phone quick-reply, worker send, day
// planning, cancel from another tab). The hub URL and topic come from the data
// attributes set by DashboardController::configureAssets().
(() => {
    const script = document.currentScript;
    if (!script || !window.EventSource) {
        return;
    }

    const hubUrl = new URL(script.dataset.hub);
    hubUrl.searchParams.append('topic', script.dataset.topic);

    let reloadPending = false;

    const reload = () => {
        // Never yank a form being filled (Settings / NotificationType edit).
        if (document.querySelector('form.ea-new-form, form.ea-edit-form')) {
            return;
        }
        // Don't lose an in-progress batch selection; the next update will retry.
        if (document.querySelector('input.form-batch-checkbox:checked')) {
            return;
        }
        if (document.hidden) {
            reloadPending = true; // refresh once the tab becomes visible again

            return;
        }
        window.location.reload();
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && reloadPending) {
            reloadPending = false;
            reload();
        }
    });

    // EventSource reconnects by itself after a hub restart (deploy), no code needed.
    const source = new EventSource(hubUrl);
    let debounce;
    source.onmessage = () => {
        // A burst (batch cancel, day planning) lands as a single publish per
        // flush, but debounce anyway in case several flushes land together.
        clearTimeout(debounce);
        debounce = setTimeout(reload, 500);
    };
})();
