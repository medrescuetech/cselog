<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    private array $square = [
        'type' => 'Polygon',
        'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]], [[4, 4], [6, 4], [6, 6], [4, 6], [4, 4]]],
    ];

    public function test_contains_respects_holes(): void
    {
        $this->assertTrue(Geo::contains($this->square, 1, 1));
        $this->assertFalse(Geo::contains($this->square, 5, 5));
        $this->assertFalse(Geo::contains($this->square, 11, 5));
    }

    public function test_area_subtracts_holes(): void
    {
        $this->assertEqualsWithDelta(96.0, Geo::area($this->square), 1e-9);
    }

    public function test_centroid_and_multipolygon(): void
    {
        [$x, $y] = Geo::centroid($this->square);
        $this->assertEqualsWithDelta(5.0, $x, 1e-9);
        $this->assertEqualsWithDelta(5.0, $y, 1e-9);

        $multi = ['type' => 'MultiPolygon', 'coordinates' => [$this->square['coordinates'], [[[20, 20], [21, 20], [21, 21], [20, 21], [20, 20]]]]];
        $this->assertTrue(Geo::contains($multi, 20.5, 20.5));
        $this->assertEqualsWithDelta(97.0, Geo::area($multi), 1e-9);
    }
}
