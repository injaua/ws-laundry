<?php
// Throwaway: white knockout variant of the transparent logo for dark surfaces.
$src = __DIR__ . '/../public/images/logo.png';
$out = __DIR__ . '/../public/images/logo-light.png';

$img = imagecreatefrompng($src);
$w = imagesx($img);
$h = imagesy($img);
imagealphablending($img, false);
imagesavealpha($img, true);

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $c = imagecolorat($img, $x, $y);
        $a = ($c >> 24) & 127;
        imagesetpixel($img, $x, $y, ($a << 24) | 0xFFFFFF);
    }
}

imagepng($img, $out, 9);
echo "written $out (" . filesize($out) . " bytes)\n";
