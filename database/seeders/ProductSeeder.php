<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'code' => 'PRD001',
                'name' => 'Pensil 2B',
                'description' => 'Pensil kualitas terbaik untuk ujian dan menggambar.',
                'price' => 2500.00,
                'stock' => 100,
                'unit' => 'pcs',
                'image' => 'pensil2b.jpg',
            ],
            [
                'code' => 'PRD002',
                'name' => 'Buku Tulis',
                'description' => 'Buku tulis 38 lembar, cocok untuk pelajar.',
                'price' => 4000.00,
                'stock' => 50,
                'unit' => 'pcs',
                'image' => 'buku_tulis.jpg',
            ],
            [
                'code' => 'PRD003',
                'name' => 'Penghapus',
                'description' => 'Penghapus putih bersih, tidak merusak kertas.',
                'price' => 1500.00,
                'stock' => 200,
                'unit' => 'pcs',
                'image' => 'penghapus.jpg',
            ],
            [
                'code' => 'PRD004',
                'name' => 'Pulpen Biru',
                'description' => 'Pulpen tinta biru yang nyaman digunakan.',
                'price' => 3000.00,
                'stock' => 120,
                'unit' => 'pcs',
                'image' => 'pulpen_biru.jpg',
            ],
            [
                'code' => 'PRD005',
                'name' => 'Penggaris 30cm',
                'description' => 'Penggaris plastik 30cm, awet dan tahan lama.',
                'price' => 5000.00,
                'stock' => 80,
                'unit' => 'pcs',
                'image' => 'penggaris_30cm.jpg',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}