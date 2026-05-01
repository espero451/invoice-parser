# Invoice Reader

This app is made to read data from different invoices.

You can upload an invoice as a PDF or image, and the app will:
- read the document text (OCR),
- extract useful invoice fields,
- show the result in a clean table in the UI.

It is useful when invoices come in different formats and you still want one structured output.

## What data it extracts

- Supplier
- Customer
- Invoice lines (items: description, quantity, price)
- Total amount
- Payment terms

If some value is missing in the source invoice, the app returns `null`.

## How it works (simple)

1. User uploads PDF/photo in the web interface.
2. App sends the file to OpenAI for OCR.
3. App sends OCR text to OpenAI again to build structured JSON.
4. UI shows:
   - parsed invoice data (table),
   - OCR text,
   - errors (if any).

## Project structure (important files)

- `routes/web.php` - web routes
- `app/Http/Controllers/UploadController.php` - main OCR + parsing logic
- `resources/views/upload.blade.php` - upload page and result UI
- `.env` - `OPENAI_API_KEY`
- `config/services.php` - OpenAI config mapping
- `tests/Feature/UploadFlowTest.php` - end-to-end upload flow tests
- `tests/Unit/UploadControllerUnitTest.php` - controller unit tests

<!-- ## Run locally

```bash
composer install
php artisan key:generate
php artisan serve
```

Open: `http://127.0.0.1:8000` -->

## Run with Docker

```bash
docker compose up --build -d
```

Open: `http://127.0.0.1:8081`

Stop:

```bash
docker compose down
```

## Tests

Run all tests:

```bash
php artisan test
```

Run only upload flow tests:

```bash
php artisan test --filter=UploadFlowTest
```

Run tests in Docker:

```bash
docker compose exec app php artisan test
```

## Tech Stack

- PHP 8.4
- Laravel 13
- Blade (server-side UI templates)
- OpenAI API (`gpt-4o-mini`) for OCR and structured extraction
- PHPUnit (unit + feature tests)
- Docker, Docker Compose, Nginx, PHP-FPM

## Requirements

- PHP 8.4+
- Composer
- OpenAI API key
