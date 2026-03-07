<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $folder->name }}</title>
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
        }
        .toolbar-meta {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }
        .container {
            max-width: 900px;
            margin: 24px auto;
            padding: 0 24px;
        }

        /* Breadcrumb */
        .breadcrumb {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
        }
        .breadcrumb a {
            color: #2563eb;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Subfolders */
        .subfolder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .subfolder-card {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 16px;
            text-decoration: none;
            color: #1f2937;
            transition: all 0.15s;
        }
        .subfolder-card:hover {
            border-color: #2563eb;
            box-shadow: 0 1px 3px rgba(37,99,235,0.1);
        }
        .subfolder-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .subfolder-icon svg {
            width: 18px;
            height: 18px;
        }
        .subfolder-name {
            font-size: 14px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .subfolder-count {
            font-size: 11px;
            color: #9ca3af;
        }

        /* Document list */
        .doc-list {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .doc-list-header {
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .doc-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            text-decoration: none;
            color: #1f2937;
            transition: background 0.1s;
        }
        .doc-item:hover {
            background: #f9fafb;
        }
        .doc-item:last-child {
            border-bottom: none;
        }
        .doc-info {
            flex: 1;
            min-width: 0;
        }
        .doc-title {
            font-size: 14px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .doc-meta {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 2px;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 12px;
            flex-shrink: 0;
        }
        .status-rendered { background: #d1fae5; color: #065f46; }
        .status-draft { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .empty-state h2 { font-size: 18px; margin-bottom: 8px; }
        .empty-state p { color: #6b7280; font-size: 14px; }
        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="toolbar-title">{{ $folder->name }}</div>
            <div class="toolbar-meta">
                {{ $folder->path }}
                @if($folder->description)
                    &middot; {{ $folder->description }}
                @endif
            </div>
        </div>
    </div>

    <div class="container">
        @if($subfolders->isNotEmpty())
            <div class="section-title">Ordner</div>
            <div class="subfolder-grid">
                @foreach($subfolders as $sub)
                    @if($sub->share_url)
                        <a href="{{ $sub->share_url }}" class="subfolder-card">
                    @else
                        <div class="subfolder-card">
                    @endif
                        <div class="subfolder-icon" style="background: {{ $sub->color ?? '#eff6ff' }}; color: {{ $sub->color ? '#fff' : '#2563eb' }};">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                        </div>
                        <div>
                            <div class="subfolder-name">{{ $sub->name }}</div>
                            <div class="subfolder-count">{{ $sub->documents()->count() }} Dokumente</div>
                        </div>
                    @if($sub->share_url)
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        @if($documents->isNotEmpty())
            <div class="doc-list">
                <div class="doc-list-header">Dokumente ({{ $documents->count() }})</div>
                @foreach($documents as $doc)
                    <a href="{{ $doc->share_url ?? '#' }}" class="doc-item" @unless($doc->share_url) style="pointer-events:none;color:#9ca3af;" @endunless>
                        <div class="doc-info">
                            <div class="doc-title">{{ $doc->title }}</div>
                            <div class="doc-meta">
                                {{ $doc->template_key }}
                                &middot;
                                {{ $doc->created_at?->format('d.m.Y H:i') }}
                            </div>
                        </div>
                        <span class="status-badge status-{{ $doc->status }}">{{ ucfirst($doc->status) }}</span>
                    </a>
                @endforeach
            </div>
        @elseif($subfolders->isEmpty())
            <div class="empty-state">
                <h2>Ordner ist leer</h2>
                <p>Diesem Ordner wurden noch keine Dokumente zugewiesen.</p>
            </div>
        @endif
    </div>
</body>
</html>
