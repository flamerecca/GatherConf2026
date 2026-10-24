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

const awaitingBunny = document.querySelector('[data-awaiting-unique-bunny]');

if (awaitingBunny) {
    let attempts = 0;

    const timer = setInterval(async () => {
        attempts += 1;

        try {
            const response = await fetch(window.location.href, { headers: { Accept: 'text/html' } });
            const next = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('[data-result-frame]');

            if (next && !next.hasAttribute('data-awaiting-unique-bunny')) {
                document.querySelector('[data-result-frame]').replaceWith(next);
                clearInterval(timer);
            }
        } catch (error) {
            // 網路暫時失敗時，下一輪再試
        }

        if (attempts >= 15) {
            clearInterval(timer);
        }
    }, 8000);
}

const quiz = document.querySelector('[data-quiz]');

if (quiz) {
    const steps = [...quiz.querySelectorAll('[data-quiz-step]')];
    const previous = quiz.querySelector('[data-quiz-previous]');
    const next = quiz.querySelector('[data-quiz-next]');
    const submit = quiz.querySelector('[data-quiz-submit]');
    const progress = quiz.querySelector('progress');
    const status = quiz.querySelector('[data-quiz-status]');
    let current = 0;

    const answered = (step) => Boolean(step.querySelector('input:checked'));

    // 切換題目後把焦點交給該題的選項，避免按鈕被隱藏時鍵盤焦點掉回頁首。
    const focusCurrent = () => (steps[current].querySelector('input:checked') ?? steps[current].querySelector('input')).focus();

    // 所有題目疊在同一格，容器高度固定為最高的那一題，換題時版面不會跳動。
    quiz.querySelector('[data-quiz-steps]').classList.add('grid');
    steps.forEach((step) => step.classList.add('col-start-1', 'row-start-1'));

    const render = () => {
        steps.forEach((step, index) => {
            const active = index === current;

            step.classList.toggle('invisible', !active);
            step.inert = !active;
        });
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
        focusCurrent();
    });
    next.addEventListener('click', () => {
        current += 1;
        render();
        focusCurrent();
    });
    quiz.addEventListener('change', render);

    // 在選項上按 Enter 直接進下一題，最後一題則送出。
    quiz.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.target.type !== 'radio') {
            return;
        }

        event.preventDefault();

        if (current < steps.length - 1) {
            next.click();
        } else if (!submit.disabled) {
            quiz.requestSubmit(submit);
        }
    });

    // 送出後鎖住按鈕，避免連按 Enter 重複送出，從上一頁回來時再恢復。
    quiz.addEventListener('submit', () => (submit.disabled = true));
    window.addEventListener('pageshow', render);
    render();
}
