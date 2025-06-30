<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashImageResource;
use App\Models\CashImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CashImageController extends Controller
{
    use HttpResponses;
    use ImageManager;
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $query = CashImage::query();

        $data = $query->paginate($limit);

        return $this->success(CashImageResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Cash Image List');

    }

    public function create()
    {
        $validate = request()->validate([
            'image' => 'required',
            'date' => 'required|date_format:Y-m-d H:i:s',
            'sender' => 'required|string|max:255',
            'receiver' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'interact_bank' => 'nullable|string|max:255',
            'currency' => 'required|string|max:10',
            'relatable_type' => 'required',
            'relatable_id' => 'required'
        ]);

        if(!empty($validated['images'])){
            $fileData = $this->uploads($validate['image'], 'images/');

            $create = CashImage::create([
                'image' => $fileData['fileName'],
                'date' => $validate['date'],
                'sender' => $validate['sender'],
                'receiver' => $validate['receiver'],
                'amount' => $validate['amount'],
                'currency' => $validate['currency'],
                'interact_bank' => $validate['interact_bank'] ?? null,
                'relatable_type' => $validate['relatable_type'],
                'relatable_id' => $validate['relatable_id'],
                'image_path' => $fileData['filePath'], // Store image path
            ]);

            return $this->success(new CashImageResource($create), 'Successfully created');
        }
    }

    public function update(Request $request, string $id)
    {
        $find = CashImage::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'date' => $request->date ?? $find->date,
            'sender' => $request->sender ?? $find->sender,
            'receiver' => $request->receiver ?? $find->receiver,
            'amount' => $request->amount ?? $find->amount,
            'currency' => $request->currency ?? $find->currency,
            'interact_bank' => $request->interact_bank ?? $find->interact_bank,
            'relatable_type' => $request->relatable_type ?? $find->relatable_type,
            'relatable_id' => $request->relatable_id ?? $find->relatable_id,
        ];

        $find->update($data);

        return $this->success(new CashImageResource($find), 'Successfully updated');
    }

    public function destroy(string $id)
    {
        $find = CashImage::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }
        Storage::delete('images/' . $find->image);
        $find->delete();
        return $this->success(null, 'Successfully deleted');
    }
}
