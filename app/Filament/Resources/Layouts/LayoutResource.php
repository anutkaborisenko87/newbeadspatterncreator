<?php

namespace App\Filament\Resources\Layouts;

use App\Filament\Resources\Layouts\Pages\EditLayout;
use App\Filament\Resources\Layouts\Pages\ListLayouts;
use App\Models\Language;
use App\Models\Layout;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LayoutResource extends Resource
{
    protected static ?string $model = Layout::class;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getModelLabel(): string
    {
        return __('admin.layouts.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.layouts.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('slug')->label(__('admin.layouts.slug'))->disabled()->dehydrated(false),
            TextInput::make('link')->label(__('admin.layouts.link'))->maxLength(255)
                ->regex('~^/(?!/)[^\x5c\s]*$~D'),
            Tabs::make(__('admin.layouts.translations'))->tabs(
                Language::getAllEnableLanguages()->map(fn (Language $language) =>
                    Tab::make($language->title)->schema([
                        TextInput::make("translations.{$language->id}.title")
                            ->label(__('admin.layouts.title'))->required()->maxLength(255),
                    ])
                )->all()
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('slug')->label(__('admin.layouts.slug'))->searchable()->sortable(),
            TextColumn::make('translations.title')->label(__('admin.layouts.title'))->listWithLineBreaks()->searchable(),
            TextColumn::make('link')->label(__('admin.layouts.link')),
        ])->defaultSort('slug')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLayouts::route('/'),
            'edit' => EditLayout::route('/{record}/edit'),
        ];
    }
}
