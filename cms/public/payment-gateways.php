<?php
/**
 * Payment Gateway Integration Functions
 * Handles Paystack, Flutterwave, and other payment gateways
 */

/**
 * Get payment gateway keys from config or settings
 */
function getPaymentGatewayKeys($provider, $pdo = null) {
    $keys = ['public_key' => '', 'secret_key' => '', 'test_mode' => false];
    
    // First try to get from payment method config
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT config FROM cms_payment_methods WHERE provider=? LIMIT 1");
            $stmt->execute([$provider]);
            $method = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($method && !empty($method['config'])) {
                $config = json_decode($method['config'], true);
                if (is_array($config)) {
                    $keys['public_key'] = $config['public_key'] ?? '';
                    $keys['secret_key'] = $config['secret_key'] ?? '';
                    $keys['test_mode'] = ($config['test_mode'] ?? false) === true;
                }
            }
        } catch (PDOException $e) {
            // Table might not exist, continue to fallback
        }
        
        // Fallback to settings if keys not found
        if (empty($keys['public_key']) || empty($keys['secret_key'])) {
            try {
                $settings = [];
                $providerPrefix = $provider . '_';
                $settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM cms_settings WHERE setting_key LIKE ? OR setting_key = 'payment_test_mode'");
                $settingsStmt->execute([$providerPrefix . '%']);
                while ($row = $settingsStmt->fetch()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                
                $keys['public_key'] = $keys['public_key'] ?: ($settings[$provider . '_public_key'] ?? '');
                $keys['secret_key'] = $keys['secret_key'] ?: ($settings[$provider . '_secret_key'] ?? '');
                $keys['test_mode'] = ($settings['payment_test_mode'] ?? '0') === '1';
            } catch (PDOException $e) {
                // Settings table might not exist, use defaults
            }
        }
    }
    
    return $keys;
}

/**
 * Initialize Paystack payment
 */
function initPaystackPayment($order, $paymentMethod, $baseUrl, $pdo = null) {
    $config = json_decode($paymentMethod['config'] ?? '{}', true);
    $publicKey = $config['public_key'] ?? '';
    
    // Fallback to settings if not in config
    if (empty($publicKey) && $pdo) {
        $keys = getPaymentGatewayKeys('paystack', $pdo);
        $publicKey = $keys['public_key'];
    }
    
    if (empty($publicKey)) {
        return ['error' => 'Paystack public key not configured. Please configure it in Settings → Payment.'];
    }
    
    // Generate reference
    $reference = 'PSK-' . $order['order_number'] . '-' . time();
    
    // Calculate amount in kobo (Paystack uses smallest currency unit)
    $amount = intval($order['total_amount'] * 100); // Convert to kobo
    
    // Store reference in session for verification
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['paystack_reference'] = $reference;
    $_SESSION['paystack_order_id'] = $order['id'];
    
    return [
        'gateway' => 'paystack',
        'public_key' => $publicKey,
        'reference' => $reference,
        'amount' => $amount,
        'email' => $order['customer_email'],
        'callback_url' => $baseUrl . '/cms/payment/callback?gateway=paystack&order=' . urlencode($order['order_number'])
    ];
}

/**
 * Initialize Flutterwave payment
 */
function initFlutterwavePayment($order, $paymentMethod, $baseUrl, $pdo = null) {
    $config = json_decode($paymentMethod['config'] ?? '{}', true);
    $publicKey = $config['public_key'] ?? '';
    
    // Fallback to settings if not in config
    if (empty($publicKey) && $pdo) {
        $keys = getPaymentGatewayKeys('flutterwave', $pdo);
        $publicKey = $keys['public_key'];
    }
    
    if (empty($publicKey)) {
        return ['error' => 'Flutterwave public key not configured. Please configure it in Settings → Payment.'];
    }
    
    // Generate transaction reference
    $txRef = 'FLW-' . $order['order_number'] . '-' . time();
    
    // Calculate amount (Flutterwave uses major currency unit)
    $amount = floatval($order['total_amount']);
    
    // Store reference in session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flutterwave_tx_ref'] = $txRef;
    $_SESSION['flutterwave_order_id'] = $order['id'];
    
    return [
        'gateway' => 'flutterwave',
        'public_key' => $publicKey,
        'tx_ref' => $txRef,
        'amount' => $amount,
        'currency' => 'GHS',
        'email' => $order['customer_email'],
        'customer' => [
            'email' => $order['customer_email'],
            'name' => $order['customer_name'],
            'phone_number' => $order['customer_phone'] ?? ''
        ],
        'redirect_url' => $baseUrl . '/cms/payment/callback?gateway=flutterwave&order=' . urlencode($order['order_number'])
    ];
}

/**
 * Verify Paystack payment
 * @param string $reference Transaction reference
 * @param string|null $secretKey Secret key (if null, will try to get from config/settings)
 * @param object|null $pdo Database connection (optional, for getting keys)
 */
function verifyPaystackPayment($reference, $secretKey = null, $pdo = null) {
    // Get secret key if not provided
    if (empty($secretKey) && $pdo) {
        $keys = getPaymentGatewayKeys('paystack', $pdo);
        $secretKey = $keys['secret_key'];
    }
    
    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'Paystack secret key not configured'];
    }
    $url = 'https://api.paystack.co/transaction/verify/' . $reference;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretKey
    ]);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ['success' => false, 'error' => $err];
    }
    
    $result = json_decode($response, true);
    
    if ($result && $result['status'] === true && $result['data']['status'] === 'success') {
        return [
            'success' => true,
            'transaction_id' => $result['data']['reference'],
            'amount' => $result['data']['amount'] / 100, // Convert from kobo
            'data' => $result['data']
        ];
    }
    
    return ['success' => false, 'error' => 'Payment verification failed'];
}

/**
 * Verify Flutterwave payment
 * @param string $txRef Transaction reference
 * @param string|null $secretKey Secret key (if null, will try to get from config/settings)
 * @param object|null $pdo Database connection (optional, for getting keys)
 */
function verifyFlutterwavePayment($txRef, $secretKey = null, $pdo = null) {
    // Get secret key if not provided
    if (empty($secretKey) && $pdo) {
        $keys = getPaymentGatewayKeys('flutterwave', $pdo);
        $secretKey = $keys['secret_key'];
    }
    
    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'Flutterwave secret key not configured'];
    }
    $url = 'https://api.flutterwave.com/v3/transactions/' . $txRef . '/verify';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ['success' => false, 'error' => $err];
    }
    
    $result = json_decode($response, true);
    
    if ($result && $result['status'] === 'success' && $result['data']['status'] === 'successful') {
        return [
            'success' => true,
            'transaction_id' => $result['data']['tx_ref'],
            'amount' => $result['data']['amount'],
            'data' => $result['data']
        ];
    }
    
    return ['success' => false, 'error' => 'Payment verification failed'];
}

