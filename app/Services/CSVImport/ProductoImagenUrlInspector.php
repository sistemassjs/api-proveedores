<?php

namespace App\Services\CSVImport;

/**
 * Comprueba que una URL de imagen responda y que el contenido sea jpeg, png, webp o gif.
 * No descarga el archivo al disco: solo lee los primeros bytes, con corte a los 2 s.
 */
class ProductoImagenUrlInspector
{
    public const MAX_URL_LENGTH = 500;

    private const READ_BYTES = 64;

    /**
     * @return array{ok: bool, message: ?string}
     */
    public function inspect(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['ok' => false, 'message' => 'La URL de imagen está vacía.'];
        }

        if (strlen($url) > self::MAX_URL_LENGTH) {
            return ['ok' => false, 'message' => 'La URL de imagen excede '.self::MAX_URL_LENGTH.' caracteres.'];
        }

        if (! $this->isPublicHttpUrl($url)) {
            return ['ok' => false, 'message' => 'La URL de imagen no es http(s) pública.'];
        }

        $fetched = $this->fetchPrefix($url);
        if ($fetched === null) {
            return ['ok' => false, 'message' => 'El enlace de imagen no responde.'];
        }

        if (! $this->looksLikeAllowedImage($fetched)) {
            return ['ok' => false, 'message' => 'El enlace no corresponde a una imagen jpeg, png, webp o gif.'];
        }

        return ['ok' => true, 'message' => null];
    }

    /**
     * Lee como máximo READ_BYTES y corta a los 2 s. No usa el cliente HTTP en stream:
     * esa descarga no respetaba el timeout y dejaba el job en 15 %.
     */
    private function fetchPrefix(string $url): ?string
    {
        if (! function_exists('curl_init')) {
            return null;
        }

        $buffer = '';
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
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
            CURLOPT_RANGE => 'bytes=0-'.(self::READ_BYTES - 1),
            CURLOPT_WRITEFUNCTION => function ($curl, string $data) use (&$buffer): int {
                $buffer .= $data;
                if (strlen($buffer) >= self::READ_BYTES) {
                    return 0;
                }

                return strlen($data);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);

        $buffer = substr($buffer, 0, self::READ_BYTES);

        return $buffer === '' ? null : $buffer;
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
