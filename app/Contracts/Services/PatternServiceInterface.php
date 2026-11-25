<?php

namespace App\Contracts\Services;

use App\Models\Pattern;

interface PatternServiceInterface
{
    /**
     * Retrieves a pattern by its unique identifier.
     *
     * @param Pattern $pattern
     * @param int $owner
     * @return Pattern The pattern instance or null if not found.
     */
    public function getOrCreateRevisionDraft(Pattern $pattern, int $owner): Pattern;

    /**
     * Retrieves the top-rated pattern previews.
     *
     * @return array An array of top-rated pattern previews.
     */
    public function getTopRatedPreviews(): array;

    /**
     * Retrieves filtered patterns for the common gallery.
     *
     * @return array An array of filtered patterns.
     */
    public function getFilteredPatternsForCommonGallery(): array;

    /**
     * Retrieves filtered patterns for the author's gallery.
     *
     * @param string $userId The ID of the author.
     * @return array An array of filtered patterns.
     */
    public function getFilteredPatternsForAuthorGalery(string $userId): array;

    /**
     * Retrieves patterns from the owner's gallery.
     *
     * @param int $userId The ID of the owner.
     * @return array An array of patterns.
     */
    public function getOwnerGalleryPatterns(int $userId): array;

    /**
     * Retrieves patterns authored by a specific user.
     *
     * @param int $authUserId The ID of the authenticated user.
     * @param bool $draft Whether to include draft patterns.
     * @param int $perPage The number of patterns per page.
     * @return array An array of patterns authored by the user.
     */
    public function getAuthorPatterns(int $authUserId, bool $draft, int $perPage = 4): array;

    /**
     * Retrieves patterns liked by a specific user.
     *
     * @param int $authUserId The ID of the authenticated user.
     * @param bool $standalone Whether to retrieve standalone patterns.
     * @return array An array of patterns liked by the user.
     */
    public function getLikedPatterns(int $authUserId, bool $standalone = false): array;
}
