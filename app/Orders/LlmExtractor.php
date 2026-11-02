<?php

namespace App\Orders;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads free-text orders with an LLM (Anthropic Messages API). The model only
 * extracts: it returns JSON lines, never touches the database, and the result
 * is a pending draft like any other. Its output is validated: a line without
 * a positive quantity, or without any identifier, is dropped.
 */
final class LlmExtractor implements OrderExtractor
{
    private const PROMPT = <<<'TXT'
        Extract the order lines from the order below, written by a hardware store to its distributor.
        Answer with JSON only: {"lines":[{"sku":string|null,"ean":string|null,"description":string|null,"quantity":integer,"raw":string}]}
        - sku: a code like E3-00001 if the line has one. ean: a 13-digit barcode if it has one.
        - description: the product as written, when there is no code.
        - quantity: units. "dos cajas" without a unit count is 2. Never guess a number that is not written.
        - raw: the line as it appears. Ignore greetings, signatures and anything that is not an order line.
        ORDER:
        TXT;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function name(): string
    {
        return 'llm';
    }

    public function extract(string $text): array
    {
        if (! $this->apiKey) {
            throw new RuntimeException('LLM extractor selected but ANTHROPIC_API_KEY is not set.');
        }

        $response = Http::withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => '2023-06-01'])
            ->timeout($this->timeout)
            ->retry(2, 1000, throw: false)
            ->post(rtrim($this->baseUrl, '/').'/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 2048,
                'messages' => [['role' => 'user', 'content' => self::PROMPT."\n".$text]],
            ]);
        $response->throw();

        $content = '';
        foreach ((array) $response->json('content', []) as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                $content = (string) ($block['text'] ?? '');

                break;
            }
        }

        return $this->parse($content);
    }

    /** @return list<ExtractedLine> */
    private function parse(string $content): array
    {
        $json = preg_match('/\{.*\}/s', $content, $m) ? json_decode($m[0], true) : null;
        if (! is_array($json) || ! is_array($json['lines'] ?? null)) {
            throw new RuntimeException('LLM answer is not the expected JSON.');
        }

        $lines = [];
        foreach ($json['lines'] as $line) {
            $quantity = is_int($line['quantity'] ?? null) ? $line['quantity'] : 0;
            $sku = self::text($line['sku'] ?? null);
            $ean = self::text($line['ean'] ?? null);
            $description = self::text($line['description'] ?? null);
            if ($quantity <= 0 || ($sku === null && $ean === null && $description === null)) {
                continue;
            }
            $lines[] = new ExtractedLine(self::text($line['raw'] ?? null) ?? '', $quantity, $sku, $ean, $description);
        }

        return $lines;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
