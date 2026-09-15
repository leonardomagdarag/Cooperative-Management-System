<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PayMongoWebhookController extends Controller
{
    public function __construct(
        protected PayMongoService $payMongoService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signatureHeader = $request->header('Paymongo-Signature');

        if (empty($rawPayload) || empty($signatureHeader)) {
            Log::warning('PayMongo webhook rejected: missing payload or signature header.');

            return response()->json(['message' => 'Missing payload or signature header.'], 400);
        }

        // Verify webhook signature (HMAC-SHA256)
        $isValid = $this->payMongoService->verifyWebhookSignature($rawPayload, $signatureHeader);
        if (! $isValid) {
            Log::warning('PayMongo webhook rejected: invalid signature.');

            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Malformed JSON payload.'], 400);
        }

        try {
            $result = $this->payMongoService->processWebhook($payload);

            return response()->json([
                'status' => 'success',
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Error processing PayMongo webhook: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Internal error processing webhook.',
            ], 500);
        }
    }
}
