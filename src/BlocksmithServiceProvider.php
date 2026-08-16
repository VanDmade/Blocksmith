<?php

namespace VanDmade\Blocksmith;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use VanDmade\Blocksmith\Anchoring\AnchorProviderInterface;
use VanDmade\Blocksmith\Console\Commands\AddOrganizationScopingCommand;
use VanDmade\Blocksmith\Console\Commands\VerifyDocumentCommand;
use VanDmade\Blocksmith\Jobs\CheckAnchorBatchThresholdJob;
use VanDmade\Blocksmith\Jobs\CheckAnchorConfirmationJob;
use VanDmade\Blocksmith\Jobs\SubmitAnchorBatchJob;
use VanDmade\Blocksmith\Jobs\VerifyAnchorBatchJob;
use VanDmade\Blocksmith\Signing\SignerInterface;
use VanDmade\Blocksmith\Verification\DocumentIntegrityVerifier;
use VanDmade\Blocksmith\Verification\IntegrityVerifierInterface;

class BlocksmithServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blocksmith.php', 'blocksmith');
        $this->publishes([
            __DIR__.'/../config/blocksmith.php' => config_path('blocksmith.php'),
        ], 'blocksmith-config');
        $this->app->bind(SignerInterface::class, function ($app) {
            return $app->make(config('blocksmith.signer'));
        });
        $this->app->bind(AnchorProviderInterface::class, function ($app) {
            return $app->make(config('blocksmith.anchor_provider'));
        });
        $this->app->bind(IntegrityVerifierInterface::class, DocumentIntegrityVerifier::class);
        $this->app->register(EventServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'blocksmith');
        if ($this->app->runningInConsole()) {
            $this->commands([
                AddOrganizationScopingCommand::class,
                VerifyDocumentCommand::class,
            ]);
        }
        $this->app->booted(function () {
            $thresholdInterval = config('blocksmith.anchoring.threshold_check_interval');
            $confirmationInterval = config('blocksmith.anchoring.confirmation_check_interval');
            $schedule = $this->app->make(Schedule::class);
            $schedule->job(new SubmitAnchorBatchJob())->cron(config('blocksmith.anchoring.batch_schedule'));
            $schedule->job(new CheckAnchorBatchThresholdJob())->cron('*/'.$thresholdInterval.' * * * *');
            $schedule->job(new CheckAnchorConfirmationJob())->cron('*/'.$confirmationInterval.' * * * *');
            $schedule->job(new VerifyAnchorBatchJob())->daily();
        });
    }

}
