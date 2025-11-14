<?php
/**
 * Global Font System - Consistent Sans-Serif Font Stack
 * This file defines the consistent font family used system-wide
 */

// Standard sans-serif font stack (system fonts, no external loading required)
define('CMS_FONT_FAMILY', "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif");

// Font stack with Inter (if you want to use Inter, uncomment and add Google Fonts link)
// define('CMS_FONT_FAMILY', "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif");

// Function to get font family CSS
function getCMSFontFamily() {
    return CMS_FONT_FAMILY;
}

// Function to output font family CSS variable
function outputCMSFontCSS() {
    echo ":root { --cms-font-family: " . CMS_FONT_FAMILY . "; }\n";
    echo "* { font-family: " . CMS_FONT_FAMILY . "; }\n";
}
?>

