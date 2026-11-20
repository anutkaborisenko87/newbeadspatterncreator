<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            // Keep the source identifier even if its author removes the original.
            $table->unsignedBigInteger('copied_from_id')->nullable();
            $table->unique(['user_id', 'copied_from_id']);
        });
    }

    public function down(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'copied_from_id']);
            $table->dropColumn('copied_from_id');
        });
    }
};
