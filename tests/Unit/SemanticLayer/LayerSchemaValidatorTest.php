<?php

namespace Platform\Core\Tests\Unit\SemanticLayer;

use PHPUnit\Framework\TestCase;
use Platform\Core\SemanticLayer\Exceptions\InvalidLayerSchemaException;
use Platform\Core\SemanticLayer\Schema\LayerSchemaValidator;

class LayerSchemaValidatorTest extends TestCase
{
    private LayerSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new LayerSchemaValidator();
    }

    public function test_accepts_valid_payload(): void
    {
        $payload = [
            'perspektive' => 'Wir sind ehrliche Handwerker, die digitale Systeme bauen.',
            'ton' => ['klar', 'präzise', 'ohne Marketing-Floskeln'],
            'heuristiken' => ['Im Zweifel: weniger sagen.', 'Benenne Trade-offs statt zu verstecken.'],
            'negativ_raum' => ['keine Buzzwords', 'keine Superlative'],
        ];
        $this->validator->validate($payload);
        $this->assertTrue(true); // kein Throw == Erfolg
    }

    public function test_rejects_missing_field(): void
    {
        $this->expectException(InvalidLayerSchemaException::class);
        $this->validator->validate([
            'perspektive' => 'Wir sind klar.',
            'ton' => ['klar'],
            'heuristiken' => ['etwas'],
            // negativ_raum fehlt
        ]);
    }

    public function test_rejects_extra_field(): void
    {
        $this->expectException(InvalidLayerSchemaException::class);
        $this->validator->validate([
            'perspektive' => 'Wir sind klar.',
            'ton' => ['klar'],
            'heuristiken' => ['etwas'],
            'negativ_raum' => ['nicht dies'],
            'extra' => 'boom',
        ]);
    }

    public function test_rejects_empty_array(): void
    {
        $this->expectException(InvalidLayerSchemaException::class);
        $this->validator->validate([
            'perspektive' => 'Wir sind klar.',
            'ton' => [],
            'heuristiken' => ['etwas'],
            'negativ_raum' => ['nicht dies'],
        ]);
    }

    public function test_rejects_too_many_items(): void
    {
        $this->expectException(InvalidLayerSchemaException::class);
        $ton = array_fill(0, 13, 'klar');
        $this->validator->validate([
            'perspektive' => 'Wir sind klar.',
            'ton' => $ton,
            'heuristiken' => ['etwas'],
            'negativ_raum' => ['nicht dies'],
        ]);
    }

    public function test_rejects_too_long_perspektive(): void
    {
        $this->expectException(InvalidLayerSchemaException::class);
        $this->validator->validate([
            'perspektive' => str_repeat('a', 501),
            'ton' => ['klar'],
            'heuristiken' => ['etwas'],
            'negativ_raum' => ['nicht dies'],
        ]);
    }

    public function test_rejects_non_list_array(): void
    {
        $this->expectException(InvalidLayerSchemaException::class);
        $this->validator->validate([
            'perspektive' => 'Wir sind klar.',
            'ton' => ['a' => 'klar'],
            'heuristiken' => ['etwas'],
            'negativ_raum' => ['nicht dies'],
        ]);
    }

    public function test_estimate_tokens_approximates(): void
    {
        $rendered = str_repeat('a', 800); // ~200 Tokens
        $tokens = $this->validator->estimateTokens($rendered);
        $this->assertGreaterThanOrEqual(190, $tokens);
        $this->assertLessThanOrEqual(210, $tokens);
    }

    public function test_token_budget_warns_low(): void
    {
        $warning = $this->validator->checkTokenBudget(50);
        $this->assertNotNull($warning);
        $this->assertStringContainsString('Minimum', $warning);
    }

    public function test_token_budget_warns_high(): void
    {
        $warning = $this->validator->checkTokenBudget(300);
        $this->assertNotNull($warning);
        $this->assertStringContainsString('Maximum', $warning);
    }

    public function test_token_budget_ok_in_range(): void
    {
        $this->assertNull($this->validator->checkTokenBudget(170));
    }
}
