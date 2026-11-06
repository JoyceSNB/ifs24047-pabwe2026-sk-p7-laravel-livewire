<?php

namespace Tests\Unit\Services;

use App\Features\Ai\Services\OpenAiStockGeneratorService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiStockGeneratorServiceTest extends TestCase
{
    private function makeService(): OpenAiStockGeneratorService
    {
        return new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');
    }

    public function test_empty_prompt_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prompt tidak boleh kosong.');
        $this->makeService()->generateStocks('   ');
    }

    public function test_successful_generation(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'stocks' => [
                                    [
                                        'name' => 'Kertas A4',
                                        'sku' => 'KRT-001',
                                        'description' => 'Kertas 80 gram',
                                        'quantity' => 40,
                                        'unit' => 'rim',
                                        'price' => 55000,
                                        'min_stock' => 10,
                                    ],
                                    [
                                        'name' => 'Pulpen',
                                        'sku' => '',
                                        'description' => null,
                                        'quantity' => 'banyak',
                                        'unit' => '',
                                        'price' => 'gratis',
                                        'min_stock' => -3,
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $results = $this->makeService()->generateStocks('Buatkan 2 stok ATK', 2);

        $this->assertCount(2, $results);
        $this->assertEquals('Kertas A4', $results[0]->name);
        $this->assertEquals('KRT-001', $results[0]->sku);
        $this->assertEquals(40, $results[0]->quantity);
        $this->assertEquals('rim', $results[0]->unit);
        $this->assertEquals(55000.0, $results[0]->price);
        $this->assertEquals(10, $results[0]->minStock);

        // Nilai tidak valid dinormalisasi ke default
        $this->assertEquals('Pulpen', $results[1]->name);
        $this->assertNull($results[1]->sku);
        $this->assertNull($results[1]->description);
        $this->assertEquals(0, $results[1]->quantity);
        $this->assertEquals('pcs', $results[1]->unit);
        $this->assertNull($results[1]->price);
        $this->assertEquals(0, $results[1]->minStock);
    }

    public function test_api_failure_throws_exception(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Invalid API Key provided'],
            ], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API Error: Invalid API Key provided');
        $this->makeService()->generateStocks('Stok gudang');
    }

    public function test_parse_content_with_markdown_fences(): void
    {
        $raw = "```json\n[{\"name\": \"Code Fence Item\", \"quantity\": 7}]\n```";

        $results = $this->makeService()->parseContent($raw);
        $this->assertCount(1, $results);
        $this->assertEquals('Code Fence Item', $results[0]->name);
        $this->assertEquals(7, $results[0]->quantity);
    }

    public function test_parse_content_invalid_json(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI mengembalikan format respon yang tidak valid.');
        $this->makeService()->parseContent('Not valid JSON');
    }

    public function test_parse_content_empty_stocks(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada item stok yang berhasil di-generate dari AI.');
        $this->makeService()->parseContent('{"stocks": []}');
    }

    public function test_parse_content_no_valid_items(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Format item stok yang dikembalikan AI tidak sesuai spesifikasi.');
        $this->makeService()->parseContent('{"stocks": [{"bad_key": "val"}]}');
    }

    public function test_constructor_handles_swapped_endpoint_and_key(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"stocks":[{"name":"Barang"}]}']]],
            ]),
        ]);

        // Key given as URL, endpoint given as secret
        $service = new OpenAiStockGeneratorService('sk-secret-key', 'https://ai.example.com/v1');
        $this->assertCount(1, $service->generateStocks('Stok gudang'));
    }

    public function test_constructor_auto_prefixes_https(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"stocks":[{"name":"Barang"}]}']]],
            ]),
        ]);

        $service = new OpenAiStockGeneratorService('api.openai.com/v1', 'mock-key');
        $this->assertCount(1, $service->generateStocks('Stok gudang'));
    }
}