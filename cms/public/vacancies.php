<?php
require_once __DIR__ . '/base-url.php';
$target = rtrim($baseUrl, '/') . '/cms/vacancies';
header('Location: ' . $target);
exit;

