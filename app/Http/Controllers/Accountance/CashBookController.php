<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashBookResource;
use App\Models\CashBook;
use App\Models\CashImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class CashBookController extends Controller
{
    use HttpResponses;
    use ImageManager;

    public function index(Request $request)
    {
        $type = $request->query('type');
        $limit = $request->query('limit', 10);
        $cash_structure_id = $request->query('cash_structure_id');
        $start_date = $request->query('start_date');
        $end_date = $request->query('end_date');

        $query = CashBook::with(['cashStructure', 'chartOfAccounts', 'cashImages']);

        // Filter by type
        if ($type && in_array($type, ['income', 'expense'])) {
            $query->where('income_or_expense', $type);
        }

        // Filter by cash structure
        if ($cash_structure_id) {
            $query->where('cash_structure_id', $cash_structure_id);
        }

        // Filter by date range (supports datetime)
        if ($start_date) {
            $startDateTime = strlen($start_date) === 10 ? $start_date . ' 00:00:00' : $start_date;
            $query->where('date', '>=', $startDateTime);
        }
        if ($end_date) {
            $endDateTime = strlen($end_date) === 10 ? $end_date . ' 23:59:59' : $end_date;
            $query->where('date', '<=', $endDateTime);
        }

        $data = $query->orderBy('date', 'desc')->paginate($limit);

        return $this->success(CashBookResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                    'current_page' => $data->currentPage(),
                    'total_records' => $data->total(),
                    'per_page' => $data->perPage(),
                ],
            ])
            ->response()
            ->getData(), 'Cash Book List');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => 'nullable|string|max:100|unique:cash_books,reference_number',
            'date' => 'nullable|date_format:Y-m-d H:i:s', // Changed to datetime and nullable
            'date_only' => 'nullable|date', // Alternative date input
            'time_only' => 'nullable|date_format:H:i:s', // Alternative time input
            'income_or_expense' => 'required|in:income,expense',
            'cash_structure_id' => 'required|exists:cash_structures,id',
            'interact_bank' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'accounts' => 'required|array|min:1',
            'accounts.*.id' => 'required|exists:chart_of_accounts,id', // Fixed table name
            'accounts.*.allocated_amount' => 'required|numeric|min:0',
            'accounts.*.note' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*.image' => 'required', // Base64 image or file path
            'images.*.date' => 'nullable|date_format:Y-m-d H:i:s', // Changed to datetime
            'images.*.sender' => 'required|string|max:255',
            'images.*.receiver' => 'required|string|max:255',
            'images.*.amount' => 'required|numeric|min:0',
            'images.*.currency' => 'required|string|max:10',
            'images.*.interact_bank' => 'nullable|string|max:255',
            'images.*.relatable_type' => 'nullable', // For polymorphic
            'images.*.relatable_id' => 'nullable|integer' // For polymorphic
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                // Generate reference number if not provided
                if (empty($validated['reference_number'])) {
                    $validated['reference_number'] = CashBook::generateReferenceNumber();
                }

                // Create cash book entry
                $cashBook = CashBook::create([
                    'reference_number' => $validated['reference_number'],
                    'date' => $validated['date'] ?? Carbon::now()->format('Y-m-d H:i:s'),
                    'income_or_expense' => $validated['income_or_expense'],
                    'cash_structure_id' => $validated['cash_structure_id'],
                    'interact_bank' => $validated['interact_bank'] ?? null,
                    'description' => $validated['description'] ?? null,
                ]);

                // Attach accounts with allocations
                foreach ($validated['accounts'] as $account) {
                    $cashBook->chartOfAccounts()->attach($account['id'], [
                        'allocated_amount' => $account['allocated_amount'],
                        'note' => $account['note'] ?? null
                    ]);
                }

                // Create cash images if provided
                if (!empty($validated['images'])) {
                    foreach ($validated['images'] as $imageData) {
                        // Handle image upload
                        $fileData = $this->uploads($imageData['image'], 'images/');

                        $relatableId = $imageData['relatable_id'] ?? $cashBook->id;

                        CashImage::create([
                            'image' => $fileData['fileName'],
                            'date' => $imageData['date'],
                            'sender' => $imageData['sender'],
                            'receiver' => $imageData['receiver'],
                            'amount' => $imageData['amount'],
                            'currency' => $imageData['currency'],
                            'interact_bank' => $imageData['interact_bank'] ?? null,
                            'relatable_type' => 'App\Models\CashBook',
                            'relatable_id' => $relatableId,
                            'image_path' => $fileData['filePath'], // Store image path
                        ]);
                    }
                }

                // Load relationships for response
                $cashBook->load(['cashStructure', 'chartOfAccounts', 'cashImages']);

                return $this->success(new CashBookResource($cashBook), 'Cash book entry created successfully');
            });
        } catch (\Exception $e) {
            return $this->error(null, 'Failed to create cash book entry: ' . $e->getMessage());
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $cashBook = CashBook::findOrFail($id);

            $validated = $request->validate([
                'date' => 'nullable|date_format:Y-m-d H:i:s',
                'date_only' => 'nullable|date',
                'time_only' => 'nullable|date_format:H:i:s',
                'income_or_expense' => 'required|in:income,expense',
                'cash_structure_id' => 'required|exists:cash_structures,id',
                'interact_bank' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'accounts' => 'required|array|min:1',
                'accounts.*.id' => 'required|exists:chart_of_accounts,id',
                'accounts.*.allocated_amount' => 'required|numeric|min:0',
                'accounts.*.note' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*.id' => 'nullable|integer|exists:cash_images,id', // For updating existing images
                'images.*.image' => 'nullable|file|image|max:2048', // File upload for new/replacement images
                'images.*.date' => 'nullable|date_format:Y-m-d H:i:s',
                'images.*.sender' => 'required|string|max:255',
                'images.*.receiver' => 'required|string|max:255',
                'images.*.amount' => 'required|numeric|min:0',
                'images.*.currency' => 'required|string|max:10',
                'images.*.interact_bank' => 'nullable|string|max:255',
            ]);

            return DB::transaction(function () use ($validated, $cashBook) {

                // Update cash book basic info
                $cashBook->update([
                    'date' => $validated['date'] ?? Carbon::now()->format('Y-m-d H:i:s'),
                    'income_or_expense' => $validated['income_or_expense'],
                    'cash_structure_id' => $validated['cash_structure_id'],
                    'interact_bank' => $validated['interact_bank'] ?? null,
                    'description' => $validated['description'] ?? null,
                ]);

                // Sync accounts
                $accountsData = [];
                foreach ($validated['accounts'] as $account) {
                    $accountsData[$account['id']] = [
                        'allocated_amount' => $account['allocated_amount'],
                        'note' => $account['note'] ?? null
                    ];
                }
                $cashBook->chartOfAccounts()->sync($accountsData);

                // Handle images if provided
                if (!empty($validated['images'])) {
                    $existingImageIds = [];

                    foreach ($validated['images'] as $imageData) {
                        // Case 1: Existing image with ID (Update existing)
                        if (isset($imageData['id']) && !empty($imageData['id'])) {
                            $existingImageIds[] = $imageData['id'];

                            $existingImage = CashImage::find($imageData['id']);
                            if ($existingImage) {
                                $updateData = [
                                    'date' => $imageData['date'],
                                    'sender' => $imageData['sender'],
                                    'receiver' => $imageData['receiver'],
                                    'amount' => $imageData['amount'],
                                    'currency' => $imageData['currency'],
                                    'interact_bank' => $imageData['interact_bank'] ?? null,
                                ];

                                // CRITICAL FIX: Only update image file if new one is provided
                                if (isset($imageData['image']) && $imageData['image'] instanceof \Illuminate\Http\UploadedFile) {
                                    $fileData = $this->uploads($imageData['image'], 'images/');
                                    $updateData['image'] = $fileData['fileName'];
                                    $updateData['image_path'] = $fileData['filePath'];
                                }
                                // If no new image file, keep the existing image unchanged

                                $existingImage->update($updateData);
                            }
                        }
                        // Case 2: New image without ID (Create new)
                        else {
                            // Only create if image file is provided
                            if (isset($imageData['image']) && $imageData['image'] instanceof \Illuminate\Http\UploadedFile) {
                                $fileData = $this->uploads($imageData['image'], 'images/');

                                $newImage = CashImage::create([
                                    'image' => $fileData['fileName'],
                                    'date' => $imageData['date'],
                                    'sender' => $imageData['sender'],
                                    'receiver' => $imageData['receiver'],
                                    'amount' => $imageData['amount'],
                                    'currency' => $imageData['currency'],
                                    'interact_bank' => $imageData['interact_bank'] ?? null,
                                    'relatable_type' => 'App\Models\CashBook',
                                    'relatable_id' => $cashBook->id,
                                    'image_path' => $fileData['filePath'],
                                ]);

                                $existingImageIds[] = $newImage->id; // Add new image ID to keep list
                            }
                        }
                    }

                    // Delete images that are no longer in the request
                    CashImage::where('relatable_type', 'App\Models\CashBook')
                        ->where('relatable_id', $cashBook->id)
                        ->whereNotIn('id', $existingImageIds)
                        ->delete();
                } else {
                    // If no images in request, delete all existing images
                    CashImage::where('relatable_type', 'App\Models\CashBook')
                        ->where('relatable_id', $cashBook->id)
                        ->delete();
                }

                // Load relationships for response
                $cashBook->refresh();
                $cashBook->load(['cashStructure', 'chartOfAccounts', 'cashImages']);

                return $this->success(new CashBookResource($cashBook), 'Cash book entry updated successfully');
            });
        } catch (\Exception $e) {
            // \Log::error('Cash book update error: ' . $e->getMessage());
            return $this->error(null, 'Failed to update cash book entry: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $cashBook = CashBook::findOrFail($id);

            return DB::transaction(function () use ($cashBook) {
                // Delete associated images and their files
                foreach ($cashBook->cashImages as $image) {
                    if ($image->image) {
                        Storage::delete('images/' . $image->image);
                    }

                    $image->delete();
                }

                // Delete the cash book (this will cascade delete images and pivot records)
                $cashBook->delete();

                return $this->success(null, 'Cash book entry deleted successfully');
            });
        } catch (\Exception $e) {
            return $this->error(null, 'Failed to delete cash book entry: ' . $e->getMessage());
        }
    }
}
