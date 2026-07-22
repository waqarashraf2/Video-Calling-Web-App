<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_rooms', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('first_guest_session_id')->constrained('guest_sessions')->cascadeOnDelete();
            $table->foreignId('second_guest_session_id')->constrained('guest_sessions')->cascadeOnDelete();
            $table->foreignId('initiator_guest_session_id')->constrained('guest_sessions')->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable()->index();
            $table->string('end_reason', 50)->nullable();
            $table->timestamps();
            $table->index(['first_guest_session_id', 'second_guest_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_rooms');
    }
};
