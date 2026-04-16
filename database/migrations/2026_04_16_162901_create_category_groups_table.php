<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('icon', 500)->nullable()->after('image');
        });

        Schema::create('category_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('icon', 500)->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
        });

        Schema::table('subcategories', function (Blueprint $table): void {
            // InnoDB uses the composite unique (category_id, slug) to support the category_id FK; drop FK first.
            $table->dropForeign(['category_id']);
        });

        Schema::table('subcategories', function (Blueprint $table): void {
            $table->dropUnique(['category_id', 'slug']);
        });

        // Nullable so existing rows survive; new subcategories must still set a real group in the app layer.
        Schema::table('subcategories', function (Blueprint $table): void {
            $table->foreignId('category_group_id')->nullable()->after('category_id')->constrained('category_groups')->cascadeOnDelete();
        });

        Schema::table('subcategories', function (Blueprint $table): void {
            $table->unique(['category_group_id', 'slug']);
        });

        Schema::table('subcategories', function (Blueprint $table): void {
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('category_group_id')->nullable()->after('subcategory_id')->constrained('category_groups')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index(['category_group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['category_group_id', 'status']);
            $table->dropForeign(['category_group_id']);
            $table->dropColumn('category_group_id');
        });

        Schema::table('subcategories', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
        });

        Schema::table('subcategories', function (Blueprint $table): void {
            $table->dropUnique(['category_group_id', 'slug']);
        });

        Schema::table('subcategories', function (Blueprint $table): void {
            $table->dropForeign(['category_group_id']);
            $table->dropColumn('category_group_id');
        });

        Schema::table('subcategories', function (Blueprint $table): void {
            $table->unique(['category_id', 'slug']);
        });

        Schema::table('subcategories', function (Blueprint $table): void {
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });

        Schema::dropIfExists('category_groups');

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('icon');
        });
    }
};
