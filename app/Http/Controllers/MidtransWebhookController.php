<?php

namespace App\Http\Controllers;

use App\Actions\ProcessTopupNotification;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans, ProcessTopupNotification $process): JsonResponse
    {
        $payload = $request->all();

        // Tanpa signature valid, request ditolak: siapa pun tidak bisa memalsukan top-up.
        abort_unless($midtrans->validSignature($payload), 403);

        $process->execute($payload);

        return response()->json(['ok' => true]);
    }
}
