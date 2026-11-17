<?php

namespace App\Contracts\Services;

use App\DTO\AuthProfileDataDTO;

interface ProfileServiceInterface
{
    public function getProfilePage(): array;
    public function getAuthUserData(): AuthProfileDataDTO;
    public function updateAuthUserData(array $userData): AuthProfileDataDTO;

}
