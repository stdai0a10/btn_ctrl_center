<?php

namespace App\Support;

final class ProductModelNumber
{
    public static function normalize(string $modelNumber): string
    {
        return mb_strtoupper(trim($modelNumber));
    }
}
