<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('blocksmith_anchor_batches', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('merkle_root', 128);
            $table->string('status', 32)->default('pending');
            $table->string('provider');
            $table->text('proof_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocksmith_anchor_batches');
    }

};
