<?php

namespace App\Models\Traits;

use App\Http\Middleware\LocaleMiddleware;
use App\Models\Language;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait Translate
{
    public function translations(): HasMany
    {
        return $this->hasMany('App\\Models\\'.class_basename($this).'Lang');
    }

    public function translate(int $lang_id = 0): HasOne
    {
        if ($lang_id === 0) {
            $locale = LocaleMiddleware::getLocale();
            $language = !is_null($locale) ? (Language::where('slug', $locale)->first() ?? Language::where('default', true)->first()) : Language::where('default', true)->first();
            $lang_id = $language->id;
        }

        $modelName = class_basename($this);
        $translationClass = "App\\Models\\{$modelName}Lang";

        return $this->hasOne($translationClass)->where('language_id', $lang_id);
    }

}
