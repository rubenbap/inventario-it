<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('db_column')->nullable()->unique();
            $table->string('format', 191)->default('ANY');
            $table->string('element', 20)->default('text');
            $table->text('field_values')->nullable();
            $table->boolean('field_encrypted')->default(false);
            $table->boolean('show_in_listview')->default(false);
            $table->string('help_text')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_fieldsets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('custom_field_custom_fieldset', function (Blueprint $table) {
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_fieldset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('required')->default(false);
            $table->primary(['custom_field_id', 'custom_fieldset_id']);
        });

        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('model_number')->nullable();
            $table->foreignId('manufacturer_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('fieldset_id')->nullable()->constrained('custom_fieldsets')->nullOnDelete();
            $table->string('image')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['manufacturer_id', 'name']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('models');
        Schema::dropIfExists('custom_field_custom_fieldset');
        Schema::dropIfExists('custom_fieldsets');
        Schema::dropIfExists('custom_fields');
    }
};
