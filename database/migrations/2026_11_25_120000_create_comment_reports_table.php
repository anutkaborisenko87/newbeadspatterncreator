<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reported_login');
            $table->foreignId('comment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pattern_title')->nullable();
            $table->text('comment_text');
            $table->string('topic');
            $table->text('message');
            $table->json('evidence');
            $table->string('locale');
            $table->string('status')->default('pending');
            $table->text('decision')->nullable();
            $table->timestamps();
            $table->index(['reporter_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reports');
    }
};
