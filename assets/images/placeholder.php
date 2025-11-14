<?php
/**
 * Placeholder Image Generator
 * Generates a placeholder image on the fly
 */
header('Content-Type: image/jpeg');
$width = isset($_GET['w']) ? max(1, min(2000, (int)$_GET['w'])) : 300;
$height = isset($_GET['h']) ? max(1, min(2000, (int)$_GET['h'])) : 300;

$img = imagecreatetruecolor($width, $height);
$bg = imagecolorallocate($img, 241, 245, 249);
$textColor = imagecolorallocate($img, 203, 213, 225);
$borderColor = imagecolorallocate($img, 226, 232, 240);

// Fill background
imagefill($img, 0, 0, $bg);

// Draw border
imagerectangle($img, 0, 0, $width - 1, $height - 1, $borderColor);

// Add text
$text = 'No Image';
$font = 5;
$textWidth = imagefontwidth($font) * strlen($text);
$textHeight = imagefontheight($font);
$x = ($width - $textWidth) / 2;
$y = ($height - $textHeight) / 2;
imagestring($img, $font, $x, $y, $text, $textColor);

imagejpeg($img, null, 90);
imagedestroy($img);
exit;

