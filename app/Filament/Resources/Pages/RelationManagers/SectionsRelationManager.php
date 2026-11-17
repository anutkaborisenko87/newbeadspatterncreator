<?php

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Filament\Support\SectionFormBuilder;
use App\Models\PageSection;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                TextColumn::make('slug')
                    ->label('Section')
                    ->formatStateUsing(
                        fn(?string $state): string => config("page_sections.{$state}.label")
                            ?? $state
                            ?? 'Unknown section'
                    ),

                TextColumn::make('order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->fillForm(function (PageSection $record): array {
                            $data = $record->attributesToArray();

                            $data['section_type'] = $record->slug;

                            $data['translations'] = $record->translations()
                                ->get()
                                ->mapWithKeys(fn ($translation) => [
                                    $translation->language_id => $translation->translation ?? [],
                                ])
                                ->all();

                            return $data;
                        }),
                    DeleteAction::make()
                        ->requiresConfirmation(),
                ])
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('slug')
                    ->label('Section type')
                    ->options(
                        collect(config('page_sections', []))
                            ->mapWithKeys(fn($section, $key) => [
                                $key => $section['label'] ?? $key,
                            ])
                            ->all()
                    )
                    ->required()
                    ->live()
                    ->native(false),

                Section::make('Section content')
                    ->schema(fn(Get $get): array => SectionFormBuilder::build($get('slug'))
                    )
                    ->visible(fn(Get $get): bool => filled($get('slug'))
                    )
                    ->columnSpanFull(),
            ]);
    }
}
