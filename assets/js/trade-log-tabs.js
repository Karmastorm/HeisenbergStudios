/**
 * Trade Log tab bar (Accounts & Journal / Metrics).
 * The server picks the default tab via data-default-tab; this remembers
 * whichever tab was last clicked in localStorage so a form postback from
 * inside a tab (add trade, delete account, paginate, ...) lands back on
 * that same tab instead of snapping to the server's default.
 */
document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelector('.trade-tabs');
    if (!tabs) return;

    const buttons = tabs.querySelectorAll('.trade-tab-btn');
    const panels = tabs.querySelectorAll('.trade-tab-panel');
    const storageKey = 'trade-log-active-tab';

    function activate(name) {
        buttons.forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.tab === name);
        });
        panels.forEach(function (panel) {
            panel.hidden = panel.dataset.tabPanel !== name;
        });
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            localStorage.setItem(storageKey, btn.dataset.tab);
            activate(btn.dataset.tab);
        });
    });

    const saved = localStorage.getItem(storageKey);
    activate(saved === 'journal' || saved === 'metrics' ? saved : tabs.dataset.defaultTab);
});
