<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Contracts\HasContextDateTimes as HasContextDateTimesContract;
use Platform\Core\Rules\AttachableContextType;
use Platform\Core\Tests\TestCase;
use Platform\Core\Traits\HasContextDateTimes;

/**
 * Deckt die Validation-Rule {@see AttachableContextType} ab: ein context_type
 * ist nur gültig, wenn er BEIDES ist – in der Whitelist UND ein Implementierer
 * des Marker-Interfaces (Lesson Learned aus Issue #147).
 */
class AttachableContextTypeRuleTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('core.context_date_times.attachable_models', [
            AttachableRuleFixtureModel::class,
            NotInterfaceRuleFixtureModel::class,
            'Platform\\Core\\Tests\\Feature\\DoesNotExistRuleFixtureModel',
        ]);
    }

    private function fails(mixed $value): bool
    {
        $failed = false;

        (new AttachableContextType)->validate('context_type', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_whitelisted_model_implementing_interface_passes(): void
    {
        $this->assertFalse($this->fails(AttachableRuleFixtureModel::class));
    }

    public function test_non_whitelisted_model_fails(): void
    {
        $this->assertTrue($this->fails(UnrelatedRuleFixtureModel::class));
    }

    public function test_whitelisted_model_without_interface_fails(): void
    {
        $this->assertTrue($this->fails(NotInterfaceRuleFixtureModel::class));
    }

    public function test_whitelisted_but_not_installed_class_fails(): void
    {
        $this->assertTrue($this->fails('Platform\\Core\\Tests\\Feature\\DoesNotExistRuleFixtureModel'));
    }

    public function test_empty_value_fails(): void
    {
        $this->assertTrue($this->fails(''));
    }
}

class AttachableRuleFixtureModel extends Model implements HasContextDateTimesContract
{
    use HasContextDateTimes;
}

class NotInterfaceRuleFixtureModel extends Model
{
}

class UnrelatedRuleFixtureModel extends Model
{
}
