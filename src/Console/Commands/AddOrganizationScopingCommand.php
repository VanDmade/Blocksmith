<?php

namespace VanDmade\Blocksmith\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;
use VanDmade\Blocksmith\Events\BlocksmithLog;

class AddOrganizationScopingCommand extends Command
{

    protected $signature = 'blocksmith:add-organization-scoping';
    protected $description = 'Add organization scoping to the application.';

    public function handle(): int
    {
        try {
            return $this->addScoping();
        } catch (Throwable $error) {
            BlocksmithLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            $this->error('An unexpected error occurred: '.$error->getMessage());
            return self::FAILURE;
        }
    }

    private function addScoping(): int
    {
        $organizationModel = config('blocksmith.organization_model', null);
        // Makes sure the set the model in the config file
        if (is_null($organizationModel)) {
            $this->error('Organization model is not configured or does not exist. Please set blocksmith.organization_model in your configuration.');
            return self::FAILURE;
        }
        // Makes sure the user setup the org model and it's the correct type
        if (!is_subclass_of($organizationModel, Model::class)) {
            $this->error('Organization model class '.$organizationModel.' does not exist or is not an Eloquent model. Please check your configuration.');
            return self::FAILURE;
        }
        $organizationModelInstance = new $organizationModel;
        $tables = config('blocksmith.tables', []);
        foreach ($tables as $tableName => $column) {
            // Checks that the base migrations have actually been run before altering the table
            if (!Schema::hasTable($tableName)) {
                $this->error($tableName.' table does not exist. Run the base Blocksmith migrations first.');
                return self::FAILURE;
            }
            // Checks to see if the organization ID column already exists
            if (Schema::hasColumn($tableName, $column)) {
                $this->info($column.' already exists on '.$tableName.', skipping.');
                continue;
            }
            Schema::table($tableName, function(Blueprint $table) use ($organizationModelInstance, $column) {
                $table->bigInteger($column)->unsigned()->nullable();
                $table->foreign($column)
                    ->references($organizationModelInstance->getKeyName())
                    ->on($organizationModelInstance->getTable())
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            });
            $this->info($column.' added to '.$tableName.' table.');
        }
        return self::SUCCESS;
    }

}
