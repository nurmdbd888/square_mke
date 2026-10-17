<?php

namespace App\Domain\Export;

use App\Domain\Export\Sources\PlReportExportSource;
use App\Domain\Export\Sources\ProductExportSource;
use App\Domain\Export\Sources\RetailerExportSource;
use InvalidArgumentException;

final class ExportRegistry
{
    public const KEYS = ['products', 'retailers', 'reports_pl'];

    /**
     * @return array<string, array{permission: string, title: string, source: class-string<ExportSource>}>
     */
    public static function definitions(): array
    {
        return [
            'products' => [
                'permission' => 'products.view',
                'title' => 'Products',
                'source' => ProductExportSource::class,
            ],
            'retailers' => [
                'permission' => 'retailers.view',
                'title' => 'Retailers',
                'source' => RetailerExportSource::class,
            ],
            'reports_pl' => [
                'permission' => 'reports.view',
                'title' => 'Profit and loss',
                'source' => PlReportExportSource::class,
            ],
        ];
    }

    public static function get(string $key): array
    {
        $def = self::definitions()[$key] ?? null;
        if ($def === null) {
            throw new InvalidArgumentException("Unknown export: {$key}");
        }

        return $def;
    }

    public static function source(string $key): ExportSource
    {
        $def = self::get($key);

        return app($def['source']);
    }
}
