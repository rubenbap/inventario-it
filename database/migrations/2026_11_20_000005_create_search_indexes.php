<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEXES = [
        'assets_asset_tag_trgm' => 'assets USING gin (asset_tag gin_trgm_ops)',
        'assets_serial_trgm' => 'assets USING gin (serial gin_trgm_ops)',
        'assets_name_trgm' => 'assets USING gin (name gin_trgm_ops)',
        'assets_custom_values_trgm' => 'assets USING gin ((custom_values::text) gin_trgm_ops)',
        'models_name_trgm' => 'models USING gin (name gin_trgm_ops)',
        'users_first_name_trgm' => 'users USING gin (first_name gin_trgm_ops)',
        'users_last_name_trgm' => 'users USING gin (last_name gin_trgm_ops)',
        'users_email_trgm' => 'users USING gin (email gin_trgm_ops)',
        'accessories_name_trgm' => 'accessories USING gin (name gin_trgm_ops)',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach (self::INDEXES as $name => $definition) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$definition}");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
