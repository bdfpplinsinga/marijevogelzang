<?php

namespace Burotoast\EventbriteImporter\Tests;

use Burotoast\EventbriteImporter\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
