<?php

namespace App\Filament\Pages;

use App\Services\MonthlyReport as Report;
use App\Support\Months;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * @property-read Schema $form
 */
class MonthlyReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Rapport mensuel';

    protected static ?string $title = 'Rapport mensuel';

    protected static ?string $slug = 'rapport-mensuel';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.monthly-report';

    #[Url]
    public ?string $month = null;

    public function mount(): void
    {
        $this->month = Months::parse($this->month)->format('Y-m');
        $this->form->fill(['month' => $this->month]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('month')
                ->label('Mois')
                ->options(fn (): array => Months::options() + [$this->month => Months::label(Months::parse($this->month))])
                ->selectablePlaceholder(false)
                ->native(false)
                ->live(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $report = new Report(Months::parse($this->month));

        $sales = $report->totalSales();
        $purchases = $report->purchasesTotal();
        $fixed = $report->fixedExpensesTotal();

        return [
            'monthLabel' => Months::label($report->start),
            'products' => $report->productSales(),
            'sales' => $sales,
            'purchases' => $purchases,
            'fixedLines' => $report->fixedExpenseLines(),
            'fixed' => $fixed,
            'netProfit' => $report->netProfit(),
            'stock' => $report->stockMovements(),
        ];
    }
}
