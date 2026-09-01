<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_issues', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('external_id')->nullable()->unique();
            $table->string('issue_key')->nullable()->unique();
            $table->string('url')->nullable();

            $table->string('external_status')->nullable();

            $table->string('sync_status')->default('pending');

            $table->timestamp('external_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->text('last_error')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_issues');
    }
};
