const delete_forms = document.querySelectorAll('.product-list__delete-form');

delete_forms.forEach((delete_form) => {
    delete_form.addEventListener('submit', (event) => {
        const confirm_message = '「' + delete_form.dataset.productName + '」を削除しますか？';

        if (!window.confirm(confirm_message)) {
            event.preventDefault();
        }
    });

    const delete_button = delete_form.querySelector('button[type="submit"]');
    if (delete_button) {
        delete_button.disabled = false;
    }
});
