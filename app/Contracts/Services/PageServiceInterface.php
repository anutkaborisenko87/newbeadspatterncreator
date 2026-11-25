<?php

namespace App\Contracts\Services;

use App\DTO\PageDataDTO;
use App\Models\User;

interface PageServiceInterface
{
    /**
     * Retrieves page data for the specified slug.
     *
     * @param string $slug The unique page slug.
     * @return PageDataDTO|null The page data, or null when no matching page exists.
     */
    public function getPageData(string $slug): ?PageDataDTO;

}
