<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductAlias;
use Illuminate\Database\Seeder;

/**
 * The 24 sample products (17 from the purchase challan, 7 seen only on a sales invoice).
 * Idempotent: matched by the candidate code (P01, S01, …).
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('seeders/data/products-seed.csv');
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            $buy = $this->decimal($data['buy_price_ex_vat']);
            $exact = $this->decimal($data['buy_price_ex_vat_exact']);
            $sell = $this->decimal($data['sell_price_seen']);

            $product = Product::query()->updateOrCreate(
                ['code' => $data['candidate_id']],
                [
                    'name_en' => $data['name_en'],
                    'name_bn' => $data['name_bn'] !== '' ? $data['name_bn'] : null,
                    'brand' => $data['brand'] !== '' ? $data['brand'] : null,
                    'pack_size' => $data['pack_size'] !== '' ? $data['pack_size'] : null,
                    'unit' => $data['unit'] !== '' ? $data['unit'] : 'Pcs',
                    'buy_price' => $buy,
                    'buy_price_exact' => $exact,
                    'sell_price' => $sell,
                    'sd_pct' => $this->decimal($data['sd_pct']) ?? '0',
                    'vat_pct' => $this->decimal($data['vat_pct']) ?? '15',
                ],
            );

            if ($data['name_bn'] !== '') {
                ProductAlias::query()->updateOrCreate(
                    ['product_id' => $product->id, 'name' => $data['name_bn']],
                );
            }
        }

        fclose($handle);
    }

    private function decimal(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
