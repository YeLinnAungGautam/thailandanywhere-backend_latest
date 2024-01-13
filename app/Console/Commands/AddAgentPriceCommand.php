<?php

namespace App\Console\Commands;

use App\Models\EntranceTicketVariation;
use App\Models\Room;
use App\Models\RoomPeriod;
use Illuminate\Console\Command;

class AddAgentPriceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:agent-price';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add agent price to rooms, room_periods and entrance_ticket_variations tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $add_amount = 100;

        Room::query()->chunk(100, function ($rooms) use ($add_amount) {
            foreach($rooms as $room) {
                if(!is_null($room->cost)) {
                    $room->update(['agent_price' => $room->cost + $add_amount]);
                }
            }
        });

        RoomPeriod::query()->chunk(100, function ($room_periods) use ($add_amount) {
            foreach($room_periods as $room_period) {
                if(!is_null($room_period->cost_price)) {
                    $room_period->update(['agent_price' => $room_period->cost_price + $add_amount]);
                }
            }
        });

        EntranceTicketVariation::query()->chunk(100, function ($variations) use ($add_amount) {
            foreach($variations as $variation) {
                if(!is_null($variation->cost_price)) {
                    $variation->update(['agent_price' => $variation->cost_price + $add_amount]);
                }
            }
        });

        $this->info('All agent prices are successfully migrated');
    }
}
