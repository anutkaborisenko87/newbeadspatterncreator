<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pattern_comment_blocks', function (Blueprint $table) {
            $table->foreignId('pattern_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['pattern_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_comment_blocks');
    }
};
