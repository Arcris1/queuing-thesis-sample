<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('avg_service_minutes');
            $table->timestamps();

            $table->index(['office_id', 'queue_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
