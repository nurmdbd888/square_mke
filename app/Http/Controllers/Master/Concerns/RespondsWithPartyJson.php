<?php

namespace App\Http\Controllers\Master\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithPartyJson
{
    protected function partyJson(Model $party, string $codeAttr = 'code', string $nameAttr = 'name'): JsonResponse
    {
        return response()->json([
            'id' => $party->getKey(),
            'code' => (string) ($party->getAttribute($codeAttr) ?? ''),
            'name' => (string) $party->getAttribute($nameAttr),
        ]);
    }

    protected function wantsPartyJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson() || $request->ajax();
    }
}
