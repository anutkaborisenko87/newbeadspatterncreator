<?php

namespace App\Contracts\Services;

interface PatternServiceInterface
{
    public function getTopRatedPreviews(): array;

    public function getFilteredPatternsForCommonGallery(): array;
    public function getFilteredPatternsForAuthorGalery(string $userId): array;
    public function getOwnerGalleryPatterns(int $userId): array;
    public function getAuthorPatterns(int $authUserId, bool $draft, int $perPage = 4): array;

    public function getLikedPatterns(int $authUserId, bool $standalone = false): array;
}
