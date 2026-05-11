(function () {
    'use strict';

    const AUTOCOMPLETE_DISABLED = 'off';
    const PASSWORD_AUTOCOMPLETE = 'new-password';
    const SKIP_FIELD_TYPES = new Set(['hidden', 'file', 'checkbox', 'radio', 'button', 'submit', 'reset']);

    function isOptedOut(element) {
        return !!element.closest('[data-allow-autocomplete="true"]');
    }

    function applyToForm(form) {
        if (isOptedOut(form)) {
            return;
        }

        form.setAttribute('autocomplete', AUTOCOMPLETE_DISABLED);
    }

    function applyToField(field) {
        if (isOptedOut(field)) {
            return;
        }

        const tagName = field.tagName.toLowerCase();
        const type = (field.getAttribute('type') || '').toLowerCase();

        if (tagName === 'input' && SKIP_FIELD_TYPES.has(type)) {
            return;
        }

        field.setAttribute('autocomplete', type === 'password' ? PASSWORD_AUTOCOMPLETE : AUTOCOMPLETE_DISABLED);

        if (tagName === 'input' || tagName === 'textarea') {
            field.setAttribute('autocorrect', AUTOCOMPLETE_DISABLED);
            field.setAttribute('autocapitalize', AUTOCOMPLETE_DISABLED);
            field.setAttribute('spellcheck', 'false');
        }
    }

    function applyAutocompleteGuard(root) {
        const scope = root && root.querySelectorAll ? root : document;

        if (scope.matches && scope.matches('form')) {
            applyToForm(scope);
        }

        if (scope.matches && scope.matches('input, textarea, select')) {
            applyToField(scope);
        }

        scope.querySelectorAll('form').forEach(applyToForm);
        scope.querySelectorAll('input, textarea, select').forEach(applyToField);
    }

    function observeDynamicFields() {
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        applyAutocompleteGuard(node);
                    }
                });
            });
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
    }

    window.AutocompleteGuard = {
        apply: applyAutocompleteGuard
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyAutocompleteGuard(document);
            observeDynamicFields();
        });
    } else {
        applyAutocompleteGuard(document);
        observeDynamicFields();
    }
})();
