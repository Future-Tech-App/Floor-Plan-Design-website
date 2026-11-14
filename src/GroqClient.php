<?php
declare(strict_types=1);

/**
 * Server-side Groq chat-completions client for the PHP AI tools
 * (/api/v1/generate-plan.php and /api/v1/analyze-plan.php).
 *
 * The key comes from config('groq_api_key') (config/secrets.php) and never
 * reaches the browser. Every upstream call counts against the per-IP limit in
 * config('ai_rate_limit') and is logged in the ai_requests table.
 */
final class GroqException extends RuntimeException
{
    /** @param int $status HTTP status to send to the browser */
    public function __construct(string $message, public readonly int $status = 502)
    {
        parent::__construct($message);
    }
}

final class GroqClient
{
    public const MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';

    public static function configured(): bool
    {
        return (string) config('groq_api_key', '') !== '';
    }

    /** Reject cross-origin browser requests (browsers always send Origin on cross-origin POSTs). */
    public static function assertSameOrigin(): void
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '') {
            return;
        }
        $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $siteHost = strtolower((string) parse_url((string) config('site_url'), PHP_URL_HOST));
        $allowed = array_unique(array_filter([$host, $siteHost, $siteHost ? 'www.' . $siteHost : '']));
        if (!in_array($originHost, $allowed, true)) {
            throw new GroqException('Cross-origin requests are not allowed.', 403);
        }
    }

    /** Throws 429 when this IP has used up its AI allowance. */
    public static function assertWithinRateLimit(int $callsNeeded = 1): void
    {
        $limit = (array) config('ai_rate_limit', ['max' => 30, 'window_minutes' => 60]);
        $used = Repository::recentAiRequestCount(client_ip(), (int) $limit['window_minutes']);
        if ($used + $callsNeeded > (int) $limit['max']) {
            header('Retry-After: 600');
            throw new GroqException('You have used the AI tools a lot recently. Please try again in a little while.', 429);
        }
    }

    /**
     * One chat-completions call. Returns the assistant message text.
     *
     * @param array<int, array<string, mixed>> $messages OpenAI-style messages (text or image_url parts)
     */
    public static function chat(array $messages, int $maxTokens = 4000, float $temperature = 0.7, ?string $model = null): string
    {
        $apiKey = (string) config('groq_api_key', '');
        if ($apiKey === '') {
            throw new GroqException('AI features are not configured on this server yet.', 503);
        }
        self::assertWithinRateLimit();

        $payload = [
            'model'       => $model ?? self::MODEL,
            'messages'    => $messages,
            'max_tokens'  => min($maxTokens, (int) config('ai_max_tokens', 8000)),
            'temperature' => max(0.0, min(2.0, $temperature)),
        ];
        $ch = curl_init((string) config('ai_upstream_url', 'https://api.groq.com/openai/v1/chat/completions'));
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        unset($ch);

        Repository::logAiRequest(client_ip(), $response === false ? 0 : $status);

        if ($response === false) {
            throw new GroqException('Could not reach the AI service: ' . $curlError, 502);
        }
        $body = json_decode((string) $response, true);
        if ($status !== 200 || !is_array($body) || isset($body['error'])) {
            $msg = is_array($body) ? (string) ($body['error']['message'] ?? '') : '';
            throw new GroqException('AI service error' . ($msg !== '' ? ': ' . $msg : ' (HTTP ' . $status . ')'), $status === 429 ? 429 : 502);
        }
        return (string) ($body['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Tolerant JSON-object extractor for model output: strips markdown fences,
     * takes the outermost {...}, and tries to recover from truncation.
     *
     * @return array<string, mixed> empty array when nothing parses
     */
    public static function parseJsonObject(string $raw): array
    {
        $s = trim(str_replace(['```json', '```', "\r\n", "\n"], ['', '', ' ', ' '], $raw));
        $start = strpos($s, '{');
        $end = strrpos($s, '}');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }
        $js = substr($s, $start, $end - $start + 1);
        $out = json_decode($js, true);
        if (is_array($out)) {
            return $out;
        }
        $lc = strrpos($js, '},');
        if ($lc !== false && $lc > 10) {
            foreach ([']}]}', ']}', ']}}', '}}'] as $closer) {
                $out = json_decode(substr($js, 0, $lc + 1) . $closer, true);
                if (is_array($out)) {
                    return $out;
                }
            }
        }
        return [];
    }
}
