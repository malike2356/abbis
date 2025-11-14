<?php

require_once __DIR__ . '/../ContextBuilderInterface.php';
require_once __DIR__ . '/../ContextSlice.php';

class PageContextBuilder implements ContextBuilderInterface
{
    public function getKey(): string
    {
        return 'page';
    }

    public function supports(array $options): bool
    {
        // Always available - provides page context
        return true;
    }

    public function build(array $options): array
    {
        $pageInfo = $this->detectPage();
        
        if (empty($pageInfo)) {
            return [];
        }

        return [
            new ContextSlice(
                'current_page',
                $pageInfo,
                priority: 15, // High priority - user is asking about this page
                approxTokens: 150
            ),
        ];
    }

    private function detectPage(): array
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $phpSelf = $_SERVER['PHP_SELF'] ?? '';
        
        // Extract page path
        $pagePath = $this->extractPagePath($requestUri, $scriptName, $phpSelf);
        
        // Map common pages to descriptions
        $pageDescriptions = $this->getPageDescriptions();
        
        $pageInfo = [
            'path' => $pagePath,
            'url' => $requestUri,
        ];

        // Match against known pages
        foreach ($pageDescriptions as $pattern => $description) {
            if ($this->matchesPattern($pagePath, $pattern)) {
                $pageInfo['name'] = $description['name'];
                $pageInfo['description'] = $description['description'];
                $pageInfo['purpose'] = $description['purpose'] ?? '';
                $pageInfo['key_features'] = $description['key_features'] ?? [];
                return $pageInfo;
            }
        }

        // Fallback: extract page name from path
        $pageInfo['name'] = $this->extractPageName($pagePath);
        return $pageInfo;
    }

    private function extractPagePath(string $requestUri, string $scriptName, string $phpSelf): string
    {
        // Remove query string
        $path = strtok($requestUri, '?');
        
        // Remove base path
        $basePath = dirname($scriptName);
        if ($basePath !== '/' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
        
        // Normalize
        $path = trim($path, '/');
        
        // If empty, try PHP_SELF
        if (empty($path)) {
            $path = trim($phpSelf, '/');
        }
        
        return $path;
    }

    private function extractPageName(string $path): string
    {
        $parts = explode('/', $path);
        $filename = end($parts);
        $name = str_replace(['.php', '-', '_'], ['', ' ', ' '], $filename);
        return ucwords($name);
    }

    private function matchesPattern(string $path, string $pattern): bool
    {
        // Simple pattern matching
        if (strpos($path, $pattern) !== false) {
            return true;
        }
        
        // Check if pattern is a regex
        if (preg_match('/^\/.+\/$/', $pattern)) {
            return preg_match($pattern, $path) === 1;
        }
        
        return false;
    }

    private function getPageDescriptions(): array
    {
        return [
            'ai-governance' => [
                'name' => 'AI Governance & Audit',
                'description' => 'Manage AI providers, usage limits, and monitor AI activity across ABBIS',
                'purpose' => 'Configure and monitor AI services (OpenAI, DeepSeek, Gemini, Ollama), set spending limits, view usage logs, and manage AI provider priorities',
                'key_features' => [
                    'Configure AI providers (OpenAI, DeepSeek, Gemini, Ollama)',
                    'Set daily and monthly usage limits per provider',
                    'Configure provider priority (failover order)',
                    'View AI usage logs and audit trails',
                    'Manage encryption keys for API key storage',
                    'Monitor provider status and performance',
                ],
            ],
            'dashboard' => [
                'name' => 'Dashboard',
                'description' => 'Overview of key performance indicators and recent activity',
                'purpose' => 'View financial metrics, operational statistics, top clients, top rigs, and recent field reports',
                'key_features' => [
                    'Financial KPIs (income, expenses, profit)',
                    'Top performing clients and rigs',
                    'Recent field reports',
                    'Operational metrics',
                    'Quick actions',
                ],
            ],
            'field-reports' => [
                'name' => 'Field Reports',
                'description' => 'Create and manage field operation reports',
                'purpose' => 'Record drilling operations, track expenses, calculate profits, and manage job details',
            ],
            'crm' => [
                'name' => 'CRM',
                'description' => 'Customer Relationship Management',
                'purpose' => 'Manage clients, contacts, follow-ups, and customer interactions',
            ],
            'resources' => [
                'name' => 'Resources',
                'description' => 'Manage materials inventory and resources',
                'purpose' => 'Track materials, inventory levels, and resource allocation',
            ],
            'finance' => [
                'name' => 'Finance',
                'description' => 'Financial management and reporting',
                'purpose' => 'View financial reports, manage transactions, and track financial health',
            ],
            'analytics' => [
                'name' => 'Analytics',
                'description' => 'Advanced analytics and reporting',
                'purpose' => 'Analyze trends, generate reports, and view detailed metrics',
            ],
        ];
    }
}

