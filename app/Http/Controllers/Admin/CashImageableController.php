<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCashImageableRequest;
use App\Http\Resources\Accountance\CashImageResource;
use App\Models\CashImage;
use App\Traits\HasCashImages;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Support\Facades\Storage;

class CashImageableController extends Controller
{
    use HasCashImages, HttpResponses, ImageManager;

    public function index($modelType, $modelId)
    {
        $model = $this->resolveModel($modelType)::findOrFail($modelId);

        // Get all related cash images with pivot data
        $images = $model->cashImages()->get()->map(function ($image) {
            return [
                'id' => $image->id,
                'path' => $image->path,
                'type' => $image->pivot->type,
                'deposit' => $image->pivot->deposit,
                'notes' => $image->pivot->notes,
                'created_at' => $image->pivot->created_at,
                'updated_at' => $image->pivot->updated_at,
            ];
        });

        return response()->json($images);
    }

    public function store(StoreCashImageableRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('cash_image')) {
            $fileData = $this->uploads($request->file('cash_image'), 'images/');

            $cashImage = CashImage::create([
                'image' => $fileData['fileName'],
                'date' => $validated['date'],
                'sender' => $validated['sender'],
                'receiver' => $validated['receiver'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'interact_bank' => $validated['interact_bank'] ?? null,
                'image_path' => $fileData['filePath'],
            ]);
        } else {
            $cashImage = CashImage::findOrFail($validated['cash_image_id']);
        }

        foreach ($validated['targets'] as $target) {
            $model = $this->resolveModel($target['model_type'])::findOrFail($target['model_id']);

            $model->addCashImage($cashImage->id, [
                'type' => $validated['type'] ?? null,
                'deposit' => $validated['deposit'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Cash image attached to multiple targets successfully',
            'cash_image_id' => $cashImage->id,
        ]);
    }

    public function update(StoreCashImageableRequest $request, $cashImageId)
    {
        $validated = $request->validated();

        $cashImage = CashImage::findOrFail($cashImageId);

        // Update cash image data if provided
        if ($request->hasFile('cash_image')) {
            $fileData = $this->uploads($request->file('cash_image'), 'images/');

            // Delete old image file if exists
            if ($cashImage->image_path && file_exists(public_path($cashImage->image_path))) {
                unlink(public_path($cashImage->image_path));
            }

            $cashImage->update([
                'image' => $fileData['fileName'],
                'date' => $validated['date'],
                'sender' => $validated['sender'],
                'receiver' => $validated['receiver'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'interact_bank' => $validated['interact_bank'] ?? null,
                'image_path' => $fileData['filePath'],
            ]);
        }

        // Update pivot data for all targets
        foreach ($validated['targets'] as $target) {
            $model = $this->resolveModel($target['model_type'])::findOrFail($target['model_id']);

            $model->updateCashImage($cashImage->id, [
                'type' => $validated['type'] ?? null,
                'deposit' => $validated['deposit'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        return $this->success(new CashImageResource($cashImage), 'Successfully updated');
    }

    public function destroy($modelType, $modelId, $cashImageId)
    {
        $model = $this->resolveModel($modelType)::findOrFail($modelId);
        $model->removeCashImage($cashImageId);

        $image = CashImage::find($cashImageId);
        if ($image) {
            Storage::delete('images/' . $image->image);
            $image->delete();
        }

        return $this->success(null, 'Cash image detached and deleted successfully');
    }

    private function resolveModel($modelType)
    {
        return match ($modelType) {
            'booking' => \App\Models\Booking::class,
            'cash_book' => \App\Models\CashBook::class,
            'booking_item_group' => \App\Models\BookingItemGroup::class,
            default => abort(404, 'Invalid model type'),
        };
    }
}
