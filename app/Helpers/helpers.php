<?php


function encodeModelId(int $id): string
{
    $encodedId =  base_convert((string) $id, 10, 36);
    $signature = substr(
        hash_hmac('sha256', $encodedId, config('app.key')),
        0,
        12
    );

    return $encodedId . $signature;
}

function decodeModelId(string $value): int
{
    $encodedId = substr($value, 0, -12);
    $signature = substr($value, -12);
    $expected = substr(
        hash_hmac('sha256', $encodedId, config('app.key')),
        0,
        12
    );
    if (!hash_equals($expected, $signature)) {
        abort(403, 'Invalid team id');
    }

    return (int) base_convert($encodedId, 36, 10);
}
