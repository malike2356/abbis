<?php

declare(strict_types=1);

/**
 * Tracks user navigation to power dynamic quick actions.
 */
class NavigationTracker
{
    private const TABLE = 'user_navigation_stats';

    /** @var bool */
    private static $tableInitialized = false;

    /** @var array<string, array{slug:string,label:string,url:string,icon:string,weight:int}> */
    private static $catalog = [
        'dashboard.php' => ['slug' => 'dashboard.php', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => '🏠', 'weight' => 10],
        'field-reports.php' => ['slug' => 'field-reports.php', 'label' => 'New Report', 'url' => 'field-reports.php', 'icon' => '📝', 'weight' => 100],
        'field-reports-list.php' => ['slug' => 'field-reports-list.php', 'label' => 'Reports Register', 'url' => 'field-reports-list.php', 'icon' => '📄', 'weight' => 60],
        'crm.php:dashboard' => ['slug' => 'crm.php:dashboard', 'label' => 'CRM Dashboard', 'url' => 'crm.php?action=dashboard', 'icon' => '📊', 'weight' => 70],
        'crm.php:clients' => ['slug' => 'crm.php:clients', 'label' => 'Clients', 'url' => 'crm.php?action=clients', 'icon' => '👥', 'weight' => 95],
        'crm.php:quote-requests' => ['slug' => 'crm.php:quote-requests', 'label' => 'Quote Requests', 'url' => 'crm.php?action=quote-requests', 'icon' => '📋', 'weight' => 80],
        'crm.php:rig-requests' => ['slug' => 'crm.php:rig-requests', 'label' => 'Rig Requests', 'url' => 'crm.php?action=rig-requests', 'icon' => '🚛', 'weight' => 75],
        'hr.php' => ['slug' => 'hr.php', 'label' => 'Human Resources', 'url' => 'hr.php', 'icon' => '🧑‍💼', 'weight' => 40],
        'recruitment.php' => ['slug' => 'recruitment.php', 'label' => 'Recruitment', 'url' => 'recruitment.php', 'icon' => '📨', 'weight' => 65],
        'resources.php:materials' => ['slug' => 'resources.php:materials', 'label' => 'Materials', 'url' => 'resources.php?action=materials', 'icon' => '📦', 'weight' => 90],
        'resources.php:catalog' => ['slug' => 'resources.php:catalog', 'label' => 'Product Catalog', 'url' => 'resources.php?action=catalog', 'icon' => '🗂️', 'weight' => 55],
        'financial.php' => ['slug' => 'financial.php', 'label' => 'Financial Hub', 'url' => 'financial.php', 'icon' => '💰', 'weight' => 85],
        'finance.php' => ['slug' => 'finance.php', 'label' => 'Accounting', 'url' => 'finance.php', 'icon' => '📘', 'weight' => 45],
        'payroll.php' => ['slug' => 'payroll.php', 'label' => 'Payroll', 'url' => 'payroll.php', 'icon' => '💵', 'weight' => 88],
        'loans.php' => ['slug' => 'loans.php', 'label' => 'Loans', 'url' => 'loans.php', 'icon' => '💳', 'weight' => 50],
        'analytics.php' => ['slug' => 'analytics.php', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => '📊', 'weight' => 92],
        'complaints.php' => ['slug' => 'complaints.php', 'label' => 'Complaints', 'url' => 'complaints.php', 'icon' => '⚠️', 'weight' => 48],
        'ai-governance.php' => ['slug' => 'ai-governance.php', 'label' => 'AI Governance', 'url' => 'ai-governance.php', 'icon' => '🤖', 'weight' => 30],
        'system-settings.php' => ['slug' => 'system-settings.php', 'label' => 'System Settings', 'url' => 'system-settings.php', 'icon' => '⚙️', 'weight' => 20],
    ];

    /** @var string[] */
    private static $defaultOrder = [
        'field-reports.php',
        'crm.php:clients',
        'crm.php:quote-requests',
        'crm.php:rig-requests',
        'resources.php:materials',
        'financial.php',
        'payroll.php',
        'analytics.php',
        'recruitment.php',
    ];

    public static function recordCurrentPage(?int $userId = null): void
    {
        if (!$userId) {
            return;
        }

        $slug = self::resolveSlug();
        if (!$slug || !isset(self::$catalog[$slug])) {
            return;
        }

        try {
            $pdo = getDBConnection();
            self::ensureTable($pdo);
            $meta = self::$catalog[$slug];

            $stmt = $pdo->prepare("
                INSERT INTO " . self::TABLE . " (user_id, slug, url, label, icon, visit_count, last_visited)
                VALUES (:user_id, :slug, :url, :label, :icon, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    visit_count = visit_count + 1,
                    last_visited = NOW(),
                    url = VALUES(url),
                    label = VALUES(label),
                    icon = VALUES(icon)
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':slug' => $meta['slug'],
                ':url' => $meta['url'],
                ':label' => $meta['label'],
                ':icon' => $meta['icon'],
            ]);
        } catch (Throwable $e) {
            error_log('[NavigationTracker] record failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, array{slug:string,label:string,url:string,icon:string}>
     */
    public static function getTopQuickActions(?int $userId, int $limit = 8): array
    {
        $limit = max(1, min(12, $limit));
        $results = [];
        $used = [];

        if ($userId) {
            try {
                $pdo = getDBConnection();
                self::ensureTable($pdo);
                $stmt = $pdo->prepare("
                    SELECT slug
                    FROM " . self::TABLE . "
                    WHERE user_id = :user_id
                    ORDER BY visit_count DESC, last_visited DESC
                    LIMIT :limit
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($rows as $slug) {
                    if (isset(self::$catalog[$slug])) {
                        $results[] = self::$catalog[$slug];
                        $used[$slug] = true;
                    }
                }
            } catch (Throwable $e) {
                error_log('[NavigationTracker] fetch failed: ' . $e->getMessage());
            }
        }

        if (count($results) < $limit) {
            $fallbacks = self::getFallbackOrder();
            foreach ($fallbacks as $slug) {
                if (isset($used[$slug]) || !isset(self::$catalog[$slug])) {
                    continue;
                }
                $results[] = self::$catalog[$slug];
                $used[$slug] = true;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * @return array<string, array{slug:string,label:string,url:string,icon:string,weight:int}>
     */
    public static function getCatalog(): array
    {
        return self::$catalog;
    }

    private static function resolveSlug(): ?string
    {
        $uriPath = $_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? '';
        $path = parse_url($uriPath, PHP_URL_PATH) ?: '';
        $script = basename($path);

        if ($script === '') {
            return null;
        }

        $action = $_GET['action'] ?? '';
        if ($action && isset(self::$catalog["{$script}:{$action}"])) {
            return "{$script}:{$action}";
        }

        $tab = $_GET['tab'] ?? '';
        if ($tab && isset(self::$catalog["{$script}:{$tab}"])) {
            return "{$script}:{$tab}";
        }

        return isset(self::$catalog[$script]) ? $script : null;
    }

    /**
     * @return string[]
     */
    private static function getFallbackOrder(): array
    {
        $order = self::$defaultOrder;

        usort($order, function (string $a, string $b): int {
            $weightA = self::$catalog[$a]['weight'] ?? 0;
            $weightB = self::$catalog[$b]['weight'] ?? 0;
            return $weightB <=> $weightA;
        });

        return $order;
    }

    private static function ensureTable(PDO $pdo): void
    {
        if (self::$tableInitialized) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
                user_id INT NOT NULL,
                slug VARCHAR(190) NOT NULL,
                url VARCHAR(255) NOT NULL,
                label VARCHAR(255) NOT NULL,
                icon VARCHAR(32) DEFAULT NULL,
                visit_count INT UNSIGNED NOT NULL DEFAULT 0,
                last_visited DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, slug),
                KEY idx_user_last (user_id, last_visited)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$tableInitialized = true;
    }
}


