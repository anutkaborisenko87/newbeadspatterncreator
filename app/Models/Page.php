<?php

namespace App\Models;

use App\Http\Resources\PagesResource;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Translate;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Guarded(['id', 'created_at', 'updated_at'])]
class Page extends Model
{
    use Translate;

    public function saveWithTranslations(array $attributes): static
    {
        $translations = $attributes['translations'] ?? [];
        unset($attributes['translations']);

        return $this->getConnection()->transaction(function () use ($attributes, $translations): static {
            if ($this->exists) {
                $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            }

            $this->fill($attributes)->save();

            foreach ($translations as $languageId => $translation) {
                $this->translations()->updateOrCreate(['language_id' => $languageId], $translation);
            }

            return $this;
        });
    }

    public function adminTranslation(): HasOne
    {
        $languageId = Language::query()
            ->where('slug', app()->getLocale())
            ->value('id') ?? Language::getDefaultValue()?->id;

        return $this->hasOne(PageLang::class)->where('language_id', $languageId);
    }

    public static function getNavbarMenu(): array
    {
        $availablePages = self::orderBy('home', 'desc')->where('status', true)->with('translate')->get();
        return PagesResource::collection($availablePages)->resolve();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->with('translate');
    }
}
