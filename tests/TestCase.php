<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Wrap both the default connection and the landlord connection (flow tables)
     * in database transactions so data is rolled back cleanly between tests.
     */
    protected $connectionsToTransact = [null, 'landlord'];
}
