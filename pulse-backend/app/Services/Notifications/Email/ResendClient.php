<?php

namespace App\Services\Notifications\Email;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ResendClient
{
    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, value: string}>  $tags
     */
    public function send(
        string $apiKey,
        string $from,
        array $to,
        string $subject,
        string $html,
        string $text,
        ?string $replyTo,
        array $tags,
        string $idempotencyKey,
    ): string {
        $payload = [
            'from' => $from,
            'to' => array_values($to),
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];

        if (filled($replyTo)) {
            $payload['reply_to'] = $replyTo;
        }

        if ($tags !== []) {
            $payload['tags'] = $tags;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'Pulse/1.0',
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->timeout(20)
                ->post('https://api.resend.com/emails', $payload);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Resend: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            $message = $response->json('message');

            throw new RuntimeException('Resend: '.(is_string($message) && $message !== '' ? $message : $response->body()));
        }

        $id = $response->json('id');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Resend did not return an email id.');
        }

        return $id;
    }

    public function formatFrom(?string $name, string $address): string
    {
        $name = trim(str_replace(['<', '>', '"'], '', (string) $name));

        if ($name === '') {
            return $address;
        }

        return $name.' <'.$address.'>';
    }

    public function renderHtml(string $title, string $body, ?string $html): string
    {
        if (is_string($html) && trim($html) !== '') {
            return $html;
        }

        $safeTitle = e($title);
        $safeBody = nl2br(e($body), false);

        return '<div style="font-family:sans-serif;line-height:1.6" dir="rtl">'
            .'<h2 style="margin:0 0 12px">'.$safeTitle.'</h2>'
            .'<p style="margin:0">'.$safeBody.'</p>'
            .'</div>';
    }

    public function plainText(string $title, string $body): string
    {
        return trim($title."\n\n".$body);
    }
}
