<?php

namespace Platform\Core\Services\Documents;

use Platform\Core\Contracts\DocumentRendererContract;
use Spatie\Browsershot\Browsershot;

class PdfRenderer implements DocumentRendererContract
{
    public function getRendererKey(): string
    {
        return 'pdf';
    }

    public function render(string $html, array $options = []): string
    {
        $config = config('platform.documents', []);
        $paperDefaults = $config['paper'] ?? [];

        $browsershot = Browsershot::html($html)
            ->format($options['format'] ?? $paperDefaults['format'] ?? 'A4')
            ->margins(
                $options['margin_top'] ?? $paperDefaults['margin_top'] ?? 20,
                $options['margin_right'] ?? $paperDefaults['margin_right'] ?? 15,
                $options['margin_bottom'] ?? $paperDefaults['margin_bottom'] ?? 20,
                $options['margin_left'] ?? $paperDefaults['margin_left'] ?? 15,
            )
            ->showBackground();

        // Chromium/Node paths from config
        if (!empty($config['chromium_path'])) {
            $browsershot->setChromePath($config['chromium_path']);
        }

        if (!empty($config['node_path'])) {
            $browsershot->setNodeBinary($config['node_path']);
        }

        if (!empty($config['npm_path'])) {
            $browsershot->setNpmBinary($config['npm_path']);
        }

        // Print background
        if ($options['print_background'] ?? $paperDefaults['print_background'] ?? true) {
            $browsershot->showBackground();
        }

        // Header/Footer HTML
        if (!empty($options['header_html'])) {
            $browsershot->headerHtml($options['header_html']);
            $browsershot->showBrowserHeaderAndFooter();
        }

        if (!empty($options['footer_html'])) {
            $browsershot->footerHtml($options['footer_html']);
            $browsershot->showBrowserHeaderAndFooter();
        }

        // Landscape mode
        if (!empty($options['landscape'])) {
            $browsershot->landscape();
        }

        return $browsershot->pdf();
    }

    public function getOutputExtension(): string
    {
        return 'pdf';
    }

    public function getOutputMimeType(): string
    {
        return 'application/pdf';
    }
}
