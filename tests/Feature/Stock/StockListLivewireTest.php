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

    public function test_member_can_open_stock_page(): void
    {
        $user = User::factory()->member()->create();

        $this->actingAs($user)
            ->get('/stocks')
            ->assertOk();
    }

    public function test_member_can_view_own_stocks(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang Spesifik Member']);

        $otherUser = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $otherUser->id, 'name' => 'Barang Rahasia Orang Lain']);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->assertSee('Barang Spesifik Member')
            ->assertDontSee('Barang Rahasia Orang Lain');
    }

    public function test_create_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        // Validation error
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', '')
            ->set('formQuantity', '-1')
            ->set('formMinStock', 'abc')
            ->set('formPrice', 'gratis')
            ->call('saveStock')
            ->assertHasErrors(['formName', 'formQuantity', 'formMinStock', 'formPrice']);

        // Success (status dihitung otomatis)
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->set('formName', 'Barang Baru')
            ->set('formSku', 'BRG-001')
            ->set('formDescription', 'Deskripsi **markdown**')
            ->set('formQuantity', '3')
            ->set('formUnit', 'box')
            ->set('formPrice', '15000')
            ->set('formMinStock', '5')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Barang Baru',
            'sku' => 'BRG-001',
            'quantity' => 3,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
    }

    public function test_create_stock_with_empty_optional_fields(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', 'Tanpa Opsional')
            ->set('formSku', '')
            ->set('formPrice', '')
            ->set('formQuantity', '20')
            ->call('saveStock')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Tanpa Opsional',
            'sku' => null,
            'price' => null,
            'status' => 'available',
        ]);
    }

    public function test_edit_stock_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nama Lama',
            'quantity' => 50,
            'price' => 2500,
        ]);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('editingStockId', $stock->id)
            ->assertSet('formName', 'Nama Lama')
            ->assertSet('formQuantity', '50')
            ->set('formName', 'Nama Telah Diedit')
            ->set('formQuantity', '0')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Telah Diedit',
            'quantity' => 0,
            'status' => 'out_of_stock',
        ]);
    }

    public function test_edit_stock_without_price_keeps_price_empty(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id, 'price' => null]);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('formPrice', null);
    }

    public function test_cannot_edit_other_users_stock(): void
    {
        $user = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertForbidden();
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

        Livewire::test(StockList::class)
            ->call('adjustQuantity', $stock->id, -1);

        $stock->refresh();
        $this->assertEquals(5, $stock->quantity);
        $this->assertEquals('low_stock', $stock->status);

        Livewire::test(StockList::class)
            ->call('adjustQuantity', $stock->id, 10);

        $stock->refresh();
        $this->assertEquals(15, $stock->quantity);
        $this->assertEquals('available', $stock->status);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->call('deleteStock', $stock->id);

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

    public function test_search_and_status_filter(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kabel Siang', 'status' => 'available']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kabel Malam', 'status' => 'low_stock']);

        Livewire::test(StockList::class)
            ->set('search', 'Siang')
            ->assertSee('Kabel Siang')
            ->assertDontSee('Kabel Malam')
            ->set('search', '')
            ->set('statusFilter', 'low_stock')
            ->assertSee('Kabel Malam')
            ->assertDontSee('Kabel Siang');
    }

    public function test_export_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Exportable Stock']);

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
        $sheet->setCellValue('C2', 'XLS-001');
        $sheet->setCellValue('D2', 'Excel Desc');
        $sheet->setCellValue('E2', '12');
        $sheet->setCellValue('F2', 'pcs');
        $sheet->setCellValue('G2', '9000');
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
            ->assertSet('showImportModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Excel Stock',
            'description' => 'Excel Desc',
            'quantity' => 12,
            'status' => 'available',
        ]);
    }

    public function test_import_requires_file(): void
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

        // Mock AiClientInterface
        $mockAiClient = $this->createStub(AiClientInterface::class);
        $mockAiClient->method('generateStocks')->willReturn([
            new GeneratedStockItem('AI Stock 1', 'AI-001', 'Desc 1', 20, 'pcs', 15000.0, 5),
            new GeneratedStockItem('AI Stock 2', null, null, 2, 'box', null, 5),
        ]);
        $this->app->instance(AiClientInterface::class, $mockAiClient);

        // Test validation for prompt and count
        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', '')
            ->set('aiCount', 0)
            ->call('generateWithAi')
            ->assertHasErrors(['aiPrompt', 'aiCount']);

        $component = Livewire::test(StockList::class)
            ->call('openAiModal')
            ->assertSet('showAiModal', true)
            ->set('aiPrompt', 'Buatkan stok ATK')
            ->set('aiCount', 2)
            ->call('generateWithAi')
            ->assertCount('aiGeneratedItems', 2)
            ->assertCount('aiSelectedIndices', 2);

        // Try saving with no items selected
        $component->set('aiSelectedIndices', [])
            ->call('saveAiGenerated')
            ->assertSet('aiErrorMessage', 'Pilih minimal satu item stok yang ingin disimpan.');

        // Save selected items
        $component->set('aiSelectedIndices', [0, 1])
            ->call('saveAiGenerated')
            ->assertSet('showAiModal', false);

        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'AI Stock 1', 'sku' => 'AI-001', 'status' => 'available']);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'AI Stock 2', 'status' => 'low_stock']);
    }

    public function test_ai_generation_handles_exception(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $mockAiClient = $this->createStub(AiClientInterface::class);
        $mockAiClient->method('generateStocks')->willThrowException(new \RuntimeException('API quota exceeded'));
        $this->app->instance(AiClientInterface::class, $mockAiClient);

        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', 'Error test')
            ->call('generateWithAi')
            ->assertSet('aiErrorMessage', 'API quota exceeded');
    }

    public function test_close_modals(): void
    {
        $this->actingAs(User::factory()->member()->create());

        Livewire::test(StockList::class)
            ->call('openCreateModal')
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