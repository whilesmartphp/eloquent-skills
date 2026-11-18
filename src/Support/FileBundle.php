<?php

namespace Whilesmart\Skills\Support;

use Whilesmart\Skills\Exceptions\InvalidSkillBundle;

final class FileBundle
{
    public const ROOT = 'SKILL.md';

    public const UTF8 = 'utf8';

    public const BASE64 = 'base64';

    public static function normalizePath(mixed $path): string
    {
        if (is_int($path)) {
            $path = (string) $path;
        }

        if (! is_string($path) || $path === '' || strlen($path) > 255) {
            throw InvalidSkillBundle::path(is_string($path) ? $path : '');
        }

        if (preg_match('/[\x00-\x1f\x7f\\\\:]/', $path) || ! mb_check_encoding($path, 'UTF-8') || str_starts_with($path, '/')) {
            throw InvalidSkillBundle::path($path);
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || trim($segment) !== $segment) {
                throw InvalidSkillBundle::path($path);
            }
        }

        return $path;
    }

    /**
     * @param  array<string|int, mixed>  $files
     * @return array<string, string>
     */
    public static function check(array $files): array
    {
        if ($files === []) {
            throw InvalidSkillBundle::empty();
        }

        $maxFiles = SkillsConfig::limit('max_files');
        $maxFileBytes = SkillsConfig::limit('max_file_bytes');
        $maxBytes = SkillsConfig::limit('max_bundle_bytes');

        if (count($files) > $maxFiles) {
            throw InvalidSkillBundle::tooManyFiles($maxFiles);
        }

        $clean = [];
        $total = 0;

        foreach ($files as $path => $bytes) {
            $path = self::normalizePath($path);

            if (! is_string($bytes)) {
                throw InvalidSkillBundle::content($path);
            }

            if (isset($clean[$path])) {
                throw InvalidSkillBundle::duplicate($path);
            }

            $size = strlen($bytes);

            if ($size > $maxFileBytes) {
                throw InvalidSkillBundle::fileTooLarge($path, $maxFileBytes);
            }

            $total += $size;

            if ($total > $maxBytes) {
                throw InvalidSkillBundle::bundleTooLarge($maxBytes);
            }

            $clean[$path] = $bytes;
        }

        $paths = array_map('strval', array_keys($clean));

        foreach ($paths as $path) {
            foreach ($paths as $other) {
                if (str_starts_with($other, $path.'/')) {
                    throw InvalidSkillBundle::path($path);
                }
            }
        }

        if (! array_key_exists(self::ROOT, $clean)) {
            throw InvalidSkillBundle::missingRoot();
        }

        if (! mb_check_encoding($clean[self::ROOT], 'UTF-8')) {
            throw InvalidSkillBundle::content(self::ROOT);
        }

        ksort($clean, SORT_STRING);

        return $clean;
    }

    /**
     * @param  array<string, string>  $files
     * @return array<string, array{encoding: string, content: string}>
     */
    public static function encode(array $files): array
    {
        $encoded = [];

        foreach ($files as $path => $bytes) {
            $text = mb_check_encoding($bytes, 'UTF-8');
            $encoded[(string) $path] = [
                'encoding' => $text ? self::UTF8 : self::BASE64,
                'content' => $text ? $bytes : base64_encode($bytes),
            ];
        }

        ksort($encoded, SORT_STRING);

        return $encoded;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, string>
     */
    public static function decode(array $stored): array
    {
        $files = [];

        foreach ($stored as $path => $entry) {
            if (! is_array($entry) || ! is_string($entry['content'] ?? null)) {
                throw InvalidSkillBundle::content((string) $path);
            }

            $files[(string) $path] = self::decodeContent((string) $path, $entry['content'], $entry['encoding'] ?? self::UTF8);
        }

        return $files;
    }

    public static function decodeContent(string $path, string $content, mixed $encoding): string
    {
        if ($encoding === null || $encoding === self::UTF8) {
            return $content;
        }

        if ($encoding === self::BASE64) {
            $bytes = base64_decode($content, true);

            if ($bytes === false) {
                throw InvalidSkillBundle::encoding($path);
            }

            return $bytes;
        }

        throw InvalidSkillBundle::encoding($path);
    }

    /**
     * @param  array<string, string>  $files
     * @return array<string, string>
     */
    public static function prefixed(array $files, string $folder): array
    {
        $prefixed = [];

        foreach ($files as $path => $bytes) {
            $prefixed[$folder.'/'.$path] = $bytes;
        }

        return $prefixed;
    }

    /**
     * @param  array<string, string>  $files
     * @return list<array{path: string, encoding: string, content: string, size: int}>
     */
    public static function present(array $files): array
    {
        $listed = [];

        foreach (self::encode($files) as $path => $entry) {
            $listed[] = [
                'path' => $path,
                'encoding' => $entry['encoding'],
                'content' => $entry['content'],
                'size' => strlen($files[$path]),
            ];
        }

        return $listed;
    }
}
