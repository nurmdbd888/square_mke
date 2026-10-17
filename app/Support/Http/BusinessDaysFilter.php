<?php

namespace App\Support\Http;

use Illuminate\Http\Request;

final class BusinessDaysFilter
{
    public static function fromRequest(Request $request): bool
    {
        return $request->boolean('business_days');
    }
}
