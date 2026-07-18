<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impediments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('availability_id')
                ->constrained('availabilities')
                ->onDelete('cascade');

            $table->string('reason')->nullable()->comment('Optional explanation for the impediment');

            $table->dateTime('start_datetime')->comment('Start of the blocked period');
            $table->dateTime('end_datetime')->comment('End of the blocked period');

            $table->json('metadata')->nullable()->comment('Optional additional data');

            $table->timestamps();
            $table->softDeletes();

            $table->index('availability_id');
            $table->index(['start_datetime', 'end_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impediments');
    }
};
