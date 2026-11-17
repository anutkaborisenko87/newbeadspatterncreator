<?php

namespace App\Models;

use App\Models\Traits\Translate;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id', 'created_at', 'updated_at'])]
class PageSection extends Model
{
    use Translate;

    public function saveWithTranslations(array $data): static
    {
        return $this->getConnection()->transaction(function () use ($data): static {
            if ($this->exists) {
                $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            }

            $this->slug = $data['slug'];

            if ($this->isDirty('slug')) {
                $this->validation_rules = json_encode(
                    config("page_sections.{$this->slug}.validation_rules", []),
                    JSON_THROW_ON_ERROR,
                );
            }

            $this->save();

            foreach ($data['translations'] ?? [] as $languageId => $fields) {
                $translation = $this->translations()->firstOrNew(['language_id' => $languageId]);
                $stored = json_decode($translation->translation ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                $translation->translation = json_encode(array_replace_recursive($stored, $fields), JSON_THROW_ON_ERROR);
                $translation->save();
            }

            return $this;
        });
    }
}
