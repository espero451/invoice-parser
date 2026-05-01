<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UploadController extends Controller
{
    public function index(): View
    {
        return view('upload');
    }

    public function upload(Request $request): View
    {
        # --- File Upload ------------------------------------------------------

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp,gif', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('uploads');
        $absolutePath = Storage::path($path);
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $originalName = $file->getClientOriginalName();
        $errorsList = [];
        $ocrText = null;
        $result = null;

        if ($mimeType === 'application/pdf' && $this->countPdfPages($absolutePath) > 3) {
            return view('upload', [
                'ocrText' => null,
                'result' => null,
                'errorsList' => ['PDF file has too much pages. Should be <= 3.'],
            ]);
        }

        # --- OCR with OpenAI --------------------------------------------------

        try {
            $ocrText = $this->extractTextFromFile($absolutePath, $originalName, $mimeType);
        } catch (\Throwable $exception) {
            $errorsList[] = $exception->getMessage();
        }

        if ($ocrText === null) {
            $errorsList[] = 'OCR text is empty.';
        }

        # --- JSON Parsing -----------------------------------------------------

        if ($ocrText !== null) {
            try {
                $result = $this->parseInvoiceData($ocrText);
            } catch (\Throwable $exception) {
                $errorsList[] = $exception->getMessage();
            }
        }

        if ($result === null) {
            $errorsList[] = 'Parsed JSON is empty or invalid.';
        }

        if (is_array($result)) {
            $request->session()->put('last_result', $result);
        } else {
            $request->session()->forget('last_result');
        }

        return view('upload', [
            'ocrText' => $ocrText,
            'result' => $result,
            'errorsList' => $errorsList,
        ]);
    }

    public function resultJson(Request $request): JsonResponse
    {
        $result = $request->session()->get('last_result');

        if (! is_array($result)) {
            return response()->json([
                'error' => 'No parsed result in session. Upload a file first.',
            ], 404);
        }

        $isDownload = $request->query('download') === '1';
        $disposition = $isDownload ? 'attachment' : 'inline';

        return response()->json($result)->header(
            'Content-Disposition',
            $disposition . '; filename="invoice-result.json"'
        );
    }

    private function extractTextFromFile(string $absolutePath, string $originalName, string $mimeType): ?string
    {
        if (empty(config('services.openai.api_key'))) {
            throw new \RuntimeException('OPENAI_API_KEY is missing in .env.');
        }

        $fileContent = @file_get_contents($absolutePath);

        if ($fileContent === false) {
            return null;
        }

        $fileData = base64_encode($fileContent);
        $content = [
            [
                'type' => 'text',
                'text' => 'Extract all readable text from this document. Return plain text only.',
            ],
        ];

        if ($this->isImageMime($mimeType)) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $mimeType . ';base64,' . $fileData,
                ],
            ];
        } else {
            $content[] = [
                'type' => 'file',
                'file' => [
                    'filename' => $originalName,
                    'file_data' => 'data:' . $mimeType . ';base64,' . $fileData,
                ],
            ];
        }

        $response = Http::timeout(60)->acceptJson()->withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
            'temperature' => 0,
        ]);

        if (! $response->ok()) {
            throw new \RuntimeException(
                'OCR request failed. Status: ' . $response->status() . '. Body: ' . $response->body()
            );
        }

        $data = $response->json();

        return $this->extractMessageContent($data);
    }

    private function parseInvoiceData(string $ocrText): ?array
    {
        $prompt = "Extract structured invoice data from the text below.\n\n"
            . "Return JSON with fields:\n"
            . "- supplier\n"
            . "- customer\n"
            . "- items (array of {description, quantity, price})\n"
            . "- total_amount\n"
            . "- payment_terms\n\n"
            . "If a field is missing, return null.\n"
            . "Return only valid JSON. No markdown, no explanation.\n\n"
            . "TEXT:\n"
            . $ocrText;

        $response = Http::timeout(60)->acceptJson()->withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'temperature' => 0,
        ]);

        if (! $response->ok()) {
            throw new \RuntimeException(
                'Parsing request failed. Status: ' . $response->status() . '. Body: ' . $response->body()
            );
        }

        $data = $response->json();
        $content = $this->extractMessageContent($data);
        $content = is_string($content) ? $this->extractJsonObject($content) : null;
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if ($decoded === null) {
            throw new \RuntimeException('JSON decode failed: ' . json_last_error_msg());
        }

        return $decoded;
    }

    private function extractJsonObject(string $content): string
    {
        $trimmed = trim($content);

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $trimmed, $matches) === 1) {
            return $matches[0];
        }

        return $trimmed;
    }

    private function extractMessageContent(array $data): ?string
    {
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return null;
        }

        $textParts = [];

        foreach ($content as $part) {
            if (($part['type'] ?? null) === 'text' && isset($part['text'])) {
                $textParts[] = $part['text'];
            }
        }

        return $textParts !== [] ? implode("\n", $textParts) : null;
    }

    private function isImageMime(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/webp',
            'image/gif',
        ], true);
    }

    private function countPdfPages(string $absolutePath): int
    {
        // Uses a simple marker count as a lightweight PDF page estimate.
        $content = @file_get_contents($absolutePath);

        if ($content === false) {
            return 0;
        }

        preg_match_all('/\/Type\s*\/Page\b/', $content, $matches);

        return count($matches[0]);
    }
}
