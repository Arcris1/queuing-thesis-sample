<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconnect-grace tracking for the away/offline skip flow (task 017, plan §9/§11).
     *
     * When the routing engine reaches a ticket whose student is Away/Offline (or
     * out of range), instead of skipping immediately it offers a short reconnect
     * grace window. `grace_until` is the deadline; `grace_offered_at` records that
     * the one-time warning has already fired so it never repeats within a window.
     */
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->timestamp('grace_until')->nullable()->after('served_at');
            $table->timestamp('grace_offered_at')->nullable()->after('grace_until');
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropColumn(['grace_until', 'grace_offered_at']);
        });
    }
};
