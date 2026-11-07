@php
    use App\Support\Money;

    // One accent per category position, so a tile's colour tells the cashier where it lives.
    // Roasted copper, mint tea, Nabeul cobalt, honey, pomegranate, olive: all hold white text at AA.
    $accents = ['#8f4a1d', '#17706a', '#2b5797', '#8a6110', '#a3364a', '#586b1c'];
    $accentFor = fn (int $index) => $accents[$index % count($accents)];
    $categoryIndex = $this->categories->search(fn ($category) => $category->id === $categoryId);
    $activeAccent = $accentFor($categoryIndex === false ? 0 : $categoryIndex);

    $shift = $this->shift;
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

        <div class="ms-auto flex items-center gap-2">
            @if ($shift)
                <span class="me-1 flex items-center gap-2 rounded-full bg-go-soft px-3.5 py-1.5 text-base font-semibold text-go" data-test="shift-since">
                    <span class="size-2.5 rounded-full bg-go" aria-hidden="true"></span>
                    {{ __('Shift since :time', ['time' => $shift->started_at->format('H:i')]) }}
                </span>
            @else
                <span class="me-1 flex items-center gap-2 rounded-full bg-stop-soft px-3.5 py-1.5 text-base font-semibold text-stop">
                    <span class="size-2.5 rounded-full bg-stop" aria-hidden="true"></span>
                    {{ __('Till closed') }}
                </span>
            @endif

            @if ($this->lastShift && ($shift || $receiptDismissed))
                <button type="button"
                        wire:click="openReceipt"
                        class="pos-press flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 text-base font-semibold text-ink shadow-[inset_0_0_0_1px_var(--color-line-strong)] hover:bg-wash active:bg-line">
                    <svg class="size-5 text-muted" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 2.75h10v14.5l-2.5-1.5-2.5 1.5-2.5-1.5L5 17.25V2.75Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8 7h4M8 10.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    {{ __('Previous receipt') }}
                </button>
            @endif

            @if ($shift)
                <button type="button"
                        wire:click="askEndShift"
                        class="pos-press flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 text-base font-semibold text-stop shadow-[inset_0_0_0_1.5px_var(--color-stop)] hover:bg-stop-soft active:bg-stop-soft">
                    <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 3.75H4.75v12.5H8M12.5 6.5 16 10l-3.5 3.5M16 10H8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ __('End shift') }}
                </button>
            @endif
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

    @if ($shift)
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
    @else
        {{-- Till closed: the last shift's receipt stays on screen until someone starts the next shift. --}}
        <div class="relative flex min-h-0 items-stretch justify-center gap-8 p-6">
            @if ($notice)
                <div wire:key="notice-{{ $notice['key'] }}"
                     role="status"
                     @class([
                         'pos-notice pointer-events-none absolute inset-x-0 top-4 z-10 mx-auto w-fit max-w-xl rounded-2xl px-6 py-4 text-lg leading-snug font-semibold text-white shadow-[0_12px_28px_-10px_rgb(34_26_21/0.45)]',
                         'bg-go' => $notice['type'] === 'success',
                         'bg-stop' => $notice['type'] === 'error',
                     ])>
                    {{ $notice['text'] }}
                </div>
            @endif

            @if ($this->receipt && ! $receiptDismissed)
                <section class="flex min-h-0 w-full max-w-[34rem] flex-col justify-center gap-3" aria-label="{{ __('Last shift') }}">
                    <x-shift-receipt :summary="$this->receipt" />
                    <button type="button"
                            wire:click="dismissReceipt"
                            class="pos-press flex min-h-14 w-full shrink-0 items-center justify-center rounded-2xl bg-white text-xl font-semibold text-ink shadow-[inset_0_0_0_1px_var(--color-line-strong)] hover:bg-raised active:bg-line">
                        {{ __('Close') }}
                    </button>
                </section>
            @endif

            <section class="flex w-full max-w-[26rem] flex-col items-center justify-center gap-5 self-center rounded-3xl bg-white px-8 py-10 text-center shadow-[inset_0_0_0_1px_var(--color-line)]"
                     data-test="till-closed">
                <span class="flex size-20 items-center justify-center rounded-full bg-wash text-muted">
                    <svg class="size-10" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <div>
                    <h2 class="font-display text-3xl leading-tight font-semibold text-ink">{{ __('Till closed') }}</h2>
                    <p class="mt-2 text-lg leading-snug text-muted">{{ __('Tap New shift to open the till.') }}</p>
                </div>
                <button type="button"
                        wire:click="askNewShift"
                        class="pos-press flex min-h-24 w-full items-center justify-center rounded-2xl bg-go text-3xl font-bold text-white shadow-[0_10px_20px_-12px_var(--color-go)] hover:bg-go-dark active:bg-go-dark">
                    {{ __('New shift') }}
                </button>
            </section>
        </div>
    @endif

    {{-- "Are you sure?" before ending or starting a shift. --}}
    @if ($confirming)
        @php $ending = $confirming === \App\Livewire\Pos::CONFIRM_END; @endphp
        <div wire:key="confirm-{{ $confirming }}" class="pos-overlay fixed inset-0 z-50 grid place-items-center bg-ink/55 p-6">
            <div role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-text"
                 class="pos-dialog w-full max-w-[36rem] rounded-3xl bg-white p-8 text-center shadow-[0_30px_60px_-20px_rgb(34_26_21/0.6)]">
                <h2 id="confirm-title" class="font-display text-[2rem] leading-tight font-semibold text-ink">
                    {{ $ending ? __('End the shift?') : __('Start a new shift?') }}
                </h2>
                <p id="confirm-text" class="mx-auto mt-3 max-w-md text-xl leading-snug text-muted">
                    {{ $ending ? __('The till will be locked until the new shift.') : __('The till will open for new orders.') }}
                </p>
                <div class="mt-8 grid grid-cols-2 gap-3">
                    <button type="button"
                            wire:click="cancelConfirm"
                            class="pos-press flex min-h-20 items-center justify-center rounded-2xl bg-white text-2xl font-semibold text-ink shadow-[inset_0_0_0_1.5px_var(--color-line-strong)] hover:bg-wash active:bg-line">
                        {{ __('No') }}
                    </button>
                    <button type="button"
                            wire:click="{{ $ending ? 'endShift' : 'startShift' }}"
                            wire:loading.attr="disabled"
                            @class([
                                'pos-press flex min-h-20 items-center justify-center rounded-2xl px-3 text-2xl font-bold text-white',
                                'bg-stop hover:bg-[#93201a] active:bg-[#93201a]' => $ending,
                                'bg-go hover:bg-go-dark active:bg-go-dark' => ! $ending,
                            ])>
                        {{ $ending ? __('Yes, end the shift') : __('Yes, start') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- The last finished shift's receipt, on demand. Read-only: closing it changes nothing. --}}
    @if ($showReceipt && $this->receipt)
        <div wire:key="receipt-overlay" class="pos-overlay fixed inset-0 z-40 flex flex-col items-center justify-center gap-3 bg-ink/55 p-6"
             role="dialog" aria-modal="true" aria-label="{{ __('Previous receipt') }}">
            <x-shift-receipt :summary="$this->receipt" class="pos-dialog max-h-[calc(100dvh-8.5rem)] w-full max-w-[34rem]" />
            <button type="button"
                    wire:click="closeReceipt"
                    class="pos-press flex min-h-16 w-full max-w-[34rem] shrink-0 items-center justify-center rounded-2xl bg-white text-2xl font-semibold text-ink hover:bg-wash active:bg-line">
                {{ __('Close') }}
            </button>
        </div>
    @endif
</div>
