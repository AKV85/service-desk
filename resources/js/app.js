document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('[data-ai-form]');
    const result = document.querySelector('[data-ai-result]');

    if (!forms.length || !result) {
        return;
    }

    forms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const button = form.querySelector('button[type="submit"]');
            const token = form.querySelector('input[name="_token"]');

            if (!button || !token) {
                return;
            }

            const originalText = button.textContent;

            disableForms(forms);

            button.textContent = 'Working...';

            result.innerHTML = '';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token.value,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(
                        payload.message ??
                        'AI assistance is currently unavailable.'
                    );
                }

                renderAiResult(result, payload);
            } catch (error) {
                renderAiError(result, error);
            } finally {
                enableForms(forms);
                button.textContent = originalText;
            }
        });
    });
});

function disableForms(forms) {
    forms.forEach((form) => {
        const button = form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = true;
        }
    });
}

function enableForms(forms) {
    forms.forEach((form) => {
        const button = form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = false;
        }
    });
}

function renderAiResult(container, payload) {
    if (payload.type === 'analysis') {
        renderAnalysis(container, payload.data);

        return;
    }

    if (payload.type === 'draft') {
        renderDraft(container, payload.data);
    }
}

function renderAnalysis(container, data) {
    container.innerHTML = `
        <div class="ai-result">
            <h4>AI Analysis</h4>

            <p>
                <strong>Summary:</strong>
                ${escapeHtml(data.summary)}
            </p>

            ${data.likely_cause
            ? `
                        <p>
                            <strong>Likely cause:</strong>
                            ${escapeHtml(data.likely_cause)}
                        </p>
                    `
            : ''
        }

            ${renderList('Suggested actions', data.suggested_actions)}
            ${renderList('Observations', data.observations)}
            ${renderList('Development context', data.development_context)}

            <small>
                Model: ${escapeHtml(data.model)}
            </small>
        </div>
    `;
}

function renderDraft(container, data) {
    container.innerHTML = `
        <div class="ai-result">
            <h4>
                AI ${escapeHtml(capitalize(data.type))} Draft
            </h4>

            <textarea rows="8" readonly>${escapeHtml(data.content)}</textarea>

            <small>
                Model: ${escapeHtml(data.model)}
            </small>
        </div>
    `;
}

function renderList(title, items) {
    if (!Array.isArray(items) || items.length === 0) {
        return '';
    }

    return `
        <div>
            <strong>${escapeHtml(title)}:</strong>

            <ul>
                ${items
            .map((item) => `<li>${escapeHtml(item)}</li>`)
            .join('')}
            </ul>
        </div>
    `;
}

function renderAiError(container, error) {
    const message =
        error instanceof Error
            ? error.message
            : 'AI assistance is currently unavailable.';

    container.innerHTML = `
        <p class="field-error">
            ${escapeHtml(message)}
        </p>
    `;
}

function capitalize(value) {
    if (!value) {
        return '';
    }

    return value.charAt(0).toUpperCase() + value.slice(1);
}

function escapeHtml(value) {
    const element = document.createElement('div');

    element.textContent = value ?? '';

    return element.innerHTML;
}