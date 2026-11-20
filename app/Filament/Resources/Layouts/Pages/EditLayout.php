<?php

namespace App\Filament\Resources\Layouts\Pages;

use App\Filament\Resources\Layouts\LayoutResource;
use App\Models\Language;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditLayout extends EditRecord
{
    protected static string $resource = LayoutResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['translations'] = $this->record->translations()
            ->get()->keyBy('language_id')
            ->map(fn ($translation) => ['title' => $translation->title])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $record->update(['link' => $data['link'] ?? null]);

            foreach (Language::getAllEnableLanguages() as $language) {
                $translation = $data['translations'][$language->id] ?? null;
                if ($translation !== null) {
                    $record->translations()->updateOrCreate(
                        ['language_id' => $language->id],
                        ['title' => $translation['title']],
                    );
                }
            }

            return $record;
        });
    }
}
