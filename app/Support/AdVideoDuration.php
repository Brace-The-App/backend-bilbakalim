<?php

namespace App\Support;

/**
 * Short ad video duration helper (max ~10s).
 * Prefers ffprobe when available; falls back to MP4/MOV mvhd parse.
 */
class AdVideoDuration
{
    public const MAX_SECONDS = 10;

    public static function seconds(string $absolutePath): ?float
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        $fromProbe = self::viaFfprobe($absolutePath);
        if ($fromProbe !== null) {
            return $fromProbe;
        }

        return self::viaMp4Mvhd($absolutePath);
    }

    public static function exceedsLimit(string $absolutePath, float $maxSeconds = self::MAX_SECONDS): ?bool
    {
        $seconds = self::seconds($absolutePath);
        if ($seconds === null) {
            return null;
        }

        return $seconds > ($maxSeconds + 0.05);
    }

    private static function viaFfprobe(string $path): ?float
    {
        $bin = trim((string) shell_exec('command -v ffprobe 2>/dev/null'));
        if ($bin === '') {
            return null;
        }

        $cmd = escapeshellarg($bin)
            . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
            . escapeshellarg($path)
            . ' 2>/dev/null';

        $out = trim((string) shell_exec($cmd));
        if ($out === '' || !is_numeric($out)) {
            return null;
        }

        return (float) $out;
    }

    private static function viaMp4Mvhd(string $path): ?float
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return null;
        }

        try {
            $size = filesize($path) ?: 0;
            $offset = 0;

            while ($offset + 8 <= $size) {
                if (fseek($fh, $offset) !== 0) {
                    return null;
                }

                $header = fread($fh, 8);
                if ($header === false || strlen($header) < 8) {
                    return null;
                }

                $boxSize = unpack('N', substr($header, 0, 4))[1];
                $type = substr($header, 4, 4);
                $headerLen = 8;

                if ($boxSize === 1) {
                    $large = fread($fh, 8);
                    if ($large === false || strlen($large) < 8) {
                        return null;
                    }
                    $hi = unpack('N', substr($large, 0, 4))[1];
                    $lo = unpack('N', substr($large, 4, 4))[1];
                    $boxSize = ($hi << 32) + $lo;
                    $headerLen = 16;
                } elseif ($boxSize === 0) {
                    $boxSize = $size - $offset;
                }

                if ($boxSize < $headerLen) {
                    return null;
                }

                if ($type === 'moov' || $type === 'trak' || $type === 'mdia' || $type === 'minf' || $type === 'stbl') {
                    $offset += $headerLen;
                    continue;
                }

                if ($type === 'mvhd') {
                    $version = ord(fread($fh, 1) ?: "\0");
                    fread($fh, 3); // flags

                    if ($version === 1) {
                        fread($fh, 16); // creation + modification (64-bit each)
                        $timescaleData = fread($fh, 4);
                        $durationData = fread($fh, 8);
                        if ($timescaleData === false || $durationData === false) {
                            return null;
                        }
                        $timescale = unpack('N', $timescaleData)[1];
                        $hi = unpack('N', substr($durationData, 0, 4))[1];
                        $lo = unpack('N', substr($durationData, 4, 4))[1];
                        $duration = ($hi << 32) + $lo;
                    } else {
                        fread($fh, 8); // creation + modification (32-bit each)
                        $timescaleData = fread($fh, 4);
                        $durationData = fread($fh, 4);
                        if ($timescaleData === false || $durationData === false) {
                            return null;
                        }
                        $timescale = unpack('N', $timescaleData)[1];
                        $duration = unpack('N', $durationData)[1];
                    }

                    if ($timescale <= 0) {
                        return null;
                    }

                    return $duration / $timescale;
                }

                $offset += $boxSize;
            }
        } finally {
            fclose($fh);
        }

        return null;
    }
}
