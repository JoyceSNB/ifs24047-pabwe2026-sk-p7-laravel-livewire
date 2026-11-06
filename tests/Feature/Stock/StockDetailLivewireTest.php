<?php

namespace Tests\Feature\Stock;

use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StockDetailLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stock_detail(): void
    {
        $stock = Stock::factory()->create();

        $this->get("/stocks/{$stock->id}")->assertRedirect(route('login'));
    }

    public function test_owner_can_open_detail_page(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang Detail Saya']);

        $this->actingAs($user)
            ->get("/stocks/{$stock->id}")
            ->assertOk()
            ->assertSee('Barang Detail Saya');
    }

    public function test_member_cannot_view_other_members_stock(): void
    {
        $owner = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->get("/stocks/{$stock->id}")
            ->assertForbidden();
    }

    public function test_admin_can_view_but_not_modify_stock(): void
    {
        $owner = User::factory()->member()->create();
        $admin = User::factory()->admin()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id, 'name' => 'Barang Milik Member']);

        $this->actingAs($admin);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSee('Barang Milik Member')
            ->call('openEditModal')
            ->assertForbidden();

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('adjustQuantity', 1)
            ->assertForbidden();

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('deleteStock')
            ->assertForbidden();

        $this->assertDatabaseHas('stocks', ['id' => $stock->id]);
    }

    public function test_edit_modal_save_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nama Awal',
            'quantity' => 50,
            'price' => null,
        ]);

        // Validation error
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->assertSet('showEditModal', true)
            ->assertSet('name', 'Nama Awal')
            ->set('name', 'ab')
            ->set('quantity', '-5')
            ->set('minStock', 'x')
            ->set('price', 'gratis')
            ->call('saveStock')
            ->assertHasErrors(['name', 'quantity', 'minStock', 'price']);

        // Success
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'Nama Baru')
            ->set('sku', 'NEW-001')
            ->set('description', 'Deskripsi **tebal**')
            ->set('quantity', '2')
            ->set('unit', 'box')
            ->set('price', '5000')
            ->set('minStock', '5')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Baru',
            'sku' => 'NEW-001',
            'quantity' => 2,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
    }

    public function test_save_with_empty_optional_fields(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id, 'sku' => 'ADA-001', 'price' => 1000]);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('sku', '')
            ->set('price', '')
            ->call('saveStock')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'sku' => null,
            'price' => null,
        ]);
    }

    public function test_close_edit_modal(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->call('closeEditModal')
            ->assertSet('showEditModal', false);
    }

    public function test_adjust_quantity(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'quantity' => 6,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('adjustQuantity', -1)
            ->assertSet('quantity', '5');

        $stock->refresh();
        $this->assertEquals(5, $stock->quantity);
        $this->assertEquals('low_stock', $stock->status);
    }

    public function test_cover_photo_upload_and_delete(): void
    {
        Storage::fake('public');

        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $component = Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', UploadedFile::fake()->image('cover.jpg'))
            ->assertHasNoErrors();

        $stock->refresh();
        $this->assertNotNull($stock->cover_image_path);
        Storage::disk('public')->assertExists($stock->cover_image_path);
        $path = $stock->cover_image_path;

        $component
            ->call('confirmDeleteCover')
            ->assertSet('confirmingCoverDeletion', true)
            ->call('cancelDeleteCover')
            ->assertSet('confirmingCoverDeletion', false)
            ->call('confirmDeleteCover')
            ->call('deleteCoverPhoto')
            ->assertSet('confirmingCoverDeletion', false);

        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_cover_photo_must_be_an_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'))
            ->assertHasErrors(['coverPhoto']);
    }

    public function test_confirm_cancel_and_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('confirmDeleteStock')
            ->assertSet('confirmingStockDeletion', true)
            ->call('cancelDeleteStock')
            ->assertSet('confirmingStockDeletion', false)
            ->call('confirmDeleteStock')
            ->call('deleteStock')
            ->assertRedirect(route('stocks.index'));

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }
}