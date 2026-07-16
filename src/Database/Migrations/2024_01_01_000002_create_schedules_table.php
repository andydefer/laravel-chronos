<?php

declare(strict_types=1);

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('availability_id')
                ->constrained('availabilities')
                ->onDelete('cascade');

            $table->morphs('schedulable');

            $table->string('title')->comment('Title of the scheduled item');
            $table->text('description')->nullable()->comment('Description of the scheduled item');

            $table->dateTime('start_datetime')->comment('Start of the scheduled period');
            $table->dateTime('end_datetime')->comment('End of the scheduled period');

            $table->string('status')->default(ScheduleStatus::AVAILABLE->value)->comment('Status of the schedule');

            $table->json('metadata')->nullable()->comment('Additional metadata');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['schedulable_type', 'schedulable_id']);
            $table->index('availability_id');
            $table->index('status');
            $table->index(['start_datetime', 'end_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
