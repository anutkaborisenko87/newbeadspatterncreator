<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CategoryNameTranslator
{
    public function translate(string $name, string $source, iterable $languages): array
    {
        $translations = [];
        foreach ($languages as $language) {
            $translations[$language->id] = $language->slug === $source
                ? $name
                : $this->translateName($name, $source, $language->slug);
        }

        return $translations;
    }

    private function translateName(string $name, string $source, string $target): string
    {
        try {
            $response = Http::acceptJson()->connectTimeout(4)->timeout(8)
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $name,
                    'langpair' => "$source|$target",
                ]);
        } catch (ConnectionException) {
            throw $this->unavailable();
        }

        $text = $response->json('responseData.translatedText');
        if (!$response->successful() || (int) $response->json('responseStatus') !== 200 || !is_string($text)) {
            throw $this->unavailable();
        }

        $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '' || mb_strlen($text) > 255) {
            throw $this->unavailable();
        }

        return $text;
    }

    private function unavailable(): ValidationException
    {
        return ValidationException::withMessages(['categoryName' => __('main.gallery_publication.translation_error')]);
    }
}
