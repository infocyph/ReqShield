<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * @phpstan-type MimeList array<int, string>
 * @phpstan-type RawMimeValue string|MimeList
 * @phpstan-type RawMimeMap array<string, RawMimeValue>
 * @phpstan-type MimeMap array<string, MimeList>
 */
class MimeTypeResolver
{
    /** @var array<string, RawMimeMap> */
    private const array MIME_GROUPS = [
        '3d' => [
            'obj' => 'text/plain',
            'stl' => 'application/sla',
            'fbx' => 'application/octet-stream',
            'dae' => 'model/vnd.collada+xml',
            'gltf' => 'model/gltf+json',
            'glb' => 'model/gltf-binary',
            '3ds' => 'image/x-3ds',
        ],
        'adobe' => [
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            'psd' => 'image/vnd.adobe.photoshop',
        ],
        'apple' => [
            'pages' => 'application/vnd.apple.pages',
            'numbers' => 'application/vnd.apple.numbers',
            'key' => 'application/vnd.apple.keynote',
        ],
        'archive' => [
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'bz2' => 'application/x-bzip2',
            '7z' => 'application/x-7z-compressed',
            'xz' => 'application/x-xz',
        ],
        'audio' => [
            'mp3' => 'audio/mpeg',
            'wav' => ['audio/wav', 'audio/x-wav'],
            'ogg' => 'audio/ogg',
            'oga' => 'audio/ogg',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'm4a' => 'audio/mp4',
            'wma' => 'audio/x-ms-wma',
            'aiff' => 'audio/x-aiff',
            'aif' => 'audio/x-aiff',
            'opus' => 'audio/opus',
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'amr' => 'audio/amr',
        ],
        'certificate' => [
            'pem' => 'application/x-pem-file',
            'crt' => 'application/x-x509-ca-cert',
            'cer' => 'application/x-x509-ca-cert',
            'der' => 'application/x-x509-ca-cert',
            'p12' => 'application/x-pkcs12',
            'pfx' => 'application/x-pkcs12',
        ],
        'code' => [
            'php' => ['text/x-php', 'application/x-httpd-php'],
            'js' => ['text/javascript', 'application/javascript'],
            'json' => 'application/json',
            'xml' => ['application/xml', 'text/xml'],
            'html' => 'text/html',
            'css' => 'text/css',
            'py' => 'text/x-python',
            'java' => 'text/x-java-source',
            'c' => 'text/x-c',
            'cpp' => 'text/x-c++',
            'h' => 'text/x-c',
            'cs' => 'text/x-csharp',
            'rb' => 'text/x-ruby',
            'go' => 'text/x-go',
            'rs' => 'text/x-rust',
            'swift' => 'text/x-swift',
            'kt' => 'text/x-kotlin',
            'sql' => 'application/sql',
            'sh' => 'application/x-sh',
            'yaml' => 'text/yaml',
            'yml' => 'text/yaml',
        ],
        'database' => [
            'db' => 'application/x-sqlite3',
            'sqlite' => 'application/x-sqlite3',
            'sqlite3' => 'application/x-sqlite3',
            'mdb' => 'application/vnd.ms-access',
        ],
        'document' => [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'rtf' => 'application/rtf',
            'pages' => 'application/vnd.apple.pages',
            'numbers' => 'application/vnd.apple.numbers',
            'key' => 'application/vnd.apple.keynote',
        ],
        'ebook' => [
            'epub' => 'application/epub+zip',
            'mobi' => 'application/x-mobipocket-ebook',
            'azw' => 'application/vnd.amazon.ebook',
            'azw3' => 'application/vnd.amazon.ebook',
        ],
        'executable' => [
            'exe' => 'application/x-msdownload',
            'dll' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'dmg' => 'application/x-apple-diskimage',
            'apk' => 'application/vnd.android.package-archive',
            'deb' => 'application/x-debian-package',
        ],
        'font' => [
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'eot' => 'application/vnd.ms-fontobject',
        ],
        'image' => [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'jfif' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => ['image/bmp', 'image/x-ms-bmp'],
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'avif' => 'image/avif',
            'raw' => 'image/x-dcraw',
            'cr2' => 'image/x-canon-cr2',
            'nef' => 'image/x-nikon-nef',
            'dng' => 'image/x-adobe-dng',
            'arw' => 'image/x-sony-arw',
            'psd' => 'image/vnd.adobe.photoshop',
            'psb' => 'image/vnd.adobe.photoshop',
        ],
        'text' => [
            'txt' => 'text/plain',
            'text' => 'text/plain',
            'log' => 'text/plain',
            'csv' => 'text/csv',
            'tsv' => 'text/tab-separated-values',
            'md' => 'text/markdown',
            'markdown' => 'text/markdown',
        ],
        'video' => [
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'ogv' => 'video/ogg',
            '3gp' => 'video/3gpp',
            '3g2' => 'video/3gpp2',
            'm4v' => 'video/x-m4v',
        ],
        'web' => [
            'html' => 'text/html',
            'htm' => 'text/html',
            'xhtml' => 'application/xhtml+xml',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'wasm' => 'application/wasm',
            'svg' => 'image/svg+xml',
        ],
    ];

    /** @return MimeList */
    public static function getMimeTypes(string $extension): array
    {
        $extension = strtolower(trim($extension, '.'));

        return self::mimeMap()[$extension] ?? ['application/octet-stream'];
    }

    public static function getPrimaryMimeType(string $extension): string
    {
        $types = self::getMimeTypes($extension);

        return $types[0] ?? 'application/octet-stream';
    }

    public static function hasExtension(string $extension): bool
    {
        $types = self::getMimeTypes($extension);

        return $types !== ['application/octet-stream'];
    }

    /** @return MimeMap */
    private static function mimeMap(): array
    {
        /** @var MimeMap|null $map */
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];

        foreach (self::MIME_GROUPS as $group) {
            foreach ($group as $extension => $mimeValue) {
                $types = is_string($mimeValue) ? [$mimeValue] : $mimeValue;
                if (isset($map[$extension])) {
                    continue;
                }

                $map[$extension] = $types;
            }
        }

        return $map;
    }
}
