<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;
    protected StockRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new StockRepository();
        $this->service = new StockService($this->repository);
    }

    public function test_get_stocks_for_user(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(2)->create(['user_id' => $user->id]);

        $paginator = $this->service->getStocksForUser($user->id);
        $this->assertCount(2, $paginator);
    }

    public function test_create_stock_computes_status_when_not_given(): void
    {
        $user = User::factory()->create();

        $stock = $this->service->createStock($user->id, [
            'name' => 'Service Stock',
            'sku' => 'SRV-001',
            'quantity' => 3,
            'min_stock' => 5,
        ]);

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertEquals('Service Stock', $stock->name);
        $this->assertEquals('pcs', $stock->unit);
        $this->assertEquals(StockStatus::LOW_STOCK->value, $stock->status);

        $empty = $this->service->createStock($user->id, ['name' => 'Kosong']);
        $this->assertEquals(0, $empty->quantity);
        $this->assertEquals(5, $empty->min_stock);
        $this->assertEquals(StockStatus::OUT_OF_STOCK->value, $empty->status);
    }

    public function test_create_stock_uses_given_status(): void
    {
        $user = User::factory()->create();

        $stock = $this->service->createStock($user->id, [
            'name' => 'Manual Status',
            'quantity' => 100,
            'status' => 'low_stock',
        ]);

        $this->assertEquals('low_stock', $stock->status);
    }

    public function test_update_stock(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

        $updated = $this->service->updateStock($stock, [
            'name' => 'New Name',
            'description' => 'Updated Desc',
        ]);

        $this->assertTrue($updated);
        $stock->refresh();
        $this->assertEquals('New Name', $stock->name);
        $this->assertEquals('Updated Desc', $stock->description);
        $this->assertEquals('available', $stock->status); // status tidak berubah
    }

    public function test_update_stock_recalculates_status_when_quantity_changes(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'quantity' => 50,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        $this->service->updateStock($stock, ['quantity' => 2]);
        $stock->refresh();
        $this->assertEquals(2, $stock->quantity);
        $this->assertEquals('low_stock', $stock->status);

        $this->service->updateStock($stock, ['quantity' => 0, 'status' => 'available']);
        $stock->refresh();
        $this->assertEquals('available', $stock->status); // status eksplisit dihormati
    }

    public function test_adjust_quantity(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'quantity' => 6,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        $this->service->adjustQuantity($stock, -1);
        $stock->refresh();
        $this->assertEquals(5, $stock->quantity);
        $this->assertEquals('low_stock', $stock->status);

        $this->service->adjustQuantity($stock, -10);
        $stock->refresh();
        $this->assertEquals(0, $stock->quantity); // tidak boleh negatif
        $this->assertEquals('out_of_stock', $stock->status);

        $this->service->adjustQuantity($stock, 20);
        $stock->refresh();
        $this->assertEquals(20, $stock->quantity);
        $this->assertEquals('available', $stock->status);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $deleted = $this->service->deleteStock($stock);
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_cover_photo_management(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $fakeFile = UploadedFile::fake()->image('cover.jpg');

        // Update cover
        $path = $this->service->updateCoverPhoto($stock, $fakeFile);
        $stock->refresh();
        $this->assertEquals($path, $stock->cover_image_path);
        Storage::disk('public')->assertExists($path);

        // Update cover again (replaces old)
        $newFakeFile = UploadedFile::fake()->image('new-cover.jpg');
        $newPath = $this->service->updateCoverPhoto($stock, $newFakeFile);
        $stock->refresh();
        $this->assertEquals($newPath, $stock->cover_image_path);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($newPath);

        // Delete cover
        $deleted = $this->service->deleteCoverPhoto($stock);
        $this->assertTrue($deleted);
        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
        Storage::disk('public')->assertMissing($newPath);

        // Delete stock cleans up cover photo if present
        $anotherFile = UploadedFile::fake()->image('another.jpg');
        $anotherPath = $this->service->updateCoverPhoto($stock, $anotherFile);
        Storage::disk('public')->assertExists($anotherPath);

        $this->service->deleteStock($stock);
        Storage::disk('public')->assertMissing($anotherPath);
    }
}