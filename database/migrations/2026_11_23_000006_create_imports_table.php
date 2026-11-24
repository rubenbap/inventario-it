<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module', 20);
            $table->string('filename')->nullable();
            $table->unsignedInteger('ok')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->jsonb('created')->nullable();
            $table->jsonb('updated')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
