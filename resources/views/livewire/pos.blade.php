@php
    use App\Support\Money;

    // One accent per category position, so a tile's colour tells the cashier where it lives.
    // Roasted copper, mint tea, Nabeul cobalt, honey, pomegranate, olive: all hold white text at AA.
    $accents = ['#8f4a1d', '#17706a', '#2b5797', '#8a6110', '#a3364a', '#586b1c'];
    $accentFor = fn (int $index) => $accents[$index % count($accents)];
    $categoryIndex = $this->categories->search(fn ($category) => $category->id === $categoryId);
    $activeAccent = $accentFor($categoryIndex === false ? 0 : $categoryIndex);
@endphp

<div class="grid h-dvh grid-rows-[auto_1fr] overflow-hidden">
    {{-- Header --}}
    <header class="flex items-center justify-between gap-4 border-b border-line bg-white px-5 py-2">
        <div class="flex items-center gap-3">
            <x-brand-mark class="size-8 text-brand" />
            <span class="font-display text-[1.375rem] leading-none font-semibold tracking-tight text-ink">CafePOS</span>
            <span class="h-5 w-px bg-line-strong" aria-hidden="true"></span>
            <span class="text-base font-medium text-muted">{{ __('Cash register') }}</span>
        </div>

        <nav class="flex gap-1 rounded-xl bg-wash p-1 text-sm font-semibold" aria-label="{{ __('Language') }}">
            @foreach (['fr' => 'FR', 'en' => 'EN'] as $code => $label)
                <a href="{{ route('locale', $code) }}"
                   hreflang="{{ $code }}"
                   @if (app()->getLocale() === $code) aria-current="true" @endif
                   @class([
                       'pos-press flex min-h-11 min-w-14 items-center justify-center rounded-lg px-3',
                       'bg-ink text-white' => app()->getLocale() === $code,
                       'text-muted hover:bg-white hover:text-ink' => app()->getLocale() !== $code,
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
                                'pos-press min-h-16 rounded-2xl px-3 pt-2 pb-3 text-[1.0625rem] leading-tight font-semibold',
                                'bg-(--accent) text-white shadow-[0_6px_14px_-8px_var(--accent)]' => $isActive,
                                'bg-white text-ink shadow-[inset_0_0_0_1px_var(--color-line),inset_0_-5px_0_var(--accent)] hover:bg-raised' => ! $isActive,
                            ])>
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>

            <div class="pos-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain pb-2">
                <div class="grid grid-cols-3 gap-3 xl:grid-cols-4 2xl:grid-cols-5" style="--accent: {{ $activeAccent }}">
                    @forelse ($this->products as $product)
                        @php $inCart = $cart[$product->id] ?? 0; @endphp
                        <button type="button"
                                wire:key="product-{{ $product->id }}"
                                wire:click="addItem({{ $product->id }}, $event.timeStamp)"
                                @class([
                                    'pos-press pos-tile relative flex min-h-32 flex-col justify-between gap-3 overflow-hidden rounded-2xl p-4 pt-5 text-start',
                                    'bg-white shadow-[inset_0_0_0_1px_var(--color-line)] hover:shadow-[inset_0_0_0_2px_color-mix(in_oklab,var(--accent)_45%,white)]' => $inCart === 0,
                                    'bg-[color-mix(in_oklab,var(--accent)_7%,white)] shadow-[inset_0_0_0_2px_var(--accent)]' => $inCart > 0,
                                ])>
                            <span class="absolute inset-x-0 top-0 h-1.5 bg-(--accent)" aria-hidden="true"></span>
                            <span class="pe-10 font-display text-[1.375rem] leading-[1.15] font-semibold text-ink">{{ $product->name }}</span>
                            <span class="text-lg font-semibold tabular-nums text-(--accent)">{{ Money::format($product->price) }}</span>
                            @if ($inCart > 0)
                                <span class="absolute end-3 top-4 flex size-9 items-center justify-center rounded-full bg-(--accent) text-base font-bold text-white tabular-nums"
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
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 pt-4 pb-3">
                <h2 class="font-display text-[1.375rem] leading-tight font-semibold text-ink">{{ __('Current order') }}</h2>
                <span @class([
                    'rounded-full px-3 py-1 text-sm font-semibold tabular-nums',
                    'bg-wash text-muted' => $this->itemCount === 0,
                    'bg-ink text-white' => $this->itemCount > 0,
                ])>
                    {{ trans_choice(':count item|:count items', $this->itemCount, ['count' => $this->itemCount]) }}
                </span>
            </div>

            @if ($notice)
                <div wire:key="notice-{{ $notice['key'] }}"
                     role="status"
                     @class([
                         'pos-notice pointer-events-none absolute inset-x-4 top-[4.25rem] z-10 rounded-2xl px-5 py-4 text-lg leading-snug font-semibold text-white shadow-[0_12px_28px_-10px_rgb(34_26_21/0.45)]',
                         'bg-go' => $notice['type'] === 'success',
                         'bg-stop' => $notice['type'] === 'error',
                     ])>
                    {{ $notice['text'] }}
                </div>
            @endif

            <div class="pos-scroll flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain px-4 py-1">
                @forelse ($this->lines as $line)
                    @php $product = $line['product']; @endphp
                    <div wire:key="line-{{ $product->id }}" class="border-b border-line py-3 last:border-b-0">
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-lg leading-snug font-semibold text-ink">{{ $product->name }}</span>
                            <span class="shrink-0 text-lg font-semibold tabular-nums text-ink">{{ Money::format($line['total']) }}</span>
                        </div>
                        <div class="mt-2 flex items-center gap-1.5">
                            <button type="button" wire:click="decrement({{ $product->id }}, $event.timeStamp)"
                                    class="pos-press flex size-12 items-center justify-center rounded-xl bg-wash text-ink hover:bg-line active:bg-line-strong"
                                    aria-label="{{ __('Remove one :name', ['name' => $product->name]) }}">
                                <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4.5 10h11" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                            </button>
                            <span class="min-w-11 text-center text-xl font-bold tabular-nums text-ink">{{ $line['quantity'] }}</span>
                            <button type="button" wire:click="increment({{ $product->id }}, $event.timeStamp)"
                                    class="pos-press flex size-12 items-center justify-center rounded-xl bg-wash text-ink hover:bg-line active:bg-line-strong"
                                    aria-label="{{ __('Add one :name', ['name' => $product->name]) }}">
                                <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4.5 10h11M10 4.5v11" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                            </button>
                            <span class="ms-2 text-sm text-muted tabular-nums">× {{ Money::format($product->price) }}</span>
                            <button type="button" wire:click="removeItem({{ $product->id }})"
                                    class="pos-press ms-auto flex size-12 items-center justify-center rounded-xl text-muted hover:bg-stop-soft hover:text-stop active:bg-stop-soft active:text-stop"
                                    aria-label="{{ __('Remove :name', ['name' => $product->name]) }}">
                                <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5.5 5.5 9 9m0-9-9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="m-auto flex flex-col items-center gap-4 px-6 py-10 text-center">
                        <span class="flex size-20 items-center justify-center rounded-full bg-wash">
                            <x-brand-mark class="size-10 text-line-strong" />
                        </span>
                        <p class="max-w-56 text-lg leading-snug text-muted">{{ __('Tap a product to add it.') }}</p>
                    </div>
                @endforelse
            </div>

            <div class="border-t border-line bg-raised px-4 pt-3 pb-4">
                <div class="flex items-baseline justify-between gap-3 pb-3">
                    <span class="text-lg font-semibold text-muted">{{ __('Total') }}</span>
                    <span class="font-display text-[2.75rem] leading-none font-bold tracking-tight tabular-nums text-ink" data-test="total">{{ Money::format($this->total) }}</span>
                </div>
                <button type="button"
                        wire:click="confirm"
                        wire:loading.attr="disabled"
                        wire:target="confirm"
                        @disabled($this->lines->isEmpty())
                        class="pos-press flex min-h-24 w-full items-center justify-center rounded-2xl bg-go text-3xl font-bold text-white shadow-[0_10px_20px_-12px_var(--color-go)] hover:bg-go-dark active:bg-go-dark disabled:cursor-not-allowed disabled:bg-line disabled:text-muted disabled:shadow-none">
                    <span wire:loading.remove wire:target="confirm">{{ __('Confirm') }}</span>
                    <span wire:loading wire:target="confirm">{{ __('Saving…') }}</span>
                </button>
                <button type="button"
                        wire:click="clear"
                        wire:loading.attr="disabled"
                        wire:target="confirm"
                        @disabled($this->lines->isEmpty())
                        class="pos-press mt-2 flex min-h-14 w-full items-center justify-center rounded-2xl bg-white text-xl font-semibold text-ink shadow-[inset_0_0_0_1px_var(--color-line-strong)] hover:bg-wash active:bg-line disabled:cursor-not-allowed disabled:opacity-50">
                    {{ __('Clear') }}
                </button>
            </div>
        </aside>
    </div>
</div>
