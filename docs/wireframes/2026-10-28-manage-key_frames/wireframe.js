// Local preview only. No API calls, authentication, or persistence.
document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (form.dataset.next) {
            window.location.href = form.dataset.next;
            return;
        }
        const notice = document.getElementById('preview-notice');
        if (notice) {
            notice.hidden = false;
            notice.textContent = 'Wireframe 示意：已預覽操作，資料不會儲存。可使用下方「相關狀態」檢視結果畫面。';
            notice.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    });
});
document.querySelectorAll('[data-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.toggle);
        target.hidden = !target.hidden;
        button.setAttribute('aria-expanded', String(!target.hidden));
    });
});
document.querySelector('[data-sidebar]')?.addEventListener('click', () => {
    const frame = document.querySelector('.manage-frame');
    frame.classList.toggle('collapsed');
    document.querySelector('[data-sidebar]').setAttribute('aria-expanded', String(!frame.classList.contains('collapsed')));
});
document.querySelector('[data-search]')?.addEventListener('input', (event) => {
    const query = event.target.value.trim().toLocaleLowerCase();
    document.querySelectorAll('.frame-card').forEach((card) => {
        card.hidden = !card.textContent.toLocaleLowerCase().includes(query);
    });
    document.querySelectorAll('.catalog-group').forEach((group) => {
        group.hidden = !group.querySelector('.frame-card:not([hidden])');
    });
});
const dialog = document.querySelector('[role="dialog"]');
if (dialog) {
    document.querySelector('[data-underlay]')?.setAttribute('inert', '');
    const controls = [...dialog.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled)')];
    (controls[0] ?? dialog).focus({ preventScroll: true });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') window.location.href = dialog.dataset.close;
        if (event.key !== 'Tab' || !controls.length) return;
        if (event.shiftKey && document.activeElement === controls[0]) {
            event.preventDefault(); controls.at(-1).focus();
        } else if (!event.shiftKey && document.activeElement === controls.at(-1)) {
            event.preventDefault(); controls[0].focus();
        }
    });
}
