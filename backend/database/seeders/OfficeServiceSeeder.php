<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QueueGroupStatus;
use App\Enums\WindowStatus;
use App\Models\Office;
use App\Models\QueueGroup;
use App\Models\Service;
use App\Models\Window;
use Illuminate\Database\Seeder;

/**
 * Seeds the full Office → Queue Group → Service hierarchy plus Windows and their
 * queue-group attachments exactly per plan §5.1 / §5.2.
 */
class OfficeServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $officeData) {
            $office = Office::create([
                'name' => $officeData['name'],
                'latitude' => $officeData['latitude'],
                'longitude' => $officeData['longitude'],
                'geofence_radius_m' => 15,
            ]);

            /** @var array<string, QueueGroup> $groupsByName */
            $groupsByName = [];

            foreach ($officeData['queue_groups'] as $groupData) {
                $group = $office->queueGroups()->create([
                    'name' => $groupData['name'],
                    'prefix' => $groupData['prefix'],
                    'status' => QueueGroupStatus::Open,
                ]);

                $groupsByName[$groupData['name']] = $group;

                foreach ($groupData['services'] as $serviceName => $avgMinutes) {
                    Service::create([
                        'office_id' => $office->id,
                        'queue_group_id' => $group->id,
                        'name' => $serviceName,
                        'avg_service_minutes' => $avgMinutes,
                    ]);
                }
            }

            foreach ($officeData['windows'] as $windowData) {
                $window = Window::create([
                    'office_id' => $office->id,
                    'name' => $windowData['name'],
                    'status' => WindowStatus::Open,
                ]);

                $groupIds = collect($windowData['queue_groups'])
                    ->map(fn (string $groupName): int => $groupsByName[$groupName]->id)
                    ->all();

                $window->queueGroups()->attach($groupIds);
            }
        }
    }

    /**
     * The authoritative seed catalog (plan §5.1 / §5.2). Services map
     * name => avg_service_minutes; windows map to queue-group names.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Registrar',
                'latitude' => 14.6001000,
                'longitude' => 121.0501000,
                'queue_groups' => [
                    [
                        'name' => 'General Services',
                        'prefix' => 'RG',
                        'services' => [
                            'Enrollment Concerns' => 15,
                            'Document Requests' => 2,
                            'Grades Verification' => 3,
                        ],
                    ],
                    [
                        'name' => 'Transcript',
                        'prefix' => 'T',
                        'services' => [
                            'Transcript Requests' => 8,
                        ],
                    ],
                ],
                'windows' => [
                    ['name' => 'Window 1', 'queue_groups' => ['General Services']],
                    ['name' => 'Window 2', 'queue_groups' => ['Transcript']],
                ],
            ],
            [
                'name' => 'Accounting',
                'latitude' => 14.6002000,
                'longitude' => 121.0502000,
                'queue_groups' => [
                    [
                        'name' => 'General Transactions',
                        'prefix' => 'A',
                        'services' => [
                            'Assessment' => 4,
                            'Payment Verification' => 5,
                        ],
                    ],
                    [
                        'name' => 'Refund',
                        'prefix' => 'R',
                        'services' => [
                            'Refund Requests' => 10,
                        ],
                    ],
                ],
                'windows' => [
                    ['name' => 'Window 1', 'queue_groups' => ['General Transactions']],
                    ['name' => 'Window 2', 'queue_groups' => ['General Transactions']],
                    ['name' => 'Window 3', 'queue_groups' => ['Refund']],
                ],
            ],
            [
                'name' => 'Cashier',
                'latitude' => 14.6003000,
                'longitude' => 121.0503000,
                'queue_groups' => [
                    [
                        'name' => 'Payments',
                        'prefix' => 'C',
                        'services' => [
                            'Tuition Payment' => 2,
                            'Miscellaneous Fees' => 3,
                            'Official Receipts' => 2,
                        ],
                    ],
                ],
                'windows' => [
                    ['name' => 'Window 1', 'queue_groups' => ['Payments']],
                    ['name' => 'Window 2', 'queue_groups' => ['Payments']],
                ],
            ],
        ];
    }
}
