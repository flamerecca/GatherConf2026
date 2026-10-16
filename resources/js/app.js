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

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-link]');

    if (!button) {
        return;
    }

    const original = button.textContent;

    try {
        await navigator.clipboard.writeText(button.dataset.copyLink);
        button.textContent = button.dataset.copiedLabel;
        document.querySelector('[data-copy-status]').textContent = button.dataset.copiedLabel;
        setTimeout(() => (button.textContent = original), 2000);
    } catch (error) {
        window.prompt('', button.dataset.copyLink);
    }
});

const quiz = document.querySelector('[data-quiz]');

if (quiz) {
    const steps = [...quiz.querySelectorAll('[data-quiz-step]')];
    const previous = quiz.querySelector('[data-quiz-previous]');
    const next = quiz.querySelector('[data-quiz-next]');
    const submit = quiz.querySelector('[data-quiz-submit]');
    const progress = quiz.querySelector('progress');
    const status = quiz.querySelector('[data-quiz-status]');
    let current = Math.max(0, steps.findIndex((step) => !step.querySelector('input:checked')));

    const answered = (step) => Boolean(step.querySelector('input:checked'));

    const render = () => {
        steps.forEach((step, index) => (step.hidden = index !== current));
        previous.hidden = current === 0;
        next.hidden = current === steps.length - 1;
        next.disabled = !answered(steps[current]);
        submit.hidden = current !== steps.length - 1;
        submit.disabled = !steps.every(answered);
        progress.value = current + 1;
        status.textContent = steps[current].dataset.questionLabel;
    };

    quiz.querySelector('[data-quiz-progress]').classList.remove('hidden');
    previous.addEventListener('click', () => {
        current -= 1;
        render();
    });
    next.addEventListener('click', () => {
        current += 1;
        render();
        steps[current].querySelector('input').focus();
    });
    quiz.addEventListener('change', render);
    render();
}
