<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Platform\Core\Livewire\Public\PublicExtraFieldForm;
use Platform\Core\Models\Document;
use Platform\Core\Models\DocumentFolder;
use Platform\Core\Services\ContextFileService;
use Platform\Core\Services\Documents\DocumentTemplateRegistry;

Route::get('/form/{token}', PublicExtraFieldForm::class)
    ->name('core.public.extra-field-form');

// Serve platform assets (JS/CSS bundles) with immutable caching
Route::get('_platform/assets/{file}', function (string $file) {
    $distDir = realpath(__DIR__ . '/../resources/dist');
    $filePath = realpath($distDir . '/' . $file);

    if (!$filePath || !str_starts_with($filePath, $distDir) || !file_exists($filePath)) {
        abort(404);
    }

    $mimeTypes = ['js' => 'application/javascript', 'css' => 'text/css'];
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

    return response()->file($filePath, [
        'Content-Type' => $mime,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('file', '[a-zA-Z0-9._-]+\.(js|css)')->name('core.platform-asset');

// Document share link (public, no auth)
Route::get('/documents/{token}', function (Request $request, string $token) {
    $document = Document::with(['template', 'outputFile', 'exports' => fn($q) => $q->latest()->limit(1)])
        ->where('share_token', $token)
        ->firstOrFail();

    $viewUrl = null;
    $downloadUrl = null;
    $htmlPreview = null;

    if ($document->outputFile) {
        $viewUrl = ContextFileService::generateUrl(
            $document->outputFile->disk,
            $document->outputFile->path,
            $document->outputFile->token,
            'core.context-files.show',
            60,
        );
        $downloadUrl = ContextFileService::generateDownloadUrl(
            $document->outputFile->disk,
            $document->outputFile->path,
            $document->outputFile->token,
            $document->outputFile->original_name,
            5,
        );
    }

    // HTML preview fallback: render template to HTML (works without Chromium)
    $renderError = null;
    if (!$viewUrl && $document->data) {
        try {
            $registry = app(DocumentTemplateRegistry::class);
            $template = $document->template
                ?? $registry->resolve($document->template_key, $document->team_id);

            if ($template) {
                $htmlPreview = $registry->renderToHtml($template, $document->data);
            }
        } catch (\Throwable $e) {
            $renderError = $e->getMessage();
        }
    }

    // Get error from latest export if rendering failed
    if (!$renderError && $document->status === 'failed') {
        $lastExport = $document->exports->first();
        $renderError = $lastExport?->error_message;
    }

    return view('platform::documents.share', [
        'document' => $document,
        'viewUrl' => $viewUrl,
        'downloadUrl' => $downloadUrl,
        'htmlPreview' => $htmlPreview,
        'renderError' => $renderError,
    ]);
})->name('core.documents.share');

// Document folder share link (public, no auth)
Route::get('/documents/folder/{token}', function (Request $request, string $token) {
    $folder = DocumentFolder::where('share_token', $token)->firstOrFail();

    $subfolders = DocumentFolder::where('parent_id', $folder->id)
        ->where('team_id', $folder->team_id)
        ->orderBy('name')
        ->get();

    $documents = Document::where('document_folder_id', $folder->id)
        ->where('team_id', $folder->team_id)
        ->orderByDesc('created_at')
        ->get();

    return view('platform::documents.folder-share', [
        'folder' => $folder,
        'subfolders' => $subfolders,
        'documents' => $documents,
    ]);
})->name('core.documents.folder.share');
