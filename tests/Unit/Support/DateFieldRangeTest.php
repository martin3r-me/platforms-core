<?php

namespace Platform\Core\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Platform\Core\Support\DateFieldRange;

class DateFieldRangeTest extends TestCase
{
    public function test_default_is_past_only_descending(): void
    {
        // Bestehendes Verhalten: nur Vergangenheit, absteigend (neuestes oben).
        $years = DateFieldRange::years(2026, 100, 0);
        $this->assertSame(2026, $years[0]);
        $this->assertSame(1926, end($years));
        $this->assertCount(101, $years);
    }

    public function test_future_years_extend_upward(): void
    {
        // "Gültig bis": aktuelles Jahr + 10 voraus, keine Vergangenheit.
        $years = DateFieldRange::years(2026, 0, 10);
        $this->assertSame(2036, $years[0]);      // größtes Jahr oben (absteigend)
        $this->assertSame(2026, end($years));
        $this->assertCount(11, $years);
        $this->assertContains(2036, $years);
        $this->assertContains(2026, $years);
    }

    public function test_combined_past_and_future(): void
    {
        $years = DateFieldRange::years(2026, 5, 3);
        $this->assertSame(2029, $years[0]);
        $this->assertSame(2021, end($years));
        $this->assertCount(9, $years); // 2021..2029
    }

    public function test_negative_inputs_are_clamped_to_zero(): void
    {
        $years = DateFieldRange::years(2026, -5, -3);
        $this->assertSame([2026], $years);
    }

    public function test_max_year_reflects_future_range(): void
    {
        $this->assertSame(2026, DateFieldRange::maxYear(2026, 0));
        $this->assertSame(2036, DateFieldRange::maxYear(2026, 10));
        $this->assertSame(2026, DateFieldRange::maxYear(2026, -4)); // clamp
    }
}
