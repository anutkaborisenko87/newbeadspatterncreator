<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Support\Collection;

interface PageSectionsServiceInterface
{
    /**
     * Retrieve the collection of page sections.
     *
     * @return Collection
     */
    public function getPageSections(): Collection;

    /**
     * Fetch the gallery author section for a specific user.
     *
     * @param string $userId The unique identifier of the user.
     * @return array
     */
    public function getGalleryAuthorSection(string $userId): array;

    /**
     * Retrieves the profile title section as an array.
     *
     * @return array
     */
    public static function getProfileTitleSection(): array;

    /**
     * Retrieves the profile user section as an array for the specified user.
     *
     * @param User $user The user whose profile section is to be retrieved.
     * @return array
     */
    public static function getProfileUserSection(User $user): array;
}
