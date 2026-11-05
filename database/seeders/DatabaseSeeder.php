<?php

namespace Database\Seeders;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('admin'),
                'role' => 'admin',
            ]
        );

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member User',
                'username' => 'member',
                'password' => Hash::make('member'),
                'role' => 'member',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'name' => 'Laptop Lenovo ThinkPad'],
            [
                'sku' => 'LPT-001',
                'description' => 'Laptop untuk kebutuhan kantor dengan **RAM 16 GB**.',
                'quantity' => 25,
                'unit' => 'unit',
                'price' => 12500000,
                'min_stock' => 5,
                'status' => 'available',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'name' => 'Mouse Wireless Logitech'],
            [
                'sku' => 'MSE-002',
                'description' => 'Mouse nirkabel dengan baterai tahan lama.',
                'quantity' => 3,
                'unit' => 'pcs',
                'price' => 250000,
                'min_stock' => 5,
                'status' => 'low_stock',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'name' => 'Keyboard Mechanical'],
            [
                'sku' => 'KBD-003',
                'description' => 'Keyboard mekanikal layout 75%.',
                'quantity' => 0,
                'unit' => 'pcs',
                'price' => 750000,
                'min_stock' => 5,
                'status' => 'out_of_stock',
            ]
        );
    }
}
