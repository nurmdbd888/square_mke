<?php

namespace App\Support\Bulk;

use Illuminate\Http\Request;

final class BulkSelection
{
    /**
     * @return list<int>
     */
    public static function ids(Request $request): array
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        return array_values(array_map('intval', $data['ids']));
    }
}
