<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class XenditWebhookController extends Controller
{
    public function __construct(private XenditService $xendit) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $sessionId = $payload['data']['payment_session_id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '' || ! $this->xendit->verifyWebhookToken($request->header('x-callback-token'), $sessionId)) {
            Log::warning('Xendit webhook rejected due to missing session or invalid callback token.');

            return response()->json(['message' => 'Invalid Xendit webhook.'], 400);
        }

        try {
            return response()->json($this->xendit->processWebhook($payload));
        } catch (Throwable $exception) {
            Log::error('Error processing Xendit webhook: '.$exception->getMessage());

            return response()->json(['message' => 'Unable to process Xendit webhook.'], 500);
        }
    }
}
