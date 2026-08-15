<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('blocksmith_documents', function (Blueprint $table) {
            $table->bigInteger('current_blocksmith_revision_id')->unsigned()->after('last_edited_by')->nullable();
            $table->foreign('current_blocksmith_revision_id')
                ->references('id')
                ->on('blocksmith_revisions')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('blocksmith_documents', function (Blueprint $table) {
            $table->dropForeign(['current_blocksmith_revision_id']);
            $table->dropColumn('current_blocksmith_revision_id');
        });
    }

};
