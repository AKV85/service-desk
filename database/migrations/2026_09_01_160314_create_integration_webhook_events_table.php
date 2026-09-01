<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_webhook_events', function (Blueprint $table) {
            $table->id();

            $table->string('provider');
            $table->string('external_event_id');
            $table->string('event_type');
            $table->string('status');

            $table->json('payload')->nullable();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(
                ['provider', 'external_event_id'],
                'integration_webhook_events_provider_event_unique',
            );

            $table->index('provider');
            $table->index('event_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_webhook_events');
    }
};
