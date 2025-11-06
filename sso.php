<?php
/**
 * SSO Endpoint - Handles Single Sign-On from CMS to ABBIS
 */
require_once 'config/app.php';
require_once 'includes/sso.php';
require_once 'includes/functions.php';

$sso = new SSO();
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: login.php?error=sso_invalid');
    exit;
}

$result = $sso->verifySSOToken($token);

if ($result['success']) {
    // Successful SSO login - redirect to dashboard
    header('Location: modules/dashboard.php');
    exit;
} else {
    // SSO failed - redirect to login with error
    header('Location: login.php?error=' . urlencode($result['message']));
    exit;
}

