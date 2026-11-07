<?php

namespace App\Livewire;

use App\Exceptions\NoOpenShiftException;
use App\Exceptions\ShiftAlreadyOpenException;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shift;
use App\Services\OrderService;
use App\Services\ShiftService;
use App\Services\ShiftSummary;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cashier screen: tap products into a cart, confirm to record the order.
 *
 * Orders are only taken while a shift is open. With no shift open the till is locked: it shows the
 * last finished shift's receipt and a "New shift" button. Ending or starting a shift asks
 * "Are you sure?" first, through the $confirming state (an in-page dialog, no browser confirm()).
 */
#[Layout('components.layouts.pos')]
class Pos extends Component
{
    public const CONFIRM_END = 'end';

    public const CONFIRM_START = 'start';

    /**
     * Current order as [product_id => quantity].
     *
     * @var array<int, int>
     */
    public array $cart = [];

    public ?int $categoryId = null;

    /** Short-lived feedback after confirm: ['type' => 'success'|'error', 'text' => string, 'key' => string]. */
    public ?array $notice = null;

    /** The "Are you sure?" dialog on screen: self::CONFIRM_END, self::CONFIRM_START or null. */
    public ?string $confirming = null;

    /** The previous receipt overlay is open. */
    public bool $showReceipt = false;

    /** On the locked screen, the receipt panel was closed (the "Previous receipt" button brings it back). */
    public bool $receiptDismissed = false;

    public function mount(): void
    {
        $this->categoryId = $this->categories->first()?->id;
    }

    #[Computed]
    public function shift(): ?Shift
    {
        return Shift::current();
    }

    #[Computed]
    public function lastShift(): ?Shift
    {
        return Shift::lastFinished();
    }

    /**
     * The last finished shift's receipt, recomputed from its orders.
     */
    #[Computed]
    public function receipt(): ?ShiftSummary
    {
        return $this->lastShift === null ? null : app(ShiftService::class)->summary($this->lastShift);
    }

    public function askEndShift(): void
    {
        if ($this->shift === null) {
            return;
        }

        if ($this->cart !== []) {
            $this->notify('error', __('Confirm or clear the current order first.'));

            return;
        }

        $this->showReceipt = false;
        $this->confirming = self::CONFIRM_END;
    }

    public function askNewShift(): void
    {
        if ($this->shift !== null) {
            return;
        }

        $this->showReceipt = false;
        $this->confirming = self::CONFIRM_START;
    }

    public function cancelConfirm(): void
    {
        $this->confirming = null;
    }

    public function endShift(ShiftService $shifts): void
    {
        if ($this->confirming !== self::CONFIRM_END) {
            return;
        }

        $this->confirming = null;

        if ($this->cart !== []) {
            $this->notify('error', __('Confirm or clear the current order first.'));

            return;
        }

        if ($this->shift !== null) {
            $shifts->end($this->shift);
        }

        $this->notice = null;
        $this->showReceipt = false;
        $this->receiptDismissed = false;
        $this->forgetShifts();
    }

    public function startShift(ShiftService $shifts): void
    {
        if ($this->confirming !== self::CONFIRM_START) {
            return;
        }

        $this->confirming = null;

        try {
            $shifts->start();
        } catch (ShiftAlreadyOpenException) {
            // Started meanwhile from another screen: the till simply opens on that shift.
        }

        $this->showReceipt = false;
        $this->receiptDismissed = false;
        $this->forgetShifts();
        $this->notify('success', __('New shift started ✓'));
    }

    public function openReceipt(): void
    {
        if ($this->lastShift === null) {
            return;
        }

        if ($this->shift === null && $this->receiptDismissed) {
            $this->receiptDismissed = false;

            return;
        }

        $this->confirming = null;
        $this->showReceipt = true;
    }

    public function closeReceipt(): void
    {
        $this->showReceipt = false;
    }

    public function dismissReceipt(): void
    {
        $this->receiptDismissed = true;
    }

    private function forgetShifts(): void
    {
        unset($this->shift, $this->lastShift, $this->receipt);
    }

    /**
     * Categories that have at least one active product, in menu order.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->whereHas('products', fn ($query) => $query->active())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        if ($this->categoryId === null) {
            return new Collection;
        }

        return Product::query()
            ->active()
            ->where('category_id', $this->categoryId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Cart lines in the order they were added.
     *
     * @return Collection<int, array{product: Product, quantity: int, total: float}>
     */
    #[Computed]
    public function lines(): Collection
    {
        $products = Product::query()->whereKey(array_keys($this->cart))->get()->keyBy('id');

        return collect($this->cart)
            ->filter(fn (int $quantity, int $productId) => $products->has($productId))
            ->map(fn (int $quantity, int $productId) => [
                'product' => $products[$productId],
                'quantity' => $quantity,
                'total' => round((float) $products[$productId]->price * $quantity, 3),
            ])
            ->values();
    }

    #[Computed]
    public function total(): float
    {
        return round($this->lines->sum('total'), 3);
    }

    #[Computed]
    public function itemCount(): int
    {
        return array_sum($this->cart);
    }

    public function selectCategory(int $categoryId): void
    {
        if ($this->categories->contains('id', $categoryId)) {
            $this->categoryId = $categoryId;
        }
    }

    /**
     * $tap is the tap's event timestamp, sent only so Livewire never merges two quick taps on the same
     * button into one request (it squashes identical queued actions). It is otherwise ignored.
     */
    public function addItem(int $productId, int|float|null $tap = null): void
    {
        if ($this->shift === null || ! Product::query()->active()->whereKey($productId)->exists()) {
            return;
        }

        $this->notice = null;
        $this->cart[$productId] = ($this->cart[$productId] ?? 0) + 1;
    }

    public function increment(int $productId, int|float|null $tap = null): void
    {
        if (isset($this->cart[$productId])) {
            $this->cart[$productId]++;
        }
    }

    public function decrement(int $productId, int|float|null $tap = null): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]--;

        if ($this->cart[$productId] <= 0) {
            unset($this->cart[$productId]);
        }
    }

    public function removeItem(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function clear(): void
    {
        $this->cart = [];
        $this->notice = null;
    }

    public function confirm(OrderService $orders): void
    {
        if ($this->cart === []) {
            $this->notify('error', __('The order is empty. Tap a product to add it.'));

            return;
        }

        try {
            $order = $orders->create($this->cart);
        } catch (NoOpenShiftException) {
            // The shift was ended from another screen: keep the order, the till now asks for a new shift.
            $this->forgetShifts();
            $this->notify('error', __('No shift is open. Start a new shift.'));

            return;
        } catch (InvalidArgumentException) {
            // A product was deactivated meanwhile: drop it and let the cashier check again.
            $active = Product::query()->active()->whereKey(array_keys($this->cart))->pluck('id')->all();
            $this->cart = array_intersect_key($this->cart, array_flip($active));
            $this->notify('error', __('Some products are no longer available. Check the order and confirm again.'));

            return;
        }

        $this->cart = [];
        $this->notify('success', __('Order saved ✓').' — '.Money::format($order->total), (string) $order->id);
    }

    private function notify(string $type, string $text, ?string $key = null): void
    {
        $this->notice = [
            'type' => $type,
            'text' => $text,
            'key' => $key ?? uniqid(),
        ];
    }

    public function render(): View
    {
        return view('livewire.pos')->title(__('Cash register').' — CafePOS');
    }
}
