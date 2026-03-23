<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table): void {
            $table->id();
            $table->string('language', 8)->default('fr');
            $table->boolean('in_europe')->default(false);
            $table->string('fingerprint')->unique();
            $table->string('utm_source')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
