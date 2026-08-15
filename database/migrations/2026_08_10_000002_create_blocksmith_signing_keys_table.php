<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        $userModel = new (config('auth.providers.users.model'));
        Schema::create('blocksmith_signing_keys', function (Blueprint $table) use ($userModel) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->foreign('user_id')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->timestamp('revoked_at')->nullable();
            $table->bigInteger('revoked_by')->unsigned()->nullable();
            $table->foreign('revoked_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->text('revoke_reason')->nullable();
            $table->string('name')->nullable();
            $table->string('public_key', 128)->unique();
            // Only ever populated when 'custodial' is true. Encrypted cast on the model,
            $table->text('private_key')->nullable();
            $table->boolean('custodial')->default(false);
            $table->string('algorithm', 32)->default('ed25519');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocksmith_signing_keys');
    }

};
