<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name')->nullable();
            $table->string('asset_tag', 50)->unique();
            $table->string('serial')->nullable()->unique();
            $table->foreignId('model_id')->constrained('models')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('status_labels')->restrictOnDelete();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->date('warranty_expires')->nullable()->index();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rtd_location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->nullableMorphs('assigned', 'assets_assigned_index');
            $table->timestamp('last_checkout')->nullable();
            $table->timestamp('last_checkin')->nullable();
            $table->date('expected_checkin')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('image')->nullable();
            $table->jsonb('custom_values')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status_id']);
            $table->index('model_id');
            $table->index('status_id');
            $table->index('location_id');
        });

        Schema::create('accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_number')->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedInteger('min_amt')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('order_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
        });

        Schema::create('accessory_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accessory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            $table->index(['accessory_id', 'checked_in_at']);
            $table->index('assigned_to');
        });

        Schema::create('action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 40);
            $table->string('item_type');
            $table->unsignedBigInteger('item_id');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->jsonb('log_meta')->nullable();
            $table->timestamps();
            $table->index(['item_type', 'item_id']);
            $table->index(['target_type', 'target_id']);
            $table->index(['company_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_logs');
        Schema::dropIfExists('accessory_checkouts');
        Schema::dropIfExists('accessories');
        Schema::dropIfExists('assets');
    }
};
