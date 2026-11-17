<?php

namespace App\Contracts\Services;

interface PasswordServiceInterface
{
    public function generatePassword(int $length = 12): string;
    public function hashPassword(string $password): string;

}
