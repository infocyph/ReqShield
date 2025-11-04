<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * Memory-Efficient MIME Type Resolver
 *
 * Uses lazy loading and only loads the MIME types actually needed.
 * Memory footprint: ~50-100 KB instead of loading full 600+ mapping array.
 *
 * Performance optimizations:
 * - Lazy loading: Only loads data when first accessed
 * - Category-based grouping: Only loads relevant category
 * - Static caching: Resolved types cached in memory
 * - Fast lookup: O(1) hash map access
 */
class MimeTypeResolver
{
    /**
     * Cached MIME types to avoid repeated lookups
     */
    private static array $cache = [];

    /**
     * Loaded categories to avoid re-loading
     */
    private static array $loadedCategories = [];

    /**
     * Category mapping for fast lookups
     * Maps extension first letter to category for efficient loading
     */
    private static array $categoryMap = [
        'a' => ['audio', 'archive', 'adobe', 'apple'],
        'b' => ['image', 'archive'],
        'c' => ['code', 'document', 'archive', 'certificate'],
        'd' => ['document', 'database', '3d', 'image'],
        'e' => ['document', 'ebook', 'audio', 'executable'],
        'f' => ['font', 'video', 'audio', '3d'],
        'g' => ['image', 'archive', 'code'],
        'h' => ['web', 'code', 'image'],
        'i' => ['image', 'text', 'archive'],
        'j' => ['image', 'code', 'web', 'video'],
        'k' => ['code', 'document', 'video'],
        'l' => ['text', 'code', 'archive'],
        'm' => ['audio', 'video', 'document', 'database', 'text', '3d'],
        'n' => ['image', 'text'],
        'o' => ['document', 'audio', 'video', 'font'],
        'p' => ['document', 'image', 'code', 'certificate', 'video', 'adobe'],
        'q' => ['video'],
        'r' => ['image', 'audio', 'text', 'video', 'code', 'document'],
        's' => ['image', 'audio', 'video', 'code', 'text', 'archive'],
        't' => ['image', 'video', 'text', 'font', 'code', 'archive'],
        'u' => ['text', 'video'],
        'v' => ['video', 'text', 'certificate'],
        'w' => ['audio', 'video', 'web', 'font', 'document', 'image'],
        'x' => ['document', 'web', 'archive', 'audio', 'image', '3d'],
        'y' => ['text', 'code'],
        'z' => ['archive'],
        '3' => ['video', '3d'],
        '7' => ['archive'],
    ];

    /**
     * Get MIME types for a file extension
     *
     * @param string $extension File extension (without dot)
     * @return array Array of MIME types
     */
    public static function getMimeTypes(string $extension): array
    {
        $extension = strtolower(trim($extension, '.'));

        // Check cache first
        if (isset(self::$cache[$extension])) {
            return self::$cache[$extension];
        }

        // Determine which category to load based on first character
        $firstChar = $extension[0] ?? '';
        $categories = self::$categoryMap[$firstChar] ?? ['other'];

        // Load and search categories
        foreach ($categories as $category) {
            if (!isset(self::$loadedCategories[$category])) {
                self::loadCategory($category);
            }

            // Check if extension exists in this category
            $categoryData = self::$loadedCategories[$category];
            if (isset($categoryData[$extension])) {
                self::$cache[$extension] = $categoryData[$extension];
                return $categoryData[$extension];
            }
        }

        // Fallback: Return generic octet-stream
        $fallback = ['application/octet-stream'];
        self::$cache[$extension] = $fallback;
        return $fallback;
    }

    /**
     * Check if extension has a known MIME type
     *
     * @param string $extension File extension
     * @return bool
     */
    public static function hasExtension(string $extension): bool
    {
        $types = self::getMimeTypes($extension);
        return $types !== ['application/octet-stream'];
    }

    /**
     * Get primary MIME type (first in array)
     *
     * @param string $extension File extension
     * @return string
     */
    public static function getPrimaryMimeType(string $extension): string
    {
        $types = self::getMimeTypes($extension);
        return $types[0] ?? 'application/octet-stream';
    }

    /**
     * Clear the cache (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$loadedCategories = [];
    }

    /**
     * Load a specific category of MIME types
     * This is where the actual data is stored, but only loaded when needed
     *
     * @param string $category Category name
     */
    private static function loadCategory(string $category): void
    {
        self::$loadedCategories[$category] = match ($category) {
            'image' => self::getImageMimes(),
            'audio' => self::getAudioMimes(),
            'video' => self::getVideoMimes(),
            'document' => self::getDocumentMimes(),
            'archive' => self::getArchiveMimes(),
            'code' => self::getCodeMimes(),
            'web' => self::getWebMimes(),
            'font' => self::getFontMimes(),
            'ebook' => self::getEbookMimes(),
            'certificate' => self::getCertificateMimes(),
            'database' => self::getDatabaseMimes(),
            'text' => self::getTextMimes(),
            '3d' => self::get3dMimes(),
            'adobe' => self::getAdobeMimes(),
            'apple' => self::getAppleMimes(),
            'executable' => self::getExecutableMimes(),
            default => [],
        };
    }

    /**
     * Image MIME types
     */
    private static function getImageMimes(): array
    {
        return [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'jpe' => ['image/jpeg'],
            'jfif' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'bmp' => ['image/bmp', 'image/x-ms-bmp'],
            'webp' => ['image/webp'],
            'svg' => ['image/svg+xml'],
            'svgz' => ['image/svg+xml'],
            'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
            'tiff' => ['image/tiff'],
            'tif' => ['image/tiff'],
            'heic' => ['image/heic'],
            'heif' => ['image/heif'],
            'avif' => ['image/avif'],
            'raw' => ['image/x-dcraw'],
            'cr2' => ['image/x-canon-cr2'],
            'nef' => ['image/x-nikon-nef'],
            'dng' => ['image/x-adobe-dng'],
            'arw' => ['image/x-sony-arw'],
            'psd' => ['image/vnd.adobe.photoshop'],
            'psb' => ['image/vnd.adobe.photoshop'],
        ];
    }

    /**
     * Audio MIME types
     */
    private static function getAudioMimes(): array
    {
        return [
            'mp3' => ['audio/mpeg'],
            'wav' => ['audio/wav', 'audio/x-wav'],
            'ogg' => ['audio/ogg'],
            'oga' => ['audio/ogg'],
            'flac' => ['audio/flac'],
            'aac' => ['audio/aac'],
            'm4a' => ['audio/mp4'],
            'wma' => ['audio/x-ms-wma'],
            'aiff' => ['audio/x-aiff'],
            'aif' => ['audio/x-aiff'],
            'opus' => ['audio/opus'],
            'mid' => ['audio/midi'],
            'midi' => ['audio/midi'],
            'amr' => ['audio/amr'],
        ];
    }

    /**
     * Video MIME types
     */
    private static function getVideoMimes(): array
    {
        return [
            'mp4' => ['video/mp4'],
            'avi' => ['video/x-msvideo'],
            'mov' => ['video/quicktime'],
            'wmv' => ['video/x-ms-wmv'],
            'flv' => ['video/x-flv'],
            'webm' => ['video/webm'],
            'mkv' => ['video/x-matroska'],
            'mpeg' => ['video/mpeg'],
            'mpg' => ['video/mpeg'],
            'ogv' => ['video/ogg'],
            '3gp' => ['video/3gpp'],
            '3g2' => ['video/3gpp2'],
            'm4v' => ['video/x-m4v'],
        ];
    }

    /**
     * Document MIME types
     */
    private static function getDocumentMimes(): array
    {
        return [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'odt' => ['application/vnd.oasis.opendocument.text'],
            'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
            'odp' => ['application/vnd.oasis.opendocument.presentation'],
            'rtf' => ['application/rtf'],
            'pages' => ['application/vnd.apple.pages'],
            'numbers' => ['application/vnd.apple.numbers'],
            'key' => ['application/vnd.apple.keynote'],
        ];
    }

    /**
     * Archive MIME types
     */
    private static function getArchiveMimes(): array
    {
        return [
            'zip' => ['application/zip'],
            'rar' => ['application/x-rar-compressed'],
            'tar' => ['application/x-tar'],
            'gz' => ['application/gzip'],
            'bz2' => ['application/x-bzip2'],
            '7z' => ['application/x-7z-compressed'],
            'xz' => ['application/x-xz'],
        ];
    }

    /**
     * Code/Programming MIME types
     */
    private static function getCodeMimes(): array
    {
        return [
            'php' => ['text/x-php', 'application/x-httpd-php'],
            'js' => ['text/javascript', 'application/javascript'],
            'json' => ['application/json'],
            'xml' => ['application/xml', 'text/xml'],
            'html' => ['text/html'],
            'css' => ['text/css'],
            'py' => ['text/x-python'],
            'java' => ['text/x-java-source'],
            'c' => ['text/x-c'],
            'cpp' => ['text/x-c++'],
            'h' => ['text/x-c'],
            'cs' => ['text/x-csharp'],
            'rb' => ['text/x-ruby'],
            'go' => ['text/x-go'],
            'rs' => ['text/x-rust'],
            'swift' => ['text/x-swift'],
            'kt' => ['text/x-kotlin'],
            'sql' => ['application/sql'],
            'sh' => ['application/x-sh'],
            'yaml' => ['text/yaml'],
            'yml' => ['text/yaml'],
        ];
    }

    /**
     * Web MIME types
     */
    private static function getWebMimes(): array
    {
        return [
            'html' => ['text/html'],
            'htm' => ['text/html'],
            'xhtml' => ['application/xhtml+xml'],
            'css' => ['text/css'],
            'js' => ['text/javascript'],
            'json' => ['application/json'],
            'xml' => ['application/xml'],
            'wasm' => ['application/wasm'],
            'svg' => ['image/svg+xml'],
        ];
    }

    /**
     * Font MIME types
     */
    private static function getFontMimes(): array
    {
        return [
            'ttf' => ['font/ttf'],
            'otf' => ['font/otf'],
            'woff' => ['font/woff'],
            'woff2' => ['font/woff2'],
            'eot' => ['application/vnd.ms-fontobject'],
        ];
    }

    /**
     * eBook MIME types
     */
    private static function getEbookMimes(): array
    {
        return [
            'epub' => ['application/epub+zip'],
            'mobi' => ['application/x-mobipocket-ebook'],
            'azw' => ['application/vnd.amazon.ebook'],
            'azw3' => ['application/vnd.amazon.ebook'],
        ];
    }

    /**
     * Certificate MIME types
     */
    private static function getCertificateMimes(): array
    {
        return [
            'pem' => ['application/x-pem-file'],
            'crt' => ['application/x-x509-ca-cert'],
            'cer' => ['application/x-x509-ca-cert'],
            'der' => ['application/x-x509-ca-cert'],
            'p12' => ['application/x-pkcs12'],
            'pfx' => ['application/x-pkcs12'],
        ];
    }

    /**
     * Database MIME types
     */
    private static function getDatabaseMimes(): array
    {
        return [
            'db' => ['application/x-sqlite3'],
            'sqlite' => ['application/x-sqlite3'],
            'sqlite3' => ['application/x-sqlite3'],
            'mdb' => ['application/vnd.ms-access'],
        ];
    }

    /**
     * Text MIME types
     */
    private static function getTextMimes(): array
    {
        return [
            'txt' => ['text/plain'],
            'text' => ['text/plain'],
            'log' => ['text/plain'],
            'csv' => ['text/csv'],
            'tsv' => ['text/tab-separated-values'],
            'md' => ['text/markdown'],
            'markdown' => ['text/markdown'],
        ];
    }

    /**
     * 3D Model MIME types
     */
    private static function get3dMimes(): array
    {
        return [
            'obj' => ['text/plain'],
            'stl' => ['application/sla'],
            'fbx' => ['application/octet-stream'],
            'dae' => ['model/vnd.collada+xml'],
            'gltf' => ['model/gltf+json'],
            'glb' => ['model/gltf-binary'],
            '3ds' => ['image/x-3ds'],
        ];
    }

    /**
     * Adobe MIME types
     */
    private static function getAdobeMimes(): array
    {
        return [
            'ai' => ['application/postscript'],
            'eps' => ['application/postscript'],
            'ps' => ['application/postscript'],
            'psd' => ['image/vnd.adobe.photoshop'],
        ];
    }

    /**
     * Apple MIME types
     */
    private static function getAppleMimes(): array
    {
        return [
            'pages' => ['application/vnd.apple.pages'],
            'numbers' => ['application/vnd.apple.numbers'],
            'key' => ['application/vnd.apple.keynote'],
        ];
    }

    /**
     * Executable MIME types
     */
    private static function getExecutableMimes(): array
    {
        return [
            'exe' => ['application/x-msdownload'],
            'dll' => ['application/x-msdownload'],
            'msi' => ['application/x-msdownload'],
            'dmg' => ['application/x-apple-diskimage'],
            'apk' => ['application/vnd.android.package-archive'],
            'deb' => ['application/x-debian-package'],
        ];
    }
}
