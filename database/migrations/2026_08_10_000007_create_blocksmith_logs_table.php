<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        $userModel = new (config('auth.providers.users.model'));
        $organizationModel = config('blocksmith.organization_model', null);
        $organizationModel = is_null($organizationModel) || !class_exists($organizationModel) ? null : new ($organizationModel)();
        Schema::create('blocksmith_logs', function (Blueprint $table) use ($userModel, $organizationModel) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->foreign('created_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('blocksmith_document_id')->unsigned()->nullable();
            $table->foreign('blocksmith_document_id')
                ->references('id')
                ->on('blocksmith_documents')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('blocksmith_revision_id')->unsigned()->nullable();
            $table->foreign('blocksmith_revision_id')
                ->references('id')
                ->on('blocksmith_revisions')
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
            $table->string('event');
            $table->string('level', 16)->default('info');
            $table->text('message')->nullable();
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->json('metadata')->nullable();
            if (!is_null($organizationModel)) {
                $table->bigInteger('organization_id')->unsigned()->nullable();
                $table->foreign('organization_id')
                    ->references($organizationModel->getKeyName())
                    ->on($organizationModel->getTable())
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocksmith_logs');
    }

};
