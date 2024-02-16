<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AirlineTicketExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\AirlineTicketResource;
use App\Models\AirlineTicket;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;

class AirlineTicketController extends Controller
{
    use HttpResponses;
    use ImageManager;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $airline_id = $request->query('airline_id');

        $query = AirlineTicket::query()
            ->when($search, function ($s_query) use ($search) {
                $s_query->where('description', 'LIKE', "%{$search}%");
            })
            ->when($airline_id, function ($a_query) use ($airline_id) {
                $a_query->where('airline_id', $airline_id);
            });

        if ($search) {
            $query->where('description', 'LIKE', "%{$search}%");
        }

        $data = $query->paginate($limit);

        return $this->success(AirlineTicketResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Airline Ticket List');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = [
            'airline_id' => $request->airline_id,
            'price' => $request->price,
            'description' => $request->description,
        ];

        $save = AirlineTicket::create($data);

        return $this->success(new AirlineTicketResource($save), 'Successfully created', 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(AirlineTicket $airline_ticket)
    {
        return $this->success(new AirlineTicketResource($airline_ticket), 'Airline Ticket Detail', 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AirlineTicket $airline_ticket)
    {
        $airline_ticket->update([
            'price' => $request->price ?? $airline_ticket->price,
            'airline_id' => $request->airline_id ?? $airline_ticket->airline_id,
            'description' => $request->description ?? $airline_ticket->description,
        ]);

        return $this->success(new AirlineTicketResource($airline_ticket), 'Successfully updated', 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AirlineTicket $airline_ticket)
    {
        $airline_ticket->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function exportCSV(Request $request)
    {
        $file_name = "airline_ticket_export_" . date('Y-m-d-H-i-s') . ".csv";

        \Excel::store(new AirlineTicketExport(), "public/export/" . $file_name);

        return $this->success(['download_link' => get_file_link('export', $file_name)], 'success export', 200);
    }
}
