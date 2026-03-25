<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sections')) {
            return;
        }

        DB::table('sections')->updateOrInsert(
            ['slug' => 'vente-flash'],
            [
                'label' => 'Vente Flash',
                'position' => 15, /* between Nouvel arrivage (10) and Meilleures ventes (20) */
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('sections')) {
            return;
        }

        DB::table('sections')->where('slug', 'vente-flash')->delete();
    }
};
