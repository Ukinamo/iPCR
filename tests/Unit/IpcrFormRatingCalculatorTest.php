<?php

namespace Tests\Unit;

use App\Services\IpcrFormRatingCalculator;
use PHPUnit\Framework\TestCase;

class IpcrFormRatingCalculatorTest extends TestCase
{
    public function test_score_row_from_ratings_returns_nulls_when_weight_is_null(): void
    {
        $result = IpcrFormRatingCalculator::scoreRowFromRatings(5, 4, 3, null);

        $this->assertNull($result['quality']);
        $this->assertNull($result['efficiency']);
        $this->assertNull($result['timeliness']);
        $this->assertNull($result['average']);
        $this->assertNull($result['weighted']);
    }

    public function test_score_row_from_ratings_calculates_when_weight_is_present(): void
    {
        $result = IpcrFormRatingCalculator::scoreRowFromRatings(5, 4, 3, 60.0);

        $this->assertSame(5, $result['quality']);
        $this->assertSame(4, $result['efficiency']);
        $this->assertSame(3, $result['timeliness']);
        $this->assertSame(4.0, $result['average']);
        $this->assertSame(2.4, $result['weighted']);
    }
}
