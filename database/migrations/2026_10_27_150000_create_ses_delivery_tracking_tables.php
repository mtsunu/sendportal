<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ses_delivery_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('event_key', 64)->unique();
            $table->unsignedInteger('workspace_id')->index();
            $table->string('message_id')->index();
            $table->string('region', 40);
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ses_message_statuses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('sendportal_message_id')->unique();
            $table->unsignedInteger('workspace_id')->index();
            $table->string('status', 30)->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->timestamp('vdm_checked_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ses_message_statuses');
        Schema::dropIfExists('ses_delivery_events');
    }
};
