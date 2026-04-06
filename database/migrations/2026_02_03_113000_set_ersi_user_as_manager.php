<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereRaw('lower(name) = ?', ['ersi'])
            ->orWhereRaw('lower(email) = ?', ['ersi'])
            ->orWhereRaw('lower(email) like ?', ['ersi@%'])
            ->update([
                'role' => UserRole::Manager->value,
                'manager_id' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereRaw('lower(name) = ?', ['ersi'])
            ->orWhereRaw('lower(email) = ?', ['ersi'])
            ->orWhereRaw('lower(email) like ?', ['ersi@%'])
            ->update([
                'role' => UserRole::Salesperson->value,
                'updated_at' => now(),
            ]);
    }
};
