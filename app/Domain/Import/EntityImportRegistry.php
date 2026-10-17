<?php

namespace App\Domain\Import;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\SalesRep;
use App\Models\SalesRoute;
use App\Models\Supplier;
use App\Models\Vehicle;

/**
 * Column templates and upsert rules for each importable list.
 *
 * @phpstan-type ColumnDef array{key: string, label: string, required?: bool}
 */
final class EntityImportRegistry
{
    public const ENTITIES = [
        'products', 'retailers', 'suppliers', 'routes', 'sales_reps', 'vehicles', 'price_lists', 'price_list_items',
    ];

    /**
     * @return array<string, array{
     *     permission: string,
     *     model: class-string,
     *     unique: string,
     *     columns: list<ColumnDef>,
     *     list_route: string,
     *     title: string
     * }>
     */
    public static function definitions(): array
    {
        return [
            'products' => [
                'permission' => 'products.manage',
                'model' => Product::class,
                'unique' => 'code',
                'title' => 'Products',
                'list_route' => 'products.index',
                'columns' => [
                    ['key' => 'code', 'label' => 'Product code', 'required' => true],
                    ['key' => 'name_en', 'label' => 'Product name', 'required' => true],
                    ['key' => 'name_bn', 'label' => 'Bangla name'],
                    ['key' => 'brand', 'label' => 'Brand'],
                    ['key' => 'pack_size', 'label' => 'Pack size'],
                    ['key' => 'unit', 'label' => 'Unit'],
                    ['key' => 'buy_price', 'label' => 'Buy price (TP)'],
                    ['key' => 'sell_price', 'label' => 'Sell price'],
                    ['key' => 'sd_pct', 'label' => 'SD %'],
                    ['key' => 'vat_pct', 'label' => 'VAT %'],
                ],
            ],
            'retailers' => [
                'permission' => 'retailers.manage',
                'model' => Retailer::class,
                'unique' => 'code',
                'title' => 'Retailers',
                'list_route' => 'retailers.index',
                'columns' => [
                    ['key' => 'code', 'label' => 'Retailer code', 'required' => true],
                    ['key' => 'name', 'label' => 'Name', 'required' => true],
                    ['key' => 'address', 'label' => 'Address'],
                    ['key' => 'phone', 'label' => 'Phone'],
                    ['key' => 'route_code', 'label' => 'Route code'],
                    ['key' => 'sales_rep_code', 'label' => 'SR code'],
                    ['key' => 'credit_limit', 'label' => 'Credit limit'],
                ],
            ],
            'suppliers' => [
                'permission' => 'masters.manage',
                'model' => Supplier::class,
                'unique' => 'code',
                'title' => 'Suppliers',
                'list_route' => 'masters.directory.index',
                'list_route_params' => ['suppliers'],
                'columns' => [
                    ['key' => 'code', 'label' => 'Supplier code', 'required' => true],
                    ['key' => 'name', 'label' => 'Name', 'required' => true],
                    ['key' => 'bin', 'label' => 'BIN'],
                    ['key' => 'address', 'label' => 'Address'],
                    ['key' => 'phone', 'label' => 'Phone'],
                ],
            ],
            'routes' => [
                'permission' => 'masters.manage',
                'model' => SalesRoute::class,
                'unique' => 'code',
                'title' => 'Routes',
                'list_route' => 'masters.directory.index',
                'list_route_params' => ['routes'],
                'columns' => [
                    ['key' => 'code', 'label' => 'Route code', 'required' => true],
                    ['key' => 'name', 'label' => 'Name', 'required' => true],
                ],
            ],
            'sales_reps' => [
                'permission' => 'masters.manage',
                'model' => SalesRep::class,
                'unique' => 'code',
                'title' => 'Sales reps',
                'list_route' => 'masters.directory.index',
                'list_route_params' => ['sales-reps'],
                'columns' => [
                    ['key' => 'code', 'label' => 'SR code', 'required' => true],
                    ['key' => 'name', 'label' => 'Name', 'required' => true],
                    ['key' => 'phone', 'label' => 'Phone'],
                ],
            ],
            'vehicles' => [
                'permission' => 'masters.manage',
                'model' => Vehicle::class,
                'unique' => 'registration',
                'title' => 'Vehicles',
                'list_route' => 'masters.directory.index',
                'list_route_params' => ['vehicles'],
                'columns' => [
                    ['key' => 'registration', 'label' => 'Registration', 'required' => true],
                    ['key' => 'type', 'label' => 'Vehicle type', 'required' => true],
                ],
            ],
            'price_lists' => [
                'permission' => 'masters.manage',
                'model' => PriceList::class,
                'unique' => 'name',
                'title' => 'Price lists',
                'list_route' => 'masters.price-lists.index',
                'columns' => [
                    ['key' => 'name', 'label' => 'Price list name', 'required' => true],
                ],
            ],
            'price_list_items' => [
                'permission' => 'masters.manage',
                'model' => PriceListItem::class,
                'unique' => 'composite',
                'title' => 'Price list items',
                'list_route' => 'masters.price-lists.index',
                'columns' => [
                    ['key' => 'price_list_name', 'label' => 'Price list name', 'required' => true],
                    ['key' => 'product_code', 'label' => 'Product code', 'required' => true],
                    ['key' => 'price', 'label' => 'Price', 'required' => true],
                    ['key' => 'effective_from', 'label' => 'Effective from (YYYY-MM-DD)'],
                ],
            ],
        ];
    }

    public static function has(string $entity): bool
    {
        return isset(self::definitions()[$entity]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $entity): array
    {
        return self::definitions()[$entity] ?? throw new \InvalidArgumentException("Unknown import entity: {$entity}");
    }

    /**
     * @return list<string>
     */
    public static function templateHeaders(string $entity): array
    {
        return array_map(fn ($c) => $c['label'], self::get($entity)['columns']);
    }
}
