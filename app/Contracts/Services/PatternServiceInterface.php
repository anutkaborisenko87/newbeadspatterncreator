<?php

namespace App\Contracts\Services;

interface PatternServiceInterface
{
    public function getTopRatedPreviews(): array;

    public function getFilteredPatternsForCommonGallery(): array;
    public function getFilteredPatternsForAuthorGalery(string $userId): array;
    public function getAuthorPatterns(int $authUserId, bool $draft): array;

    public function getLikedPatterns(int $authUserId): array;
}
