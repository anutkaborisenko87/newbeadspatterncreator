<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\ListCommentReports;
use App\Models\CommentReport;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentReportResource extends Resource
{
    protected static ?string $model = CommentReport::class;

    public static function getPluralModelLabel(): string
    {
        return __('reports.admin');
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('reported_login')->label(__('reports.target'))->searchable(),
            TextColumn::make('reporter.login')->label(__('reports.reporter')),
            TextColumn::make('topic')->label(__('reports.topic'))->formatStateUsing(fn ($state) => __('reports.topics.'.$state)),
            TextColumn::make('status')->label(__('reports.status'))->formatStateUsing(fn ($state) => __('reports.statuses.'.$state)),
            TextColumn::make('created_at')->dateTime(),
        ])->recordActions([
            Action::make('details')->label(__('reports.details'))
                ->modalContent(fn (CommentReport $record) => view('filament.report-details', ['report' => $record]))
                ->modalSubmitAction(false),
            Action::make('review')->label(__('reports.review'))->visible(fn (CommentReport $record) => $record->status === 'pending')
                ->modalContent(fn (CommentReport $record) => view('filament.report-details', ['report' => $record]))
                ->schema([
                    Select::make('status')->label(__('reports.status'))->required()->options([
                        'upheld' => __('reports.statuses.upheld'), 'dismissed' => __('reports.statuses.dismissed'),
                    ]),
                    Textarea::make('decision')->label(__('reports.decision'))->required()->maxLength(4000),
                ])->action(fn (CommentReport $record, array $data) => $record->review($data['status'], $data['decision'])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCommentReports::route('/')];
    }
}
