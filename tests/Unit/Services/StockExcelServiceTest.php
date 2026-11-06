<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockExcelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class StockExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockExcelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockExcelService(new StockRepository());
    }

    public function test_build_spreadsheet_and_export_response(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(2)->create(['user_id' => $user->id]);
        Stock::factory()->outOfStock()->create(['user_id' => $user->id, 'sku' => null, 'price' => null]);
        $stocks = Stock::query()->where('user_id', $user->id)->get();

        $spreadsheet = $this->service->buildSpreadsheet($stocks, 'Custom Stocks');
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $this->assertEquals('Custom Stocks', $spreadsheet->getActiveSheet()->getTitle());
        $this->assertEquals('Name', $spreadsheet->getActiveSheet()->getCell('B1')->getValue());
        $this->assertEquals('Out of Stock', $spreadsheet->getActiveSheet()->getCell('I4')->getValue());
        $this->assertEquals('-', $spreadsheet->getActiveSheet()->getCell('C4')->getValue());

        $response = $this->service->exportToResponse($stocks, 'test.xlsx');
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $outputContent = ob_get_clean();
        $this->assertNotEmpty($outputContent);
    }

    public function test_import_spreadsheet(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        foreach (['A' => 'No', 'B' => 'Name', 'C' => 'SKU', 'D' => 'Description', 'E' => 'Quantity', 'F' => 'Unit', 'G' => 'Price', 'H' => 'Min Stock', 'I' => 'Status'] as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
        }

        // Row 2: lengkap dengan status berlabel
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', 'Imported Stock 1');
        $sheet->setCellValue('C2', 'IMP-001');
        $sheet->setCellValue('D2', 'Desc 1');
        $sheet->setCellValue('E2', '3');
        $sheet->setCellValue('F2', 'box');
        $sheet->setCellValue('G2', '15000');
        $sheet->setCellValue('H2', '10');
        $sheet->setCellValue('I2', 'Low Stock');

        // Row 3: nilai tidak valid -> default, status dihitung otomatis
        $sheet->setCellValue('A3', '2');
        $sheet->setCellValue('B3', 'Imported Stock 2');
        $sheet->setCellValue('C3', '-');
        $sheet->setCellValue('D3', '-');
        $sheet->setCellValue('E3', 'abc');
        $sheet->setCellValue('F3', '-');
        $sheet->setCellValue('G3', 'gratis');
        $sheet->setCellValue('H3', 'x');
        $sheet->setCellValue('I3', 'unknown');

        // Row 4: kolom B kosong, nama diambil dari kolom A
        $sheet->setCellValue('A4', 'Stock from Col A');
        $sheet->setCellValue('B4', '');
        $sheet->setCellValue('E4', '50');

        // Row 5: baris header berulang (diabaikan)
        $sheet->setCellValue('A5', 'No');
        $sheet->setCellValue('B5', 'Name');

        // Row 6: tanpa nama sama sekali (diabaikan)
        $sheet->setCellValue('E6', '9');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $result = $this->service->import($tempPath, $user->id);
        @unlink($tempPath);

        $this->assertEquals(3, $result['imported']);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Stock 1',
            'sku' => 'IMP-001',
            'quantity' => 3,
            'unit' => 'box',
            'min_stock' => 10,
            'status' => 'low_stock',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Stock 2',
            'sku' => null,
            'description' => null,
            'quantity' => 0,
            'unit' => 'pcs',
            'price' => null,
            'min_stock' => 5,
            'status' => 'out_of_stock',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Stock from Col A',
            'quantity' => 50,
            'status' => 'available',
        ]);
    }

    public function test_import_empty_file_returns_zero(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'No');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $result = $this->service->import($tempPath, $user->id);
        @unlink($tempPath);

        $this->assertEquals(0, $result['imported']);
        $this->assertDatabaseCount('stocks', 0);
    }
}