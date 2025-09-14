<?php
namespace App\Http\Middleware;

use App\Http\Requests\StoreOrderRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class EnsureOrderIdempotency
{
    public function handle(Request $request, Closure $next)
    {
        $payload   = $request->input();
        $validator = Validator::make(
            $payload,
            StoreOrderRequest::clientRules()
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid client data',
                'errors'  => $validator->errors(),
            ], 400);
        }

        $client = $payload['client'];
        $hash   = hash('sha256', json_encode($client));
        $key    = "order:$hash";

        $isUniqueRequest = Cache::add(key: $key, value: 1, ttl: 30);

        if (! $isUniqueRequest) {
            return response()->json([
                'message' => 'You are submitting orders too quickly. Please wait a few seconds and try again.',
            ], 409);
        }

        $response = $next($request);

        if (! ($response->isSuccessful() || $response->isRedirection())) {
            Cache::forget($key);
        }

        return $response;
    }
}
