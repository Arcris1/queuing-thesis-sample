<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_offices_index_lists_all_offices(): void
    {
        Office::factory()->count(3)->create();

        $this->getJson('/api/offices')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'latitude', 'longitude', 'geofence_radius_m'],
                ],
            ]);
    }

    public function test_offices_index_is_public(): void
    {
        Office::factory()->create();

        // No Authorization header — catalog is public so the app can render pre-login.
        $this->getJson('/api/offices')->assertOk();
    }

    public function test_services_are_grouped_by_queue_group(): void
    {
        $office = Office::factory()->create();

        $groupA = QueueGroup::factory()->for($office)->create([
            'name' => 'General Services',
            'prefix' => 'RG',
        ]);
        $groupB = QueueGroup::factory()->for($office)->create([
            'name' => 'Transcript',
            'prefix' => 'T',
        ]);

        Service::factory()->forQueueGroup($groupA)->create([
            'name' => 'Document Requests',
            'avg_service_minutes' => 2,
        ]);
        Service::factory()->forQueueGroup($groupA)->create([
            'name' => 'Enrollment Concerns',
            'avg_service_minutes' => 15,
        ]);
        Service::factory()->forQueueGroup($groupB)->create([
            'name' => 'Transcript Requests',
            'avg_service_minutes' => 8,
        ]);

        $response = $this->getJson("/api/offices/{$office->id}/services");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'office' => ['id', 'name', 'latitude', 'longitude', 'geofence_radius_m'],
                    'queue_groups' => [
                        [
                            'id',
                            'name',
                            'prefix',
                            'services' => [['id', 'name', 'avg_service_minutes']],
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.office.id', $office->id)
            ->assertJsonCount(2, 'data.queue_groups');

        // Group A (alphabetically "General Services" first) carries its two services.
        $response->assertJsonPath('data.queue_groups.0.name', 'General Services')
            ->assertJsonPath('data.queue_groups.0.prefix', 'RG');

        $this->assertCount(2, $response->json('data.queue_groups.0.services'));
        $this->assertCount(1, $response->json('data.queue_groups.1.services'));
        $this->assertSame('Transcript Requests', $response->json('data.queue_groups.1.services.0.name'));
    }

    public function test_services_excludes_closed_queue_groups(): void
    {
        $office = Office::factory()->create();

        $open = QueueGroup::factory()->for($office)->create(['name' => 'Open Group']);
        $closed = QueueGroup::factory()->for($office)->closed()->create(['name' => 'Closed Group']);

        Service::factory()->forQueueGroup($open)->create();
        Service::factory()->forQueueGroup($closed)->create();

        $this->getJson("/api/offices/{$office->id}/services")
            ->assertOk()
            ->assertJsonCount(1, 'data.queue_groups')
            ->assertJsonPath('data.queue_groups.0.name', 'Open Group');
    }

    public function test_services_returns_404_for_unknown_office(): void
    {
        $this->getJson('/api/offices/99999/services')->assertNotFound();
    }
}
