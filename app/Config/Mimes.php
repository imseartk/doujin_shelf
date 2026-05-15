<?php

namespace Config;

class Mimes
{
    public static array $mimes = [
        'css' => ['text/css', 'text/plain'],
        'js' => ['application/javascript', 'application/x-javascript', 'text/javascript', 'text/plain'],
        'json' => ['application/json', 'text/json'],
        'html' => ['text/html', 'text/plain'],
        'htm' => ['text/html', 'text/plain'],
        'txt' => 'text/plain',
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'xml' => ['application/xml', 'text/xml', 'text/plain'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png', 'image/x-png'],
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => ['image/svg+xml', 'application/xml', 'text/xml'],
        'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
        'pdf' => ['application/pdf', 'application/x-download'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];

    public static function guessTypeFromExtension(string $extension): ?string
    {
        $extension = trim(strtolower($extension), '. ');
        if (! array_key_exists($extension, static::$mimes)) {
            return null;
        }

        return is_array(static::$mimes[$extension]) ? static::$mimes[$extension][0] : static::$mimes[$extension];
    }

    public static function guessExtensionFromType(string $type, ?string $proposedExtension = null): ?string
    {
        $type = trim(strtolower($type), '. ');
        $proposedExtension = trim(strtolower($proposedExtension ?? ''));

        if ($proposedExtension !== '' && array_key_exists($proposedExtension, static::$mimes) && in_array($type, (array) static::$mimes[$proposedExtension], true)) {
            return $proposedExtension;
        }

        foreach (static::$mimes as $extension => $types) {
            if (in_array($type, (array) $types, true)) {
                return $extension;
            }
        }

        return null;
    }
}
