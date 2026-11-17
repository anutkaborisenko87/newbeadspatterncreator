<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Page $record): bool => $record->deletable)
                ->requiresConfirmation(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['translations'] = $this->record
            ->translations()
            ->get()
            ->keyBy('language_id')
            ->map(fn ($translation) => [
                'title' => $translation->title,
                'intro' => $translation->intro,
                'seo_title' => $translation->seo_title,
                'meta_description' => $translation->meta_description,
                'meta_robots' => $translation->meta_robots,
            ])
            ->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $record->saveWithTranslations($data);
    }
}
