<?php
declare(strict_types=1);
namespace App;

final class UploadHttp
{
    public static function wantsJson(array $server, array $post): bool
    {
        return ($server['REQUEST_METHOD'] ?? '') === 'POST'
            && str_contains($server['HTTP_ACCEPT'] ?? '', 'application/json')
            && (in_array($post['action'] ?? '', ['upload', 'bulk-upload'], true)
                || str_starts_with(strtolower($server['CONTENT_TYPE'] ?? ''), 'multipart/form-data'));
    }

    public static function exceedsPostLimit(array $server, string $limit): bool
    {
        $bytes = ini_parse_quantity($limit);
        return ($server['REQUEST_METHOD'] ?? '') === 'POST' && $bytes > 0
            && (int) ($server['CONTENT_LENGTH'] ?? 0) > $bytes;
    }
}
