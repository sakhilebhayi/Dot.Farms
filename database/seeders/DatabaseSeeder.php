<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Deliberately seeds nothing.
     *
     * Dot.Farms has no reference data a fresh install needs: teams, farms,
     * crops and units are all created by the people using it (units are the
     * App\Enums\Unit enum, not a table). The demo "Vaalharts Farm" data lives
     * in DemoFarmSeeder, which is never called from here, so `php artisan
     * db:seed` and `migrate --seed` are safe to run anywhere, production
     * included. Load the demo explicitly, on a local database only:
     *
     *     php artisan db:seed --class=DemoFarmSeeder
     */
    public function run(): void
    {
        //
    }
}
