<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availabilities', function (Blueprint $table): void {
            $table->id();

            $table->string('type')->nullable()->comment('Grouping type for availability (e.g., "office", "remote", "consultation")');
            $table->string('name')->comment('Display name for this availability');

            $table->time('daily_start')->comment('Start time within the day');
            $table->time('daily_end')->comment('End time within the day');

            $table->morphs('schedulable');

            $table->json('days')->nullable()->comment('Recurring days of the week (e.g., ["monday", "wednesday", "friday"])');

            $table->dateTime('validity_start')->nullable()->comment('Start of validity period');
            $table->dateTime('validity_end')->nullable()->comment('End of validity period');

            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index(['validity_start', 'validity_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availabilities');
    }
};
