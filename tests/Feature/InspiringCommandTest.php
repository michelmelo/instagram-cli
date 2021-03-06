<?php

namespace Tests\Feature;

use Tests\TestCase;

class InspiringCommandTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testInspiringCommand()
    {
        $this->artisan('instagram:viewstories')
            ->expectsOutput('Simplicity is the ultimate sophistication.')
            ->assertExitCode(0);
    }
}
