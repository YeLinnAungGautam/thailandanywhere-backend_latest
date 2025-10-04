<?php

namespace App\Console\Commands;

use App\Models\PrivateVanTour;
use Illuminate\Console\Command;

class UpdatePrivateVanTourCarCosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'van-tour:update-car-costs
                            {city_id : The city ID to filter van tours}
                            {car_id : The car ID to update}
                            {cost : The new cost price}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update cost price for specific car in van tours of a specific city';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cityId = $this->argument('city_id');
        $carId = $this->argument('car_id');
        $cost = $this->argument('cost');

        // Confirm before updating
        if (!$this->confirm("Do you want to update cost to {$cost} for car_id {$carId} in city_id {$cityId}?")) {
            $this->info('Update cancelled.');
            return Command::SUCCESS;
        }

        // Get all private van tours that include the specified city
        $vanTours = PrivateVanTour::whereHas('cities', function ($query) use ($cityId) {
            $query->where('city_id', $cityId);
        })->get();

        $this->info("Found {$vanTours->count()} van tours with city_id {$cityId}");

        $updatedCount = 0;

        foreach ($vanTours as $vanTour) {
            // Check if this van tour has this car
            if ($vanTour->cars()->where('car_id', $carId)->exists()) {
                // Update the cost in the pivot table
                $vanTour->cars()->updateExistingPivot($carId, [
                    'cost' => $cost
                ]);

                $updatedCount++;
                $this->line("✓ Updated van tour ID {$vanTour->id} - car ID {$carId} to cost {$cost}");
            }
        }

        if ($updatedCount > 0) {
            $this->info("Successfully updated {$updatedCount} records!");
        } else {
            $this->warn("No records found to update. Car ID {$carId} not found in any van tours for city ID {$cityId}");
        }

        return Command::SUCCESS;
    }
}
