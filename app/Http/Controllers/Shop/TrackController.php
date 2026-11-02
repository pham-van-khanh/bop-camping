<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\VisitEvent;
use App\Services\VisitTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Nhận beacon "khách bấm liên hệ" từ trình duyệt (xem lib/analytics.ts). */
class TrackController extends Controller
{
    public function contact(Request $request, VisitTracker $tracker): Response
    {
        $data = $request->validate([
            'method' => ['required', 'in:'.implode(',', VisitEvent::METHODS)],
            'placement' => ['nullable', 'string', 'max:40'],
            'path' => ['nullable', 'string', 'max:255'],
        ]);

        if ($tracker->countable($request)) {
            $tracker->recordContact($request, $data['method'], $data['placement'] ?? null, $data['path'] ?? null);
        }

        return response()->noContent();
    }
}
