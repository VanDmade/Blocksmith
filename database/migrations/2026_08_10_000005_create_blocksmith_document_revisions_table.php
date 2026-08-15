<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('blocksmith_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('blocksmith_document_id')->unsigned();
            $table->foreign('blocksmith_document_id')
                ->references('id')
                ->on('blocksmith_documents')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->bigInteger('blocksmith_revision_id')->unsigned();
            $table->foreign('blocksmith_revision_id')
                ->references('id')
                ->on('blocksmith_revisions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            // A revision only ever belongs to one document - this is what enforces that
            // at the database level, even though the relationship is modeled as a pivot.
            $table->unique('blocksmith_revision_id');
            $table->unique(['blocksmith_document_id', 'blocksmith_revision_id']);
            // Everything below is document-specific "what did we actually store" detail.
            // blocksmith_revisions itself has no idea any of this exists - a different
            // resource linking into revisions later would have its own linking table
            // with whatever fields make sense for it instead of these.
            $table->string('disk', 32)->default(config('blocksmith.disk', 'local'));
            $table->string('path');
            $table->string('extension', 16)->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->integer('size')->nullable();
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocksmith_document_revisions');
    }

};
