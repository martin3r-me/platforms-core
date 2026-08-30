<?php

namespace Platform\Core\Tests\Unit\Tools;

use Platform\Core\Tests\TestCase;
use Platform\Core\Mcp\Tools\ExecuteToolContract;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\EchoTool;
use Platform\Core\Contracts\ToolContext;
use Illuminate\Foundation\Auth\User;

class ExecuteToolContractTest extends TestCase
{
    private ExecuteToolContract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contract = new ExecuteToolContract();

        $registry = app(ToolRegistry::class);
        if (!$registry->has('echo')) {
            $registry->register(new EchoTool());
        }
        // Ein lesendes Tool (Name endet auf .GET → read_only) für die Scope-Tests.
        if (!$registry->has('echoread.GET')) {
            $registry->register(new class extends EchoTool {
                public function getName(): string
                {
                    return 'echoread.GET';
                }
            });
        }
    }

    /**
     * Kontext mit einem User, dessen currentAccessToken()->can(scope) das Callback abbildet.
     * $tokenCan === null → gar kein Access-Token (Session-/Edge-Fall).
     */
    private function contextWithScopes(?\Closure $tokenCan): ToolContext
    {
        $token = $tokenCan === null ? null : new class($tokenCan) {
            private $cb;
            public function __construct(\Closure $cb)
            {
                $this->cb = $cb;
            }
            public function can(string $scope): bool
            {
                return ($this->cb)($scope);
            }
        };

        $user = new class($token) extends User {
            public $id = 1;
            private $tok;
            public function __construct($tok)
            {
                $this->tok = $tok;
            }
            public function currentAccessToken()
            {
                return $this->tok;
            }
        };

        return new ToolContext($user);
    }

    private function createTestContext(): ToolContext
    {
        $user = new class extends User {
            public $id = 1;
            protected $fillable = ['id'];
        };

        return new ToolContext($user);
    }

    public function test_can_execute_with_valid_tool_and_arguments(): void
    {
        $result = $this->contract->execute([
            'tool' => 'echo',
            'arguments' => ['message' => 'Hallo'],
        ], $this->createTestContext());

        $this->assertTrue($result->success);
        $this->assertEquals('Hallo', $result->data['echo']);
    }

    public function test_rejects_params_instead_of_arguments(): void
    {
        $result = $this->contract->execute([
            'tool' => 'echo',
            'params' => ['message' => 'Hallo'],
        ], $this->createTestContext());

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_PAYLOAD', $result->errorCode);
        $this->assertStringContainsString('params', $result->error);
        $this->assertStringContainsString('arguments', $result->error);
    }

    public function test_rejects_unknown_top_level_key(): void
    {
        $result = $this->contract->execute([
            'tool' => 'echo',
            'arguments' => ['message' => 'Hallo'],
            'foo' => 'bar',
        ], $this->createTestContext());

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_PAYLOAD', $result->errorCode);
        $this->assertStringContainsString('foo', $result->error);
    }

    public function test_read_only_token_blocks_write_tool(): void
    {
        // Lese-Ausweis: nur "read", kein "*"/"write" → schreibendes Tool (echo) wird geblockt.
        $ctx = $this->contextWithScopes(fn (string $s) => $s === 'read');

        $result = $this->contract->execute([
            'tool' => 'echo',
            'arguments' => ['message' => 'Hallo'],
        ], $ctx);

        $this->assertFalse($result->success);
        $this->assertEquals('SCOPE_DENIED', $result->errorCode);
    }

    public function test_read_only_token_allows_read_tool(): void
    {
        // Derselbe Lese-Ausweis darf ein read_only-Tool (echoread.GET) ausführen.
        $ctx = $this->contextWithScopes(fn (string $s) => $s === 'read');

        $result = $this->contract->execute([
            'tool' => 'echoread.GET',
            'arguments' => ['message' => 'Hallo'],
        ], $ctx);

        $this->assertTrue($result->success);
        $this->assertEquals('Hallo', $result->data['echo']);
    }

    public function test_full_scope_token_allows_write_tool(): void
    {
        // Voll-Token (scope "*") → schreibendes Tool bleibt erlaubt (rückwärtskompatibel).
        $ctx = $this->contextWithScopes(fn (string $s) => true);

        $result = $this->contract->execute([
            'tool' => 'echo',
            'arguments' => ['message' => 'Hallo'],
        ], $ctx);

        $this->assertTrue($result->success);
        $this->assertEquals('Hallo', $result->data['echo']);
    }

    public function test_missing_access_token_does_not_restrict(): void
    {
        // KEIN Access-Token (Session/Edge-Fall) → availability-safe: Schreib-Tool bleibt erlaubt,
        // niemals ein Totalausfall für Agenten, deren Token nicht angehängt wäre.
        $ctx = $this->contextWithScopes(null);

        $result = $this->contract->execute([
            'tool' => 'echo',
            'arguments' => ['message' => 'Hallo'],
        ], $ctx);

        $this->assertTrue($result->success);
        $this->assertEquals('Hallo', $result->data['echo']);
    }

    public function test_token_without_read_scope_is_not_restricted(): void
    {
        // OAuth-Connector-Token o.ä.: trägt WEDER read NOCH write/* → KEINE Einschränkung. Nur ein
        // Token, der `read` positiv trägt (opt-in-Lese-Ausweis), wird eingeschränkt.
        $ctx = $this->contextWithScopes(fn (string $s) => false);

        $result = $this->contract->execute([
            'tool' => 'echo',
            'arguments' => ['message' => 'Hallo'],
        ], $ctx);

        $this->assertTrue($result->success);
        $this->assertEquals('Hallo', $result->data['echo']);
    }
}
