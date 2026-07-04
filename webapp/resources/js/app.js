import './bootstrap';
import './admin-dashboard';

const builderQuestionCards = () => Array.from(document.querySelectorAll('.builder-question-card'));

const getBuilderValue = (card, field) => card.querySelector(`[name$="[${field}]"]`)?.value?.trim() ?? '';

const isBuilderTabOpen = (tabName) => {
    const panel = document.querySelector(`[data-builder-tab-panel="${tabName}"]`);

    return panel ? !panel.hidden : false;
};

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

const updateQuestionLibraryCount = () => {
    const availableTag = document.querySelector('[data-question-library-count]');

    if (!availableTag) {
        return;
    }

    const remaining = document.querySelectorAll('#question-library .question-library-card').length;
    availableTag.textContent = `${remaining} available`;
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

const showBuilderTab = (tabName) => {
    const panels = document.querySelectorAll('[data-builder-tab-panel]');

    if (panels.length === 0) {
        return;
    }

    document.querySelectorAll('[data-builder-tab]').forEach((tab) => {
        const isActive = tab.getAttribute('data-builder-tab') === tabName;
        tab.classList.toggle('is-active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    panels.forEach((panel) => {
        panel.hidden = panel.getAttribute('data-builder-tab-panel') !== tabName;
    });

    if (tabName === 'preview') {
        renderMobileFlowPreview();
    }
};

document.addEventListener('click', (event) => {
    const tabTrigger = event.target.closest('[data-builder-tab]');
    if (tabTrigger) {
        event.preventDefault();
        showBuilderTab(tabTrigger.getAttribute('data-builder-tab'));
        return;
    }

    const selectAllTrigger = event.target.closest('[data-builder-select-all]');
    if (selectAllTrigger) {
        event.preventDefault();
        document.querySelectorAll('.include-field').forEach((field) => {
            field.checked = true;
        });
        updateBuilderSelectedCount();

        if (isBuilderTabOpen('preview')) {
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

        if (isBuilderTabOpen('preview')) {
            renderMobileFlowPreview();
        }
        return;
    }

    const libraryTrigger = event.target.closest('[data-open-question-library]');
    if (libraryTrigger) {
        event.preventDefault();
        showBuilderTab('library');
        return;
    }

    const previewTrigger = event.target.closest('[data-open-mobile-preview]');
    if (previewTrigger) {
        event.preventDefault();
        showBuilderTab('preview');
        return;
    }

    const closePreviewTrigger = event.target.closest('[data-close-mobile-preview]');
    if (closePreviewTrigger) {
        event.preventDefault();
        showBuilderTab('form');
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
    showBuilderTab('form');
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    card.querySelector('input[type="text"], select, textarea')?.focus({ preventScroll: true });

    addQuestionTrigger.closest('.question-library-card')?.remove();
    updateQuestionLibraryCount();

    updateBuilderSelectedCount();

    if (isBuilderTabOpen('preview')) {
        renderMobileFlowPreview();
    }
});

document.addEventListener('change', (event) => {
    if (!event.target.closest('.include-field')) {
        return;
    }

    updateBuilderSelectedCount();

    if (isBuilderTabOpen('preview')) {
        renderMobileFlowPreview();
    }
});
