<?php

// Throwaway: white-background matte extraction of logo-source.jpg -> transparent logo.png
$src = __DIR__ . '/../public/images/logo-source.jpg';
$out = __DIR__ . '/../public/images/logo.png';

$img = imagecreatefromjpeg($src);
$w = imagesx($img);
$h = imagesy($img);

imagealphablending($img, false);
imagesavealpha($img, true);

$floor = 245; // min(r,g,b) at/above this -> fully transparent
$ramp = 65;   // pixels become fully opaque at floor - ramp

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 255;
        $g = ($rgb >> 8) & 255;
        $b = $rgb & 255;
        $m = min($r, $g, $b);
        if ($m >= $floor) {
            imagesetpixel($img, $x, $y, 0x7F000000);

            continue;
        }
        $a = (($floor - $m) / $ramp);
        if ($a > 1) {
            $a = 1;
        }
        // unpremultiply against white backdrop
        $nr = (int) round(max(0, min(255, ($r - (1 - $a) * 255) / $a)));
        $ng = (int) round(max(0, min(255, ($g - (1 - $a) * 255) / $a)));
        $nb = (int) round(max(0, min(255, ($b - (1 - $a) * 255) / $a)));
        $alpha = (int) round(127 * (1 - $a));
        imagesetpixel($img, $x, $y, ($alpha << 24) | ($nr << 16) | ($ng << 8) | $nb);
    }
}

imagepng($img, $out, 9);
echo "written $out (" . filesize($out) . " bytes)\n";
