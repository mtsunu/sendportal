<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('senders', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('workspace_id');
            $table->string('label');
            $table->string('from_name');
            $table->string('from_email');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces');
            $table->unique(['workspace_id', 'from_name', 'from_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('senders');
    }
};
