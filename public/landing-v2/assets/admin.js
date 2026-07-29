function refreshIconSelect(select) {
    const preview = select.closest('.icon-select')?.querySelector('.icon-preview .material-symbols-outlined');
    if (preview) {
        preview.textContent = select.value || 'star';
    }
}

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-icon-select]')) {
        refreshIconSelect(event.target);
    }
});

document.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-remove]');
    if (removeButton) {
        removeButton.closest('.repeat-item')?.remove();
        return;
    }

    const addButton = event.target.closest('[data-add]');
    if (!addButton) {
        return;
    }

    const template = document.getElementById(`template-${addButton.dataset.add}`);
    const repeater = addButton.previousElementSibling;

    if (!template || !repeater) {
        return;
    }

    const index = Date.now().toString();
    const html = template.innerHTML.replaceAll('__i__', index);
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    const item = wrapper.firstElementChild;
    repeater.appendChild(item);
    item.querySelectorAll('[data-icon-select]').forEach(refreshIconSelect);
});

document.querySelectorAll('[data-icon-select]').forEach(refreshIconSelect);
