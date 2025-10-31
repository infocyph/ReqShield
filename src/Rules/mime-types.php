<?php

declare(strict_types=1);

/**
 * MIME Type Mappings
 *
 * Maps file extensions to their corresponding MIME types.
 * Used by the Mimes validation rule.
 *
 * Format: 'extension' => ['mime/type', 'alternative/mime']
 */

return [
    // Images
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'gif' => ['image/gif'],
    'bmp' => ['image/bmp', 'image/x-windows-bmp'],
    'webp' => ['image/webp'],
    'svg' => ['image/svg+xml'],
    'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
    'tiff' => ['image/tiff'],
    'tif' => ['image/tiff'],

    // Documents - PDF
    'pdf' => ['application/pdf', 'application/x-pdf'],

    // Documents - Microsoft Office (Legacy)
    'doc' => ['application/msword', 'application/vnd.ms-word'],
    'dot' => ['application/msword'],
    'xls' => ['application/vnd.ms-excel', 'application/excel'],
    'xlt' => ['application/vnd.ms-excel'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/powerpoint'],
    'pps' => ['application/vnd.ms-powerpoint'],

    // Documents - Microsoft Office (OpenXML)
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'docm' => ['application/vnd.ms-word.document.macroEnabled.12'],
    'dotx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.template'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'xlsm' => ['application/vnd.ms-excel.sheet.macroEnabled.12'],
    'xltx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.template'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'pptm' => ['application/vnd.ms-powerpoint.presentation.macroEnabled.12'],
    'ppsx' => ['application/vnd.openxmlformats-officedocument.presentationml.slideshow'],

    // Documents - OpenDocument
    'odt' => ['application/vnd.oasis.opendocument.text'],
    'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
    'odp' => ['application/vnd.oasis.opendocument.presentation'],

    // Text Files
    'txt' => ['text/plain'],
    'csv' => ['text/csv', 'text/plain', 'application/csv'],
    'tsv' => ['text/tab-separated-values'],
    'rtf' => ['application/rtf', 'text/rtf'],

    // Web Files
    'html' => ['text/html'],
    'htm' => ['text/html'],
    'css' => ['text/css'],
    'js' => ['text/javascript', 'application/javascript', 'application/x-javascript'],
    'json' => ['application/json'],
    'xml' => ['application/xml', 'text/xml'],

    // Programming Languages
    'php' => ['text/x-php', 'application/x-httpd-php', 'application/php'],
    'py' => ['text/x-python', 'application/x-python-code'],
    'java' => ['text/x-java-source', 'text/x-java'],
    'c' => ['text/x-c'],
    'cpp' => ['text/x-c++'],
    'h' => ['text/x-c'],
    'cs' => ['text/x-csharp'],
    'rb' => ['text/x-ruby', 'application/x-ruby'],
    'go' => ['text/x-go'],
    'rs' => ['text/x-rust'],
    'swift' => ['text/x-swift'],
    'kt' => ['text/x-kotlin'],
    'sql' => ['application/sql', 'text/x-sql'],

    // Archives
    'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
    'rar' => ['application/x-rar-compressed', 'application/x-rar'],
    'tar' => ['application/x-tar'],
    'gz' => ['application/gzip', 'application/x-gzip'],
    'bz2' => ['application/x-bzip2'],
    '7z' => ['application/x-7z-compressed'],

    // Audio
    'mp3' => ['audio/mpeg', 'audio/mp3'],
    'wav' => ['audio/wav', 'audio/x-wav'],
    'ogg' => ['audio/ogg'],
    'flac' => ['audio/flac'],
    'aac' => ['audio/aac', 'audio/x-aac'],
    'm4a' => ['audio/mp4', 'audio/x-m4a'],
    'wma' => ['audio/x-ms-wma'],

    // Video
    'mp4' => ['video/mp4'],
    'avi' => ['video/x-msvideo', 'video/avi'],
    'mov' => ['video/quicktime'],
    'wmv' => ['video/x-ms-wmv'],
    'flv' => ['video/x-flv'],
    'webm' => ['video/webm'],
    'mkv' => ['video/x-matroska'],
    'mpeg' => ['video/mpeg'],
    'mpg' => ['video/mpeg'],
    '3gp' => ['video/3gpp'],

    // Fonts
    'ttf' => ['font/ttf', 'application/x-font-ttf'],
    'otf' => ['font/otf', 'application/x-font-opentype'],
    'woff' => ['font/woff', 'application/font-woff'],
    'woff2' => ['font/woff2'],
    'eot' => ['application/vnd.ms-fontobject'],

    // Other Common Types
    'bin' => ['application/octet-stream'],
    'exe' => ['application/x-msdownload', 'application/exe'],
    'dmg' => ['application/x-apple-diskimage'],
    'iso' => ['application/x-iso9660-image'],
    'apk' => ['application/vnd.android.package-archive'],
    'ipa' => ['application/octet-stream'],
];
