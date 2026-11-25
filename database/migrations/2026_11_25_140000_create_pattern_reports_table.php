<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            $table->boolean('publication_blocked')->default(false);
        });
        Schema::create('pattern_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pattern_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('preview')->nullable();
            $table->json('drawing')->nullable();
            $table->string('topic');
            $table->text('message');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->index(['pattern_id', 'reporter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_reports');
        Schema::table('patterns', fn (Blueprint $table) => $table->dropColumn('publication_blocked'));
    }
};
