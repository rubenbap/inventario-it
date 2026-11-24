<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('username')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('employee_num', 50)->nullable();
            $table->string('jobtitle')->nullable();
            $table->string('phone', 40)->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('can_login')->default(false);
            $table->string('role', 20)->nullable();
            $table->boolean('activated')->default(true);
            $table->text('notes')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index(['first_name', 'last_name']);
        });


        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
    }
};
