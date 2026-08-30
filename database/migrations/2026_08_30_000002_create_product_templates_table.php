<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->tinyInteger('type')->nullable();
            $table->tinyInteger('material')->nullable();
            $table->decimal('weight', 12, 4)->nullable();
            $table->tinyInteger('weight_unit')->nullable();
            $table->unsignedInteger('amount')->default(1);
            $table->decimal('face_value', 12, 2)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('size')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->string('front_image_path')->nullable();
            $table->string('back_image_path')->nullable();
            $table->string('text_front')->nullable();
            $table->string('text_back')->nullable();
            $table->json('assets')->nullable();
            $table->text('notes')->nullable();
            $table->string('quality')->nullable();
            $table->boolean('certificate')->default(false);
            $table->string('manufacturer')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->string('fineness')->nullable();
            $table->string('packaging')->nullable();
            $table->unsignedInteger('mintage')->nullable();
            $table->date('purchase_date')->nullable();
            $table->unsignedSmallInteger('tax_year')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('depository_id')->constrained('product_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
        });

        Schema::dropIfExists('product_templates');
    }
};
