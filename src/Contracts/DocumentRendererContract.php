<?php

namespace Platform\Core\Contracts;

interface DocumentRendererContract
{
    /**
     * Unique renderer key (e.g. 'pdf', 'canva')
     */
    public function getRendererKey(): string;

    /**
     * Render HTML to binary output.
     *
     * @param string $html Fully rendered HTML
     * @param array $options Renderer-specific options (margins, format, header/footer, etc.)
     * @return string Binary content
     */
    public function render(string $html, array $options = []): string;

    /**
     * File extension for the output (e.g. 'pdf')
     */
    public function getOutputExtension(): string;

    /**
     * MIME type for the output (e.g. 'application/pdf')
     */
    public function getOutputMimeType(): string;
}
