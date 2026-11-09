<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductSearchRequest;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ProductController extends Controller
{
    public function index(ProductSearchRequest $request): View|JsonResponse {
        $search_conditions = $request->validated();
        $search_conditions['sort_by'] = $search_conditions['sort_by'] ?? 'id';
        $search_conditions['sort_order'] = $search_conditions['sort_order'] ?? 'desc';
        $products = Product::getProductList($search_conditions);
        $search_conditions['page'] = $products->currentPage();

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('products.partials.results', compact('products', 'search_conditions'))->render(),
                'url' => route('products.index', $search_conditions),
                'sort_by' => $search_conditions['sort_by'],
                'sort_order' => $search_conditions['sort_order'],
                'page' => $products->currentPage(),
                'total' => $products->total(),
            ]);
        }

        $companies = Company::getCompanyList();

        return view('products.index', compact('products', 'companies', 'search_conditions'));
    }

    public function show(int $product_id): View {
        $product = Product::getProductDetail($product_id);

        return view('products.show', compact('product'));
    }

    public function create(): View {
        $companies = Company::getCompanyList();

        return view('products.create', compact('companies'));
    }

    public function store(ProductStoreRequest $request): RedirectResponse {
        try {
            Product::registerProduct($request->validated(), $request->file('img_path'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('products.create')->withInput($request->safe()->except('img_path'))
                ->withErrors(['registration' => '商品を登録できませんでした。もう一度お試しください。']);
        }

        return redirect()->route('products.create')->with('status', '商品を登録しました。');
    }

    public function edit(int $product_id): View {
        $product = Product::getProductDetail($product_id);
        $companies = Company::getCompanyList();

        return view('products.edit', compact('product', 'companies'));
    }

    public function update(ProductUpdateRequest $request, int $product_id): RedirectResponse {
        $product = Product::getProductDetail($product_id);

        try {
            $product->updateProduct($request->validated(), $request->file('img_path'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('products.edit', $product_id)->withInput($request->safe()->except('img_path'))
                ->withErrors(['update' => '商品情報を更新できませんでした。もう一度お試しください。']);
        }

        return redirect()->route('products.edit', $product_id)->with('status', '商品情報を更新しました。');
    }

    public function destroy(Request $request, int $product_id): RedirectResponse|JsonResponse {
        $product = Product::getProductDetail($product_id);

        try {
            $product->deleteProduct();
        } catch (Throwable $exception) {
            report($exception);
            $error_message = '商品を削除できませんでした。もう一度お試しください。';
            if ($exception instanceof QueryException && ($exception->errorInfo[0] ?? null) === '23000') {
                $error_message = '売上データがある商品は削除できません。';
            }

            if ($request->expectsJson()) {
                $status_code = $exception instanceof QueryException
                    && ($exception->errorInfo[0] ?? null) === '23000' ? 409 : 500;

                return response()->json(['message' => $error_message], $status_code);
            }

            return redirect()->route('products.index')->withErrors(['deletion' => $error_message]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => '商品を削除しました。', 'product_id' => $product_id]);
        }

        return redirect()->route('products.index')->with('status', '商品を削除しました。');
    }
}
