<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseMigrationTest extends TestCase
{
    public function test_all_migrations_can_run_and_rollback(): void
    {
        $this->artisan('migrate')->assertExitCode(0);
        $this->artisan('migrate:reset')->assertExitCode(0);
    }

    public function test_can_seed_database(): void
    {
        $this->artisan('migrate')->assertExitCode(0);
        $this->artisan('db:seed')->assertExitCode(0);
    }
}
