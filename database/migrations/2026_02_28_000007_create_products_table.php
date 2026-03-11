<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();

            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();

            $table->string('stock_status')->default('in_stock');
            $table->integer('stock_quantity')->nullable();

            $table->string('status')->default('active');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['subcategory_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
