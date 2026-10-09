import.meta.glob(['../images/**'], { eager: true, query: '?url', import: 'default' });

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-theme-toggle]')) {
        return;
    }

    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;

    try {
        localStorage.setItem('theme', next);
    } catch (error) {
        // 隱私模式或封鎖儲存時，僅本次瀏覽生效
    }
});
