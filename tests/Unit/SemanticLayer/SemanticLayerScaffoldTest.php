<?php

namespace Platform\Core\Tests\Unit\SemanticLayer;

use PHPUnit\Framework\TestCase;
use Platform\Core\SemanticLayer\Services\SemanticLayerScaffold;

class SemanticLayerScaffoldTest extends TestCase
{
    private SemanticLayerScaffold $scaffold;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scaffold = new SemanticLayerScaffold();
    }

    public function test_renders_all_sections_in_fixed_order(): void
    {
        $out = $this->scaffold->render(
            perspektive: 'Wir sind ehrliche Handwerker.',
            ton: ['klar', 'präzise'],
            heuristiken: ['Im Zweifel: weniger sagen.'],
            negativRaum: ['keine Buzzwords'],
            versionChain: ['1.0.0'],
        );

        // Header
        $this->assertStringContainsString('[SEMANTIC LAYER · v1.0.0]', $out);
        $this->assertStringContainsString('[/SEMANTIC LAYER]', $out);

        // Reihenfolge: Perspektive vor Ton vor Heuristiken vor Negativ-Raum
        $posPerspektive = strpos($out, 'Perspektive:');
        $posTon = strpos($out, 'Ton:');
        $posHeuristiken = strpos($out, 'Heuristiken (im Zweifel):');
        $posNegativ = strpos($out, 'Was wir nie sagen / sind:');

        $this->assertNotFalse($posPerspektive);
        $this->assertNotFalse($posTon);
        $this->assertNotFalse($posHeuristiken);
        $this->assertNotFalse($posNegativ);

        $this->assertLessThan($posTon, $posPerspektive);
        $this->assertLessThan($posHeuristiken, $posTon);
        $this->assertLessThan($posNegativ, $posHeuristiken);

        // Inhalte
        $this->assertStringContainsString('- klar', $out);
        $this->assertStringContainsString('- präzise', $out);
        $this->assertStringContainsString('- Im Zweifel: weniger sagen.', $out);
        $this->assertStringContainsString('- keine Buzzwords', $out);
    }

    public function test_renders_version_chain_with_multiple_versions(): void
    {
        $out = $this->scaffold->render(
            perspektive: 'Extension',
            ton: ['klar'],
            heuristiken: ['etwas'],
            negativRaum: ['nicht dies'],
            versionChain: ['1.0.0', '0.2.0'],
        );
        $this->assertStringContainsString('v1.0.0 + v0.2.0', $out);
    }

    public function test_renders_fallback_version_label_when_empty(): void
    {
        $out = $this->scaffold->render(
            perspektive: 'Irgendwas',
            ton: ['a'],
            heuristiken: ['b'],
            negativRaum: ['c'],
            versionChain: [],
        );
        $this->assertStringContainsString('v?', $out);
    }

    public function test_trims_whitespace_in_items(): void
    {
        $out = $this->scaffold->render(
            perspektive: '  Klar.  ',
            ton: ['  klar  '],
            heuristiken: ['weniger'],
            negativRaum: ['keine'],
            versionChain: ['1.0.0'],
        );
        $this->assertStringContainsString('Perspektive: Klar.', $out);
        $this->assertStringContainsString('- klar', $out);
        $this->assertStringNotContainsString('-   klar', $out);
    }
}
