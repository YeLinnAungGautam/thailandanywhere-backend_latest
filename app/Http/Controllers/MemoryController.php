<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Memory;
use App\Models\MemoryImage;
use App\Traits\HttpResponses;
use App\Traits\ImageResizeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MemoryController extends Controller
{
    use HttpResponses, ImageResizeManager;

    protected string $imagePath = 'images/memories/';

    /**
     * Public feed — any user can browse memories from completed trips.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'user_id'    => 'sometimes|integer',
            'booking_id' => 'sometimes|integer',
            'product_id' => 'sometimes|integer',
            'limit'      => 'sometimes|integer|min:1',
            'sort'       => 'sometimes|in:asc,desc', // created_at direction
            'page'       => 'sometimes|integer|min:1',
        ]);

        $limit = $validated['limit'] ?? 10;
        $sort  = $validated['sort'] ?? 'asc'; // default: oldest first

        $memories = Memory::query()
            ->where('status', 'published')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('booking_id'), fn ($q) => $q->where('booking_id', $request->booking_id))
            ->when($request->filled('product_id'), function ($q) use ($request) {
                $q->whereHas('bookingItem', fn ($sub) => $sub->where('product_id', $request->product_id));
            })
            ->with(['images', 'user:id,name', 'bookingItem.product', 'booking:id,crm_id'])
            ->orderBy('created_at', $sort)
            ->paginate($limit);

        return $this->success($memories, 'Memories retrieved');
    }

    public function show(string $id)
    {
        $memory = Memory::with(['images', 'user:id,name', 'bookingItem.product', 'booking:id,crm_id'])->find($id);

        if (!$memory) {
            return $this->error(null, 'Memory not found', 404);
        }

        return $this->success($memory, 'Memory retrieved');
    }

    /**
     * Look up whether the current user already has a memory for a given
     * booking (+ booking item). Used by the client to decide whether to
     * open the "Add memory" flow or the "Edit memory" flow, and to
     * pre-fill the edit form.
     */
    public function existingForBooking(Request $request)
    {
        $validated = $request->validate([
            'booking_id'      => 'required|exists:bookings,id',
            'booking_item_id' => 'nullable|exists:booking_items,id',
        ]);

        $memory = Memory::with(['images'])
            ->where('booking_id', $validated['booking_id'])
            ->where('user_id', Auth::id())
            ->when(
                $validated['booking_item_id'] ?? null,
                fn ($q, $itemId) => $q->where('booking_item_id', $itemId),
                fn ($q) => $q->whereNull('booking_item_id')
            )
            ->first();

        return $this->success($memory, $memory ? 'Existing memory found' : 'No memory yet');
    }

    /**
     * Create a memory for a completed booking: title + up to 3 images.
     * Only one memory is allowed per booking_id + booking_item_id combination
     * per user — if one already exists, the client should call update()
     * (and the image endpoints) instead.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id'      => 'required|exists:bookings,id',
            'booking_item_id' => 'nullable|exists:booking_items,id',
            'title'           => 'required|string|max:150',
            'caption'         => 'nullable|string|max:1000',
            'images'          => 'required|array|min:1|max:3',
            'images.*'        => 'image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        $booking = Booking::find($validated['booking_id']);

        if ($booking->user_id !== Auth::id()) {
            return $this->error(null, 'You are not allowed to add a memory to this booking', 403);
        }

        if ($booking->app_show_status !== 'completed') {
            return $this->error(null, 'You can only add a memory once this trip is completed', 422);
        }

        if (!empty($validated['booking_item_id'])) {
            $belongsToBooking = BookingItem::where('id', $validated['booking_item_id'])
                ->where('booking_id', $booking->id)
                ->exists();

            if (!$belongsToBooking) {
                return $this->error(null, 'This item does not belong to the selected booking', 422);
            }
        }

        // Enforce: only one memory per booking_id + booking_item_id per user.
        $existing = Memory::with('images')
            ->where('booking_id', $booking->id)
            ->where('user_id', Auth::id())
            ->when(
                $validated['booking_item_id'] ?? null,
                fn ($q, $itemId) => $q->where('booking_item_id', $itemId),
                fn ($q) => $q->whereNull('booking_item_id')
            )
            ->first();

        if ($existing) {
            return $this->error(
                ['memory' => $existing],
                'You already added a memory for this trip item. Edit it instead of creating a new one.',
                409
            );
        }

        $memory = DB::transaction(function () use ($validated, $request, $booking) {
            $memory = Memory::create([
                'booking_id'      => $booking->id,
                'booking_item_id' => $validated['booking_item_id'] ?? null,
                'user_id'         => Auth::id(),
                'title'           => $validated['title'],
                'caption'         => $validated['caption'] ?? null,
                'status'          => 'published',
            ]);

            foreach ($request->file('images') as $index => $file) {
                $uploaded = $this->uploadResizedImage($file, $this->imagePath);

                MemoryImage::create([
                    'memory_id'  => $memory->id,
                    'image'      => $uploaded['filePath'],
                    'sort_order' => $index,
                ]);
            }

            return $memory;
        });

        $memory->load(['images', 'user:id,name', 'bookingItem.product', 'booking:id,crm_id']);

        return $this->success($memory, 'Memory created successfully');
    }

    /**
     * Update title/caption. Restricted to the memory's own author.
     */
    public function update(Request $request, string $id)
    {
        $memory = Memory::find($id);

        if (!$memory) {
            return $this->error(null, 'Memory not found', 404);
        }

        if ($memory->user_id !== Auth::id()) {
            return $this->error(null, 'You are not allowed to edit this memory', 403);
        }

        $validated = $request->validate([
            'title'   => 'sometimes|required|string|max:150',
            'caption' => 'nullable|string|max:1000',
        ]);

        $memory->update($validated);

        return $this->success($memory->fresh(['images']), 'Memory updated successfully');
    }

    /**
     * Replace one existing image on a memory (old file deleted, new one resized + stored).
     */
    public function updateImage(Request $request, string $memoryId, string $imageId)
    {
        $memory = Memory::find($memoryId);

        if (!$memory) {
            return $this->error(null, 'Memory not found', 404);
        }

        if ($memory->user_id !== Auth::id()) {
            return $this->error(null, 'You are not allowed to edit this memory', 403);
        }

        $memoryImage = $memory->images()->where('id', $imageId)->first();

        if (!$memoryImage) {
            return $this->error(null, 'Image not found on this memory', 404);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        $this->deleteImage($memoryImage->image);
        $uploaded = $this->uploadResizedImage($request->file('image'), $this->imagePath);

        $memoryImage->update(['image' => $uploaded['filePath']]);

        return $this->success($memoryImage->fresh(), 'Memory image updated successfully');
    }

    /**
     * Add another image to a memory (capped at 3 total).
     */
    public function addImage(Request $request, string $memoryId)
    {
        $memory = Memory::find($memoryId);

        if (!$memory) {
            return $this->error(null, 'Memory not found', 404);
        }

        if ($memory->user_id !== Auth::id()) {
            return $this->error(null, 'You are not allowed to edit this memory', 403);
        }

        if ($memory->images()->count() >= 3) {
            return $this->error(null, 'A memory can only have up to 3 images', 422);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        $uploaded = $this->uploadResizedImage($request->file('image'), $this->imagePath);

        $memoryImage = MemoryImage::create([
            'memory_id'  => $memory->id,
            'image'      => $uploaded['filePath'],
            'sort_order' => $memory->images()->count(),
        ]);

        return $this->success($memoryImage, 'Image added successfully');
    }

    public function destroyImage(string $memoryId, string $imageId)
    {
        $memory = Memory::find($memoryId);

        if (!$memory) {
            return $this->error(null, 'Memory not found', 404);
        }

        if ($memory->user_id !== Auth::id()) {
            return $this->error(null, 'You are not allowed to edit this memory', 403);
        }

        $memoryImage = $memory->images()->where('id', $imageId)->first();

        if (!$memoryImage) {
            return $this->error(null, 'Image not found on this memory', 404);
        }

        $this->deleteImage($memoryImage->image);
        $memoryImage->delete();

        return $this->success(null, 'Image removed successfully');
    }

    /**
     * Delete a memory. Restricted to the memory's own author.
     */
    public function destroy(string $id)
    {
        $memory = Memory::with('images')->find($id);

        if (!$memory) {
            return $this->error(null, 'Memory not found', 404);
        }

        if ($memory->user_id !== Auth::id()) {
            return $this->error(null, 'You are not allowed to delete this memory', 403);
        }

        DB::transaction(function () use ($memory) {
            foreach ($memory->images as $image) {
                $this->deleteImage($image->image);
                $image->delete();
            }

            $memory->delete();
        });

        return $this->success(null, 'Memory deleted successfully');
    }
}
