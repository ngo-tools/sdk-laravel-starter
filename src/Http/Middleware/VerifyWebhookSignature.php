<?php

namespace NgoTools\LaravelStarter\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('ngotools.webhook_secret');

        if (! $secret) {
            return $next($request);
        }

        $signature = $request->header('X-NGOTools-Signature');
        $timestamp = $request->header('X-NGOTools-Timestamp');

        if (! $signature || ! $timestamp) {
            abort(401, 'Missing signature headers');
        }

        $ts = (int) $timestamp;
        $age = abs(time() - $ts);

        if ($age > 300) {
            abort(401, 'Timestamp too old (replay protection)');
        }

        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Signature mismatch');
        }

        return $next($request);
    }
}
