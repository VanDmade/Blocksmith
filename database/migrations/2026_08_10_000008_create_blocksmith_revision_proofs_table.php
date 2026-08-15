<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('blocksmith_revision_proofs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->bigInteger('blocksmith_revision_id')->unsigned();
            $table->foreign('blocksmith_revision_id')
                ->references('id')
                ->on('blocksmith_revisions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unique('blocksmith_revision_id');
            $table->json('proof');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocksmith_revision_proofs');
    }

};
