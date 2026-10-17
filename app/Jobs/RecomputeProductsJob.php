<?php

namespace App\Jobs;

use App\Domain\Costing\Recompute;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RecomputeProductsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(
        public array $productIds,
        public ?string $fromDate = null,
    ) {}

    public function handle(Recompute $recompute): void
    {
        foreach ($this->productIds as $productId) {
            $recompute->one((int) $productId, $this->fromDate);
        }

        if (! self::pending()) {
            cache()->forget('sdm.costing_pending');
        }
    }

    public static function pending(): bool
    {
        return DB::table('jobs')
            ->where('payload', 'like', '%RecomputeProductsJob%')
            ->exists();
    }
}
