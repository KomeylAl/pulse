<?php

namespace App\Services\Notifications\Email;

class ResendWebhookVerifier
{
    public function verify(
        string $payload,
        string $secret,
        ?string $id,
        ?string $timestamp,
        ?string $signatureHeader,
    ): bool {
        if (blank($id) || blank($timestamp) || blank($signatureHeader) || blank($secret)) {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $encoded = preg_replace('/^whsec_/', '', $secret) ?? '';
        $key = base64_decode($encoded, true);

        if ($key === false || $key === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$payload, $key, true));

        foreach (explode(' ', $signatureHeader) as $part) {
            $signature = str_contains($part, ',') ? explode(',', $part, 2)[1] : $part;

            if ($signature !== '' && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
