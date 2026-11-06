<?php

namespace Tests\Feature\Stock;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Stock\Livewire\StockList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StockListLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stocks(): void
    {
        $this->get('/stocks')->assertRedirect(route('login'));
    }

    public function test_member_can_view_own_stocks_only(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang Milik Member']);

        $other = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $other->id, 'name' => 'Barang Rahasia Orang Lain']);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->assertSee('Barang Milik Member')
            ->assertDontSee('Barang Rahasia Orang Lain');
    }

    public function test_empty_state_is_shown_when_no_stock(): void
    {
        $this->actingAs(User::factory()->member()->create());

        Livewire::test(StockList::class)->assertSee('Belum ada stok ditemukan');
    }

    public function test_create_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        // Validasi gagal
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', '')
            ->set('formQuantity', '-1')
            ->set('formUnit', '')
            ->set('formMinStock', 'abc')
            ->set('formPrice', '-5')
            ->call('saveStock')
            ->assertHasErrors(['formName', 'formQuantity', 'formUnit', 'formMinStock', 'formPrice']);

        // Berhasil, lengkap (quantity 3 <= min 5 => low_stock)
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', 'Kabel USB-C')
            ->set('formSku', 'ELK-001')
            ->set('formDescription', 'Kabel data 1 meter')
            ->set('formQuantity', '3')
            ->set('formUnit', 'pcs')
            ->set('formPrice', '25000')
            ->set('formMinStock', '5')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Kabel USB-C',
            'sku' => 'ELK-001',
            'quantity' => 3,
            'status' => 'low_stock',
        ]);

        // Berhasil, SKU dan harga dikosongkan => null
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', 'Lakban')
            ->set('formSku', '')
            ->set('formPrice', '')
            ->call('saveStock')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Lakban',
            'sku' => null,
            'price' => null,
            'quantity' => 0,
            'status' => 'out_of_stock',
        ]);
    }

    public function test_edit_stock_success_recalculates_status(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Nama Lama', 'quantity' => 50]);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('editingStockId', $stock->id)
            ->assertSet('formName', 'Nama Lama')
            ->assertSet('formQuantity', '50')
            ->set('formName', 'Nama Baru')
            ->set('formQuantity', '2')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Baru',
            'quantity' => 2,
            'status' => 'low_stock',
        ]);
    }

    public function test_edit_modal_handles_stock_without_price(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id, 'price' => null]);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('formPrice', null);
    }

    public function test_adjust_quantity_updates_status(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'quantity' => 6,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        $component = Livewire::test(StockList::class);

        $component->call('adjustQuantity', $stock->id, -1);
        $this->assertSame(5, $stock->fresh()->quantity);
        $this->assertSame('low_stock', $stock->fresh()->status);

        $component->call('adjustQuantity', $stock->id, 10);
        $this->assertSame(15, $stock->fresh()->quantity);
        $this->assertSame('available', $stock->fresh()->status);

        $component->call('adjustQuantity', $stock->id, -100);
        $this->assertSame(0, $stock->fresh()->quantity);
        $this->assertSame('out_of_stock', $stock->fresh()->status);
    }

    public function test_member_cannot_modify_other_members_stock(): void
    {
        $user = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $other->id, 'quantity' => 10]);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->call('adjustQuantity', $stock->id, 1)
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->call('deleteStock', $stock->id)
            ->assertForbidden();

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id]);
    }

    public function test_edit_save_is_forbidden_for_other_members_stock(): void
    {
        $user = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $other->id, 'name' => 'Asli']);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->set('editingStockId', $stock->id)
            ->set('formName', 'Dibajak')
            ->call('saveStock')
            ->assertForbidden();

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Asli']);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)->call('deleteStock', $stock->id);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->assertSet('confirmingStockDeletionId', null)
            ->call('confirmDelete', $stock->id)
            ->assertSet('confirmingStockDeletionId', $stock->id)
            ->call('cancelDelete')
            ->assertSet('confirmingStockDeletionId', null)
            ->call('confirmDelete', $stock->id)
            ->call('deleteStock')
            ->assertSet('confirmingStockDeletionId', null);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_delete_without_target_does_nothing(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)->call('deleteStock');

        $this->assertDatabaseHas('stocks', ['id' => $stock->id]);
    }

    public function test_search_and_status_filter(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kertas Folio', 'sku' => 'ATK-777', 'status' => 'available']);
        Stock::factory()->lowStock()->create(['user_id' => $user->id, 'name' => 'Lakban Bening', 'sku' => 'ATK-888']);

        Livewire::test(StockList::class)
            ->set('search', 'Kertas')
            ->assertSee('Kertas Folio')
            ->assertDontSee('Lakban Bening')
            ->set('search', 'ATK-888')
            ->assertSee('Lakban Bening')
            ->assertDontSee('Kertas Folio')
            ->set('search', '')
            ->set('statusFilter', 'low_stock')
            ->assertSee('Lakban Bening')
            ->assertDontSee('Kertas Folio');
    }

    public function test_export_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang Export']);

        Livewire::test(StockList::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_import_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach (['A' => 'No', 'B' => 'Name', 'C' => 'SKU', 'D' => 'Description', 'E' => 'Quantity', 'F' => 'Unit', 'G' => 'Price', 'H' => 'Min Stock', 'I' => 'Status'] as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
        }

        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', 'Imported Excel Stock');
        $sheet->setCellValue('C2', 'IMP-001');
        $sheet->setCellValue('D2', 'Excel Desc');
        $sheet->setCellValue('E2', '12');
        $sheet->setCellValue('F2', 'box');
        $sheet->setCellValue('G2', '15000');
        $sheet->setCellValue('H2', '5');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_upload_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);
        $fileContent = file_get_contents($tempPath);
        @unlink($tempPath);

        $uploadedFile = UploadedFile::fake()->createWithContent('stocks.xlsx', $fileContent);

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->set('importFile', $uploadedFile)
            ->call('importExcel')
            ->assertHasNoErrors()
            ->assertSet('showImportModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Excel Stock',
            'sku' => 'IMP-001',
            'quantity' => 12,
            'unit' => 'box',
            'status' => 'available',
        ]);
    }

    public function test_import_excel_requires_file(): void
    {
        $this->actingAs(User::factory()->member()->create());

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->call('importExcel')
            ->assertHasErrors(['importFile']);
    }

    public function test_ai_generation_and_saving(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stubAi = $this->createStub(AiClientInterface::class);
        $stubAi->method('generateStocks')->willReturn([
            new GeneratedStockItem(
                name: 'AI Kertas A4',
                sku: 'AI-001',
                description: 'Kertas 80 gram',
                quantity: 40,
                unit: 'rim',
                price: 55000.0,
                minStock: 10,
            ),
            new GeneratedStockItem(name: 'AI Pulpen'),
        ]);
        $this->app->instance(AiClientInterface::class, $stubAi);

        // Validasi prompt dan jumlah
        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', '')
            ->set('aiCount', 0)
            ->call('generateWithAi')
            ->assertHasErrors(['aiPrompt', 'aiCount']);

        $component = Livewire::test(StockList::class)
            ->call('openAiModal')
            ->assertSet('showAiModal', true)
            ->set('aiPrompt', 'Buatkan barang ATK')
            ->set('aiCount', 2)
            ->call('generateWithAi')
            ->assertCount('aiGeneratedItems', 2)
            ->assertCount('aiSelectedIndices', 2)
            ->assertSee('AI Kertas A4');

        // Simpan tanpa memilih item
        $component->set('aiSelectedIndices', [])
            ->call('saveAiGenerated')
            ->assertSet('aiErrorMessage', 'Pilih minimal satu item stok yang ingin disimpan.');

        // Simpan item terpilih
        $component->set('aiSelectedIndices', [0, 1, 99])
            ->call('saveAiGenerated')
            ->assertSet('showAiModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'AI Kertas A4',
            'sku' => 'AI-001',
            'quantity' => 40,
            'unit' => 'rim',
            'min_stock' => 10,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'AI Pulpen',
            'quantity' => 0,
            'status' => 'out_of_stock',
        ]);
    }

    public function test_ai_generation_handles_exception(): void
    {
        $this->actingAs(User::factory()->member()->create());

        $stubAi = $this->createStub(AiClientInterface::class);
        $stubAi->method('generateStocks')->willThrowException(new \RuntimeException('API quota exceeded'));
        $this->app->instance(AiClientInterface::class, $stubAi);

        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', 'Tes error')
            ->call('generateWithAi')
            ->assertSet('aiErrorMessage', 'API quota exceeded')
            ->assertSet('aiIsGenerating', false);
    }

    public function test_close_modals(): void
    {
        $this->actingAs(User::factory()->member()->create());

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->call('closeFormModal')
            ->assertSet('showFormModal', false)
            ->call('openImportModal')
            ->call('closeImportModal')
            ->assertSet('showImportModal', false)
            ->call('openAiModal')
            ->call('closeAiModal')
            ->assertSet('showAiModal', false);
    }
}