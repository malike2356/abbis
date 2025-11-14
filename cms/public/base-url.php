<?php
/**
 * Base URL Helper for CMS Public Pages
 * Include this file to get $baseUrl variable
 */

require_once __DIR__ . '/../../config/environment.php';

if (!isset($baseUrl)) {
    $baseUrl = app_base_path();
}

