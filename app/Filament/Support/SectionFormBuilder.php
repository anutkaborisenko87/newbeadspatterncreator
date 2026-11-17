<?php

namespace App\Filament\Support;

use App\Models\Language;
use App\Models\PageSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class SectionFormBuilder
{
    public static function build(?string $sectionType, ?PageSection $record = null): array
    {
        if (! $sectionType) {
            return [];
        }

        $rules = $record?->slug === $sectionType
            ? json_decode($record->validation_rules, true, flags: JSON_THROW_ON_ERROR)
            : config("page_sections.{$sectionType}.validation_rules", []);

        return [
            Tabs::make('Translations')
                ->tabs(
                    Language::getAllEnableLanguages()
                        ->map(fn (Language $language) =>
                        Tab::make($language->title)
                            ->schema(
                                self::buildFields(
                                    $rules,
                                    "translations.{$language->id}"
                                )
                            )
                        )
                        ->all()
                )
                ->columnSpanFull(),
        ];
    }

    private static function buildFields(
        array $rules,
        string $prefix
    ): array {
        $fields = [];

        foreach ($rules as $name => $type) {

            $path = "{$prefix}.{$name}";
            if (is_array($type)) {
                array_push(
                    $fields,
                    ...self::buildFields($type, $path)
                );

                continue;
            }

            $label = str($name)
                ->replace('_', ' ')
                ->title()
                ->toString();

            $fields[] = match ($type) {
                'string' => TextInput::make($path)
                    ->label($label)
                    ->maxLength(255),

                'text' => Textarea::make($path)
                    ->label($label)
                    ->rows(5),

                'text_editor' => RichEditor::make($path)
                    ->label($label),

                'function' => Select::make($path)
                    ->label($label)
                    ->options(config('section_functions', []))
                    ->searchable()
                    ->native(false),

                default => TextInput::make($path)
                    ->label($label),
            };
        }

        return $fields;
    }
}
