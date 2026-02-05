<?php

namespace Burotoast\EventbriteImporter;

use Statamic\Providers\AddonServiceProvider;
use Burotoast\EventbriteImporter\Commands\ImportEvents;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        // Add boot logic if needed
    }

    public function register()
    {
        parent::register();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportEvents::class,
            ]);
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/eventbrite.php', 'eventbrite');
    }
}