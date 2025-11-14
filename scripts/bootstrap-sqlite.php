<?php
putenv('DB_CONNECTION=sqlite');
putenv('USE_SQLITE=1');
putenv('MAIL_DRIVER=log');

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "PDO SQLite driver is not available in this environment.\n");
    exit(1);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/request-response-manager.php';

createDatabaseIfNotExists();
$pdo = getDBConnection();

// Create supporting tables missing from ensureTables()
$pdo->exec("CREATE TABLE IF NOT EXISTS system_config (config_key TEXT PRIMARY KEY, config_value TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS cms_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS catalog_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS catalog_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    sku TEXT DEFAULT NULL,
    item_type TEXT DEFAULT 'service',
    category_id INTEGER DEFAULT NULL,
    unit TEXT DEFAULT NULL,
    cost_price REAL DEFAULT 0,
    sell_price REAL DEFAULT 0,
    sale_price REAL DEFAULT 0,
    taxable INTEGER DEFAULT 0,
    is_purchasable INTEGER DEFAULT 1,
    is_sellable INTEGER DEFAULT 1,
    is_active INTEGER DEFAULT 1,
    notes TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS cms_quote_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT DEFAULT NULL,
    location TEXT DEFAULT NULL,
    service_type TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status TEXT DEFAULT 'new',
    include_drilling INTEGER DEFAULT 0,
    include_construction INTEGER DEFAULT 0,
    include_mechanization INTEGER DEFAULT 0,
    include_yield_test INTEGER DEFAULT 0,
    include_chemical_test INTEGER DEFAULT 0,
    include_polytank_stand INTEGER DEFAULT 0,
    pump_preferences TEXT DEFAULT NULL,
    latitude REAL DEFAULT NULL,
    longitude REAL DEFAULT NULL,
    address TEXT DEFAULT NULL,
    estimated_budget REAL DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS rig_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_number TEXT NOT NULL,
    requester_name TEXT NOT NULL,
    requester_email TEXT NOT NULL,
    requester_phone TEXT DEFAULT NULL,
    requester_type TEXT DEFAULT 'contractor',
    company_name TEXT DEFAULT NULL,
    location_address TEXT DEFAULT NULL,
    latitude REAL DEFAULT NULL,
    longitude REAL DEFAULT NULL,
    region TEXT DEFAULT NULL,
    number_of_boreholes INTEGER DEFAULT 1,
    estimated_budget REAL DEFAULT NULL,
    preferred_start_date TEXT DEFAULT NULL,
    urgency TEXT DEFAULT 'medium',
    status TEXT DEFAULT 'new',
    notes TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
)");

// Seed baseline configuration
$pdo->prepare("INSERT OR REPLACE INTO system_config (config_key, config_value) VALUES ('company_name', ?)" )
    ->execute(['Kari Boreholes']);
$pdo->prepare("INSERT OR REPLACE INTO cms_settings (setting_key, setting_value) VALUES ('default_tax_rate', ?)" )
    ->execute(['0']);

// Seed catalog data
$catalogSeed = [
    ['Borehole Drilling Service', 'DRILL100', 'service', 'Drilling to desired depth including crew and rig operations.'],
    ['Borehole Construction & Casing', 'CONST200', 'service', 'Supply and installation of casing materials.'],
    ['Mechanization & Pump Installation', 'MECH300', 'service', 'Installation of pumps and electrical controls.'],
    ['Yield Test Package', 'YIELD400', 'service', 'Comprehensive pumping test services.'],
    ['Water Chemical Analysis', 'CHEM500', 'service', 'Laboratory grade chemical water analysis.'],
    ['Polytank Stand Construction', 'POLY600', 'service', 'Fabrication and installation of polytank stands.'],
    ['Rig Mobilisation & Demobilisation', 'RIG700', 'service', 'Transportation of drilling rigs to the site.'],
    ['Rig Daily Drilling Rate', 'RIG800', 'service', 'Daily drilling rate for deployed rigs.'],
    ['Support Logistics & Consumables', 'RIG900', 'service', 'Consumables and logistics support for drilling projects.'],
];
$insertItem = $pdo->prepare("INSERT INTO catalog_items (name, sku, item_type, sell_price, sale_price, taxable, notes) VALUES (?, ?, 'service', ?, ?, 0, ?)");
foreach ($catalogSeed as $seed) {
    [$name, $sku, $type, $notes] = $seed;
    $insertItem->execute([$name, $sku, 1000.00, 1000.00, $notes]);
}

// Seed quote request if none exists
$countQuote = (int)$pdo->query('SELECT COUNT(*) FROM cms_quote_requests')->fetchColumn();
if ($countQuote === 0) {
    $pdo->prepare("INSERT INTO cms_quote_requests (
        name, email, phone, location, service_type, description, status,
        include_drilling, include_construction, include_mechanization,
        include_yield_test, include_chemical_test, include_polytank_stand,
        pump_preferences, estimated_budget
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, ?, ?)")
    ->execute([
        'Alice Contractor', 'alice@example.com', '233201234567', 'Accra', 'full_borehole',
        'Full borehole drilling with construction and mechanization.', 'new',
        1, 1, 1, 1, 0, 1,
        json_encode([]), 75000
    ]);
}

// Seed rig request if none exists
$countRig = (int)$pdo->query('SELECT COUNT(*) FROM rig_requests')->fetchColumn();
if ($countRig === 0) {
    $pdo->prepare("INSERT INTO rig_requests (
        request_number, requester_name, requester_email, requester_phone,
        requester_type, company_name, location_address, number_of_boreholes,
        estimated_budget, urgency, status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([
        'RR-20241101-0001', 'Ben Supervisor', 'ben@example.com', '233208765432',
        'contractor', 'HydroWorks Ltd', 'Kumasi, Ashanti Region', 3,
        120000, 'high', 'new'
    ]);
}

// Ensure dependent infrastructure is created
new Email();
new RequestResponseManager($pdo);

echo "SQLite bootstrap complete.\n";
