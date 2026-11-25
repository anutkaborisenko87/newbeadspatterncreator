<?php

namespace App\Contracts\Services;

use App\DTO\AuthProfileDataDTO;

interface ProfileServiceInterface
{
    /**
     * Retrieves the profile page data for the application.
     *
     * @return array Profile page data.
     */
    public function getProfilePage(): array;

    /**
     * Retrieves the authenticated user's profile data.
     *
     * @return AuthProfileDataDTO Authenticated user profile data.
     */
    public function getAuthUserData(): AuthProfileDataDTO;

    /**
     * Updates the authenticated user's profile data.
     *
     * @param array $userData User data to update.
     * @return AuthProfileDataDTO Updated authenticated user profile data.
     */
    public function updateAuthUserData(array $userData): AuthProfileDataDTO;

}
