<?php

namespace App\Http\Controllers\Accountance;

use App\Exports\CashImageExport;
use App\Exports\CashInvoiceExport;
use App\Exports\CashParchaseExport;
use App\Exports\CashParchaseTaxExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashImageDetailResource;
use App\Http\Resources\Accountance\CashImageResource;
use App\Jobs\GenerateCashImagePdfJob;
use App\Jobs\GenerateCashParchasePdfJob;
use App\Models\CashImage;
use App\Services\CashImageService;
use App\Services\PrintPDFService;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CashImageController extends Controller
{
    use HttpResponses;
    use ImageManager;

    protected $cashImageService;
    protected $csvExportService;
    protected $printPDFService;

    public function __construct(
        CashImageService $cashImageService,
        PrintPDFService $printPDFService
    ) {
        $this->cashImageService = $cashImageService;
        $this->printPDFService = $printPDFService;
    }
    public function index(Request $request)
    {

        $result = $this->cashImageService->getAll($request);

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

    public function summary(Request $request)
    {

        $result = $this->cashImageService->getAllSummary($request);

        if ($result['status'] == 1) {
            return response()->json([
                'status' => 1,
                'message' => $result['message'],
                'result' => $result['result']
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => $result['message'],
                'result' => null
            ], $result['error_type'] === 'validation' ? 422 : 500);
        }
    }

    public function remindTaxReceipt(Request $request)
    {
        try {
            $result = $this->cashImageService->getAllGroupedByProductForExport($request);

            if ($result['status'] == 1) {
                return response()->json([
                    'status' => 1,
                    'message' => $result['message'],
                    'result' => $result['result']
                ]);
            }

            // Error case - use null coalescing operator for safe access
            return response()->json([
                'status' => 0,
                'message' => $result['message'] ?? 'An error occurred',
                'result' => null
            ], ($result['error_type'] ?? 'system') === 'validation' ? 422 : 500);

        } catch (\Exception $e) {
            \Log::error('remindTaxReceipt error: ' . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'An unexpected error occurred',
                'result' => null
            ], 500);
        }
    }

    public function duplicateCashImage(Request $request)
    {
        $result = $this->cashImageService->duplicateCashImage($request);

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

    public function mergeCashImages(Request $request)
    {
        $validated = $request->validate([
            'keep_id' => 'required|exists:cash_images,id',
            'delete_ids' => 'required|array',
            'delete_ids.*' => 'required|exists:cash_images,id|different:keep_id'
        ]);

        try {
            DB::beginTransaction();

            $keepCashImage = CashImage::with(['cashBookings', 'cashBooks', 'cashBookingItemGroups'])
                ->findOrFail($validated['keep_id']);

            // FIRST: Transfer keep_id's own polymorphic relationship to cash_imageables
            if ($keepCashImage->relatable_type && $keepCashImage->relatable_id) {
                $relatableClass = $keepCashImage->relatable_type;

                if ($relatableClass === 'App\Models\Booking') {
                    if (!$keepCashImage->cashBookings()
                        ->wherePivot('imageable_id', $keepCashImage->relatable_id)
                        ->exists()) {
                        $keepCashImage->cashBookings()->attach($keepCashImage->relatable_id, [
                            'type' => null,
                            'deposit' => null,
                            'notes' => 'Original relationship from cash image #' . $keepCashImage->id,
                        ]);
                    }
                } elseif ($relatableClass === 'App\Models\CashBook') {
                    if (!$keepCashImage->cashBooks()
                        ->wherePivot('imageable_id', $keepCashImage->relatable_id)
                        ->exists()) {
                        $keepCashImage->cashBooks()->attach($keepCashImage->relatable_id, [
                            'type' => null,
                            'deposit' => null,
                            'notes' => 'Original relationship from cash image #' . $keepCashImage->id,
                        ]);
                    }
                } elseif ($relatableClass === 'App\Models\BookingItemGroup') {
                    if (!$keepCashImage->cashBookingItemGroups()
                        ->wherePivot('imageable_id', $keepCashImage->relatable_id)
                        ->exists()) {
                        $keepCashImage->cashBookingItemGroups()->attach($keepCashImage->relatable_id, [
                            'type' => null,
                            'deposit' => null,
                            'notes' => 'Original relationship from cash image #' . $keepCashImage->id,
                        ]);
                    }
                }
            }

            // SECOND: Process all delete_ids and transfer their relationships
            foreach ($validated['delete_ids'] as $deleteId) {
                $deleteCashImage = CashImage::with([
                    'cashBookings',
                    'cashBooks',
                    'cashBookingItemGroups'
                ])->findOrFail($deleteId);

                // Transfer the polymorphic relationship (relatable_type/relatable_id) to cash_imageables
                if ($deleteCashImage->relatable_type && $deleteCashImage->relatable_id) {
                    $relatableClass = $deleteCashImage->relatable_type;

                    if ($relatableClass === 'App\Models\Booking') {
                        if (!$keepCashImage->cashBookings()
                            ->wherePivot('imageable_id', $deleteCashImage->relatable_id)
                            ->exists()) {
                            $keepCashImage->cashBookings()->attach($deleteCashImage->relatable_id, [
                                'type' => null,
                                'deposit' => null,
                                'notes' => 'Merged from cash image #' . $deleteId,
                            ]);
                        }
                    } elseif ($relatableClass === 'App\Models\CashBook') {
                        if (!$keepCashImage->cashBooks()
                            ->wherePivot('imageable_id', $deleteCashImage->relatable_id)
                            ->exists()) {
                            $keepCashImage->cashBooks()->attach($deleteCashImage->relatable_id, [
                                'type' => null,
                                'deposit' => null,
                                'notes' => 'Merged from cash image #' . $deleteId,
                            ]);
                        }
                    } elseif ($relatableClass === 'App\Models\BookingItemGroup') {
                        if (!$keepCashImage->cashBookingItemGroups()
                            ->wherePivot('imageable_id', $deleteCashImage->relatable_id)
                            ->exists()) {
                            $keepCashImage->cashBookingItemGroups()->attach($deleteCashImage->relatable_id, [
                                'type' => null,
                                'deposit' => null,
                                'notes' => 'Merged from cash image #' . $deleteId,
                            ]);
                        }
                    }
                }

                // Transfer all existing many-to-many relationships from deleted to kept

                // Transfer bookings from cash_imageables
                if ($deleteCashImage->cashBookings && $deleteCashImage->cashBookings->count() > 0) {
                    foreach ($deleteCashImage->cashBookings as $booking) {
                        if (!$keepCashImage->cashBookings()
                            ->wherePivot('imageable_id', $booking->id)
                            ->exists()) {
                            $keepCashImage->cashBookings()->attach($booking->id, [
                                'type' => $booking->pivot->type ?? null,
                                'deposit' => $booking->pivot->deposit ?? null,
                                'notes' => $booking->pivot->notes ?? null,
                            ]);
                        }
                    }
                }

                // Transfer cash_books from cash_imageables
                if ($deleteCashImage->cashBooks && $deleteCashImage->cashBooks->count() > 0) {
                    foreach ($deleteCashImage->cashBooks as $cashBook) {
                        if (!$keepCashImage->cashBooks()
                            ->wherePivot('imageable_id', $cashBook->id)
                            ->exists()) {
                            $keepCashImage->cashBooks()->attach($cashBook->id, [
                                'type' => $cashBook->pivot->type ?? null,
                                'deposit' => $cashBook->pivot->deposit ?? null,
                                'notes' => $cashBook->pivot->notes ?? null,
                            ]);
                        }
                    }
                }

                // Transfer booking_item_groups from cash_imageables
                if ($deleteCashImage->cashBookingItemGroups && $deleteCashImage->cashBookingItemGroups->count() > 0) {
                    foreach ($deleteCashImage->cashBookingItemGroups as $itemGroup) {
                        if (!$keepCashImage->cashBookingItemGroups()
                            ->wherePivot('imageable_id', $itemGroup->id)
                            ->exists()) {
                            $keepCashImage->cashBookingItemGroups()->attach($itemGroup->id, [
                                'type' => $itemGroup->pivot->type ?? null,
                                'deposit' => $itemGroup->pivot->deposit ?? null,
                                'notes' => $itemGroup->pivot->notes ?? null,
                            ]);
                        }
                    }
                }

                // Detach all relationships before deleting
                $deleteCashImage->cashBookings()->detach();
                $deleteCashImage->cashBooks()->detach();
                $deleteCashImage->cashBookingItemGroups()->detach();

                // Delete image file
                if ($deleteCashImage->image) {
                    Storage::delete('images/' . $deleteCashImage->image);
                }

                // Delete the cash image record (this removes it from cash_images table)
                $deleteCashImage->delete();
            }

            // FINALLY: Clear polymorphic fields from keep_id since relationships are now in cash_imageables
            $keepCashImage->update([
                'relatable_id' => 0,
                'relatables' => null
            ]);

            DB::commit();

            return $this->success([
                'kept_cash_image_id' => $keepCashImage->id,
                'deleted_count' => count($validated['delete_ids']),
                'message' => 'All relationships (including keep_id original relationship) transferred to cash_imageables table. Delete IDs removed from cash_images.'
            ], 'Cash images merged successfully');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Merge Cash Images Error: ' . $e->getMessage());

            return $this->error(null, 'Failed to merge cash images: ' . $e->getMessage(), 500);
        }
    }

    public function getCashImageInternal(Request $request)
    {
        try {
            $result = $this->cashImageService->getCashImageInternal($request);

            if ($result['success']) {
                return response()->json([
                    'status' => 'Request was successful.',
                    'message' => $result['message'],
                    'result' => $result['data']
                ], 200);
            }

            return response()->json([
                'status' => 'Error has occurred.',
                'message' => $result['message'],
                'result' => null
            ], $result['error_type'] === 'validation' ? 422 : 500);

        } catch (\Exception $e) {
            \Log::error('Controller Error in getCashImageInternal: ' . $e->getMessage());

            return response()->json([
                'status' => 'Error has occurred.',
                'message' => 'An unexpected error occurred',
                'result' => null
            ], 500);
        }
    }

    public function CashImageInternalEdit(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'internal_transfer_id' => 'nullable|exists:internal_transfers,id',
                'from_cash_image_ids' => 'nullable|array',
                'from_cash_image_ids.*' => 'exists:cash_images,id',
                'to_cash_image_ids' => 'nullable|array',
                'to_cash_image_ids.*' => 'exists:cash_images,id',
                'exchange_rate' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            // Check if we're editing existing or creating new
            if ($validated['internal_transfer_id']) {
                // Editing existing internal transfer
                $internalTransfer = \App\Models\InternalTransfer::findOrFail($validated['internal_transfer_id']);

                $internalTransfer->update([
                    'exchange_rate' => $validated['exchange_rate'],
                    'notes' => $validated['notes'] ?? null,
                ]);
            } else {
                // Creating new internal transfer
                $internalTransfer = \App\Models\InternalTransfer::create([
                    'exchange_rate' => $validated['exchange_rate'],
                    'notes' => $validated['notes'] ?? null,
                ]);
            }

            // Handle FROM images
            if (!empty($validated['from_cash_image_ids'])) {
                foreach ($validated['from_cash_image_ids'] as $cashImageId) {
                    $cashImage = \App\Models\CashImage::findOrFail($cashImageId);

                    // Update the cash_image to mark as internal transfer
                    $cashImage->update(['internal_transfer' => true]);

                    // Check if already attached to avoid duplicates
                    if (!$internalTransfer->cashImagesFrom()->where('cash_image_id', $cashImageId)->exists()) {
                        $internalTransfer->cashImagesFrom()->attach($cashImageId, [
                            'direction' => 'from'
                        ]);
                    }
                }
            }

            // Handle TO images
            if (!empty($validated['to_cash_image_ids'])) {
                foreach ($validated['to_cash_image_ids'] as $cashImageId) {
                    $cashImage = \App\Models\CashImage::findOrFail($cashImageId);

                    // Update the cash_image to mark as internal transfer
                    $cashImage->update(['internal_transfer' => true]);

                    // Check if already attached to avoid duplicates
                    if (!$internalTransfer->cashImagesTo()->where('cash_image_id', $cashImageId)->exists()) {
                        $internalTransfer->cashImagesTo()->attach($cashImageId, [
                            'direction' => 'to'
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => 'Internal transfer updated successfully',
                'data' => $internalTransfer->load(['cashImagesFrom', 'cashImagesTo'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 0,
                'message' => 'Failed to update internal transfer: ' . $e->getMessage()
            ], 500);
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
            'relatable_type' => 'required', // booking_item_group
            'relatable_id' => 'required', // 123

            // targets is nullable, but if present, models are required
            'targets' => 'nullable|array',
            'targets.*.model_type' => 'required_with:targets|string|in:booking,cash_book,booking_item_group',
            'targets.*.model_id' => 'required_with:targets|integer',
            'type' => 'nullable|string|max:255',
            'deposit' => 'nullable|numeric',
            'notes' => 'nullable|string',
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
            'interact_bank' => $validated['interact_bank'],
            'relatable_type' => $validated['relatable_type'],
            'relatable_id' => $validated['relatable_id'],
            'image_path' => $fileData['filePath'],
        ]);

        // Attach to targets if provided
        if (!empty($validated['targets'])) {
            foreach ($validated['targets'] as $target) {
                $modelType = $target['model_type'];
                $modelId = $target['model_id'];

                // Create the attachment data
                $attachmentData = [
                    'type' => $validated['type'] ?? null,
                    'deposit' => $validated['deposit'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ];

                // Attach to the appropriate relationship based on model type
                switch ($modelType) {
                    case 'booking':
                        $create->cashBookings()->attach($modelId, $attachmentData);

                        break;
                    case 'cash_book':
                        $create->cashBooks()->attach($modelId, $attachmentData);

                        break;
                    case 'booking_item_group':
                        $create->cashBookingItemGroups()->attach($modelId, $attachmentData);

                        break;
                }
            }
        }

        return $this->success(new CashImageResource($create), 'Successfully created');
    }

    public function show($id)
    {
        $cashImage = CashImage::with([
            'cashBookings.items.group.customerDocuments',
            'cashBookings.items.group.taxReceipts',
            'cashBookings.items.group.cashImages',
            'cashBookings.items.product',
            'cashBookings.customer',
            'cashBookings.receipts',
            'cashBookingItemGroups.bookingItems.product',
            'cashBookingItemGroups.bookingItems.booking.customer',
            'cashBookingItemGroups.customerDocuments',
            'cashBookingItemGroups.taxReceipts',
            'cashBookingItemGroups.cashImages',
            'relatable'
        ])->findOrFail($id);

        return $this->success(new CashImageDetailResource($cashImage), 'Successfully retrieved');
    }

    public function update(Request $request, string $id)
    {
        $find = CashImage::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d H:i:s',
            'sender' => 'nullable|string|max:255',
            'reciever' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'interact_bank' => 'nullable|string|max:255',
            'relatable_type' => 'nullable|string',
            'relatable_id' => 'nullable|integer',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:10240',

            // targets management
            'targets' => 'nullable|array',
            'targets.*.model_type' => 'required_with:targets|string|in:booking,cash_book,booking_item_group',
            'targets.*.model_id' => 'required_with:targets|integer',
            'type' => 'nullable|string|max:255',
            'deposit' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        // Update basic cash image data
        $data = [
            'date' => $validated['date'] ?? $find->date,
            'sender' => $validated['sender'] ?? $find->sender,
            'receiver' => $validated['reciever'] ?? $find->receiver,
            'amount' => $validated['amount'] ?? $find->amount,
            'currency' => $validated['currency'] ?? $find->currency,
            'interact_bank' => $validated['interact_bank'] ?? $find->interact_bank,
            'relatable_type' => $validated['relatable_type'] ?? $find->relatable_type,
            'relatable_id' => $validated['relatable_id'] ?? $find->relatable_id,
        ];

        // Handle image upload if provided
        if ($request->hasFile('image')) {
            // Delete old image
            if ($find->image) {
                Storage::delete('images/' . $find->image);
            }

            $fileData = $this->uploads($request->file('image'), 'images/');
            $data['image'] = $fileData['fileName'];
            $data['image_path'] = $fileData['filePath'];
        }

        $find->update($data);

        // Handle targets if provided
        if (isset($validated['targets'])) {
            // Detach all current relationships
            $find->cashBookings()->detach();
            $find->cashBooks()->detach();
            $find->cashBookingItemGroups()->detach();

            // Attach new targets
            foreach ($validated['targets'] as $target) {
                $modelType = $target['model_type'];
                $modelId = $target['model_id'];

                $attachmentData = [
                    'type' => $validated['type'] ?? null,
                    'deposit' => $validated['deposit'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ];

                switch ($modelType) {
                    case 'booking':
                        $find->cashBookings()->attach($modelId, $attachmentData);

                        break;
                    case 'cash_book':
                        $find->cashBooks()->attach($modelId, $attachmentData);

                        break;
                    case 'booking_item_group':
                        $find->cashBookingItemGroups()->attach($modelId, $attachmentData);

                        break;
                }
            }
        }

        return $this->success(new CashImageResource($find), 'Successfully updated');
    }

    public function destroy(string $id)
    {
        $find = CashImage::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        // Detach all pivot relationships before deletion
        $find->cashBookings()->detach();
        $find->cashBooks()->detach();
        $find->cashBookingItemGroups()->detach();

        // Delete associated image file if exists
        if ($find->image) {
            Storage::delete('images/' . $find->image);
        }

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function exportSummaryToCsv(Request $request)
    {
        try {
            $file_name = "cash_image_export_" . date('Y-m-d-H-i-s') . ".csv";

            // Pass request parameters to the export class
            $export = new CashImageExport($request->all());

            // Check if there's any data to export
            if ($export->collection()->isEmpty()) {
                return $this->error(null, 'No data available for export', 404);
            }

            \Excel::store($export, "export/" . $file_name);

            return $this->success(
                ['download_link' => get_file_link('export', $file_name)],
                'CSV export is successful',
                200
            );
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function exportParchaseToCsv(Request $request)
    {
        try {
            // $data = $this->cashImageService->getAllParchaseForExport($request);
            // return $this->success($data, 'CSV export is successful', 200);
            $file_name = "cash_parchase_export_" . date('Y-m-d-H-i-s') . ".csv";

            $export = new CashParchaseExport($request->all());

            // Check if there's any data to export
            if ($export->collection()->isEmpty()) {
                return $this->error(null, 'No data available for export', 404);
            }

            \Excel::store($export, "export/" . $file_name);

            return $this->success(
                ['download_link' => get_file_link('export', $file_name)],
                'CSV export is successful',
                200
            );
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function exportParchaseTaxToCsv(Request $request)
    {
        try {
            // $data = $this->printPDFService->getExportCSVData($request);
            // return response()->json([
            //     'data' => $data
            // ]);
            $file_name = "cash_parchase_export_" . date('Y-m-d-H-i-s') . ".csv";

            $export = new CashParchaseTaxExport($request->all());

            // Check if there's any data to export
            if ($export->collection()->isEmpty()) {
                return $this->error(null, 'No data available for export', 404);
            }

            \Excel::store($export, "export/" . $file_name);

            return $this->success(
                ['download_link' => get_file_link('export', $file_name)],
                'CSV export is successful',
                200
            );
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function exportInvoiceToCsv(Request $request)
    {
        try {
            // Get total count first
            $count = $this->cashImageService->getTotalRecordsCount($request);

            // $result = $this->cashImageService->getAllParchaseLimitForExport($request);

            // return response()->json([
            //     'data' => $result
            // ]);

            // If no data, return error
            if ($count == 0) {
                return $this->error(null, 'No data available for export', 404);
            }

            $limit = 50; // Records per file
            $totalChunks = ceil($count / $limit);
            $downloadLinks = [];

            // If total records are 50 or less, create single file
            if ($count <= $limit) {
                $file_name = "cash_invoice_export_" . date('Y-m-d-H-i-s') . ".csv";

                $export = new CashInvoiceExport($request->all());

                if (!$export->collection()->isEmpty()) {
                    \Excel::store($export, "export/" . $file_name);

                    return $this->success([
                        'total_records' => $count,
                        'download_link' => get_file_link('export', $file_name)
                    ], 'CSV export is successful');
                }
            }

            // Generate multiple CSV files in chunks
            for ($chunk = 0; $chunk < $totalChunks; $chunk++) {
                $offset = $chunk * $limit;

                // Clone request and add pagination parameters
                $chunkParams = $request->all();
                $chunkParams['limit'] = $limit;
                $chunkParams['offset'] = $offset;

                $file_name = "cash_invoice_export_" . date('Y-m-d-H-i-s') . "_part_" . ($chunk + 1) . "_of_" . $totalChunks . ".csv";

                $export = new CashInvoiceExport($chunkParams);

                // Check if this chunk has data
                if (!$export->collection()->isEmpty()) {
                    \Excel::store($export, "export/" . $file_name);

                    $downloadLinks[] = [
                        'file_name' => $file_name,
                        'download_link' => get_file_link('export', $file_name),
                        'part' => $chunk + 1,
                        'total_parts' => $totalChunks,
                        'records_in_file' => min($limit, $count - $offset)
                    ];
                }
            }

            return $this->success([
                'total_records' => $count,
                'total_files' => count($downloadLinks),
                'records_per_file' => $limit,
                'files' => $downloadLinks
            ], 'CSV export completed successfully - ' . count($downloadLinks) . ' files generated');

        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function printCashImage(Request $request)
    {
        try {
            // Create a method to get count or use query builder
            $count = $this->cashImageService->getAllSummaryForExport($request);

            $totalItems = $count['result']['total_records'];

            if ($totalItems === 0) {
                return response()
                    ->header('Access-Control-Allow-Origin', '*')
                    ->json([
                        'success' => false,
                        'message' => 'No data found to generate PDF'
                    ], 404);
            }

            $batchSize = 50;
            $totalBatches = ceil($totalItems / $batchSize);

            Log::info("Total items: {$totalItems}, Creating {$totalBatches} batches");

            $jobIds = [];
            $statusUrls = [];

            // Create batches and dispatch jobs
            for ($i = 0; $i < $totalBatches; $i++) {
                $offset = $i * $batchSize;
                $batchNumber = $i + 1;

                // Create batch request with pagination
                $batchRequest = array_merge($request->all(), [
                    'batch_offset' => $offset,
                    'batch_limit' => $batchSize,
                    'invoice_start_number' => $offset + 1,
                ]);

                $jobId = "cash_image_pdf_batch_{$batchNumber}_" . date('YmdHis') . "_" . uniqid();

                // Dispatch job for this batch
                GenerateCashImagePdfJob::dispatch($batchRequest, $jobId, $batchNumber, $totalBatches)
                    ->onQueue('pdf_generation')
                    ->delay(now()->addSeconds($i * 10)); // Increased delay to 10 seconds

                $jobIds[] = $jobId;
                $statusUrls[] = url("/admin/pdf-status/{$jobId}");
            }

            // Store overall job status
            $masterJobId = "master_pdf_" . date('YmdHis') . "_" . uniqid();
            Cache::put("pdf_job_{$masterJobId}", [
                'status' => 'processing',
                'total_batches' => $totalBatches,
                'total_items' => $totalItems,
                'batch_jobs' => $jobIds,
                'created_at' => now()
            ], 7200);

            return response()
                ->header('Access-Control-Allow-Origin', '*')
                ->json([
                    'success' => true,
                    'message' => "PDF generation started for {$totalItems} items in {$totalBatches} batches",
                    'master_job_id' => $masterJobId,
                    'batch_jobs' => $jobIds,
                    'status_urls' => $statusUrls,
                    'total_items' => $totalItems,
                    'total_batches' => $totalBatches,
                    'batch_size' => $batchSize,
                    'estimated_time' => "Approximately " . ($totalBatches * 2) . "-" . ($totalBatches * 5) . " minutes"
                ], 202);

        } catch (Exception $e) {
            Log::error('PDF Job Dispatch Error: ' . $e->getMessage());

            return response()
                ->header('Access-Control-Allow-Origin', '*')
                ->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
        }
    }

    public function checkPdfStatus($jobId)
    {
        $status = Cache::get("pdf_job_{$jobId}");

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or expired'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'status' => $status['status'],
            'download_url' => $status['download_url'] ?? null,
            'filename' => $status['filename'] ?? null,
            'error' => $status['error'] ?? null,
            'progress' => $status['progress'] ?? null
        ]);
    }

    public function printCashParchaseImage(Request $request)
    {
        try {
            // Total records ရေ ရမယ်
            $totalRecords = $this->cashImageService->getTotalRecordsCount($request);

            // $data = $this->cashImageService->getAllPurchaseForPrintBatch($request, 0, 100);

            // return response()->json([
            //     'data' => $data
            // ]);

            if ($totalRecords === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found to generate PDF'
                ], 404);
            }

            $batchSize = 50; // တစ်ခါမှာ 50 items ပဲ
            $totalBatches = ceil($totalRecords / $batchSize);

            // Job ID generate လုပ်မယ်
            $jobId = "cash_batch_pdf_" . date('Y-m-d-H-i-s');

            // Batch status initialize လုပ်မယ်
            Cache::put("batch_pdf_job_{$jobId}", [
                'status' => 'processing',
                'total_records' => $totalRecords,
                'total_batches' => $totalBatches,
                'completed_batches' => 0,
                'batch_files' => [],
                'created_at' => now(),
                'progress' => 0
            ], 7200); // 2 hours

            // ပထမ batch ကို စမယ်
            $this->dispatchNextBatch($request->all(), $jobId, 0, $batchSize, 1, $totalBatches);

            return response()->json([
                'success' => true,
                'message' => 'PDF batch generation started',
                'job_id' => $jobId,
                'total_records' => $totalRecords,
                'total_batches' => $totalBatches,
                'batch_size' => $batchSize,
                'status_url' => url("/admin/pdf-batch-status/{$jobId}"),
                'estimated_time' => 'Each batch takes 1-2 minutes'
            ], 202);

        } catch (Exception $e) {
            Log::error('PDF Batch Job Dispatch Error: ' . $e->getMessage());

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    private function dispatchNextBatch($requestData, $jobId, $offset, $batchSize, $batchNumber, $totalBatches)
    {
        GenerateCashParchasePdfJob::dispatch(
            $requestData,
            $jobId,
            $offset,
            $batchSize,
            $batchNumber,
            $totalBatches
        )->onQueue('pdf_generation');
    }

    public function checkPdfBatchStatus($jobId)
    {
        $status = Cache::get("batch_pdf_job_{$jobId}");

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or expired'
            ], 404);
        }

        // Progress calculate လုပ်မယ်
        $progress = 0;
        if ($status['total_batches'] > 0) {
            $progress = ($status['completed_batches'] / $status['total_batches']) * 100;
        }

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'status' => $status['status'],
            'progress' => round($progress, 1),
            'total_records' => $status['total_records'],
            'total_batches' => $status['total_batches'],
            'completed_batches' => $status['completed_batches'],
            'batch_files' => $status['batch_files'], // အားလုံး files list
            'created_at' => $status['created_at'],
            'updated_at' => $status['updated_at'] ?? null
        ]);
    }

    public function verifyData(Request $request, $id)
    {
        $find = CashImage::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $request->validate([
            'data_verify' => 'required|boolean'
        ]);

        $find->update([
            'data_verify' => $request->data_verify
        ]);

        return $this->success(null, 'Data verified successfully');
    }

    public function verifyBank(Request $request, $id)
    {
        $find = CashImage::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $request->validate([
            'bank_verify' => 'required|boolean'
        ]);

        $find->update([
            'bank_verify' => $request->bank_verify
        ]);

        return $this->success(null, 'Data verified successfully');
    }
}
