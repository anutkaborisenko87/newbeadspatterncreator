<?php

namespace App\Models;

use App\Http\Middleware\LocaleMiddleware;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

#[Guarded(['id', 'created_at', 'updated_at'])]
class Language extends Model
{
    public static function getAllEnableLanguages()
    {
        return self::where('status', 1)->get();
    }

    public static function getDefaultValue()
    {
        return self::where('default', 1)->first();
    }

    public static function getDropdownList()
    {
        $languages = self::getAllEnableLanguages()->toArray();
        $currentLocale = LocaleMiddleware::getLocale();;

        return array_map(function($item) use ($currentLocale) {
            return [
                'id' => $item['id'],
                'slug' => $item['slug'],
                'title' => $item['title'],
                'name' => $item['name'],
                'current' => !is_null($currentLocale) ? $item['slug'] === $currentLocale : $item['default']
            ];
        }, $languages);
    }

    public static function getCurrent()
    {
        $locale = LocaleMiddleware::getLocale();
        return !is_null($locale) ? self::where('slug', $locale)->first() : self::getDefaultValue();
    }
}
