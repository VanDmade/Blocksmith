<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        $userModel = new (config('auth.providers.users.model'));
        Schema::create('blocksmith_revisions', function (Blueprint $table) use ($userModel) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->bigInteger('deleted_by')->unsigned()->nullable();
            $table->foreign('deleted_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->foreign('created_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('blocksmith_signing_key_id')->unsigned()->nullable();
            $table->foreign('blocksmith_signing_key_id')
                ->references('id')
                ->on('blocksmith_signing_keys')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('blocksmith_anchor_batch_id')->unsigned()->nullable();
            $table->foreign('blocksmith_anchor_batch_id')
                ->references('id')
                ->on('blocksmith_anchor_batches')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->string('uuid', 64)->unique();
            // No longer unique against a parent here - this table has no idea what it
            // belongs to. "No duplicate revision_number for the same parent" is enforced
            // by whichever linking table (e.g. blocksmith_document_revisions) attaches this
            // revision to something, not here.
            $table->unsignedInteger('revision_number');
            $table->string('revision_identifier', 64)->nullable();
            $table->text('revision_reason')->nullable();
            $table->string('status', 64)->default('pending');
            $table->string('content_hash', 128);
            $table->string('previous_hash', 128)->nullable();
            $table->string('signature', 512)->nullable();
            $table->string('hash', 128);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocksmith_revisions');
    }

};
