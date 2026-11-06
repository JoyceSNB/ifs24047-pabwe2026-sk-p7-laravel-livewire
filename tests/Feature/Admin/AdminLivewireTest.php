<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Livewire\AdminUserStockList;
use App\Features\Admin\Livewire\UserList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_forbidden_from_admin_pages(): void
    {
        $member = User::factory()->member()->create();
        $targetUser = User::factory()->member()->create();

        $this->actingAs($member)
            ->get('/admin/users')
            ->assertStatus(403);

        $this->actingAs($member)
            ->get("/admin/users/{$targetUser->id}/stocks")
            ->assertStatus(403);
    }

    public function test_admin_can_open_user_stock_page(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create();

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}/stocks")
            ->assertOk();
    }

    public function test_admin_can_view_user_list(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@test.com']);
        User::factory()->create(['name' => 'Citra Lestari', 'email' => 'citra@test.com']);

        $this->actingAs($admin);

        Livewire::test(UserList::class)
            ->assertSee('Budi Santoso')
            ->assertSee('Citra Lestari')
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Citra Lestari');
    }

    public function test_admin_can_view_user_stocks_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create(['name' => 'Dewi Sartika']);

        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Kabel USB-C', 'sku' => 'ELK-001', 'price' => null]);
        Stock::factory()->lowStock()->create(['user_id' => $member->id, 'name' => 'Lakban Bening', 'sku' => 'ATK-002']);

        $this->actingAs($admin);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Dewi Sartika')
            ->assertSee('Kabel USB-C')
            ->assertSee('Lakban Bening')
            ->assertDontSee('Hapus Stok')
            ->assertDontSee('Edit Stok')
            ->set('search', 'Kabel')
            ->assertSee('Kabel USB-C')
            ->assertDontSee('Lakban Bening')
            ->set('search', '')
            ->set('statusFilter', 'low_stock')
            ->assertSee('Lakban Bening')
            ->assertDontSee('Kabel USB-C');
    }

    public function test_admin_sees_empty_state_for_user_without_stocks(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create();

        $this->actingAs($admin);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Pengguna ini belum memiliki stok barang.');
    }

    public function test_admin_can_export_user_stocks(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create(['name' => 'Eko Prasetyo']);
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Barang Export Admin']);

        $this->actingAs($admin);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->call('exportExcel')
            ->assertFileDownloaded();
    }
}