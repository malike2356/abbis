<?php
/**
 * Tab Navigation Helper
 * Renders consistent tab navigation across all modules
 */

/**
 * Render tab navigation
 * 
 * @param array $tabs Array of tabs: ['key' => 'Label', ...]
 * @param string $currentAction Current active tab key
 * @param string $baseUrl Base URL for tab links (can include query params)
 * @param string $paramName URL parameter name for action (default: 'action')
 * @return string HTML for tab navigation
 */
function renderTabNavigation($tabs, $currentAction, $baseUrl = '', $paramName = 'action') {
    if (empty($tabs)) {
        return '';
    }
    
    // Ensure baseUrl doesn't have trailing slash
    $baseUrl = rtrim($baseUrl, '/');
    if (empty($baseUrl)) {
        $baseUrl = $_SERVER['PHP_SELF'];
    }
    
    // Parse existing query string
    $queryParams = $_GET;
    unset($queryParams[$paramName]); // Remove action param to rebuild cleanly
    
    $html = '<div class="config-tabs" style="margin-bottom: 30px;">';
    $html .= '<div class="tabs">';
    
    foreach ($tabs as $key => $label) {
        $isActive = ($key === $currentAction);
        $activeClass = $isActive ? 'active' : '';
        
        // Build URL
        $url = $baseUrl;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query(array_merge($queryParams, [$paramName => $key]));
        } else {
            // Always include action parameter for clarity, even for dashboard
            $url .= '?' . $paramName . '=' . urlencode($key);
        }
        
        // Handle labels with emoji (check if label is array or string)
        $displayLabel = is_array($label) ? ($label['emoji'] ?? '') . ' ' . ($label['text'] ?? $key) : $label;
        
        $html .= sprintf(
            '<button type="button" class="tab %s" onclick="window.location.href=\'%s\'">',
            $activeClass,
            htmlspecialchars($url)
        );
        $html .= '<span>' . htmlspecialchars($displayLabel) . '</span>';
        $html .= '</button>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Get current action from request
 * 
 * @param string $default Default action if not specified
 * @param string $paramName Parameter name (default: 'action')
 * @return string Current action
 */
function getCurrentAction($default = 'dashboard', $paramName = 'action') {
    return $_GET[$paramName] ?? $default;
}
