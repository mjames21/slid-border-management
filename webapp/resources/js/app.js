import './bootstrap';
import './admin-dashboard';

const builderQuestionCards = () => Array.from(document.querySelectorAll('.builder-question-card'));

const getBuilderValue = (card, field) => card.querySelector(`[name$="[${field}]"]`)?.value?.trim() ?? '';

const updateBuilderSelectedCount = () => {
    const countLabel = document.querySelector('.selected-count');

    if (!countLabel) {
        return;
    }

    const included = builderQuestionCards().filter((card) => {
        const checkbox = card.querySelector('.include-field');
        const hasContent = ['id', 'label', 'hint', 'options'].some((field) => getBuilderValue(card, field) !== '');

        return checkbox?.checked && hasContent;
    }).length;

    countLabel.textContent = `${included} configured fields`;
};

const scrollToPanel = (panel) => {
    if (!panel) {
        return;
    }

    panel.hidden = false;
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    panel.classList.add('is-highlighted');
    window.setTimeout(() => panel.classList.remove('is-highlighted'), 1200);
};

const renderMobileFlowPreview = () => {
    const panel = document.getElementById('mobile-flow-preview');
    const list = panel?.querySelector('[data-mobile-preview-list]');

    if (!panel || !list) {
        return;
    }

    list.replaceChildren();

    const includedCards = builderQuestionCards().filter((card) => card.querySelector('.include-field')?.checked);

    if (includedCards.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'mobile-flow-empty';
        empty.textContent = 'No questions are currently selected for the mobile form.';
        list.append(empty);
        return;
    }

    includedCards.forEach((card, index) => {
        const label = getBuilderValue(card, 'label') || getBuilderValue(card, 'id') || 'Untitled question';
        const type = getBuilderValue(card, 'type') || 'text';
        const hint = getBuilderValue(card, 'hint');
        const previewCard = document.createElement('article');
        const step = document.createElement('span');
        const title = document.createElement('strong');
        const typeTag = document.createElement('span');

        previewCard.className = 'mobile-preview-card';
        step.className = 'mobile-preview-step';
        step.textContent = type === 'note' ? `Section ${index + 1}` : `Step ${index + 1}`;
        title.textContent = label;
        typeTag.className = 'mobile-preview-type';
        typeTag.textContent = type;

        previewCard.append(step, title);

        if (hint !== '') {
            const help = document.createElement('p');
            help.textContent = hint;
            previewCard.append(help);
        }

        previewCard.append(typeTag);
        list.append(previewCard);
    });
};

document.addEventListener('click', (event) => {
    const selectAllTrigger = event.target.closest('[data-builder-select-all]');
    if (selectAllTrigger) {
        event.preventDefault();
        document.querySelectorAll('.include-field').forEach((field) => {
            field.checked = true;
        });
        updateBuilderSelectedCount();

        if (!document.getElementById('mobile-flow-preview')?.hidden) {
            renderMobileFlowPreview();
        }
        return;
    }

    const clearOptionalTrigger = event.target.closest('[data-builder-clear-optional]');
    if (clearOptionalTrigger) {
        event.preventDefault();
        document.querySelectorAll('.include-field[data-required="0"]').forEach((field) => {
            field.checked = false;
        });
        updateBuilderSelectedCount();

        if (!document.getElementById('mobile-flow-preview')?.hidden) {
            renderMobileFlowPreview();
        }
        return;
    }

    const libraryTrigger = event.target.closest('[data-open-question-library]');
    if (libraryTrigger) {
        event.preventDefault();
        scrollToPanel(document.getElementById('question-library'));
        return;
    }

    const previewTrigger = event.target.closest('[data-open-mobile-preview]');
    if (previewTrigger) {
        event.preventDefault();
        renderMobileFlowPreview();
        scrollToPanel(document.getElementById('mobile-flow-preview'));
        return;
    }

    const closePreviewTrigger = event.target.closest('[data-close-mobile-preview]');
    if (closePreviewTrigger) {
        event.preventDefault();
        const panel = document.getElementById('mobile-flow-preview');
        if (panel) {
            panel.hidden = true;
        }
        return;
    }

    const addQuestionTrigger = event.target.closest('[data-add-builder-question]');
    if (!addQuestionTrigger) {
        return;
    }

    const index = addQuestionTrigger.getAttribute('data-add-builder-question');
    const card = document.getElementById(`builder-question-${index}`);
    const checkbox = card?.querySelector('.include-field');

    if (!card || !checkbox) {
        return;
    }

    checkbox.checked = true;
    card.classList.remove('builder-question-excluded');
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    card.querySelector('input[type="text"], select, textarea')?.focus({ preventScroll: true });

    addQuestionTrigger.closest('.question-library-card')?.remove();

    const availableTag = document.querySelector('#question-library .tag');
    if (availableTag) {
        const remaining = document.querySelectorAll('#question-library .question-library-card').length;
        availableTag.textContent = `${remaining} available`;
    }

    updateBuilderSelectedCount();

    if (!document.getElementById('mobile-flow-preview')?.hidden) {
        renderMobileFlowPreview();
    }
});

document.addEventListener('change', (event) => {
    if (!event.target.closest('.include-field')) {
        return;
    }

    updateBuilderSelectedCount();

    if (!document.getElementById('mobile-flow-preview')?.hidden) {
        renderMobileFlowPreview();
    }
});
