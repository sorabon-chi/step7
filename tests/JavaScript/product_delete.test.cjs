const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const jquery = require('jquery');
const { JSDOM } = require('jsdom');

const product_script = fs.readFileSync(path.join(__dirname, '../../resources/js/products.js'), 'utf8')
    .replace(/^import \$ from 'jquery';\r?\n/, '');
const base_url = 'http://localhost:8888/step7/public/products';

function resultsHtml(product_id = 1, product_name = 'コーラ') {
    return `
        <a class="product-list__sort" href="${base_url}?sort_by=price&amp;sort_order=asc&amp;stock_max=0">価格</a>
        <table><tbody><tr data-product-id="${product_id}">
            <td>${product_name}</td>
            <td><form method="POST" action="${base_url}/${product_id}"
                class="product-list__delete-form" data-product-name="${product_name}">
                <input type="hidden" name="_token" value="test-csrf-token">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="product-list__delete" disabled>削除</button>
            </form></td>
        </tr></tbody></table>
        <div class="product-list__pagination">
            <a href="${base_url}?stock_max=0&amp;sort_by=price&amp;sort_order=asc&amp;page=2">次へ</a>
        </div>
    `;
}

function initializePage(options = {}) {
    const dom = new JSDOM(`<!doctype html><html><head>
        <meta name="csrf-token" content="test-csrf-token"></head><body>
        <div id="productList">
            <div id="productErrors" hidden></div><div id="productStatus" hidden></div>
            <form action="${base_url}" class="product-list__filters">
                <input name="product_name" value="コーラ"><input name="company_id" value="2">
                <input name="price_min" value=""><input name="price_max" value="">
                <input name="stock_min" value="0"><input name="stock_max" value="0">
                <input type="hidden" name="sort_by" value="id">
                <input type="hidden" name="sort_order" value="desc">
                <button type="submit">検索</button>
            </form>
            <div id="productResults" aria-busy="false">${resultsHtml()}</div>
        </div></body></html>`, { url: options.url || base_url, runScripts: 'outside-only' });
    const { window } = dom;
    const $ = jquery(window);
    const requests = [];
    const confirmations = [];
    window.$ = $;
    window.confirm = (message) => {
        confirmations.push(message);
        return options.confirmed !== false;
    };
    $.ajax = (settings) => {
        const deferred = $.Deferred();
        const request = deferred.promise({
            abort() {
                this.aborted = true;
                deferred.reject({ status: 0 }, 'abort');
            },
        });
        requests.push({ settings, deferred, request });
        return request;
    };
    if (options.without_list) {
        $('#productList').remove();
    }
    window.eval(product_script);

    return { dom, window, $, requests, confirmations };
}

function search(page) {
    const event = page.$.Event('submit');
    page.$('.product-list__filters').trigger(event);
    assert.equal(event.isDefaultPrevented(), true);
}

function submitDelete(page) {
    const event = page.$.Event('submit');
    page.$('.product-list__delete-form').trigger(event);
    assert.equal(event.isDefaultPrevented(), true);
}

function resolveList(entry, html = resultsHtml(2, 'お茶'), query = 'stock_max=0&sort_by=id&sort_order=desc') {
    const params = new URLSearchParams(query);
    entry.deferred.resolve({
        html,
        url: base_url + '?' + query,
        sort_by: params.get('sort_by') || 'id',
        sort_order: params.get('sort_order') || 'desc',
        page: Number(params.get('page') || 1),
        total: 1,
    });
}

test('search uses Ajax with all filters including zero and resets the page', () => {
    const page = initializePage({ url: base_url + '?page=2' });
    search(page);
    assert.equal(page.requests.length, 1);
    const settings = page.requests[0].settings;
    const url = new URL(settings.url);
    assert.equal(settings.method, 'GET');
    assert.equal(settings.dataType, 'json');
    assert.equal(settings.headers.Accept, 'application/json');
    assert.equal(url.searchParams.get('product_name'), 'コーラ');
    assert.equal(url.searchParams.get('company_id'), '2');
    assert.equal(url.searchParams.get('stock_min'), '0');
    assert.equal(url.searchParams.get('stock_max'), '0');
    assert.equal(url.searchParams.get('sort_order'), 'desc');
    assert.equal(url.searchParams.has('page'), false);
    assert.equal(page.$('#productResults').attr('aria-busy'), 'true');
    page.dom.window.close();
});

test('a successful search replaces results and pagination and enables new delete buttons', () => {
    const page = initializePage();
    search(page);
    resolveList(page.requests[0]);
    assert.equal(page.$('[data-product-id="1"]').length, 0);
    assert.equal(page.$('[data-product-id="2"]').length, 1);
    assert.equal(page.$('.product-list__pagination a').length, 1);
    assert.equal(page.$('.product-list__delete').prop('disabled'), false);
    assert.equal(page.$('#productResults').attr('aria-busy'), 'false');
    assert.equal(page.window.location.search, '?stock_max=0&sort_by=id&sort_order=desc');
    page.dom.window.close();
});

test('header sorting and pagination use Ajax and keep the applied conditions', () => {
    const page = initializePage();
    page.$('.product-list__sort').trigger('click');
    let url = new URL(page.requests[0].settings.url);
    assert.equal(url.searchParams.get('sort_by'), 'price');
    assert.equal(url.searchParams.get('sort_order'), 'asc');
    assert.equal(url.searchParams.get('stock_max'), '0');
    resolveList(page.requests[0], resultsHtml(), 'stock_max=0&sort_by=price&sort_order=asc');
    assert.equal(page.$('[name="sort_by"]').val(), 'price');
    assert.equal(page.$('[name="sort_order"]').val(), 'asc');
    page.$('.product-list__pagination a').trigger('click');
    url = new URL(page.requests[1].settings.url);
    assert.equal(url.searchParams.get('page'), '2');
    assert.equal(url.searchParams.get('stock_max'), '0');
    assert.equal(url.searchParams.get('sort_order'), 'asc');
    page.dom.window.close();
});

test('a newer search aborts the old request and prevents stale results', () => {
    const page = initializePage();
    search(page);
    page.$('[name="product_name"]').val('お茶');
    search(page);
    assert.equal(page.requests[0].request.aborted, true);
    resolveList(page.requests[1], resultsHtml(3, '最新商品'));
    resolveList(page.requests[0], resultsHtml(9, '古い商品'));
    assert.equal(page.$('[data-product-id="3"]').length, 1);
    assert.equal(page.$('[data-product-id="9"]').length, 0);
    assert.equal(page.$('#productErrors').prop('hidden'), true);
    page.dom.window.close();
});

test('cancel leaves the product and sends no request', () => {
    const page = initializePage({ confirmed: false });
    submitDelete(page);
    assert.equal(page.requests.length, 0);
    assert.deepEqual(page.confirmations, ['「コーラ」を削除しますか？']);
    assert.equal(page.$('[data-product-id="1"]').length, 1);
    assert.equal(page.$('.product-list__delete').prop('disabled'), false);
    page.dom.window.close();
});

test('confirmed deletion sends CSRF and removes the row only after server success', () => {
    const page = initializePage({ url: base_url + '?stock_max=0&sort_by=price&sort_order=asc&page=2' });
    submitDelete(page);
    assert.equal(page.requests.length, 1);
    const deletion = page.requests[0];
    assert.equal(deletion.settings.method, 'DELETE');
    assert.equal(deletion.settings.url, base_url + '/1');
    assert.equal(deletion.settings.headers['X-CSRF-TOKEN'], 'test-csrf-token');
    assert.equal(new URLSearchParams(deletion.settings.data).get('_token'), 'test-csrf-token');
    assert.equal(page.$('[data-product-id="1"]').length, 1);
    assert.equal(page.$('.product-list__delete').prop('disabled'), true);
    deletion.deferred.resolve({ message: '商品を削除しました。', product_id: 1 });
    assert.equal(page.$('[data-product-id="1"]').length, 0);
    assert.equal(page.requests.length, 2);
    const refresh_url = new URL(page.requests[1].settings.url);
    assert.equal(refresh_url.searchParams.get('page'), '2');
    assert.equal(refresh_url.searchParams.get('stock_max'), '0');
    assert.equal(refresh_url.searchParams.get('sort_by'), 'price');
    resolveList(page.requests[1], resultsHtml(2), 'stock_max=0&sort_by=price&sort_order=asc&page=1');
    assert.equal(page.$('#productStatus').text(), '商品を削除しました。');
    assert.equal(page.$('.product-list__delete').prop('disabled'), false);
    assert.equal(new URL(page.window.location.href).searchParams.get('page'), '1');
    page.dom.window.close();
});

test('a sales-reference failure keeps the row and displays the reason', () => {
    const page = initializePage();
    submitDelete(page);
    page.requests[0].deferred.reject({
        status: 409, responseJSON: { message: '売上データがある商品は削除できません。' },
    });
    assert.equal(page.requests.length, 1);
    assert.equal(page.$('[data-product-id="1"]').length, 1);
    assert.equal(page.$('#productErrors').text(), '売上データがある商品は削除できません。');
    assert.equal(page.$('#productErrors').prop('hidden'), false);
    assert.equal(page.$('.product-list__delete').prop('disabled'), false);
    page.dom.window.close();
});

test('rows inserted by a search can be deleted with the same delegated handler', () => {
    const page = initializePage();
    search(page);
    resolveList(page.requests[0], resultsHtml(8, '検索後の商品'));
    submitDelete(page);
    assert.deepEqual(page.confirmations, ['「検索後の商品」を削除しますか？']);
    assert.equal(page.requests[1].settings.method, 'DELETE');
    assert.equal(page.requests[1].settings.url, base_url + '/8');
    page.dom.window.close();
});

test('a duplicate deletion is not sent while the first one is pending', () => {
    const page = initializePage();
    submitDelete(page);
    submitDelete(page);
    assert.equal(page.requests.length, 1);
    assert.equal(page.confirmations.length, 1);
    page.dom.window.close();
});

test('search validation errors keep old results and show field errors', () => {
    const page = initializePage();
    search(page);
    page.requests[0].deferred.reject({
        status: 422, responseJSON: { errors: { stock_max: ['在庫数の上限を確認してください。'] } },
    });
    assert.equal(page.$('[data-product-id="1"]').length, 1);
    assert.equal(page.$('#productErrors').text(), '在庫数の上限を確認してください。');
    assert.equal(page.$('#productResults').attr('aria-busy'), 'false');
    page.dom.window.close();
});

test('an expired session gives a login message without removing products', () => {
    const page = initializePage();
    submitDelete(page);
    page.requests[0].deferred.reject({ status: 419 });
    assert.equal(page.$('[data-product-id="1"]').length, 1);
    assert.match(page.$('#productErrors').text(), /ログインしてください/);
    page.dom.window.close();
});

test('error messages are rendered as text and cannot insert HTML', () => {
    const page = initializePage();
    search(page);
    const message = '<img src=x onerror=alert(1)>';
    page.requests[0].deferred.reject({ status: 500, responseJSON: { message } });
    assert.equal(page.$('#productErrors').text(), message);
    assert.equal(page.$('#productErrors img').length, 0);
    page.dom.window.close();
});

test('a failed list refresh after deletion retains the success message and shows an error', () => {
    const page = initializePage();
    submitDelete(page);
    page.requests[0].deferred.resolve({ message: '商品を削除しました。', product_id: 1 });
    page.requests[1].deferred.reject({ status: 0 });
    assert.equal(page.$('[data-product-id="1"]').length, 0);
    assert.equal(page.$('#productStatus').text(), '商品を削除しました。');
    assert.equal(page.$('#productErrors').prop('hidden'), false);
    assert.match(page.$('#productErrors').text(), /通信に失敗しました/);
    page.dom.window.close();
});

test('pages without a product list do not register Ajax work', () => {
    const page = initializePage({ without_list: true });
    assert.equal(page.requests.length, 0);
    assert.deepEqual(page.confirmations, []);
    page.dom.window.close();
});
