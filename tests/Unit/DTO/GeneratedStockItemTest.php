<?php

namespace Tests\Unit\DTO;

use App\Features\Ai\DTO\GeneratedStockItem;
use PHPUnit\Framework\TestCase;

class GeneratedStockItemTest extends TestCase
{
    public function test_dto_properties_and_to_array(): void
    {
        $dto = new GeneratedStockItem(
            name: 'Laptop Lenovo',
            sku: 'LPT-001',
            description: 'Laptop untuk kantor',
            quantity: 25,
            unit: 'unit',
            price: 12500000.0,
            minStock: 4,
        );

        $this->assertEquals('Laptop Lenovo', $dto->name);
        $this->assertEquals('LPT-001', $dto->sku);
        $this->assertEquals(25, $dto->quantity);

        $this->assertEquals([
            'name' => 'Laptop Lenovo',
            'sku' => 'LPT-001',
            'description' => 'Laptop untuk kantor',
            'quantity' => 25,
            'unit' => 'unit',
            'price' => 12500000.0,
            'min_stock' => 4,
        ], $dto->toArray());
    }

    public function test_dto_default_values(): void
    {
        $dto = new GeneratedStockItem(name: 'Barang');

        $this->assertNull($dto->sku);
        $this->assertNull($dto->description);
        $this->assertEquals(0, $dto->quantity);
        $this->assertEquals('pcs', $dto->unit);
        $this->assertNull($dto->price);
        $this->assertEquals(5, $dto->minStock);
    }
}