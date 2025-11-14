<?php
/**
 * Generate water droplets favicon
 */
$size = 32;
$img = imagecreatetruecolor($size, $size);

// Enable alpha blending
imagealphablending($img, false);
imagesavealpha($img, true);

// Create transparent background
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $transparent);

// Colors
$primaryBlue = imagecolorallocate($img, 14, 165, 233);   // Primary blue
$lightBlue = imagecolorallocate($img, 125, 211, 252);    // Light blue
$white = imagecolorallocatealpha($img, 255, 255, 255, 80);

// Draw main water drop
imagefilledellipse($img, $size/2, $size/2 - 1, 24, 30, $primaryBlue);

// Add smaller droplets
imagefilledellipse($img, $size/2 - 6, $size/2 + 8, 8, 10, $lightBlue);
imagefilledellipse($img, $size/2 + 6, $size/2 + 10, 6, 8, $lightBlue);

// Add highlight
imagefilledellipse($img, $size/2 - 4, $size/2 - 6, 6, 6, $white);

// Save to file
imagepng($img, __DIR__ . '/../assets/images/favicon.png', 9);
imagedestroy($img);

echo "Water droplets favicon generated successfully";
?>

