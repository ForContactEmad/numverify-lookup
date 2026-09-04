<?php

declare(strict_types=1);

namespace Numverify\Http;

use Numverify\Lookup\PhoneLookup;

/**
 * موجّه ووحدة تحكم في آنٍ واحد — مقبول في هذا الحجم.
 * لا يعرف شيئاً عن Numverify: يتعامل مع PhoneLookup فقط.
 */
final class Controller
{
    public function __construct(
        private readonly PhoneLookup $lookup,
        private readonly string $viewsPath,
        private readonly string $engine = 'builtin',
    ) {
    }

    public function handle(string $method, string $path, array $query, array $body): void
    {
        match (true) {
            $path === '/api/validate' => $this->json($query),
            $method === 'POST' => $this->page($body),
            default => $this->page($query),
        };
    }

    private function page(array $input): void
    {
        $number = trim((string) ($input['number'] ?? ''));
        $countryCode = trim((string) ($input['country_code'] ?? ''));

        $result = $number === ''
            ? null
            : $this->lookup->lookup($number, $countryCode ?: null);

        $this->render('home', [
            'number' => $number,
            'countryCode' => $countryCode,
            'result' => $result,
            'engine' => $this->engine,
        ]);
    }

    private function json(array $query): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $number = trim((string) ($query['number'] ?? ''));

        if ($number === '') {
            http_response_code(422);

            echo $this->encode(['success' => false, 'error' => 'المعامل number مطلوب.']);

            return;
        }

        $result = $this->lookup->lookup($number, trim((string) ($query['country_code'] ?? '')) ?: null);

        if (!$result->isValid()) {
            http_response_code(422);
        }

        echo $this->encode(['success' => true, 'data' => $result->toArray()]);
    }

    private function encode(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): void
    {
        extract($data, EXTR_OVERWRITE);

        require $this->viewsPath . '/' . $view . '.php';
    }
}
