<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SessionTracker;

class TrackingController extends Controller
{
    protected $tracker;

    public function __construct(SessionTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    /**
     * Initialize session (Vue calls on mount)
     */
    public function init(Request $request)
    {
        $sessionHash = $request->input('session_hash');

        // Check if existing session is valid
        if ($sessionHash && $this->tracker->isValidSession($sessionHash)) {
            $this->tracker->updateActivity($sessionHash);

            return response()->json([
                'session_hash' => $sessionHash,
                'is_new' => false,
            ]);
        }

        // Create new session
        $newSessionHash = $this->tracker->createNewSession($request);

        return response()->json([
            'session_hash' => $newSessionHash,
            'is_new' => true,
        ]);
    }

    /**
     * Track event manually (if needed)
     */
    public function track(Request $request)
    {
        $validated = $request->validate([
            'session_hash' => 'required|string',
            'event_type' => 'required|in:visit_site,view_detail,add_to_cart,go_checkout,complete_purchase',
            'product_type' => 'nullable|in:hotel,attraction,vantour,destination,inclusive',
            'product_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        $this->tracker->trackEvent(
            $validated['session_hash'],
            $validated['event_type'],
            $validated['product_type'] ?? null,
            $validated['product_id'] ?? null,
            $validated['metadata'] ?? []
        );

        return response()->json(['success' => true]);
    }

    /**
     * Identify user and link session to user
     */
    public function identify(Request $request)
    {
        $validated = $request->validate([
            'session_hash' => 'required|string',
            'user_id' => 'required|integer',
        ]);

        $this->tracker->linkSessionToUser($validated['session_hash'], $validated['user_id']);

        return response()->json(['success' => true]);
    }
}
