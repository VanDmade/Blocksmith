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
        Schema::create('blocksmith_documents', function (Blueprint $table) use ($userModel, $organizationModel) {
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
            $table->timestamp('last_edited_at')->nullable();
            $table->bigInteger('last_edited_by')->unsigned()->nullable();
            $table->foreign('last_edited_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('blocksmith_document_type_id')->unsigned()->nullable();
            $table->foreign('blocksmith_document_type_id')
                ->references('id')
                ->on('blocksmith_document_types')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->string('name');
            $table->string('status', 64)->default('active');
            $table->string('uuid', 64)->unique();
            $table->text('description')->nullable();
            $table->json('keywords')->nullable();
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
        Schema::dropIfExists('blocksmith_documents');
    }

};
