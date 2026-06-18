<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('window_queue_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('window_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['window_id', 'queue_group_id']);
            $table->index('queue_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('window_queue_groups');
    }
};
