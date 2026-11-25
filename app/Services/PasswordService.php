<?php

namespace App\Services;

use App\Contracts\Services\PasswordServiceInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordService implements PasswordServiceInterface
{
    /**
     * @inheritDoc
     */
    public function generatePassword(int $length = 12): string
    {
        $uppercase = chr(random_int(65, 90));

        $lowercase = '';
        for ($i = 0; $i < $length - 4; $i++) {
            $lowercase .= chr(random_int(97, 122));
        }

        $digits = (string) random_int(10, 99);

        $specials = '!@#$%^&*()';
        $special = $specials[random_int(0, strlen($specials) - 1)];

        return str_shuffle(
            $uppercase .
            $lowercase .
            $digits .
            $special
        );
    }
    /**
     * @inheritDoc
     */
    public function hashPassword(string $password): string
    {
        return Hash::make($password);
    }
}
