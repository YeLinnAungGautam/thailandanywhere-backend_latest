<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Models\MemoryLike;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemoryLikeController extends Controller
{
    use HttpResponses;

    /**
     * Toggle a reaction on a memory.
     * Same type again -> removes it.
     * Different type -> switches to it.
     * No existing reaction -> creates it.
     */
    public function react(Request $request, string $memoryId)
    {
        $memory = Memory::find($memoryId);

        if (!$memory) {
            return $this->error(null, 'Memory not found', 404);
        }

        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', MemoryLike::TYPES),
        ]);

        $existing = MemoryLike::where('memory_id', $memory->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing && $existing->type === $validated['type']) {
            $existing->delete();
            $action = 'removed';
            $reaction = null;
        } elseif ($existing) {
            $existing->update(['type' => $validated['type']]);
            $action = 'updated';
            $reaction = $validated['type'];
        } else {
            MemoryLike::create([
                'memory_id' => $memory->id,
                'user_id'   => Auth::id(),
                'type'      => $validated['type'],
            ]);
            $action = 'added';
            $reaction = $validated['type'];
        }

        return $this->success([
            'action'   => $action,
            'reaction' => $reaction,
            'counts'   => $memory->reaction_counts,
        ], 'Reaction ' . $action);
    }

    /**
     * List users who reacted to a memory (optionally filtered by type).
     */
    public function index(Request $request, string $memoryId)
    {
        $memory = Memory::find($memoryId);

        if (!$memory) {
            return $this->error(null, 'Memory not found', 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|in:' . implode(',', MemoryLike::TYPES),
        ]);

        $likes = $memory->likes()
            ->with('user:id,name')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $validated['type']))
            ->latest()
            ->get();

        return $this->success($likes, 'Reactions retrieved');
    }
}
