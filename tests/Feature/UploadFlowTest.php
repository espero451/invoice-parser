<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadFlowTest extends TestCase
{
    public function test_index_page_contains_upload_form(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Invoice Parser');
        $response->assertSee('name="file"', false);
    }

    public function test_upload_requires_file(): void
    {
        $this->get('/');

        $response = $this->post('/upload', [
            '_token' => session()->token(),
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_upload_processes_file_and_renders_parsed_result(): void
    {
        Storage::fake('local');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Invoice #1001 Total: 1200 EUR',
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'supplier' => 'ACME Ltd',
                                    'customer' => 'Client LLC',
                                    'items' => [
                                        [
                                            'description' => 'Service',
                                            'quantity' => 1,
                                            'price' => 1200,
                                        ],
                                    ],
                                    'total_amount' => 1200,
                                    'payment_terms' => '14 days',
                                ]),
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $file = UploadedFile::fake()->create('invoice.jpg', 50, 'image/jpeg');
        $this->get('/');

        $response = $this->post('/upload', [
            '_token' => session()->token(),
            'file' => $file,
        ]);

        $response->assertOk();
        $response->assertSee('OCR Text');
        $response->assertSee('Invoice #1001 Total: 1200 EUR');
        $response->assertSee('Parsed Invoice Data');
        $response->assertSee('ACME Ltd');
        $response->assertSee('Client LLC');
        $response->assertSee('14 days');
        $response->assertSee('1200');
    }

    public function test_upload_blocks_pdf_with_more_than_three_pages(): void
    {
        Storage::fake('local');
        Http::fake();

        $pdfContent = "%PDF-1.4\n"
            . "/Type /Page\n"
            . "/Type /Page\n"
            . "/Type /Page\n"
            . "/Type /Page\n";

        $file = UploadedFile::fake()->createWithContent('many-pages.pdf', $pdfContent);
        $this->get('/');

        $response = $this->post('/upload', [
            '_token' => session()->token(),
            'file' => $file,
        ]);

        $response->assertOk();
        $response->assertSee('Слишком много страниц');
        Http::assertNothingSent();
    }
}
