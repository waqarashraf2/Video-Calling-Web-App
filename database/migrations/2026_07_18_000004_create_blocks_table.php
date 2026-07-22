<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_session_id')->nullable()->constrained('guest_sessions')->nullOnDelete();
            $table->foreignId('blocked_session_id')->nullable()->constrained('guest_sessions')->nullOnDelete();
            $table->string('blocker_fingerprint', 128)->index();
            $table->string('blocked_fingerprint', 128)->index();
            $table->dateTime('expires_at')->index();
            $table->timestamps();
            $table->unique(['blocker_fingerprint', 'blocked_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
