<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Language;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
            Section::make('Page settings')
                ->schema([
                    TextInput::make('slug')
                        ->label('Slug')
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Toggle::make('home')
                        ->label('Home page')
                        ->default(false),

                    Toggle::make('status')
                        ->label('Published')
                        ->default(true),

                    DatePicker::make('publish_up')
                        ->label('Publish date')
                        ->default('1970-01-01'),

                ])
                ->columns(1),

            Tabs::make('Translations')
                ->tabs(
                    Language::getAllEnableLanguages()
                        ->map(fn (Language $language) =>
                        Tab::make($language->title)
                            ->schema([
                                TextInput::make("translations.{$language->id}.title")
                                    ->label('Title')
                                    ->required()
                                    ->maxLength(50),

                                TextInput::make("translations.{$language->id}.intro")
                                    ->label('Intro')
                                    ->maxLength(255),

                                TextInput::make("translations.{$language->id}.seo_title")
                                    ->label('SEO title')
                                    ->maxLength(150),

                                Textarea::make("translations.{$language->id}.meta_description")
                                    ->label('Meta description'),

                                TextInput::make("translations.{$language->id}.meta_robots")
                                    ->label('Meta robots'),
                            ])
                        )
                        ->all()
                )
                ->columnSpanFull(),
        ]);
    }
}
