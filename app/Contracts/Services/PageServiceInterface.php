<?php

namespace App\Contracts\Services;

use App\DTO\PageDataDTO;
use App\Models\User;

interface PageServiceInterface
{
    public function getPageData(string $slug): ?PageDataDTO;

}
