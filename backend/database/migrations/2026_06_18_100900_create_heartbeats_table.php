<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('queue_tickets')->nullOnDelete();
            $table->timestamp('last_seen');
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->string('network_status')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'last_seen']);
            $table->index(['user_id', 'last_seen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heartbeats');
    }
};
