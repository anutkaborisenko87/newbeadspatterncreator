<?php

namespace App\Contracts\Services;

interface PasswordServiceInterface
{
    /**
     * Generates a random password with the specified length.
     *
     * @param int $length The desired password length.
     * @return string The generated password.
     */
    public function generatePassword(int $length = 12): string;

    /**
     * Hashes the given plain-text password.
     *
     * @param string $password The plain-text password to hash.
     * @return string The hashed password.
     */
    public function hashPassword(string $password): string;

}
