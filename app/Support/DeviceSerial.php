<?php

namespace App\Support;

final class DeviceSerial
{
    public static function normalize(string $serialNumber): string
    {
        return mb_strtoupper(trim($serialNumber));
    }

    private function __construct() {}
}
