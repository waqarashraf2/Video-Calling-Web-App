<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_session_id')->nullable()->constrained('guest_sessions')->nullOnDelete();
            $table->foreignId('reported_session_id')->nullable()->constrained('guest_sessions')->nullOnDelete();
            $table->foreignId('call_room_id')->nullable()->constrained('call_rooms')->nullOnDelete();
            $table->string('reason', 80);
            $table->string('description', 500)->nullable();
            $table->string('abuse_fingerprint', 128)->index();
            $table->string('status', 30)->default('open')->index();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
