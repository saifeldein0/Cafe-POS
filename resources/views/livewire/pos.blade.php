@php
    use App\Support\Money;

    // One accent per category position, so a tile's colour tells the cashier where it lives.
    $accents = ['#8a4b1f', '#0b7a85', '#1f5fae', '#7b3d91', '#b03a58', '#4e7d12'];
    $accentFor = fn (int $index) => $accents[$index % count($accents)];
    $categoryIndex = $this->categories->search(fn ($category) => $category->id === $categoryId);
    $activeAccent = $accentFor($categoryIndex === false ? 0 : $categoryIndex);
@endphp

<div class="grid h-dvh grid-rows-[auto_1fr] overflow-hidden">
    {{-- Header --}}
    <header class="flex items-center justify-between gap-4 border-b border-line bg-white px-5 py-2.5">
        <div class="flex items-baseline gap-3">
            <span class="text-2xl font-extrabold tracking-tight text-sidi">CafePOS</span>
            <span class="text-sm font-semibold uppercase tracking-[0.18em] text-muted">{{ __('Cash register') }}</span>
        </div>

        <nav class="flex overflow-hidden rounded-lg border border-line text-sm font-bold" aria-label="{{ __('Language') }}">
            @foreach (['fr' => 'FR', 'en' => 'EN'] as $code => $label)
                <a href="{{ route('locale', $code) }}"
                   hreflang="{{ $code }}"
                   @if (app()->getLocale() === $code) aria-current="true" @endif
                   @class([
                       'flex min-h-11 min-w-14 items-center justify-center px-3',
                       'bg-ink text-white' => app()->getLocale() === $code,
                       'bg-white text-ink hover:bg-wash' => app()->getLocale() !== $code,
                   ])>{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    <div class="grid min-h-0 grid-cols-[minmax(0,7fr)_minmax(20rem,3fr)]">
        {{-- Menu --}}
        <main class="flex min-h-0 flex-col gap-4 overflow-hidden p-4">
            <div class="grid grid-cols-[repeat(auto-fit,minmax(8.5rem,1fr))] gap-2" role="tablist" aria-label="{{ __('Categories') }}">
                @foreach ($this->categories as $index => $category)
                    @php $isActive = $category->id === $categoryId; @endphp
                    <button type="button"
                            role="tab"
                            aria-selected="{{ $isActive ? 'true' : 'false' }}"
                            wire:key="category-{{ $category->id }}"
                            wire:click="selectCategory({{ $category->id }})"
                            style="--accent: {{ $accentFor($index) }}"
                            @class([
                                'pos-press min-h-16 rounded-xl border-2 px-3 py-2 text-lg leading-tight font-bold',
                                'border-(--accent) bg-(--accent) text-white shadow-md' => $isActive,
                                'border-line bg-white text-ink border-b-[6px] border-b-(--accent) hover:bg-wash' => ! $isActive,
                            ])>
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain pb-2">
                <div class="grid grid-cols-3 gap-3 xl:grid-cols-4" style="--accent: {{ $activeAccent }}">
                    @forelse ($this->products as $product)
                        @php $inCart = $cart[$product->id] ?? 0; @endphp
                        <button type="button"
                                wire:key="product-{{ $product->id }}"
                                wire:click="addItem({{ $product->id }}, $event.timeStamp)"
                                class="pos-press relative flex min-h-32 flex-col justify-between gap-2 overflow-hidden rounded-xl border-2 border-line bg-white p-3 pt-4 text-start shadow-sm hover:border-(--accent)">
                            <span class="absolute inset-x-0 top-0 h-1.5 bg-(--accent)" aria-hidden="true"></span>
                            <span class="pe-9 text-[1.35rem] leading-snug font-bold text-ink">{{ $product->name }}</span>
                            <span class="text-lg font-semibold tabular-nums text-muted">{{ Money::format($product->price) }}</span>
                            @if ($inCart > 0)
                                <span class="absolute end-2 top-3 flex size-9 items-center justify-center rounded-full bg-(--accent) text-base font-extrabold text-white tabular-nums"
                                      aria-label="{{ __(':count in the order', ['count' => $inCart]) }}">{{ $inCart }}</span>
                            @endif
                        </button>
                    @empty
                        <p class="col-span-full py-10 text-center text-lg text-muted">{{ __('No products in this category.') }}</p>
                    @endforelse
                </div>
            </div>
        </main>

        {{-- Current order (the ticket) --}}
        <aside class="relative flex min-h-0 flex-col border-s border-line bg-white" aria-label="{{ __('Current order') }}">
            <div class="flex items-baseline justify-between border-b border-dashed border-line px-5 pt-4 pb-3">
                <h2 class="text-xl font-extrabold text-ink">{{ __('Current order') }}</h2>
                <span class="text-base font-semibold text-muted tabular-nums">
                    {{ trans_choice(':count item|:count items', $this->itemCount, ['count' => $this->itemCount]) }}
                </span>
            </div>

            @if ($notice)
                <div wire:key="notice-{{ $notice['key'] }}"
                     role="status"
                     @class([
                         'pos-notice pointer-events-none absolute inset-x-4 top-16 z-10 rounded-lg px-4 py-3 text-lg font-bold shadow-lg',
                         'bg-go-soft text-go' => $notice['type'] === 'success',
                         'bg-stop-soft text-stop' => $notice['type'] === 'error',
                     ])>
                    {{ $notice['text'] }}
                </div>
            @endif

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-2">
                @forelse ($this->lines as $line)
                    @php $product = $line['product']; @endphp
                    <div wire:key="line-{{ $product->id }}" class="border-b border-line py-3 last:border-b-0">
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-lg leading-snug font-bold text-ink">{{ $product->name }}</span>
                            <span class="shrink-0 text-lg font-bold tabular-nums text-ink">{{ Money::format($line['total']) }}</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" wire:click="decrement({{ $product->id }}, $event.timeStamp)"
                                    class="pos-press flex size-12 items-center justify-center rounded-lg border-2 border-line bg-wash text-2xl font-bold text-ink"
                                    aria-label="{{ __('Remove one :name', ['name' => $product->name]) }}">−</button>
                            <span class="min-w-10 text-center text-xl font-extrabold tabular-nums text-ink">{{ $line['quantity'] }}</span>
                            <button type="button" wire:click="increment({{ $product->id }}, $event.timeStamp)"
                                    class="pos-press flex size-12 items-center justify-center rounded-lg border-2 border-line bg-wash text-2xl font-bold text-ink"
                                    aria-label="{{ __('Add one :name', ['name' => $product->name]) }}">+</button>
                            <span class="ms-1 text-sm text-muted tabular-nums">× {{ Money::format($product->price) }}</span>
                            <button type="button" wire:click="removeItem({{ $product->id }})"
                                    class="pos-press ms-auto flex size-12 items-center justify-center rounded-lg text-2xl text-muted hover:bg-stop-soft hover:text-stop"
                                    aria-label="{{ __('Remove :name', ['name' => $product->name]) }}">×</button>
                        </div>
                    </div>
                @empty
                    <p class="py-12 text-center text-lg text-muted">{{ __('Tap a product to add it.') }}</p>
                @endforelse
            </div>

            <div class="border-t-2 border-dashed border-line px-4 pt-3 pb-4">
                <div class="flex items-baseline justify-between gap-3 pb-3">
                    <span class="text-lg font-extrabold uppercase tracking-[0.14em] text-muted">{{ __('Total') }}</span>
                    <span class="text-[2.6rem] leading-none font-extrabold tabular-nums text-ink" data-test="total">{{ Money::format($this->total) }}</span>
                </div>
                <button type="button"
                        wire:click="confirm"
                        wire:loading.attr="disabled"
                        wire:target="confirm"
                        @disabled($this->lines->isEmpty())
                        class="pos-press flex min-h-24 w-full items-center justify-center rounded-xl bg-go text-3xl font-extrabold text-white shadow-lg hover:bg-go-dark disabled:cursor-not-allowed disabled:bg-line disabled:text-muted disabled:shadow-none">
                    <span wire:loading.remove wire:target="confirm">{{ __('Confirm') }}</span>
                    <span wire:loading wire:target="confirm">{{ __('Saving…') }}</span>
                </button>
                <button type="button"
                        wire:click="clear"
                        wire:loading.attr="disabled"
                        wire:target="confirm"
                        @disabled($this->lines->isEmpty())
                        class="pos-press mt-2 flex min-h-14 w-full items-center justify-center rounded-xl bg-wash text-xl font-bold text-ink hover:bg-line disabled:cursor-not-allowed disabled:opacity-50">
                    {{ __('Clear') }}
                </button>
            </div>
        </aside>
    </div>
</div>
