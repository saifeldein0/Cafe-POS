@php
    use App\Support\Money;

    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ' '), '0'), '.') ?: '0';
    $th = 'px-3 py-2 text-start text-sm font-semibold text-gray-950 dark:text-white';
    $thNum = 'px-3 py-2 text-end text-sm font-semibold text-gray-950 dark:text-white';
    $td = 'px-3 py-2 text-sm text-gray-700 dark:text-gray-300';
    $tdNum = 'px-3 py-2 text-end text-sm tabular-nums text-gray-700 dark:text-gray-300';
@endphp

<x-filament-panels::page>
    <div class="max-w-xs">
        {{ $this->form }}
    </div>

    {{-- Totals --}}
    <x-filament::section :heading="'Résumé — ' . $monthLabel">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Ventes</dt>
                <dd class="text-xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ Money::format($sales) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Achats du mois</dt>
                <dd class="text-xl font-semibold tabular-nums text-gray-950 dark:text-white">− {{ Money::format($purchases) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Charges fixes (mensualisées)</dt>
                <dd class="text-xl font-semibold tabular-nums text-gray-950 dark:text-white">− {{ Money::format($fixed) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Bénéfice net</dt>
                <dd @class([
                    'text-xl font-bold tabular-nums',
                    'text-success-600 dark:text-success-400' => $netProfit >= 0,
                    'text-danger-600 dark:text-danger-400' => $netProfit < 0,
                ])>{{ Money::format($netProfit) }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Bénéfice net = ventes − achats du mois − charges fixes mensualisées (comptabilité de caisse). Les commandes annulées sont exclues.
        </p>
    </x-filament::section>

    {{-- Per-product sales --}}
    <x-filament::section heading="Ventes par produit">
        @if ($products->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune vente ce mois-ci.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="{{ $th }}">Produit</th>
                            <th class="{{ $thNum }}">Qté</th>
                            <th class="{{ $thNum }}">Chiffre d'affaires</th>
                            <th class="{{ $thNum }}">Coût estimé</th>
                            <th class="{{ $thNum }}">Marge estimée</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($products as $row)
                            <tr>
                                <td class="{{ $td }}">{{ $row->product_name }}</td>
                                <td class="{{ $tdNum }}">{{ $row->quantity }}</td>
                                <td class="{{ $tdNum }}">{{ Money::format($row->revenue) }}</td>
                                <td class="{{ $tdNum }}">
                                    @if ($row->incomplete)
                                        <span class="text-warning-600 dark:text-warning-400" title="Recette incomplète : coût estimé (minimum)">~</span>
                                    @endif
                                    {{ Money::format($row->estimated_cost) }}
                                </td>
                                <td class="{{ $tdNum }}">
                                    @if ($row->incomplete)
                                        <span class="text-warning-600 dark:text-warning-400">~</span>
                                    @endif
                                    {{ Money::format($row->margin) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-white/20">
                            <td class="{{ $th }}">Total</td>
                            <td class="{{ $thNum }}">{{ $products->sum('quantity') }}</td>
                            <td class="{{ $thNum }}">{{ Money::format($products->sum('revenue')) }}</td>
                            <td class="{{ $thNum }}">{{ Money::format($products->sum('estimated_cost')) }}</td>
                            <td class="{{ $thNum }}">{{ Money::format($products->sum('margin')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($products->contains('incomplete', true))
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    ~ = estimé : la recette est incomplète (quantités inconnues), le coût réel est plus élevé.
                </p>
            @endif
        @endif
    </x-filament::section>

    {{-- Fixed expenses --}}
    <x-filament::section heading="Charges fixes du mois" collapsible>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-white/10">
                <thead>
                    <tr>
                        <th class="{{ $th }}">Charge</th>
                        <th class="{{ $thNum }}">Montant</th>
                        <th class="{{ $thNum }}">Période</th>
                        <th class="{{ $thNum }}">Par mois</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($fixedLines as $line)
                        <tr>
                            <td class="{{ $td }}">{{ $line->name }}</td>
                            <td class="{{ $tdNum }}">{{ Money::format($line->amount) }}</td>
                            <td class="{{ $tdNum }}">{{ $line->period_months }} mois</td>
                            <td class="{{ $tdNum }}">{{ Money::format($line->monthly) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 dark:border-white/20">
                        <td class="{{ $th }}" colspan="3">Total</td>
                        <td class="{{ $thNum }}">{{ Money::format($fixed) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>

    {{-- Stock movements --}}
    <x-filament::section heading="Mouvements de stock" collapsible>
        @if ($stock->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucun mouvement de stock ce mois-ci.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="{{ $th }}">Ingrédient</th>
                            <th class="{{ $thNum }}">Entrées</th>
                            <th class="{{ $thNum }}">Sorties</th>
                            <th class="{{ $thNum }}">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($stock as $row)
                            <tr>
                                <td class="{{ $td }}">{{ $row->name }}</td>
                                <td class="{{ $tdNum }}">{{ $qty($row->in) }} {{ $row->unit }}</td>
                                <td class="{{ $tdNum }}">{{ $qty($row->out) }} {{ $row->unit }}</td>
                                <td class="{{ $tdNum }}">{{ $qty($row->net) }} {{ $row->unit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
