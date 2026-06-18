<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number');
            $table->string('status')->default('waiting');
            $table->unsignedTinyInteger('priority')->default(0);
            $table->timestamp('joined_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();

            // Oldest-eligible-per-group routing lookup (plan §5.3).
            $table->index(['queue_group_id', 'status', 'priority', 'joined_at'], 'queue_tickets_routing_index');
            $table->index(['user_id', 'status']);
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};
