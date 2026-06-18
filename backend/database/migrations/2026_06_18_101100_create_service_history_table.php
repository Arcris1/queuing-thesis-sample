<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('window_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('served_at');
            $table->decimal('duration_minutes', 8, 2);
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedTinyInteger('hour_of_day');
            $table->unsignedTinyInteger('active_windows');
            $table->timestamps();

            // ML training query indexes (plan §10).
            $table->index(['office_id', 'served_at']);
            $table->index(['queue_group_id', 'served_at']);
            $table->index(['day_of_week', 'hour_of_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_history');
    }
};
