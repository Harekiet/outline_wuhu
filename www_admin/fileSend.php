<?php

function get_mime_type(string $filePath): string {
    // Try using PHP's built-in function
    if (function_exists('mime_content_type')) {
        $type = mime_content_type($filePath);
        if ($type !== false && $type !== 'application/octet-stream') {
            return $type;
        }
    }

    // Fallback MIME types by file extension
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html'=> 'text/html',
        'php' => 'text/x-php',
        'css' => 'text/css',
        'js'  => 'application/javascript',
        'json'=> 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',

        // Images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg'=> 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff'=> 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'webp'=> 'image/webp',

        // Archives
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        'tar' => 'application/x-tar',
        'gz'  => 'application/gzip',
        '7z'  => 'application/x-7z-compressed',

        // Audio/video
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'wmv' => 'video/x-ms-wmv',
        'webm'=> 'video/webm',

        // Documents
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx'=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx'=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx'=> 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}


function send_file(string $fullPath, string $downloadName = null, string $mimeType = null): void {
    if (!file_exists($fullPath) || !is_readable($fullPath)) {
        http_response_code(404);
        echo "File not found or not readable.";
        exit;
    }

    $filesize = filesize($fullPath);
    $downloadName = $downloadName ?? basename($fullPath);
    $mimeType = $mimeType ?? mime_content_type($fullPath);

    // Clean output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Headers
    header("Content-Type: $mimeType");
    header("Accept-Ranges: bytes");

    $start = 0;
    $end = $filesize - 1;
    $length = $filesize;

    // Handle HTTP Range
    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
            if ($matches[1] !== '') $start = intval($matches[1]);
            if ($matches[2] !== '') $end = intval($matches[2]);

            // Clamp values
            $start = max(0, $start);
            $end = min($filesize - 1, $end);
            $length = $end - $start + 1;

            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes $start-$end/$filesize");
        }
    } else {
        header('HTTP/1.1 200 OK');
    }

    header("Content-Disposition: attachment; filename=\"" . basename($downloadName) . "\"");
    header("Content-Length: $length");
    header("Cache-Control: no-cache");
    header("Pragma: no-cache");
    header("Expires: 0");

    $chunkSize = 8192; // 8KB
    $handle = fopen($fullPath, 'rb');
    if ($handle === false) {
        http_response_code(500);
        echo "Failed to open file.";
        exit;
    }

    fseek($handle, $start);
    $bytesRemaining = $length;

    while (!feof($handle) && $bytesRemaining > 0) {
        $readLength = min($chunkSize, $bytesRemaining);
        echo fread($handle, $readLength);
        flush();

        $bytesRemaining -= $readLength;

        if (connection_status() != CONNECTION_NORMAL) {
            break;
        }
    }

    fclose($handle);
    exit;
}

?>
