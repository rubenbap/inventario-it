<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 5)->unique();
            $table->string('tax_id', 20)->nullable();
            $table->string('logo')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('asset_tag_counter')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('country', 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('url')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category_type', 20)->default('asset');
            $table->string('kind', 20)->default('device')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name', 'category_type']);
        });

        Schema::create('status_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('deployable')->default(false);
            $table->boolean('pending')->default(false);
            $table->boolean('archived')->default(false);
            $table->string('color', 10)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_labels');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('companies');
    }
};
