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

        $this->get(route('stocks.show', $stock->id))
            ->assertRedirect(route('login'));
    }

    public function test_member_cannot_access_other_member_stock(): void
    {
        $owner = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other);

        $this->get(route('stocks.show', $stock->id))->assertStatus(403);
    }

    public function test_member_can_view_own_stock_detail(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Laptop Kantor',
            'sku' => 'LPT-001',
            'description' => '# Spesifikasi',
            'quantity' => 20,
            'unit' => 'unit',
            'price' => 12500000,
            'min_stock' => 4,
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertOk()
            ->assertSet('name', 'Laptop Kantor')
            ->assertSet('sku', 'LPT-001')
            ->assertSet('description', '# Spesifikasi')
            ->assertSet('quantity', '20')
            ->assertSet('unit', 'unit')
            ->assertSet('minStock', '4')
            ->assertSee('Laptop Kantor')
            ->assertSee('LPT-001');
    }

    public function test_detail_renders_stock_without_description_sku_and_price(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Barang Polos',
            'sku' => null,
            'description' => null,
            'price' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertOk()
            ->assertSet('description', '')
            ->assertSet('price', null)
            ->assertSee('Belum ada deskripsi untuk barang ini');
    }

    public function test_open_and_close_edit_modal(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nama Detail',
            'description' => 'Deskripsi lama',
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('showEditModal', false)
            ->call('openEditModal')
            ->assertSet('showEditModal', true)
            ->assertSet('name', 'Nama Detail')
            ->assertSet('description', 'Deskripsi lama')
            ->call('closeEditModal')
            ->assertSet('showEditModal', false);
    }

    public function test_update_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Nama Awal', 'quantity' => 50]);

        $this->actingAs($user);

        // Validasi gagal
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'ab')
            ->set('quantity', '-1')
            ->set('unit', '')
            ->set('minStock', 'x')
            ->set('price', '-1')
            ->call('saveStock')
            ->assertHasErrors(['name', 'quantity', 'unit', 'minStock', 'price']);

        // Berhasil
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'Nama Diperbarui')
            ->set('sku', '')
            ->set('description', '## Rincian Baru')
            ->set('quantity', '2')
            ->set('unit', 'box')
            ->set('price', '')
            ->set('minStock', '5')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Diperbarui',
            'sku' => null,
            'description' => '## Rincian Baru',
            'quantity' => 2,
            'unit' => 'box',
            'price' => null,
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
    }

    public function test_update_stock_keeps_sku_and_price_when_filled(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('sku', 'NEW-001')
            ->set('price', '9000')
            ->call('saveStock')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'sku' => 'NEW-001',
            'price' => 9000,
        ]);
    }

    public function test_adjust_quantity(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'quantity' => 6,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('adjustQuantity', -1)
            ->assertSet('quantity', '5');

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'quantity' => 5,
            'status' => 'low_stock',
        ]);
    }

    public function test_cover_photo_upload_and_delete(): void
    {
        Storage::fake('public');

        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'cover_image_path' => null]);

        $this->actingAs($user);

        // File bukan gambar
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', UploadedFile::fake()->create('document.pdf', 100))
            ->assertHasErrors(['coverPhoto']);

        // Gambar valid
        $component = Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', UploadedFile::fake()->image('cover.jpg', 800, 400))
            ->assertHasNoErrors();

        $stock->refresh();
        $this->assertNotNull($stock->cover_image_path);
        Storage::disk('public')->assertExists($stock->cover_image_path);

        // Hapus cover
        $component->call('deleteCoverPhoto');

        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
    }

    public function test_delete_stock_redirects(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('deleteStock')
            ->assertRedirect(route('stocks.index'));

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_modal_actions(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('confirmingStockDeletion', false)
            ->call('confirmDeleteStock')
            ->assertSet('confirmingStockDeletion', true)
            ->call('cancelDeleteStock')
            ->assertSet('confirmingStockDeletion', false)
            ->assertSet('confirmingCoverDeletion', false)
            ->call('confirmDeleteCover')
            ->assertSet('confirmingCoverDeletion', true)
            ->call('cancelDeleteCover')
            ->assertSet('confirmingCoverDeletion', false);
    }

    public function test_admin_can_view_but_cannot_modify_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id, 'quantity' => 10]);

        $this->actingAs($admin);

        Livewire::test(StockDetail::class, ['stock' => $stock])->assertOk();

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->assertForbidden();

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('adjustQuantity', 1)
            ->assertForbidden();

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('deleteStock')
            ->assertForbidden();

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id]);
    }

    public function test_member_cannot_mount_other_members_stock_directly(): void
    {
        $owner = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other);

        Livewire::test(StockDetail::class, ['stock' => $stock])->assertForbidden();
    }
}