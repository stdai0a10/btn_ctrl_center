<?php

namespace App\Services\Manage;

use App\Exceptions\ApiException;
use App\Models\Product;
use App\Models\ProductFunction;
use App\Support\ProductModelNumber;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCatalogService
{
    public function __construct(private readonly ManageActionLogger $logger) {}

    public function createProduct(Request $request, string $modelNumber, string $name): Product
    {
        $modelNumber = ProductModelNumber::normalize($modelNumber);

        if (Product::query()->where('model_number', $modelNumber)->exists()) {
            throw $this->duplicateModel();
        }

        try {
            return DB::transaction(function () use ($request, $modelNumber, $name): Product {
                $product = Product::query()->create([
                    'model_number' => $modelNumber,
                    'name' => $name,
                ]);

                $this->logger->forManageUser(
                    request: $request,
                    action: 'products.create',
                    targetType: 'product',
                    targetId: $product->id,
                    targetPublicId: $product->public_id,
                    metadata: [
                        'before' => null,
                        'after' => $this->auditPayload($product),
                    ],
                );

                return $product;
            });
        } catch (QueryException $exception) {
            if (Product::query()->where('model_number', $modelNumber)->exists()) {
                throw $this->duplicateModel();
            }

            throw $exception;
        }
    }

    public function updateProduct(Request $request, Product $product, string $modelNumber, string $name): Product
    {
        $this->ensureProductUnlocked($product);

        $modelNumber = ProductModelNumber::normalize($modelNumber);

        if (Product::query()
            ->where('model_number', $modelNumber)
            ->whereKeyNot($product->id)
            ->exists()) {
            throw $this->duplicateModel();
        }

        try {
            return DB::transaction(function () use ($request, $product, $modelNumber, $name): Product {
                $before = $this->auditPayload($product);

                $product->forceFill([
                    'model_number' => $modelNumber,
                    'name' => $name,
                ])->save();

                $product->refresh();

                $this->logger->forManageUser(
                    request: $request,
                    action: 'products.update',
                    targetType: 'product',
                    targetId: $product->id,
                    targetPublicId: $product->public_id,
                    metadata: [
                        'before' => $before,
                        'after' => $this->auditPayload($product),
                    ],
                );

                return $product;
            });
        } catch (QueryException $exception) {
            if (Product::query()
                ->where('model_number', $modelNumber)
                ->whereKeyNot($product->id)
                ->exists()) {
                throw $this->duplicateModel();
            }

            throw $exception;
        }
    }

    public function createFunction(Request $request, Product $product, string $description): ProductFunction
    {
        $this->ensureProductUnlocked($product);

        return DB::transaction(function () use ($request, $product, $description): ProductFunction {
            $function = $product->functions()->create([
                'description' => $description,
            ]);

            $this->logger->forManageUser(
                request: $request,
                action: 'product_functions.create',
                targetType: 'product_function',
                targetId: $function->id,
                targetPublicId: $function->code,
                metadata: [
                    'product_public_id' => $product->public_id,
                    'before' => null,
                    'after' => $this->functionAuditPayload($function),
                ],
            );

            return $function;
        });
    }

    public function updateFunction(Request $request, ProductFunction $function, string $description): ProductFunction
    {
        return DB::transaction(function () use ($request, $function, $description): ProductFunction {
            $function->loadMissing('product');
            $this->ensureProductUnlocked($function->product);
            $before = $this->functionAuditPayload($function);

            $function->forceFill([
                'description' => $description,
            ])->save();

            $function->refresh()->loadMissing('product');

            $this->logger->forManageUser(
                request: $request,
                action: 'product_functions.update',
                targetType: 'product_function',
                targetId: $function->id,
                targetPublicId: $function->code,
                metadata: [
                    'product_public_id' => $function->product?->public_id,
                    'before' => $before,
                    'after' => $this->functionAuditPayload($function),
                ],
            );

            return $function;
        });
    }

    public function deleteFunction(Request $request, ProductFunction $function): void
    {
        DB::transaction(function () use ($request, $function): void {
            $function->loadMissing('product');
            $this->ensureProductUnlocked($function->product);
            $before = $this->functionAuditPayload($function);
            $productPublicId = $function->product?->public_id;
            $targetId = $function->id;
            $targetPublicId = $function->code;

            $function->delete();

            $this->logger->forManageUser(
                request: $request,
                action: 'product_functions.delete',
                targetType: 'product_function',
                targetId: $targetId,
                targetPublicId: $targetPublicId,
                metadata: [
                    'product_public_id' => $productPublicId,
                    'before' => $before,
                    'after' => null,
                ],
            );
        });
    }

    public function lockProduct(Request $request, Product $product): Product
    {
        if ($product->is_locked) {
            return $product;
        }

        return DB::transaction(function () use ($request, $product): Product {
            $before = $this->auditPayload($product);

            $product->forceFill(['is_locked' => true])->save();
            $product->refresh();

            $this->logger->forManageUser(
                request: $request,
                action: 'products.lock',
                targetType: 'product',
                targetId: $product->id,
                targetPublicId: $product->public_id,
                metadata: [
                    'before' => $before,
                    'after' => $this->auditPayload($product),
                ],
            );

            return $product;
        });
    }

    private function duplicateModel(): ApiException
    {
        return new ApiException(
            'Product model number already exists.',
            'PRODUCT_MODEL_ALREADY_EXISTS',
        );
    }

    private function ensureProductUnlocked(?Product $product): void
    {
        if ($product?->is_locked) {
            throw new ApiException(
                'Product is locked.',
                'PRODUCT_LOCKED',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(Product $product): array
    {
        return [
            'public_id' => $product->public_id,
            'model_number' => $product->model_number,
            'name' => $product->name,
            'is_locked' => $product->is_locked,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function functionAuditPayload(ProductFunction $function): array
    {
        $function->loadMissing('product');

        return [
            'code' => $function->code,
            'description' => $function->description,
            'product_public_id' => $function->product?->public_id,
        ];
    }
}
