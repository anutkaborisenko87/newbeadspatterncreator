<?php

namespace App\Filament\Resources\PatternReports;

use App\Filament\Resources\PatternReports\Pages\ListPatternReports;
use App\Models\PatternReport;
use App\Services\PatternReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PatternReportResource extends Resource
{
    protected static ?string $model = PatternReport::class;

    public static function getNavigationGroup(): string
    {
        return __('reports.navigation_group');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pattern_reports.heading');
    }

    public static function table(Table $table): Table
    {
        $details = fn (PatternReport $record) => view('filament.pattern-report', ['report' => $record]);
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('title')->label(__('main.gallery_publication.title'))->searchable(),
            TextColumn::make('author.login')->label(__('reports.target')),
            TextColumn::make('reporter.login')->label(__('reports.reporter')),
            TextColumn::make('topic')->label(__('reports.topic'))->formatStateUsing(fn ($state) => __('pattern_reports.topics.'.$state)),
            TextColumn::make('status')->label(__('reports.status'))->formatStateUsing(fn ($state) => __('reports.statuses.'.$state)),
        ])->recordActions([
            Action::make('details')->label(__('reports.details'))->modalContent($details)->modalSubmitAction(false),
            Action::make('review')->label(__('reports.review'))->modalContent($details)
                ->visible(fn (PatternReport $record) => $record->status === 'pending')
                ->schema([
                    Select::make('status')->label(__('reports.status'))->required()->live()->options([
                        'upheld' => __('reports.statuses.upheld'), 'dismissed' => __('reports.statuses.dismissed'),
                    ]),
                    Toggle::make('block')->label(__('pattern_reports.block'))
                        ->visible(fn ($get, PatternReport $record) => $record->pattern_id && $get('status') === 'upheld'),
                ])->action(fn (PatternReport $record, array $data, PatternReportService $service) =>
                    $service->review($record, $data['status'], $data['status'] === 'upheld' && !empty($data['block']))),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPatternReports::route('/')];
    }
}
