<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Office;
use App\Services\GeofenceService;
use Tests\TestCase;

class GeofenceServiceTest extends TestCase
{
    private GeofenceService $geofence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geofence = new GeofenceService;
    }

    public function test_reproduces_plan_section_8_worked_example(): void
    {
        // Plan §8 office vs student. The plan text rounds the distance to "≈8.4 m",
        // but the mathematically correct Haversine distance for these coordinates
        // is ~3.92 m. We assert the true value; either way the conclusion holds:
        // it is comfortably within the 15 m radius → eligible.
        $distance = $this->geofence->distanceMeters(
            14.600100,
            121.050100,
            14.600120,
            121.050130,
        );

        $this->assertEqualsWithDelta(3.92, $distance, 0.05);
        $this->assertLessThan(15.0, $distance);
    }

    public function test_zero_distance_for_identical_coordinates(): void
    {
        $this->assertSame(
            0.0,
            $this->geofence->distanceMeters(14.6001, 121.0501, 14.6001, 121.0501),
        );
    }

    public function test_antipodal_points_are_about_half_the_earth_circumference(): void
    {
        // London ~(51.5, 0) and its antipode ~(-51.5, 180): ~half circumference.
        $distance = $this->geofence->distanceMeters(51.5, 0.0, -51.5, 180.0);

        // Half of 2πR ≈ 20,015 km. Allow generous slack for the spherical model.
        $this->assertEqualsWithDelta(20_015_000.0, $distance, 50_000.0);
    }

    public function test_one_degree_of_latitude_is_about_111_km(): void
    {
        $distance = $this->geofence->distanceMeters(0.0, 0.0, 1.0, 0.0);

        $this->assertEqualsWithDelta(111_195.0, $distance, 500.0);
    }

    public function test_is_within_radius_honors_the_threshold(): void
    {
        // ~8.4 m apart.
        $this->assertTrue($this->geofence->isWithinRadius(
            14.600100,
            121.050100,
            14.600120,
            121.050130,
            15.0,
        ));

        // Far apart.
        $this->assertFalse($this->geofence->isWithinRadius(
            14.600100,
            121.050100,
            14.610000,
            121.060000,
            15.0,
        ));
    }

    public function test_is_within_office_uses_per_office_radius(): void
    {
        $office = new Office([
            'latitude' => 14.600100,
            'longitude' => 121.050100,
            'geofence_radius_m' => 15,
        ]);

        // ~3.92 m → inside a 15 m radius.
        $this->assertTrue($this->geofence->isWithinOffice($office, 14.600120, 121.050130));

        // Same point, but a tighter 2 m office radius → outside.
        $office->geofence_radius_m = 2;
        $this->assertFalse($this->geofence->isWithinOffice($office, 14.600120, 121.050130));
    }

    public function test_radius_for_falls_back_to_config_default_when_office_has_none(): void
    {
        $office = new Office([
            'latitude' => 14.6001,
            'longitude' => 121.0501,
            'geofence_radius_m' => null,
        ]);

        $this->assertSame(15.0, $this->geofence->radiusFor($office));
    }
}
