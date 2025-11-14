<?php
/**
 * ThirdPartyTrackingClient
 *
 * Provides a flexible way to fetch live GPS locations from external telematics
 * providers (e.g. Fleetsmart, Rewire Security GPSLive, UK Telematics / Radius)
 * using metadata stored in the rig_tracking_config table.
 */

class ThirdPartyTrackingClient
{
    /**
     * Fetch latest location from a third-party provider using configuration data.
     *
     * @param array $config Row from rig_tracking_config (joined with rigs table)
     * @return array|null Returns associative array with location fields or null if not found
     * @throws Exception When configuration is incomplete or provider call fails
     */
    public static function fetchLatestLocation(array $config): ?array
    {
        if (empty($config['tracking_provider'])) {
            throw new Exception('Tracking provider is not configured for this rig.');
        }

        if (empty($config['device_id'])) {
            throw new Exception('Device ID is required for third-party tracking.');
        }

        $provider = strtolower(trim($config['tracking_provider']));
        $payloadConfig = self::resolveProviderConfig($provider, $config);

        $url = self::buildRequestUrl($config, $payloadConfig);
        [$headers, $queryParams, $body] = self::buildRequestComponents($config, $payloadConfig);

        $response = self::executeHttpRequest(
            $url,
            $payloadConfig['http_method'] ?? 'GET',
            $headers,
            $queryParams,
            $body,
            $payloadConfig['timeout'] ?? 10
        );

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new Exception('Provider response is not valid JSON.');
        }

        $location = self::extractLocationFromResponse($decoded, $payloadConfig);

        if (!$location) {
            throw new Exception('Unable to extract location from provider response.');
        }

        return $location;
    }

    /**
     * Resolve provider configuration by merging DB config payload with defaults.
     */
    private static function resolveProviderConfig(string $provider, array $config): array
    {
        $payload = [];
        if (!empty($config['config_payload'])) {
            $payload = json_decode($config['config_payload'], true);
            if (!is_array($payload)) {
                throw new Exception('Invalid config payload JSON for provider integration.');
            }
        }

        $defaults = self::defaultConfigForProvider($provider);

        return array_replace_recursive($defaults, $payload);
    }

    /**
     * Default configuration templates for supported providers.
     */
    private static function defaultConfigForProvider(string $provider): array
    {
        switch ($provider) {
            case 'fleetsmart':
                return [
                    'http_method' => 'GET',
                    'location_endpoint' => '/api/v1/assets/{{device_id}}/latest-location',
                    'lat_path' => 'data.location.latitude',
                    'lng_path' => 'data.location.longitude',
                    'speed_path' => 'data.telemetry.speed',
                    'heading_path' => 'data.telemetry.heading',
                    'accuracy_path' => 'data.location.accuracy',
                    'timestamp_path' => 'data.timestamp',
                    'auth_header' => 'Authorization: Bearer {{api_key}}',
                ];

            case 'rewire_security':
            case 'gpslive':
                return [
                    'http_method' => 'GET',
                    'location_endpoint' => '/api/v1/devices/{{device_id}}/location',
                    'lat_path' => 'data.latitude',
                    'lng_path' => 'data.longitude',
                    'speed_path' => 'data.speed',
                    'heading_path' => 'data.heading',
                    'accuracy_path' => 'data.accuracy',
                    'timestamp_path' => 'data.timestamp',
                    'query' => [
                        'key' => '{{api_key}}',
                    ],
                ];

            case 'uk_telematics':
            case 'radius':
                return [
                    'http_method' => 'GET',
                    'location_endpoint' => '/telematics/v2/assets/{{device_id}}/position',
                    'lat_path' => 'position.lat',
                    'lng_path' => 'position.lng',
                    'speed_path' => 'position.speed',
                    'heading_path' => 'position.heading',
                    'timestamp_path' => 'position.timestamp',
                    'auth_header' => 'X-API-Key: {{api_key}}',
                ];

            default:
                return [
                    'http_method' => 'GET',
                    'location_endpoint' => '/devices/{{device_id}}/location',
                    'lat_path' => 'latitude',
                    'lng_path' => 'longitude',
                    'speed_path' => 'speed',
                    'heading_path' => 'heading',
                    'timestamp_path' => 'timestamp',
                ];
        }
    }

    /**
     * Build full request URL including base URL and endpoint template.
     */
    private static function buildRequestUrl(array $config, array $payloadConfig): string
    {
        $baseUrl = rtrim($config['api_base_url'] ?? '', '/');
        if (empty($baseUrl)) {
            throw new Exception('Provider API base URL is not configured.');
        }

        $endpoint = $payloadConfig['location_endpoint'] ?? '';
        if (empty($endpoint)) {
            throw new Exception('Provider endpoint template is missing.');
        }

        $resolvedEndpoint = self::replaceTemplateVariables($endpoint, $config, $payloadConfig);

        if (strpos($resolvedEndpoint, 'http://') === 0 || strpos($resolvedEndpoint, 'https://') === 0) {
            return $resolvedEndpoint;
        }

        return $baseUrl . $resolvedEndpoint;
    }

    /**
     * Build request headers, query params and body payload.
     *
     * @return array{0: array, 1: array, 2: string|null}
     */
    private static function buildRequestComponents(array $config, array $payloadConfig): array
    {
        $headers = [];
        $queryParams = [];
        $body = null;

        // Authentication header based on auth_method or explicit config
        $authHeader = $payloadConfig['auth_header'] ?? null;
        if (!$authHeader) {
            $authHeader = self::buildAuthHeaderFromMethod($config);
        }

        if ($authHeader) {
            $resolvedHeader = self::replaceTemplateVariables($authHeader, $config, $payloadConfig);
            $headers[] = $resolvedHeader;
        }

        // Additional headers from config payload
        if (!empty($payloadConfig['headers']) && is_array($payloadConfig['headers'])) {
            foreach ($payloadConfig['headers'] as $header) {
                $headers[] = self::replaceTemplateVariables($header, $config, $payloadConfig);
            }
        }

        // Query parameters
        if (!empty($payloadConfig['query']) && is_array($payloadConfig['query'])) {
            foreach ($payloadConfig['query'] as $key => $value) {
                $queryParams[$key] = self::replaceTemplateVariables($value, $config, $payloadConfig);
            }
        }

        // Request body (for POST/PUT providers)
        if (!empty($payloadConfig['body'])) {
            $bodyTemplate = $payloadConfig['body'];
            if (is_array($bodyTemplate)) {
                $bodyResolved = self::replaceTemplateVariablesRecursively($bodyTemplate, $config, $payloadConfig);
                $body = json_encode($bodyResolved);
                $headers[] = 'Content-Type: application/json';
            } else {
                $body = self::replaceTemplateVariables($bodyTemplate, $config, $payloadConfig);
            }
        }

        return [$headers, $queryParams, $body];
    }

    /**
     * Build auth header from auth_method column if no explicit header defined.
     */
    private static function buildAuthHeaderFromMethod(array $config): ?string
    {
        $method = $config['auth_method'] ?? 'bearer_token';
        $apiKey = $config['api_key'] ?? '';
        $apiSecret = $config['api_secret'] ?? '';

        switch ($method) {
            case 'bearer_token':
                if (!$apiKey) {
                    throw new Exception('Bearer token authentication requires api_key.');
                }
                return 'Authorization: Bearer {{api_key}}';

            case 'api_key_header':
                if (!$apiKey) {
                    throw new Exception('API key header authentication requires api_key.');
                }
                return 'X-API-Key: {{api_key}}';

            case 'basic_auth':
                if (!$apiKey || !$apiSecret) {
                    throw new Exception('Basic auth requires api_key (username) and api_secret (password).');
                }
                $encoded = base64_encode($apiKey . ':' . $apiSecret);
                return 'Authorization: Basic ' . $encoded;

            case 'none':
            case 'query_param':
                return null;

            default:
                return null;
        }
    }

    /**
     * Execute HTTP request using cURL.
     */
    private static function executeHttpRequest(
        string $url,
        string $method,
        array $headers,
        array $queryParams,
        ?string $body,
        int $timeout
    ): string {
        $ch = curl_init();

        if (!empty($queryParams)) {
            $queryString = http_build_query($queryParams);
            $url .= (strpos($url, '?') === false ? '?' : '&') . $queryString;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $upperMethod = strtoupper($method);
        if ($upperMethod === 'POST' || $upperMethod === 'PUT' || $upperMethod === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upperMethod);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        } elseif ($upperMethod !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upperMethod);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("HTTP request failed: {$error}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("Provider API returned HTTP {$httpCode}");
        }

        return $response;
    }

    /**
     * Extract location data from JSON response using dot-notation paths.
     */
    private static function extractLocationFromResponse(array $data, array $payloadConfig): ?array
    {
        $latitude = self::getValueByPath($data, $payloadConfig['lat_path'] ?? '');
        $longitude = self::getValueByPath($data, $payloadConfig['lng_path'] ?? '');

        if ($latitude === null || $longitude === null) {
            return null;
        }

        $location = [
            'latitude' => floatval($latitude),
            'longitude' => floatval($longitude),
        ];

        $optionalFields = [
            'accuracy' => 'accuracy_path',
            'speed' => 'speed_path',
            'heading' => 'heading_path',
            'altitude' => 'altitude_path',
            'recorded_at' => 'timestamp_path',
        ];

        foreach ($optionalFields as $field => $pathKey) {
            if (!empty($payloadConfig[$pathKey])) {
                $value = self::getValueByPath($data, $payloadConfig[$pathKey]);
                if ($value !== null) {
                    $location[$field] = $value;
                }
            }
        }

        return $location;
    }

    /**
     * Retrieve nested value from array using dot-notation path.
     */
    private static function getValueByPath(array $data, string $path)
    {
        if ($path === '') {
            return null;
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Replace template placeholders with config values.
     */
    private static function replaceTemplateVariables(string $template, array $config, array $payloadConfig): string
    {
        $replacements = [
            '{{device_id}}' => $config['device_id'] ?? '',
            '{{api_key}}' => $config['api_key'] ?? '',
            '{{api_secret}}' => $config['api_secret'] ?? '',
        ];

        if (!empty($payloadConfig['variables']) && is_array($payloadConfig['variables'])) {
            foreach ($payloadConfig['variables'] as $key => $value) {
                $replacements['{{' . $key . '}}'] = $value;
            }
        }

        return strtr($template, $replacements);
    }

    /**
     * Recursively replace placeholders in arrays.
     */
    private static function replaceTemplateVariablesRecursively($data, array $config, array $payloadConfig)
    {
        if (is_array($data)) {
            $resolved = [];
            foreach ($data as $key => $value) {
                $resolved[$key] = self::replaceTemplateVariablesRecursively($value, $config, $payloadConfig);
            }
            return $resolved;
        }

        if (is_string($data)) {
            return self::replaceTemplateVariables($data, $config, $payloadConfig);
        }

        return $data;
    }
}

