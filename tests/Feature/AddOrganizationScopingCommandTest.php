<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use VanDmade\Blocksmith\Tests\TestCase;
use stdClass;

class AddOrganizationScopingCommandTest extends TestCase
{

    public function test_fails_when_no_organization_model_is_configured(): void
    {
        config(['blocksmith.organization_model' => null]);
        $this->artisan('blocksmith:add-organization-scoping')
            ->assertExitCode(1);
    }

    public function test_fails_when_the_organization_model_is_not_an_eloquent_model(): void
    {
        config(['blocksmith.organization_model' => stdClass::class]);
        $this->artisan('blocksmith:add-organization-scoping')
            ->assertExitCode(1);
    }

    public function test_adds_the_configured_column_to_every_configured_table(): void
    {
        config(['blocksmith.organization_model' => config('auth.providers.users.model')]);
        config(['blocksmith.tables' => [
            'blocksmith_documents' => 'organization_id',
            'blocksmith_document_types' => 'organization_id',
        ]]);
        $this->artisan('blocksmith:add-organization-scoping')
            ->assertExitCode(0);
        $this->assertTrue(Schema::hasColumn('blocksmith_documents', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('blocksmith_document_types', 'organization_id'));
    }

    public function test_safe_to_run_more_than_once(): void
    {
        config(['blocksmith.organization_model' => config('auth.providers.users.model')]);
        config(['blocksmith.tables' => [
            'blocksmith_documents' => 'organization_id',
        ]]);
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('blocksmith:add-organization-scoping')
                ->assertExitCode(0);
        }
        $this->assertTrue(Schema::hasColumn('blocksmith_documents', 'organization_id'));
    }

}
