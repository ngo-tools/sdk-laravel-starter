<?php

namespace NgoTools\LaravelStarter\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $event = $request->header('X-NGOTools-Event');

        Log::info("NGO.Tools webhook received: {$event}", [
            'event' => $event,
            'payload' => $request->all(),
        ]);

        return response()->json(['ok' => true]);
    }
}
