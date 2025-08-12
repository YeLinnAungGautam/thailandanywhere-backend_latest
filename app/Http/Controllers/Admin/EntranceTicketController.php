<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntranceTicketRequest;
use App\Http\Requests\UpdateEntranceTicketRequest;
use App\Http\Resources\EntranceTicketResource;
use App\Models\EntranceTicket;
use App\Models\EntranceTicketContract;
use App\Models\EntranceTicketImage;
use App\Models\EntranceTicketVariation;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EntranceTicketController extends Controller
{
    use ImageManager;
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = EntranceTicket::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })
            ->when($request->query('city_id'), function ($c_query) use ($request) {
                $c_query->whereIn('id', function ($q) use ($request) {
                    $q->select('entrance_ticket_id')->from('entrance_ticket_cities')->where('city_id', $request->query('city_id'));
                });
            })
            ->when($request->activities, function ($query) use ($request) {
                $query->whereIn('id', function ($q) use ($request) {
                    $q->select('entrance_ticket_id')
                        ->from('activity_entrance_ticket')
                        ->whereIn('attraction_activity_id', explode(',', $request->activities));
                });
            })
            ->when($request->show_only, function ($query) {
                $query->where(function ($query) {
                    $query->where('meta_data', 'LIKE', '%"is_show":1%')
                        ->orWhere('meta_data', 'LIKE', '%"is_show":"1"%');
                });
            })
            ->orderBy('created_at', 'desc');

        $data = $query->paginate($limit);

        return $this->success(EntranceTicketResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Entrance Ticket List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEntranceTicketRequest $request)
    {
        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'full_description' => $request->full_description,
            'full_description_en' => $request->full_description_en,
            'provider' => $request->provider,
            'place' => $request->place,
            'legal_name' => $request->legal_name,
            'bank_name' => $request->bank_name,
            'payment_method' => $request->payment_method,
            'bank_account_number' => $request->bank_account_number,
            'account_name' => $request->account_name,
            'cancellation_policy_id' => $request->cancellation_policy_id,
            'youtube_link' => json_encode($request->youtube_link),
            'location_map_title' => $request->location_map_title,
            'location_map' => $request->location_map,
            'vat_inclusion' => $request->vat_inclusion,
            'meta_data' => $request->meta_data ? json_encode($request->meta_data) : null,
            'email' => $request->email ? json_encode($request->email) : null,
            'contract_name' => $request->contract_name,
            'vat_id' => $request->vat_id,
            'vat_name' => $request->vat_name,
            'vat_address' => $request->vat_address,
        ];

        if ($file = $request->file('cover_image')) {
            $fileData = $this->uploads($file, 'images/');
            $data['cover_image'] = $fileData['fileName'];
        }

        $save = EntranceTicket::create($data);

        if ($request->activities) {
            $save->activities()->attach($request->activities);
        }
        if ($request->tag_ids) {
            $save->tags()->sync($request->tag_ids);
        }

        if ($request->city_ids) {
            $save->cities()->sync($request->city_ids);
        }

        if ($request->category_ids) {
            $save->categories()->sync($request->category_ids);
        }

        // if ($request->variations) {
        //     $save->variations()->sync($request->variations);
        // }

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                EntranceTicketImage::create(['entrance_ticket_id' => $save->id, 'image' => $fileData['fileName']]);
            };
        }

        $contractArr = [];

        if ($request->file('contracts')) {
            foreach ($request->file('contracts') as $file) {
                $fileData = $this->uploads($file, 'contracts/');
                $contractArr[] = [
                    'entrance_ticket_id' => $save->id,
                    'file' => $fileData['fileName']
                ];
            }

            EntranceTicketContract::insert($contractArr);
        }
        // foreach ($request->variations as $variation) {
        //     EntranceTicketVariation::create(['entrance_ticket_id' => $save->id, 'name' => $variation['name'], 'age_group' => $variation['age_group'], 'price' => $variation['price']]);
        // };

        return $this->success(new EntranceTicketResource($save), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = EntranceTicket::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new EntranceTicketResource($find), 'Entrance Ticket Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEntranceTicketRequest $request, string $id)
    {
        $find = EntranceTicket::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'name' => $request->name ?? $find->name,
            'description' => $request->description ?? $find->description,
            'full_description' => $request->full_description ?? $find->full_description,
            'full_description_en' => $request->full_description_en ?? $find->full_description_en,
            'provider' => $request->provider ?? $find->provider,
            'place' => $request->place ?? $find->place,

            'legal_name' => $request->legal_name ?? $find->legal_name,
            'bank_name' => $request->bank_name ?? $find->bank_name,
            'payment_method' => $request->payment_method ?? $find->payment_method,
            'bank_account_number' => $request->bank_account_number ?? $find->bank_account_number,
            'account_name' => $request->account_name ?? $find->account_name,
            'cancellation_policy_id' => $request->cancellation_policy_id ?? $find->cancellation_policy_id,
            'youtube_link' => $request->youtube_link ? json_encode($request->youtube_link) : $find->youtube_link,
            'location_map_title' => $request->location_map_title ?? $find->location_map_title,
            'location_map' => $request->location_map ?? $find->location_map,
            'vat_inclusion' => $request->vat_inclusion ?? $find->vat_inclusion,
            'meta_data' => $request->meta_data ? json_encode($request->meta_data) : $find->meta_data,
            'email' => $request->email ? json_encode($request->email) : $find->email,
            'contract_name' => $request->contract_name ?? $find->contract_name,
            'vat_id' => $request->vat_id ?? $find->vat_id,
            'vat_name' => $request->vat_name ?? $find->vat_name,
            'vat_address' => $request->vat_address ?? $find->vat_address,
        ];


        if ($file = $request->file('cover_image')) {
            $fileData = $this->uploads($file, 'images/');
            $data['cover_image'] = $fileData['fileName'];

            if ($find->cover_image) {
                Storage::delete('images/' . $find->cover_image);
            }
        }

        $find->update($data);

        if ($request->activities) {
            $find->activities()->sync($request->activities);
        }

        if ($request->tag_ids) {
            $find->tags()->sync($request->tag_ids);
        }

        if ($request->city_ids) {
            $find->cities()->sync($request->city_ids);
        }

        if ($request->category_ids) {
            $find->categories()->sync($request->category_ids);
        }

        // if ($request->variations) {
        //     $find->variations()->sync($request->variations);
        // }

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                // Delete existing images
                // if (count($find->images) > 0) {
                //     foreach ($find->images as $exImage) {
                //         // Delete the file from storage
                //         Storage::delete('images/' . $exImage->image);
                //         // Delete the image from the database
                //         $exImage->delete();
                //     }
                // }

                $fileData = $this->uploads($image, 'images/');
                EntranceTicketImage::create(['entrance_ticket_id' => $find->id, 'image' => $fileData['fileName']]);
            };
        }

        $contractArr = [];

        if ($request->file('contracts')) {
            foreach ($request->file('contracts') as $file) {
                $fileData = $this->uploads($file, 'contracts/');
                $contractArr[] = [
                    'entrance_ticket_id' => $find->id,
                    'file' => $fileData['fileName']
                ];
            }

            EntranceTicketContract::insert($contractArr);
        }
        // if ($request->variations) {
        //     foreach ($request->variations as $variation) {
        //         if (count($find->variations) > 0) {
        //             foreach ($find->variations as $v) {
        //                 $v->delete();
        //             }
        //         }
        //         EntranceTicketVariation::create(['entrance_ticket_id' => $find->id, 'name' => $variation['name'], 'age_group' => $variation['age_group'], 'price' => $variation['price']]);
        //     };
        // }


        return $this->success(new EntranceTicketResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = EntranceTicket::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function forceDelete(string $id)
    {
        $find = EntranceTicket::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->activities()->detach();
        $find->tags()->detach();
        $find->categories()->detach();
        $find->cities()->detach();

        Storage::delete('images/' . $find->cover_image);

        foreach ($find->images as $image) {
            // Delete the file from storage
            Storage::delete('images/' . $image->image);
            // Delete the image from the database
            $image->delete();
        }

        foreach ($find->variations as $variation) {
            $variation->forceDelete();
        }

        $find->forceDelete();

        return $this->success(null, 'Product is completely deleted');
    }

    public function restore(string $id)
    {
        $find = EntranceTicket::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->restore();

        return $this->success(null, 'Product is successfully restored');
    }

    public function deleteContract(EntranceTicket $entrance_ticket, EntranceTicketContract $entrance_ticket_contract)
    {
        if ($entrance_ticket->id !== $entrance_ticket_contract->entrance_ticket_id) {
            return $this->error(null, 'This contract is not belongs to this attraction', 403);
        }

        Storage::delete('contracts/' . $entrance_ticket_contract->file);

        $entrance_ticket_contract->delete();

        return $this->success(null, 'Entrance ticket contract is successfully deleted');
    }

    public function deleteImage(string $id)
    {
        $entrance_ticket_image = EntranceTicketImage::find($id);

        if (!$entrance_ticket_image) {
            return $this->success(null, 'Entrance ticket image cannot be found');
        }

        Storage::delete('images/' . $entrance_ticket_image->image);

        $entrance_ticket_image->delete();

        return $this->success(null, 'Entrance ticket image is successfully deleted');
    }
}
