import $ from 'jquery';

const product_list = $('#productList');

if (product_list.length) {
    const search_form = product_list.find('.product-list__filters');
    const product_results = $('#productResults');
    const error_box = $('#productErrors');
    const status_box = $('#productStatus');
    let current_url = new URL(window.location.href);
    let list_request = null;
    let request_number = 0;
    let deleting = false;

    function enableDeleteButtons() {
        product_list.find('.product-list__delete').prop('disabled', deleting);
    }

    function showError(request) {
        let messages = ['通信に失敗しました。もう一度お試しください。'];
        if (request.status === 401 || request.status === 419) {
            messages = ['セッションの有効期限が切れました。画面を再表示してログインしてください。'];
        } else if (request.responseJSON?.errors) {
            messages = Object.values(request.responseJSON.errors).flat();
        } else if (request.responseJSON?.message) {
            messages = [request.responseJSON.message];
        }
        const error_list = $('<ul>');
        messages.forEach((message) => error_list.append($('<li>').text(message)));
        error_box.empty().append(error_list).prop('hidden', false);
    }

    function loadProducts(url, keep_status = false) {
        const active_number = ++request_number;
        if (list_request) {
            list_request.abort();
        }
        error_box.prop('hidden', true);
        if (!keep_status) {
            status_box.prop('hidden', true);
        }
        product_results.attr('aria-busy', 'true');
        list_request = $.ajax({
            url: url.toString(),
            method: 'GET',
            dataType: 'json',
            headers: { Accept: 'application/json' },
        });
        list_request.done((response) => {
            if (active_number !== request_number) {
                return;
            }
            product_results.html(response.html);
            current_url = new URL(response.url, window.location.href);
            search_form.find('[name="sort_by"]').val(response.sort_by);
            search_form.find('[name="sort_order"]').val(response.sort_order);
            window.history.replaceState(null, '', current_url.pathname + current_url.search);
            enableDeleteButtons();
        });
        list_request.fail((request, status) => {
            if (status !== 'abort' && active_number === request_number) {
                showError(request);
            }
        });
        list_request.always(() => {
            if (active_number === request_number) {
                product_results.attr('aria-busy', 'false');
                list_request = null;
            }
        });
    }

    product_list.on('submit', '.product-list__filters', function (event) {
        event.preventDefault();
        const search_url = new URL(this.action);
        search_url.search = search_form.serialize();
        loadProducts(search_url);
    });

    product_list.on('click', '.product-list__sort, .product-list__pagination a', function (event) {
        event.preventDefault();
        loadProducts(new URL(this.href));
    });

    product_list.on('submit', '.product-list__delete-form', function (event) {
        event.preventDefault();
        if (deleting || !window.confirm('「' + this.dataset.productName + '」を削除しますか？')) {
            return;
        }
        deleting = true;
        enableDeleteButtons();
        error_box.prop('hidden', true);
        status_box.prop('hidden', true);
        const delete_form = $(this);
        $.ajax({
            url: this.action,
            method: 'DELETE',
            data: delete_form.serialize(),
            dataType: 'json',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        }).done((response) => {
            delete_form.closest('tr').remove();
            status_box.text(response.message).prop('hidden', false);
            loadProducts(current_url, true);
        }).fail((request) => {
            showError(request);
        }).always(() => {
            deleting = false;
            enableDeleteButtons();
        });
    });

    enableDeleteButtons();
}
