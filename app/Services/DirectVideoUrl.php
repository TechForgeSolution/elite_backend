<?php

namespace App\Services;

use RuntimeException;

class DirectVideoUrl
{
    public function forDriveId(string $id): string
    {
        if (! preg_match('/^[\w-]+$/', $id)) {
            throw new RuntimeException('Identifiant vidÃ©o Drive invalide.');
        }

        return 'https://drive.usercontent.google.com/download?'.http_build_query([
            'id' => $id,
            'export' => 'download',
            'confirm' => 't',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function driveId(?string $url): ?string
    {
        $parts = parse_url($url ?? '');
        if (! is_array($parts) || ! in_array(strtolower($parts['host'] ?? ''),
            ['drive.google.com', 'docs.google.com', 'drive.usercontent.google.com'], true)) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        $id = $query['id'] ?? null;
        if (preg_match('~/file/d/([\w-]+)(?:/|$)~', $parts['path'] ?? '', $match)) {
            $id = $match[1];
        }

        return is_string($id) && preg_match('/^[\w-]+$/', $id) ? $id : null;
    }

    public function fromFile(?array $file): ?string
    {
        if (! $file) {
            return null;
        }
        $source = $file['url_direct'] ?? $file['url'] ?? $file['url_preview'] ?? null;
        $id = $this->driveId($source);
        if ($id === null && ! empty($source)) {
            if (! filter_var($source, FILTER_VALIDATE_URL) || ! in_array(parse_url($source, PHP_URL_SCHEME), ['https', 'http'], true)) {
                throw new RuntimeException('URL vidéo invalide.');
            }

            return $source;
        }
        $id ??= $file['id'] ?? null;
        if (! is_string($id) || ! preg_match('/^[\w-]+$/', $id)) {
            throw new RuntimeException('Identifiant vidéo Drive absent ou invalide.');
        }
        if (isset($file['id']) && $file['id'] !== $id) {
            throw new RuntimeException('Identifiants vidéo Drive contradictoires.');
        }
        $query = ['id' => $id, 'export' => 'download', 'confirm' => 't'];
        parse_str(parse_url($source ?? '', PHP_URL_QUERY) ?: '', $sourceQuery);
        if (isset($sourceQuery['resourcekey']) && is_string($sourceQuery['resourcekey'])) {
            $query['resourcekey'] = $sourceQuery['resourcekey'];
        }

        return 'https://drive.usercontent.google.com/download?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
