<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\Device;
use App\Models\Product;
use App\Models\ProductFunction;
use App\Services\Manage\ProductCatalogService;
use App\Support\ProductModelNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ProductController extends ApiController
{
    #[OA\Get(
        path: '/manage/api/products',
        operationId: 'manageProductsIndex',
        summary: 'List products or return product suggestions',
        security: [['sessionCookie' => []]],
        tags: ['Manage Products'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/QuerySearch'),
            new OA\Parameter(name: 'suggest', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(ref: '#/components/parameters/QueryPage'),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', enum: [10, 20, 50, 100])),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'suggest' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ]);

        $search = $validated['search'] ?? null;
        $normalized = is_string($search) ? ProductModelNumber::normalize($search) : null;
        $suggest = $request->boolean('suggest');
        $perPage = $validated['per_page'] ?? ($suggest ? 10 : 20);

        $products = Product::query()
            ->when(! $suggest, fn (Builder $query) => $query->withCount(['functions', 'devices']))
            ->when($search, function (Builder $query) use ($search, $normalized): void {
                $query->where(function (Builder $query) use ($search, $normalized): void {
                    $query->where('public_id', 'like', "%{$search}%")
                        ->orWhere('model_number', 'like', "%{$normalized}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });

        if ($suggest && $normalized !== null && $normalized !== '') {
            $products->orderByRaw(
                'CASE WHEN model_number = ? THEN 0 WHEN model_number LIKE ? THEN 1 WHEN model_number LIKE ? THEN 2 WHEN name LIKE ? THEN 3 ELSE 4 END',
                [$normalized, "{$normalized}%", "%{$normalized}%", "%{$search}%"],
            );
        } else {
            $products->latest('created_at');
        }

        $products = $products->paginate($perPage, ['*'], 'page', $validated['page'] ?? 1);

        $products->getCollection()->transform(
            fn (Product $product): array => $suggest
                ? $this->productSuggestionPayload($product)
                : $this->productPayload($product),
        );

        return $this->response([
            ...$this->paginatedPayload($products),
            'filters' => $validated,
        ]);
    }

    #[OA\Get(
        path: '/manage/api/products/{product_public_id}',
        operationId: 'manageProductsShow',
        summary: 'Get a product, its functions, and its devices',
        security: [['sessionCookie' => []]],
        tags: ['Manage Products'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PathProductPublicId'),
            new OA\Parameter(name: 'devices_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'devices_per_page', in: 'query', schema: new OA\Schema(type: 'integer', enum: [10, 20, 50, 100])),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function show(Request $request, string $productPublicId)
    {
        $validated = $request->validate([
            'devices_page' => ['nullable', 'integer', 'min:1'],
            'devices_per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ]);

        $product = Product::query()
            ->with('functions')
            ->withCount(['functions', 'devices'])
            ->where('public_id', $productPublicId)
            ->firstOrFail();

        $devices = $product->devices()
            ->with('currentRoom')
            ->latest('created_at')
            ->paginate($validated['devices_per_page'] ?? 20, ['*'], 'devices_page', $validated['devices_page'] ?? 1);

        $devices->getCollection()->transform(fn (Device $device): array => [
            'serial_number' => $device->serial_number,
            'name' => $device->name,
            'is_locked' => $device->is_locked,
            'is_enabled' => $device->is_enabled,
            'room' => $device->currentRoom === null ? null : [
                'public_id' => $device->currentRoom->public_id,
                'name' => $device->currentRoom->name,
                'status' => $device->currentRoom->trashed() ? 'deleted' : 'active',
            ],
            'created_at' => $device->created_at?->toISOString(),
            'updated_at' => $device->updated_at?->toISOString(),
        ]);

        return $this->response([
            ...$this->productPayload($product),
            'functions' => $product->functions
                ->sortBy('created_at')
                ->values()
                ->map(fn (ProductFunction $function): array => $this->functionPayload($function))
                ->all(),
            'devices' => $this->paginatedPayload($devices),
        ]);
    }

    #[OA\Post(
        path: '/manage/api/products',
        operationId: 'manageProductsStore',
        summary: 'Create a product',
        security: [['sessionCookie' => []]],
        tags: ['Manage Products'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductRequest')),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/Created'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function store(Request $request, ProductCatalogService $catalog)
    {
        $validated = $request->validate([
            'model_number' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $product = $catalog->createProduct($request, $validated['model_number'], $validated['name']);

        return $this->response($this->productPayload($product), '產品主檔已建立。', 201);
    }

    #[OA\Patch(
        path: '/manage/api/products/{product_public_id}',
        operationId: 'manageProductsUpdate',
        summary: 'Update a product',
        security: [['sessionCookie' => []]],
        tags: ['Manage Products'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathProductPublicId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function update(Request $request, ProductCatalogService $catalog, string $productPublicId)
    {
        $validated = $request->validate([
            'model_number' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $product = Product::query()->where('public_id', $productPublicId)->firstOrFail();
        $product = $catalog->updateProduct($request, $product, $validated['model_number'], $validated['name']);

        return $this->response($this->productPayload($product), '產品主檔已更新。');
    }

    #[OA\Post(
        path: '/manage/api/products/{product_public_id}/lock',
        operationId: 'manageProductsLock',
        summary: 'Permanently lock a product',
        security: [['sessionCookie' => []]],
        tags: ['Manage Products'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathProductPublicId')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function lock(Request $request, ProductCatalogService $catalog, string $productPublicId)
    {
        $product = Product::query()->where('public_id', $productPublicId)->firstOrFail();
        $product = $catalog->lockProduct($request, $product);

        return $this->response($this->productPayload($product), '產品已鎖定。');
    }

    #[OA\Post(
        path: '/manage/api/products/{product_public_id}/functions',
        operationId: 'manageProductFunctionsStore',
        summary: 'Create a product function',
        security: [['sessionCookie' => []]],
        tags: ['Manage Products'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathProductPublicId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductFunctionRequest')),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/Created'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function storeFunction(Request $request, ProductCatalogService $catalog, string $productPublicId)
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
        ]);

        $product = Product::query()->where('public_id', $productPublicId)->firstOrFail();
        $function = $catalog->createFunction($request, $product, $validated['description']);

        return $this->response($this->functionPayload($function), '產品功能已建立。', 201);
    }

    #[OA\Patch(
        path: '/manage/api/product-functions/{code}',
        operationId: 'manageProductFunctionsUpdate',
        summary: 'Update a product function',
        security: [['sessionCookie' => []]],
        tags: ['Manage Products'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathProductFunctionCode')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductFunctionRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function updateFunction(Request $request, ProductCatalogService $catalog, string $code)
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
        ]);

        $function = ProductFunction::query()->where('code', $code)->firstOrFail();
        $function = $catalog->updateFunction($request, $function, $validated['description']);

        return $this->response($this->functionPayload($function), '產品功能已更新。');
    }

    #[OA\Delete(
        path: '/manage/api/product-functions/{code}',
        operationId: 'manageProductFunctionsDestroy',
        summary: 'Delete a product function',
        security: [['sessionCookie' => []]],
        tags: ['Manage Products'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathProductFunctionCode')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function destroyFunction(Request $request, ProductCatalogService $catalog, string $code)
    {
        $function = ProductFunction::query()->where('code', $code)->firstOrFail();
        $catalog->deleteFunction($request, $function);

        return $this->response(null, '產品功能已刪除。');
    }

    private function productPayload(Product $product): array
    {
        return [
            'public_id' => $product->public_id,
            'model_number' => $product->model_number,
            'name' => $product->name,
            'is_locked' => $product->is_locked,
            'function_count' => $product->functions_count ?? $product->functions()->count(),
            'device_count' => $product->devices_count ?? $product->devices()->count(),
            'created_at' => $product->created_at?->toISOString(),
            'updated_at' => $product->updated_at?->toISOString(),
        ];
    }

    private function productSuggestionPayload(Product $product): array
    {
        return [
            'public_id' => $product->public_id,
            'model_number' => $product->model_number,
            'name' => $product->name,
        ];
    }

    private function functionPayload(ProductFunction $function): array
    {
        return [
            'code' => $function->code,
            'description' => $function->description,
            'created_at' => $function->created_at?->toISOString(),
            'updated_at' => $function->updated_at?->toISOString(),
        ];
    }

    private function paginatedPayload($paginator): array
    {
        return [
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
