<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('size');
            $table->string('mime_type')->nullable();
            $table->string('extension', 10)->nullable();
            $table->string('path');
            $table->nullableMorphs('uploadable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};

