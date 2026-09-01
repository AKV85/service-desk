<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_resources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('resource_type');

            $table->string('external_id')->nullable();
            $table->string('repository');

            $table->unsignedBigInteger('resource_number')->nullable();
            $table->string('reference')->nullable();

            $table->string('url')->nullable();
            $table->string('external_state')->nullable();

            $table->timestamp('external_updated_at')->nullable();

            $table->string('sync_status')->default('pending');

            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('ticket_id');
            $table->index(['ticket_id', 'resource_type']);
            $table->index(['repository', 'resource_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_resources');
    }
};
