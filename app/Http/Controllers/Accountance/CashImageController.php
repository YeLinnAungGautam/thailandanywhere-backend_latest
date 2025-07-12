<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashImageDetailResource;
use App\Http\Resources\Accountance\CashImageResource;
use App\Models\CashImage;
use App\Services\CashImageService;
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
        $cashImageService = new CashImageService();
        $result = $cashImageService->getAll($request);

        if ($result['success']) {
            return response()->json([
                'status' => 'Request was successful.',
                'message' => $result['message'],
                'result' => $result['data']
            ]);
        } else {
            return response()->json([
                'status' => 'Error has occurred.',
                'message' => $result['message'],
                'result' => null
            ], $result['error_type'] === 'validation' ? 422 : 500);
        }
    }

    public function store(Request $request)
    {
        $validated = request()->validate([
            'image' => 'required',
            'date' => 'required|date_format:Y-m-d H:i:s',
            'sender' => 'required|string|max:255',
            'reciever' => 'required|string|max:255', // Fixed spelling
            'amount' => 'required|numeric|min:0',
            'interact_bank' => 'nullable|string|max:255',
            'currency' => 'required|string|max:10',
            'relatable_type' => 'required',
            'relatable_id' => 'required'
        ]);

        // Since image is required, no need to check if empty
        $fileData = $this->uploads($validated['image'], 'images/');

        $create = CashImage::create([
            'image' => $fileData['fileName'],
            'date' => $validated['date'],
            'sender' => $validated['sender'],
            'receiver' => $validated['reciever'], // Fixed spelling
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'interact_bank' => $validated['interact_bank'] ?? null,
            'relatable_type' => $validated['relatable_type'],
            'relatable_id' => $validated['relatable_id'],
            'image_path' => $fileData['filePath'],
        ]);

        return $this->success(new CashImageResource($create), 'Successfully created');
    }

    public function show(string $id)
    {
        $find = CashImage::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }
        return $this->success(new CashImageDetailResource($find), 'Successfully retrieved');
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
            'receiver' => $request->reciever ?? $find->reciever,
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
