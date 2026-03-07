<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            min-height: 100vh;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .toolbar-title {
            font-size: 16px;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 60%;
        }
        .toolbar-meta {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }
        .toolbar-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-secondary:hover {
            background: #f9fafb;
        }
        .btn-print {
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-print:hover {
            background: #f9fafb;
        }
        .btn svg {
            width: 16px;
            height: 16px;
        }
        .preview-container {
            max-width: 900px;
            margin: 24px auto;
            padding: 0 24px;
        }
        .pdf-embed {
            width: 100%;
            height: calc(100vh - 120px);
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .html-preview {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 40px 48px;
            min-height: 600px;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-rendered { background: #d1fae5; color: #065f46; }
        .status-draft { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .status-rendering { background: #dbeafe; color: #1e40af; }
        .empty-state {
            text-align: center;
            padding: 80px 24px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .empty-state h2 { font-size: 18px; margin-bottom: 8px; }
        .empty-state p { color: #6b7280; font-size: 14px; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .preview-container { max-width: 100%; margin: 0; padding: 0; }
            .html-preview { border: none; box-shadow: none; border-radius: 0; padding: 0; }
            .pdf-embed { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="toolbar-title">{{ $document->title }}</div>
            <div class="toolbar-meta">
                {{ $document->template?->name ?? $document->template_key }}
                &middot;
                <span class="status-badge status-{{ $document->status }}">{{ ucfirst($document->status) }}</span>
                &middot;
                {{ $document->created_at?->format('d.m.Y H:i') }}
            </div>
        </div>
        <div class="toolbar-actions">
            @if($downloadUrl)
                <a href="{{ $downloadUrl }}" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Download PDF
                </a>
                <a href="{{ $viewUrl }}" target="_blank" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-4.5-4.5h6m0 0v6m0-6L9.75 14.25" /></svg>
                    Öffnen
                </a>
            @elseif($htmlPreview)
                <button onclick="window.print()" class="btn btn-print">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" /></svg>
                    Drucken
                </button>
            @endif
        </div>
    </div>

    <div class="preview-container">
        @if($document->status === 'rendered' && $viewUrl)
            {{-- PDF exists: embed as iframe --}}
            <iframe src="{{ $viewUrl }}" class="pdf-embed" title="{{ $document->title }}"></iframe>
        @elseif($htmlPreview)
            {{-- No PDF but HTML renderable: show inline preview --}}
            <div class="html-preview">
                {!! $htmlPreview !!}
            </div>
        @elseif($document->status === 'failed')
            <div class="empty-state">
                <h2>Rendering fehlgeschlagen</h2>
                <p>Beim Generieren des PDFs ist ein Fehler aufgetreten.</p>
            </div>
        @else
            <div class="empty-state">
                <h2>Dokument wird vorbereitet...</h2>
                <p>Bitte laden Sie die Seite in einigen Sekunden neu.</p>
            </div>
        @endif
    </div>
</body>
</html>
