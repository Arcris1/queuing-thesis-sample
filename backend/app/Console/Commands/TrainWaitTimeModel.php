<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WaitTimeTrainer;
use App\Support\WaitTimeModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * `php artisan ml:train` — fit the wait-time regression from `service_history`
 * and export the model artifact the API loads at inference (task 023, plan §10).
 *
 * Pure-PHP training (no Python). Prints the holdout metrics (R², RMSE) and the
 * basis it stored. Re-run any time history grows (the retrain process); it
 * overwrites the artifact in place. Below the cold-start row floor it stores a
 * per-service-average fallback instead of a regression.
 */
final class TrainWaitTimeModel extends Command
{
    protected $signature = 'ml:train';

    protected $description = 'Train the wait-time regression from service_history and export the model (plan §10).';

    public function handle(WaitTimeTrainer $trainer): int
    {
        $this->info('Training wait-time model from service_history…');

        $model = $trainer->train();

        if ($model->kind === WaitTimeModel::KIND_FALLBACK) {
            $this->warn(sprintf(
                'Cold-start: only %d rows (< %d). Stored a per-service-average FALLBACK model.',
                $model->trainingRows,
                (int) config('queue_system.prediction.min_training_rows'),
            ));
        } else {
            $this->info(sprintf('Fitted linear regression on %d rows.', $model->trainingRows));
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Kind', $model->kind],
                ['Version', $model->version],
                ['Training rows', (string) $model->trainingRows],
                ['Features', (string) $model->featureCount()],
                ['R² (holdout)', number_format($model->rSquared, 4)],
                ['RMSE (holdout)', number_format($model->rmse, 4).' min'],
                ['Artifact', Storage::disk('local')->path((string) config('queue_system.prediction.model_path'))],
            ],
        );

        return self::SUCCESS;
    }
}
