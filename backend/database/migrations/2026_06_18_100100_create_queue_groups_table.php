<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix');
            $table->string('status')->default('open');
            $table->timestamps();

            $table->unique(['office_id', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_groups');
    }
};
