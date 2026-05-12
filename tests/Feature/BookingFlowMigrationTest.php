<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingFlowMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify the booking flow migration can be rolled back without removing worker booking support.
     */
    public function test_booking_flow_migration_can_roll_back_cleanly(): void
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $migrationPath = 'database/migrations/2026_05_07_092629_add_booking_flow_columns_to_bookings_table.php';

        $migrator->path(base_path('database/migrations'));
        $migrator->run([$migrationPath]);

        $this->assertTrue(Schema::hasColumns('bookings', [
            'start_time',
            'end_time',
            'issue_description',
            'rejection_reason',
        ]));

        $migrator->rollback([$migrationPath]);

        $this->assertFalse(Schema::hasColumn('bookings', 'start_time'));
        $this->assertFalse(Schema::hasColumn('bookings', 'end_time'));
        $this->assertFalse(Schema::hasColumn('bookings', 'issue_description'));
        $this->assertFalse(Schema::hasColumn('bookings', 'rejection_reason'));
        $this->assertTrue(Schema::hasColumn('bookings', 'worker_id'));
    }
}
