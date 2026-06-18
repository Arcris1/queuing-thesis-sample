<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('window_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('window_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('queue_tickets')->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('served_at')->nullable();
            $table->timestamps();

            $table->index(['window_id', 'served_at']);
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('window_assignments');
    }
};
