<?php

namespace App\Http\Controllers;

use App\Models\ButtonPage;
use App\Models\ButtonPageItem;
use App\Services\Button\ButtonAvailabilityService;
use App\Services\Button\ButtonPageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ButtonPageController extends ApiController
{
    public function __construct(
        private readonly ButtonPageService $pages,
        private readonly ButtonAvailabilityService $availability,
    ) {
    }

    public function index(Request $request)
    {
        $pages = ButtonPage::query()
            ->with(['items.device.currentRoom', 'items.device.product', 'items.productFunction'])
            ->where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ButtonPage $page): array => $this->pagePayload($request, $page))
            ->all();

        return $this->response($pages);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->pageRules());

        $page = $this->pages->create($request->user(), $validated['name'], (int) $validated['layout_columns']);

        return $this->response($this->pagePayload($request, $page->load('items')), '按鈕分頁已建立。', 201);
    }

    public function update(Request $request, string $buttonPagePublicId)
    {
        $page = $this->findOwnedPage($request, $buttonPagePublicId);
        $validated = $request->validate($this->pageRules());

        $page = $this->pages->update($page, $validated['name'], (int) $validated['layout_columns']);

        return $this->response($this->pagePayload($request, $page->load('items.device.currentRoom', 'items.device.product', 'items.productFunction')), '按鈕分頁已更新。');
    }

    public function saveLayout(Request $request, string $buttonPagePublicId)
    {
        $page = $this->findOwnedPage($request, $buttonPagePublicId);
        $validated = $request->validate([
            ...$this->pageRules(),
            'buttons' => ['array', 'max:100'],
            'buttons.*.public_id' => ['nullable', 'string', 'max:20'],
            'buttons.*.device_serial_number' => ['required', 'string', 'max:100'],
            'buttons.*.product_function_code' => ['required', 'string', 'max:20'],
            'buttons.*.position' => ['required', 'integer', 'min:0'],
            'buttons.*.shape' => ['required', Rule::in([ButtonPageItem::SHAPE_ROUNDED_SQUARE, ButtonPageItem::SHAPE_CIRCLE])],
            'buttons.*.background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'buttons.*.content_type' => ['required', Rule::in([ButtonPageItem::CONTENT_ICON, ButtonPageItem::CONTENT_TEXT])],
            'buttons.*.icon_key' => ['nullable', 'string', 'max:50'],
            'buttons.*.label' => ['nullable', 'string', 'max:100'],
            'buttons.*.foreground_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $page = $this->pages->saveLayout(
            $request->user(),
            $page,
            $validated['name'],
            (int) $validated['layout_columns'],
            $validated['buttons'] ?? [],
        );

        return $this->response($this->pagePayload($request, $page), '按鈕分頁已儲存。');
    }

    public function destroy(Request $request, string $buttonPagePublicId)
    {
        $this->pages->delete($this->findOwnedPage($request, $buttonPagePublicId));

        return $this->response(null, '按鈕分頁已刪除。');
    }

    public function order(Request $request)
    {
        $validated = $request->validate([
            'button_page_public_ids' => ['required', 'array'],
            'button_page_public_ids.*' => ['required', 'string', 'max:20'],
        ]);

        $this->pages->reorder($request->user(), $validated['button_page_public_ids']);

        return $this->response(null, '按鈕分頁順序已更新。');
    }

    private function findOwnedPage(Request $request, string $publicId): ButtonPage
    {
        return ButtonPage::query()
            ->where('user_id', $request->user()->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function pageRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'layout_columns' => ['required', 'integer', Rule::in([2, 3, 4, 5])],
        ];
    }

    private function pagePayload(Request $request, ButtonPage $page): array
    {
        $page->loadMissing(['items.device.currentRoom', 'items.device.product', 'items.productFunction']);

        return [
            'public_id' => $page->public_id,
            'name' => $page->name,
            'layout_columns' => $page->layout_columns,
            'sort_order' => $page->sort_order,
            'buttons' => $page->items->map(fn (ButtonPageItem $item): array => $this->itemPayload($request, $item))->values()->all(),
        ];
    }

    private function itemPayload(Request $request, ButtonPageItem $item): array
    {
        $status = $this->availability->for($request->user(), $item->device, $item->productFunction);

        return [
            'public_id' => $item->public_id,
            'position' => $item->position,
            'shape' => $item->shape,
            'background_color' => $item->background_color,
            'content_type' => $item->content_type,
            'icon_key' => $item->icon_key,
            'label' => $item->label,
            'foreground_color' => $item->foreground_color,
            'availability' => $status,
            'device' => [
                'serial_number' => $item->device->serial_number,
                'name' => $item->device->name,
                'room' => $item->device->currentRoom === null ? null : [
                    'public_id' => $item->device->currentRoom->public_id,
                    'name' => $item->device->currentRoom->name,
                ],
                'product' => $item->device->product === null ? null : [
                    'model_number' => $item->device->product->model_number,
                    'name' => $item->device->product->name,
                ],
            ],
            'function' => [
                'code' => $item->productFunction->code,
                'description' => $item->productFunction->description,
            ],
        ];
    }
}
