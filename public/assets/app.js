document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.querySelector('#qr_type');
    const variants = document.querySelectorAll('.form-variant');

    function updateVisibility() {
        const selected = typeSelect.value;
        variants.forEach((variant) => {
            if (!(variant instanceof HTMLElement)) {
                return;
            }

            if (variant.dataset.type === selected) {
                variant.removeAttribute('hidden');
            } else {
                variant.setAttribute('hidden', 'hidden');
            }
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', updateVisibility);
        updateVisibility();
    }
});
