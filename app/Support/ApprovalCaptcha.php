<?php

namespace App\Support;

final class ApprovalCaptcha
{
    public static function imageDataUri(string $code): string
    {
        $width = 300;
        $height = 92;
        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);
        $background = imagecolorallocate($image, 246, 245, 255);
        imagefill($image, 0, 0, $background);

        $violet = imagecolorallocatealpha($image, 109, 40, 217, 62);
        $slate = imagecolorallocatealpha($image, 100, 116, 139, 72);
        for ($i = 0; $i < 11; $i++) {
            imageline($image, random_int(-30, 80), random_int(0, $height), random_int(220, 340), random_int(0, $height), $i % 2 ? $violet : $slate);
        }
        for ($i = 0; $i < 90; $i++) {
            imagesetpixel($image, random_int(2, $width - 3), random_int(2, $height - 3), $i % 2 ? $violet : $slate);
        }

        $font = collect([
            'C:/Windows/Fonts/arialbd.ttf',
            'C:/Windows/Fonts/calibrib.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ])->first(fn (string $path) => is_file($path));
        $ink = [imagecolorallocate($image, 49, 46, 129), imagecolorallocate($image, 109, 40, 217)];

        foreach (str_split($code) as $index => $digit) {
            $x = 31 + ($index * 53) + random_int(-3, 3);
            $y = 66 + random_int(-5, 5);
            if ($font) {
                imagettftext($image, random_int(33, 40), random_int(-22, 22), $x, $y, $ink[$index % 2], $font, $digit);
            } else {
                imagestring($image, 5, $x + 8, $y - 28, $digit, $ink[$index % 2]);
            }
        }

        ob_start();
        imagepng($image, null, 7);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
