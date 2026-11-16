(() => {
    'use strict';
    const form = document.querySelector('[data-mcp-consent]');
    if (!form) return;
    let submitted = false;
    form.addEventListener('submit', (event) => {
        if (submitted) {
            event.preventDefault();
            return;
        }
        const button = event.submitter;
        // Native keyboard submission uses the default submit button as submitter.
        if (!button || button.name !== 'decision' || !['allow', 'deny'].includes(button.value)) {
            event.preventDefault();
            return;
        }
        const decision = document.createElement('input');
        decision.type = 'hidden';
        decision.name = 'decision';
        decision.value = button.value;
        form.appendChild(decision);
        submitted = true;
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach((submit) => {
            submit.disabled = true;
        });
        button.textContent = decision.value === 'allow' ? 'Autorizando…' : 'Recusando…';
    });
})();
