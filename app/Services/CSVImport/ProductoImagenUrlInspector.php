<?php

namespace App\Services\CSVImport;

/**
 * Comprueba que una URL de imagen responda con un tipo image/jpeg, image/jpg,
 * image/png, image/gif o image/webp (o con la firma binaria de esos formatos).
 * No descarga el archivo al disco: solo lee los primeros bytes, con corte a los 2 s.
 */
class ProductoImagenUrlInspector
{
    public const MAX_URL_LENGTH = 500;

    private const READ_BYTES = 64;

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/gif',
        'image/webp',
    ];

    /**
     * @return array{ok: bool, message: ?string, http_code: ?int, curl_error: ?string, bytes: int, content_type: ?string}
     */
    public function inspect(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return $this->reject('La URL de imagen está vacía.');
        }

        if (strlen($url) > self::MAX_URL_LENGTH) {
            return $this->reject('La URL de imagen excede '.self::MAX_URL_LENGTH.' caracteres.');
        }

        if (! $this->isPublicHttpUrl($url)) {
            return $this->reject('La URL de imagen no es http(s) pública.');
        }

        $fetched = $this->fetchPrefix($url);
        if ($fetched['body'] === null && ! $this->isAllowedImageMime($fetched['content_type'])) {
            return $this->reject('El enlace de imagen no responde.', $fetched);
        }

        $httpOk = in_array($fetched['http_code'], [200, 206], true);
        $mimeOk = $this->isAllowedImageMime($fetched['content_type']);
        $magicOk = $fetched['body'] !== null && $this->looksLikeAllowedImage($fetched['body']);

        if (! $httpOk || (! $mimeOk && ! $magicOk)) {
            $type = $fetched['content_type'] ?: 'sin content-type';

            return $this->reject(
                'El enlace no corresponde a una imagen jpeg, jpg, png, webp o gif ('.$type.').',
                $fetched
            );
        }

        return [
            'ok' => true,
            'message' => null,
            'http_code' => $fetched['http_code'],
            'curl_error' => $fetched['curl_error'],
            'bytes' => $fetched['bytes'],
            'content_type' => $fetched['content_type'],
        ];
    }

    /**
     * @param  array{http_code: ?int, curl_error: ?string, bytes: int, content_type: ?string}|null  $fetched
     * @return array{ok: bool, message: string, http_code: ?int, curl_error: ?string, bytes: int, content_type: ?string}
     */
    private function reject(string $message, ?array $fetched = null): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'http_code' => $fetched['http_code'] ?? null,
            'curl_error' => $fetched['curl_error'] ?? null,
            'bytes' => $fetched['bytes'] ?? 0,
            'content_type' => $fetched['content_type'] ?? null,
        ];
    }

    /**
     * Lee como máximo READ_BYTES y corta a los 2 s. No usa el cliente HTTP en stream:
     * esa descarga no respetaba el timeout y dejaba el job en 15 %.
     *
     * @return array{body: ?string, http_code: ?int, curl_error: ?string, bytes: int, content_type: ?string}
     */
    private function fetchPrefix(string $url): array
    {
        $empty = [
            'body' => null,
            'http_code' => null,
            'curl_error' => 'cURL no está disponible.',
            'bytes' => 0,
            'content_type' => null,
        ];
        if (! function_exists('curl_init')) {
            return $empty;
        }

        $buffer = '';
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'body' => null,
                'http_code' => null,
                'curl_error' => 'No se pudo iniciar cURL.',
                'bytes' => 0,
                'content_type' => null,
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'NexProvCatalogoImport/1.0',
            // libcurl antepone "bytes="; "bytes=0-63" sale como "bytes=bytes=0-63" y IIS responde 400 HTML.
            CURLOPT_RANGE => '0-'.(self::READ_BYTES - 1),
            CURLOPT_WRITEFUNCTION => function ($curl, string $data) use (&$buffer): int {
                $buffer .= $data;
                if (strlen($buffer) >= self::READ_BYTES) {
                    return 0;
                }

                return strlen($data);
            },
        ]);

        curl_exec($ch);
        $errno = curl_errno($ch);
        $curlError = $errno ? curl_error($ch) : null;
        // Cortar a los 64 bytes provoca WRITE_ERROR o PARTIAL_FILE; no es un fallo de red.
        if (in_array($errno, [CURLE_WRITE_ERROR, CURLE_PARTIAL_FILE], true) && $buffer !== '') {
            $curlError = null;
        }
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $buffer = substr($buffer, 0, self::READ_BYTES);

        return [
            'body' => $buffer === '' ? null : $buffer,
            'http_code' => $httpCode > 0 ? $httpCode : null,
            'curl_error' => $curlError,
            'bytes' => strlen($buffer),
            'content_type' => is_string($contentType) && $contentType !== '' ? $contentType : null,
        ];
    }

    private function isAllowedImageMime(?string $contentType): bool
    {
        if ($contentType === null || $contentType === '') {
            return false;
        }

        $mime = strtolower(trim(explode(';', $contentType, 2)[0]));

        return in_array($mime, self::ALLOWED_MIME_TYPES, true);
    }

    public function looksLikeAllowedImage(string $bytes): bool
    {
        if ($bytes === '') {
            return false;
        }

        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return true;
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return true;
        }

        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return true;
        }

        return strlen($bytes) >= 12
            && str_starts_with($bytes, 'RIFF')
            && substr($bytes, 8, 4) === 'WEBP';
    }

    private function isPublicHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
