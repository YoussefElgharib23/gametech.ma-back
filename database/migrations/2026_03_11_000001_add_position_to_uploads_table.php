<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table): void {
            $table->unsignedInteger('position')->default(0)->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
