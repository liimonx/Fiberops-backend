<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MikrotikService
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(private readonly array $credentials) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public static function fromCredentials(array $credentials): self
    {
        return new self($credentials);
    }

    /**
     * @return array{ok: bool, message: string, identity?: string}
     */
    public function testConnection(): array
    {
        try {
            $identity = $this->getSystemIdentity();

            return [
                'ok' => true,
                'message' => 'Connected successfully',
                'identity' => $identity,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return list<string>
     */
    public function getActivePppoeSessions(): array
    {
        if ($this->apiMode() === 'classic') {
            return $this->getActivePppoeSessionsClassic();
        }

        $rows = $this->restGet('/rest/ppp/active');

        return collect($rows)
            ->map(fn (array $row) => strtolower((string) ($row['name'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{host: string, status: string}>
     */
    public function getNetwatchEntries(): array
    {
        if ($this->apiMode() === 'classic') {
            return $this->getNetwatchEntriesClassic();
        }

        $rows = $this->restGet('/rest/tool/netwatch');

        return collect($rows)
            ->map(function (array $row): array {
                return [
                    'host' => (string) ($row['host'] ?? ''),
                    'status' => strtolower((string) ($row['status'] ?? 'unknown')),
                ];
            })
            ->filter(fn (array $row) => $row['host'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{rxBytes: int, txBytes: int, name: string}|null
     */
    public function getInterfaceStats(string $interfaceName): ?array
    {
        if ($interfaceName === '') {
            return null;
        }

        if ($this->apiMode() === 'classic') {
            return $this->getInterfaceStatsClassic($interfaceName);
        }

        $rows = $this->restGet('/rest/interface', ['name' => $interfaceName]);
        $row = $rows[0] ?? null;

        if (! is_array($row)) {
            return null;
        }

        return [
            'name' => (string) ($row['name'] ?? $interfaceName),
            'rxBytes' => (int) ($row['rx-byte'] ?? 0),
            'txBytes' => (int) ($row['tx-byte'] ?? 0),
        ];
    }

    public function getSystemIdentity(): string
    {
        if ($this->apiMode() === 'classic') {
            return $this->getSystemIdentityClassic();
        }

        $rows = $this->restGet('/rest/system/identity');
        $row = $rows[0] ?? [];

        return (string) ($row['name'] ?? 'Mikrotik');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function restGet(string $path, array $query = []): array
    {
        $response = Http::withBasicAuth(
            (string) ($this->credentials['username'] ?? ''),
            (string) ($this->credentials['password'] ?? '')
        )
            ->timeout(10)
            ->withOptions([
                'verify' => (bool) ($this->credentials['verifySsl'] ?? true),
            ])
            ->get($this->baseUrl().$path, $query);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Mikrotik REST request failed ({$response->status()}): ".$response->body()
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function baseUrl(): string
    {
        $host = (string) ($this->credentials['host'] ?? '');
        $port = (int) ($this->credentials['port'] ?? 443);
        $useSsl = (bool) ($this->credentials['useSsl'] ?? true);
        $scheme = $useSsl ? 'https' : 'http';

        if ($host === '') {
            throw new RuntimeException('Mikrotik host is not configured.');
        }

        return "{$scheme}://{$host}:{$port}";
    }

    private function apiMode(): string
    {
        return (string) ($this->credentials['apiMode'] ?? 'rest');
    }

    /**
     * @return list<string>
     */
    private function getActivePppoeSessionsClassic(): array
    {
        $rows = $this->classicPrint('/ppp/active');

        return collect($rows)
            ->map(fn (array $row) => strtolower((string) ($row['name'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{host: string, status: string}>
     */
    private function getNetwatchEntriesClassic(): array
    {
        $rows = $this->classicPrint('/tool/netwatch');

        return collect($rows)
            ->map(fn (array $row) => [
                'host' => (string) ($row['host'] ?? ''),
                'status' => strtolower((string) ($row['status'] ?? 'unknown')),
            ])
            ->filter(fn (array $row) => $row['host'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{rxBytes: int, txBytes: int, name: string}|null
     */
    private function getInterfaceStatsClassic(string $interfaceName): ?array
    {
        $rows = $this->classicPrint('/interface', ['?name' => $interfaceName]);
        $row = $rows[0] ?? null;

        if (! is_array($row)) {
            return null;
        }

        return [
            'name' => (string) ($row['name'] ?? $interfaceName),
            'rxBytes' => (int) ($row['rx-byte'] ?? 0),
            'txBytes' => (int) ($row['tx-byte'] ?? 0),
        ];
    }

    private function getSystemIdentityClassic(): string
    {
        $rows = $this->classicPrint('/system/identity');
        $row = $rows[0] ?? [];

        return (string) ($row['name'] ?? 'Mikrotik');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function classicPrint(string $path, array $query = []): array
    {
        $socket = $this->openClassicSocket();

        try {
            $this->classicLogin($socket);
            $this->classicWriteSentence($socket, [
                '/'.ltrim($path, '/').'/print',
                ...$this->classicQueryToSentences($query),
            ]);

            return $this->classicReadUntilDone($socket);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @return resource
     */
    private function openClassicSocket()
    {
        $host = (string) ($this->credentials['host'] ?? '');
        $port = (int) ($this->credentials['port'] ?? 8728);

        if ($host === '') {
            throw new RuntimeException('Mikrotik host is not configured.');
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 10);

        if (! is_resource($socket)) {
            throw new ConnectionException("Unable to connect to Mikrotik API ({$errno}): {$errstr}");
        }

        stream_set_timeout($socket, 10);

        return $socket;
    }

    /**
     * @param  resource  $socket
     */
    private function classicLogin($socket): void
    {
        $username = (string) ($this->credentials['username'] ?? '');
        $password = (string) ($this->credentials['password'] ?? '');

        $this->classicWriteSentence($socket, ['/login', '=name='.$username, '=password='.$password]);
        $response = $this->classicReadSentence($socket);

        if (($response['!type'] ?? null) === '!trap') {
            throw new RuntimeException('Mikrotik login failed.');
        }
    }

    /**
     * @param  resource  $socket
     * @param  list<string>  $words
     */
    private function classicWriteSentence($socket, array $words): void
    {
        foreach ($words as $word) {
            $length = strlen($word);
            fwrite($socket, $this->encodeClassicLength($length).$word);
        }

        fwrite($socket, chr(0));
    }

    /**
     * @param  resource  $socket
     * @return array<string, string>
     */
    private function classicReadSentence($socket): array
    {
        $response = [];

        while (true) {
            $word = $this->classicReadWord($socket);

            if ($word === '') {
                break;
            }

            if (str_starts_with($word, '=')) {
                $key = substr($word, 1, strcspn($word, '=', 1));
                $value = substr($word, 1 + strlen($key) + 1);
                $response[$key] = $value;
            } else {
                $response['!type'] = $word;
            }
        }

        return $response;
    }

    /**
     * @param  resource  $socket
     * @return list<array<string, mixed>>
     */
    private function classicReadUntilDone($socket): array
    {
        $rows = [];

        while (true) {
            $sentence = $this->classicReadSentence($socket);
            $type = $sentence['!type'] ?? null;

            if ($type === '!done') {
                break;
            }

            if ($type === '!re') {
                unset($sentence['!type']);
                $rows[] = $sentence;
            }

            if ($type === '!trap') {
                throw new RuntimeException($sentence['message'] ?? 'Mikrotik API trap');
            }
        }

        return $rows;
    }

    /**
     * @param  resource  $socket
     */
    private function classicReadWord($socket): string
    {
        $lengthBytes = '';

        while (true) {
            $byte = fgetc($socket);

            if ($byte === false) {
                throw new RuntimeException('Unexpected end of Mikrotik API stream.');
            }

            $lengthBytes .= $byte;
            $length = $this->decodeClassicLength($lengthBytes);

            if ($length !== null) {
                if ($length === 0) {
                    return '';
                }

                $data = fread($socket, $length);

                return $data === false ? '' : $data;
            }

            if (strlen($lengthBytes) >= 5) {
                throw new RuntimeException('Invalid Mikrotik API length prefix.');
            }
        }
    }

    private function encodeClassicLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        if ($length < 0x4000) {
            $length |= 0x8000;

            return chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        if ($length < 0x200000) {
            $length |= 0xC00000;

            return chr(($length >> 16) & 0xFF)
                .chr(($length >> 8) & 0xFF)
                .chr($length & 0xFF);
        }

        if ($length < 0x10000000) {
            $length |= 0xE0000000;

            return chr(($length >> 24) & 0xFF)
                .chr(($length >> 16) & 0xFF)
                .chr(($length >> 8) & 0xFF)
                .chr($length & 0xFF);
        }

        return chr(0xF0)
            .chr(($length >> 24) & 0xFF)
            .chr(($length >> 16) & 0xFF)
            .chr(($length >> 8) & 0xFF)
            .chr($length & 0xFF);
    }

    private function decodeClassicLength(string $bytes): ?int
    {
        $first = ord($bytes[0]);

        if ($first < 0x80) {
            return $first;
        }

        if (($first & 0xC0) === 0x80 && strlen($bytes) >= 2) {
            return ((ord($bytes[0]) & 0x3F) << 8) + ord($bytes[1]);
        }

        if (($first & 0xE0) === 0xC0 && strlen($bytes) >= 3) {
            return ((ord($bytes[0]) & 0x1F) << 16)
                + (ord($bytes[1]) << 8)
                + ord($bytes[2]);
        }

        if (($first & 0xF0) === 0xE0 && strlen($bytes) >= 4) {
            return ((ord($bytes[0]) & 0x0F) << 24)
                + (ord($bytes[1]) << 16)
                + (ord($bytes[2]) << 8)
                + ord($bytes[3]);
        }

        if ($first === 0xF0 && strlen($bytes) >= 5) {
            return (ord($bytes[1]) << 24)
                + (ord($bytes[2]) << 16)
                + (ord($bytes[3]) << 8)
                + ord($bytes[4]);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function classicQueryToSentences(array $query): array
    {
        $sentences = [];

        foreach ($query as $key => $value) {
            $sentences[] = '='.ltrim((string) $key, '=').$value;
        }

        return $sentences;
    }
}
