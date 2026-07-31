<?php

namespace Platform\Core\Content;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * ContentParser auf Basis von league/commonmark (AST-Walk).
 *
 * Block-Ebene wird tokenisiert (heading/paragraph/list/quote/callout/code/applet
 * /divider), Inline-Ebene bleibt sanitiertes HTML im jeweiligen Block (Hybrid).
 * Sicherheit: html_input=strip + allow_unsafe_links=false → das erzeugte
 * Inline-HTML ist bereits ungefährlich. Applet-Code bleibt roh (Fenced-Literal)
 * und wird erst von der nx-Applet-Komponente sandboxed.
 */
class CommonMarkContentParser implements ContentParser
{
    /** GitHub-Alert → nx-callout-Variante. */
    private const CALLOUT_MAP = [
        'INFO'      => 'info',
        'TIP'       => 'success',
        'WARNING'   => 'warning',
        'CAUTION'   => 'danger',
        'NOTE'      => 'neutral',
        'IMPORTANT' => 'info',
    ];

    private Environment $env;
    private MarkdownParser $parser;
    private HtmlRenderer $renderer;

    public function __construct()
    {
        $this->env = new Environment([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $this->env->addExtension(new CommonMarkCoreExtension());
        $this->env->addExtension(new GithubFlavoredMarkdownExtension());

        $this->parser = new MarkdownParser($this->env);
        $this->renderer = new HtmlRenderer($this->env);
    }

    public function parse(?string $markdown): array
    {
        $markdown = trim((string) $markdown);
        if ($markdown === '') {
            return ['blocks' => [], 'meta' => []];
        }

        $document = $this->parser->parse($markdown);

        $blocks = [];
        foreach ($document->children() as $node) {
            $block = $this->mapBlock($node);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return ['blocks' => $blocks, 'meta' => []];
    }

    private function mapBlock(Node $node): ?array
    {
        return match (true) {
            $node instanceof Heading       => $this->heading($node),
            $node instanceof Paragraph     => ['type' => BlockType::Paragraph->value, 'inline_html' => $this->inline($node)],
            $node instanceof ListBlock     => $this->list($node),
            $node instanceof BlockQuote    => $this->blockquote($node),
            $node instanceof FencedCode    => $this->fenced($node),
            $node instanceof IndentedCode  => ['type' => BlockType::Code->value, 'language' => null, 'code' => $node->getLiteral()],
            $node instanceof ThematicBreak => ['type' => BlockType::Divider->value],
            // Unbekannt (z. B. GFM-Tabelle) → graceful HTML-Passthrough.
            default                        => $this->htmlFallback($node),
        };
    }

    private function heading(Heading $node): array
    {
        return [
            'type'        => BlockType::Heading->value,
            'level'       => $node->getLevel(),
            'inline_html' => $this->inline($node),
            'anchor'      => $this->slug($this->plain($node)),
        ];
    }

    private function list(ListBlock $node): array
    {
        $items = [];
        foreach ($node->children() as $item) {
            $items[] = $this->stripParagraph($this->renderer->renderNodes($item->children()));
        }

        return [
            'type'    => BlockType::ListBlock->value,
            'ordered' => $node->getListData()->type === 'ordered',
            'items'   => $items,
        ];
    }

    private function blockquote(BlockQuote $node): array
    {
        $inner = trim($this->renderer->renderNodes($node->children()));

        // GitHub-Alert erkennen:  > [!WARNING]\n> text
        if (preg_match('/^<p>\s*\[!(INFO|TIP|WARNING|NOTE|IMPORTANT|CAUTION)\]\s*(?:<br\s*\/?>)?\s*(.*)$/is', $inner, $m)) {
            return [
                'type'        => BlockType::Callout->value,
                'variant'     => self::CALLOUT_MAP[strtoupper($m[1])] ?? 'info',
                'inline_html' => trim(preg_replace('#</p>\s*$#', '', $m[2])),
            ];
        }

        return ['type' => BlockType::Quote->value, 'inline_html' => $this->stripParagraph($inner)];
    }

    private function fenced(FencedCode $node): array
    {
        $words = $node->getInfoWords();
        if (isset($words[0]) && strtolower($words[0]) === 'applet') {
            return ['type' => BlockType::Applet->value, 'code' => $node->getLiteral()];
        }

        return [
            'type'     => BlockType::Code->value,
            'language' => $words[0] ?? null,
            'code'     => $node->getLiteral(),
        ];
    }

    private function htmlFallback(Node $node): array
    {
        return ['type' => BlockType::Html->value, 'html' => $this->renderer->renderNodes([$node])];
    }

    /** Inline-HTML eines Blocks (dessen Inline-Kinder gerendert). */
    private function inline(Node $node): string
    {
        return trim($this->renderer->renderNodes($node->children()));
    }

    /** Reiner Text eines Knotens (für Anchor-Slugs). */
    private function plain(Node $node): string
    {
        return trim(strip_tags($this->renderer->renderNodes($node->children())));
    }

    private function stripParagraph(string $html): string
    {
        $html = trim($html);
        if (preg_match('#^<p>(.*)</p>$#is', $html, $m)) {
            return trim($m[1]);
        }

        return $html;
    }

    private function slug(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';

        return trim($text, '-');
    }
}
