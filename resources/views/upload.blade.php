<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Parser</title>
    <style>
        :root {
            --bg: #f6f7f9;
            --card: #ffffff;
            --text: #171a1f;
            --muted: #6b7280;
            --line: #e6e8ec;
            --accent: #0f172a;
            --danger: #b91c1c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 10% 0%, #ffffff 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, #eef2ff 0%, transparent 35%),
                var(--bg);
        }

        .container {
            max-width: 920px;
            margin: 40px auto;
            padding: 0 16px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            margin-bottom: 16px;
        }

        h1, h2, h3 {
            margin: 0 0 12px 0;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        p {
            margin: 0 0 10px 0;
            color: var(--muted);
        }

        form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        input[type="file"] {
            padding: 9px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
        }

        button {
            border: 0;
            background: var(--accent);
            color: #fff;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
        }

        .button-link {
            display: inline-block;
            text-decoration: none;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 14px;
        }

        .actions {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        pre {
            margin: 0;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fafafa;
            white-space: pre-wrap;
            word-break: break-word;
            line-height: 1.45;
        }

        .error {
            color: var(--danger);
            margin: 0;
            padding-left: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }

        th {
            width: 220px;
            background: #f9fafb;
            font-weight: 600;
        }

        .items-table th, .items-table td {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Invoice Parser</h1>
            <p>Upload PDF or image and get structured invoice data.</p>
            <form method="POST" action="/upload" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" required>
                <button type="submit">Upload</button>
            </form>
            @if (is_array($result ?? null))
                <div class="actions">
                    <a class="button-link" href="/result/json" target="_blank" rel="noopener">Open JSON</a>
                    <a class="button-link" href="/result/json?download=1">Export JSON</a>
                </div>
            @endif
        </div>

        @if ($errors->any())
            <div class="card">
                <h2>Validation Error</h2>
                <ul class="error">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!empty($errorsList))
            <div class="card">
                <h2>Errors</h2>
                <ul class="error">
                    @foreach ($errorsList as $errorItem)
                        <li>{{ $errorItem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @isset($ocrText)
            <div class="card">
                <details>
                    <summary>OCR Text</summary>
                    <pre>{{ $ocrText ?? 'No text extracted.' }}</pre>
                </details>
            </div>
        @endisset

        @isset($result)
            <div class="card">
                <h2>Parsed Invoice Data</h2>
                @if (is_array($result))
                    <table>
                        <tr>
                            <th>Supplier</th>
                            <td>{{ $result['supplier'] ?? 'null' }}</td>
                        </tr>
                        <tr>
                            <th>Customer</th>
                            <td>{{ $result['customer'] ?? 'null' }}</td>
                        </tr>
                        <tr>
                            <th>Total Amount</th>
                            <td>{{ $result['total_amount'] ?? 'null' }}</td>
                        </tr>
                        <tr>
                            <th>Payment Terms</th>
                            <td>{{ $result['payment_terms'] ?? 'null' }}</td>
                        </tr>
                    </table>

                    <h3 style="margin-top: 14px;">Items</h3>
                    <table class="items-table">
                        <tr>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Price</th>
                        </tr>
                        @forelse (($result['items'] ?? []) as $item)
                            <tr>
                                <td>{{ $item['description'] ?? 'null' }}</td>
                                <td>{{ $item['quantity'] ?? 'null' }}</td>
                                <td>{{ $item['price'] ?? 'null' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">No items</td>
                            </tr>
                        @endforelse
                    </table>
                @else
                    <p>Could not parse JSON response.</p>
                @endif
            </div>
        @endisset
    </div>
</body>
</html>
