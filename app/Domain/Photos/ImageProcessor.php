<?php

namespace App\Domain\Photos;

use Illuminate\Support\Facades\Storage;

final class ImageProcessor
{
    public function compress(string $source, string $directory = 'recipes', bool $crop = true): array
    {
        $size = @getimagesize($source);
        if (! $size || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Bitte JPEG, PNG oder WebP verwenden. HEIC wird nicht unterstützt.');
        }
        if ($size[0] * $size[1] > 24000000 || filesize($source) > 20 * 1024 * 1024) {
            throw new \RuntimeException('Dieses Bild ist zu gross. Bitte ein Bild mit höchstens 24 Megapixeln und 20 MB wählen.');
        }
        $image = match ($size['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($source),'image/png' => @imagecreatefrompng($source), 'image/webp' => @imagecreatefromwebp($source)
        };
        if (! $image) {
            throw new \RuntimeException('Dieses Bild kann nicht gelesen werden.');
        }
        try {
            if ($size['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($source);
                $orientation = $exif['Orientation'] ?? 1;
                if (in_array($orientation, [3, 6, 8], true)) {
                    $rotated = imagerotate($image, match ($orientation) {
                        3 => 180,6 => -90,8 => 90
                    }, 0);
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
            $w = imagesx($image);
            $h = imagesy($image);
            $sw = $crop ? min($w, $h) : $w;
            $sh = $crop ? min($w, $h) : $h;
            $ratio = min(1, ($crop ? 1200 : 1920) / max($sw, $sh));
            $tw = max(1, (int) round($sw * $ratio));
            $th = max(1, (int) round($sh * $ratio));
            $out = imagecreatetruecolor($tw, $th);
            imagecopyresampled($out, $image, 0, 0, (int) (($w - $sw) / 2), (int) (($h - $sh) / 2), $tw, $th, $sw, $sh);
            ob_start();
            imagewebp($out, null, 82);
            $bytes = ob_get_clean();
            imagedestroy($out);
            $path = $directory.'/'.hash('sha256', $bytes).'.webp';
            Storage::disk('local')->put($path, $bytes);

            return ['path' => $path, 'bytes' => strlen($bytes)];
        } finally {
            imagedestroy($image);
        }
    }
}
