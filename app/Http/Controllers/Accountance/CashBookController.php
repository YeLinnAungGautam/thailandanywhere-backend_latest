<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashBookResource;
use App\Models\CashBook;
use App\Models\CashBookImage;
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

        $query = CashBook::with(['cashStructure', 'chartOfAccounts', 'cashImages', 'cashBookImages']);

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
            'date' => 'nullable|date_format:Y-m-d H:i:s',
            'date_only' => 'nullable|date',
            'time_only' => 'nullable|date_format:H:i:s',
            'income_or_expense' => 'required|in:income,expense',
            'cash_structure_id' => 'required|exists:cash_structures,id',
            'interact_bank' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'accounts' => 'required|array|min:1',
            'accounts.*.id' => 'required|exists:chart_of_accounts,id',
            'accounts.*.allocated_amount' => 'required|numeric|min:0',
            'accounts.*.note' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*.image' => 'required',
            'images.*.date' => 'nullable|date_format:Y-m-d H:i:s',
            'images.*.sender' => 'required|string|max:255',
            'images.*.receiver' => 'required|string|max:255',
            'images.*.amount' => 'required|numeric|min:0',
            'images.*.currency' => 'required|string|max:10',
            'images.*.interact_bank' => 'nullable|string|max:255',
            'cash_book_images' => 'nullable|array',
            'cash_book_images.*.image' => 'required',
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
                    'amount' => $validated['amount'],
                ]);

                // Attach accounts with allocations
                foreach ($validated['accounts'] as $account) {
                    $cashBook->chartOfAccounts()->attach($account['id'], [
                        'allocated_amount' => $account['allocated_amount'],
                        'note' => $account['note'] ?? null
                    ]);
                }

                // Create cash images if provided (polymorphic relationship)
                if (!empty($validated['images'])) {
                    foreach ($validated['images'] as $imageData) {
                        $fileData = $this->uploads($imageData['image'], 'images/');

                        CashImage::create([
                            'image' => $fileData['fileName'],
                            'date' => $imageData['date'] ?? Carbon::now()->format('Y-m-d H:i:s'),
                            'sender' => $imageData['sender'],
                            'receiver' => $imageData['receiver'],
                            'amount' => $imageData['amount'],
                            'currency' => $imageData['currency'],
                            'interact_bank' => $imageData['interact_bank'] ?? null,
                            'relatable_type' => CashBook::class,
                            'relatable_id' => $cashBook->id,
                            'image_path' => $fileData['filePath'],
                        ]);
                    }
                }

                // Create cash book images if provided (one-to-many relationship)
                if (!empty($validated['cash_book_images'])) {
                    foreach ($validated['cash_book_images'] as $imageData) {
                        $fileData = $this->uploads($imageData['image'], 'images/');

                        CashBookImage::create([
                            'image' => $fileData['fileName'],
                            'cash_book_id' => $cashBook->id,
                        ]);
                    }
                }

                // Load relationships for response
                $cashBook->load(['cashStructure', 'chartOfAccounts', 'cashImages', 'cashBookImages']);

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
                'amount' => 'required|numeric|min:0',
                'accounts' => 'required|array|min:1',
                'accounts.*.id' => 'required|exists:chart_of_accounts,id',
                'accounts.*.allocated_amount' => 'required|numeric|min:0',
                'accounts.*.note' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*.id' => 'nullable|integer|exists:cash_images,id',
                'images.*.image' => 'nullable',
                'images.*.date' => 'nullable|date_format:Y-m-d H:i:s',
                'images.*.sender' => 'required|string|max:255',
                'images.*.receiver' => 'required|string|max:255',
                'images.*.amount' => 'required|numeric|min:0',
                'images.*.currency' => 'required|string|max:10',
                'images.*.interact_bank' => 'nullable|string|max:255',
                'cash_book_images' => 'nullable|array',
                'cash_book_images.*.id' => 'nullable|integer|exists:cash_book_images,id',
                'cash_book_images.*.image' => 'nullable',
            ]);

            return DB::transaction(function () use ($validated, $cashBook) {
                // Update cash book basic info
                $cashBook->update([
                    'date' => $validated['date'] ?? $cashBook->date,
                    'income_or_expense' => $validated['income_or_expense'],
                    'cash_structure_id' => $validated['cash_structure_id'],
                    'interact_bank' => $validated['interact_bank'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'amount' => $validated['amount'],
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

                // Create cash images if provided (polymorphic relationship)
                if (!empty($validated['images'])) {
                    foreach ($validated['images'] as $imageData) {
                        $fileData = $this->uploads($imageData['image'], 'images/');

                        CashImage::create([
                            'image' => $fileData['fileName'],
                            'date' => $imageData['date'] ?? Carbon::now()->format('Y-m-d H:i:s'),
                            'sender' => $imageData['sender'],
                            'receiver' => $imageData['receiver'],
                            'amount' => $imageData['amount'],
                            'currency' => $imageData['currency'],
                            'interact_bank' => $imageData['interact_bank'] ?? null,
                            'relatable_type' => CashBook::class,
                            'relatable_id' => $cashBook->id,
                            'image_path' => $fileData['filePath'],
                        ]);
                    }
                }

                // Create cash book images if provided (one-to-many relationship)
                if (!empty($validated['cash_book_images'])) {
                    foreach ($validated['cash_book_images'] as $imageData) {
                        $fileData = $this->uploads($imageData['image'], 'images/');

                        CashBookImage::create([
                            'image' => $fileData['fileName'],
                            'cash_book_id' => $cashBook->id,
                        ]);
                    }
                }

                // Load relationships for response
                $cashBook->refresh();
                $cashBook->load(['cashStructure', 'chartOfAccounts', 'cashImages', 'cashBookImages']);

                return $this->success(new CashBookResource($cashBook), 'Cash book entry updated successfully');
            });
        } catch (\Exception $e) {
            return $this->error(null, 'Failed to update cash book entry: ' . $e->getMessage());
        }
    }

    public function destoryCashBookImage(string $id){
        try {
            $cashBookImage = CashBookImage::findOrFail($id);
            $cashBookImage->delete();
            return $this->success(null, 'Cash book image deleted successfully');
        } catch (\Exception $e) {
            return $this->error(null, 'Failed to delete cash book image: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $cashBook = CashBook::findOrFail($id);

            return DB::transaction(function () use ($cashBook) {
                // Delete polymorphic cash images and their files
                foreach ($cashBook->cashImages as $image) {
                    if ($image->image) {
                        Storage::delete('images/' . $image->image);
                    }
                    $image->delete();
                }

                // Delete one-to-many cash book images and their files
                foreach ($cashBook->cashBookImages as $image) {
                    if ($image->image) {
                        Storage::delete('images/' . $image->image);
                    }
                    $image->delete();
                }

                // Delete the cash book (this will cascade delete pivot records)
                $cashBook->delete();

                return $this->success(null, 'Cash book entry deleted successfully');
            });
        } catch (\Exception $e) {
            return $this->error(null, 'Failed to delete cash book entry: ' . $e->getMessage());
        }
    }
}
