<?php
/**
 * Import Real Field Report Data for GREEN RIG
 * Extracts and imports data from October 2025 field reports
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// CLI mode
if (php_sapi_name() === 'cli') {
    $_SESSION = ['user_id' => 1, 'role' => 'admin'];
}

$pdo = getDBConnection();

// Field report data extracted from images
$fieldReports = [
    // Report 1: 2025-10-04 - Owenase
    [
        'date' => '2025-10-04',
        'site_name' => 'Owenase',
        'agent' => 'Eric Adams',
        'agent_contact' => null,
        'client_name' => 'Owenase Client',
        'personnel' => array (
  0 => 'BOSS',
  1 => 'Kweku',
  2 => 'Rasta',
  3 => 'chief',
),
        'start_time' => '08:30',
        'finish_time' => '16:50',
        'duration_minutes' => 500.00,
        'start_rpm' => 285.80,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 13.00,
        'rod_length' => 4.50,
        'total_depth' => 60.00,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 16,
        'construction_depth' => 57.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 2355.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Salary', 'amount' => 1200.00],
            ['description' => 'Bonus', 'amount' => 40.00],
            ['description' => 'Jebanus', 'amount' => 100.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of $9,000 was given to Boss by agent',
    ],

    // Report 2: 2025-10-06 - Allwatia - Sadams
    [
        'date' => '2025-10-06',
        'site_name' => 'Allwatia - Sadams',
        'agent' => 'COSMOS',
        'agent_contact' => '0246215187',
        'client_name' => 'COSMOS Client',
        'personnel' => array (
  0 => 'BOSS',
  1 => 'Kweku',
  2 => 'Rasta',
  3 => 'Chief',
),
        'start_time' => '07:30',
        'finish_time' => '11:40',
        'duration_minutes' => 250.00,
        'start_rpm' => 285.80,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 7.00,
        'rod_length' => 4.50,
        'total_depth' => 30.00,
        'screen_pipes_used' => 2,
        'plain_pipes_used' => 8,
        'construction_depth' => 30.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 2505.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Injection', 'amount' => 100.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of ¢9,000 was given to Bos by agent at site',
    ],

    // Report 3: 2025-10-07 - Subi
    [
        'date' => '2025-10-07',
        'site_name' => 'Subi',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Subi Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Rasta',
  2 => 'kwelry',
  3 => 'Chief',
),
        'start_time' => '09:50',
        'finish_time' => '15:00',
        'duration_minutes' => 310.00,
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 13.00,
        'rod_length' => 4.50,
        'total_depth' => 60.00,
        'screen_pipes_used' => 4,
        'plain_pipes_used' => 16,
        'construction_depth' => 60.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 2518.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Fuel compressor', 'amount' => 3070.00],
            ['description' => 'Fuel truck', 'amount' => 3860.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of ¢9,000 was given to boss directly by agent at site',
    ],

    // Report 4: 2025-10-08 - Kade - Tina Agyei
    [
        'date' => '2025-10-08',
        'site_name' => 'Kade - Tina Agyei',
        'agent' => 'Anthony',
        'agent_contact' => null,
        'client_name' => 'Kade Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Kween',
  2 => 'Chief',
  3 => 'new',
  4 => 'new',
),
        'start_time' => '14:30',
        'finish_time' => '19:20',
        'duration_minutes' => 290.00,
        'start_rpm' => 291.80,
        'finish_rpm' => 293.70,
        'total_rpm' => 1.90,
        'rods_used' => 9.00,
        'rod_length' => 4.50,
        'total_depth' => 42.00,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 11,
        'construction_depth' => 42.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 2518.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Head pan', 'amount' => 60.00],
            ['description' => 'Shovel', 'amount' => 160.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of ¢9,000 was given to boss by agent at site',
    ],

    // Report 5: 2025-10-09 - Subi MKWLaw
    [
        'date' => '2025-10-09',
        'site_name' => 'Subi MKWLaw',
        'agent' => 'Anthony',
        'agent_contact' => null,
        'client_name' => 'Subi Client',
        'personnel' => array (
  0 => 'Finest',
  1 => 'Kweku',
  2 => 'Chief',
),
        'start_time' => '11:50',
        'finish_time' => '16:50',
        'duration_minutes' => 300.00,
        'start_rpm' => 293.70,
        'finish_rpm' => 295.90,
        'total_rpm' => 2.20,
        'rods_used' => 11.00,
        'rod_length' => 4.50,
        'total_depth' => 50.00,
        'screen_pipes_used' => 2,
        'plain_pipes_used' => 9,
        'construction_depth' => 33.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 2298.00,
        'rig_fee_charged' => 9500.00,
        'rig_fee_collected' => 9500.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9500.00,
        'expenses' => [
            ['description' => 'Fuel compressor', 'amount' => 20.00],
            ['description' => 'Salary', 'amount' => 3000.00],
            ['description' => 'Bonus', 'amount' => 60.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9500.00,
        'remark' => 'Amount of GHS 9,500 was given to boss as drilling fee',
    ],

    // Report 6: 2025-10-10 - Ofoase
    [
        'date' => '2025-10-10',
        'site_name' => 'Ofoase',
        'agent' => null,
        'agent_contact' => '0554482245',
        'client_name' => 'Ofoase Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Rusta',
  2 => 'kwenu',
  3 => 'chief',
),
        'start_time' => '08:30',
        'finish_time' => '18:00',
        'duration_minutes' => 570.00,
        'start_rpm' => 295.90,
        'finish_rpm' => 300.00,
        'total_rpm' => 4.10,
        'rods_used' => 14.00,
        'rod_length' => 4.50,
        'total_depth' => 65.00,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 12,
        'construction_depth' => 45.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 8718.00,
        'rig_fee_charged' => 11500.00,
        'rig_fee_collected' => 11500.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 11500.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 11500.00,
        'remark' => 'Amount of GHS 11,500 was given to boss as drilling fee',
    ],

    // Report 7: 2025-10-11 - Nkawkaw
    [
        'date' => '2025-10-11',
        'site_name' => 'Nkawkaw',
        'agent' => 'Boss',
        'agent_contact' => '058442245',
        'client_name' => 'Nkawkaw Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Kweky',
  2 => 'Chief',
  3 => 'Rastu',
),
        'start_time' => '10:30',
        'finish_time' => '15:30',
        'duration_minutes' => 300.00,
        'start_rpm' => 300.00,
        'finish_rpm' => 302.40,
        'total_rpm' => 2.40,
        'rods_used' => 13.00,
        'rod_length' => 4.50,
        'total_depth' => 60.00,
        'screen_pipes_used' => 2,
        'plain_pipes_used' => 10,
        'construction_depth' => 36.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 20208.00,
        'rig_fee_charged' => 9500.00,
        'rig_fee_collected' => 9500.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9500.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9500.00,
        'remark' => 'Amount of GHS 9,500 was given to boss as drilling fee',
    ],

    // Report 8: 2025-10-12 - Asamankese
    [
        'date' => '2025-10-12',
        'site_name' => 'Asamankese',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Asamankese Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Kwerd',
  2 => 'Chief',
  3 => 'Rasta',
),
        'start_time' => '08:00',
        'finish_time' => '14:00',
        'duration_minutes' => 360.00,
        'start_rpm' => 302.40,
        'finish_rpm' => 304.50,
        'total_rpm' => 2.10,
        'rods_used' => 9.00,
        'rod_length' => 4.50,
        'total_depth' => 40.00,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 14,
        'construction_depth' => 40.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 20198.00,
        'rig_fee_charged' => 11000.00,
        'rig_fee_collected' => 11000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 11000.00,
        'expenses' => [
            ['description' => 'Salary', 'amount' => 3000.00],
            ['description' => 'Bonus', 'amount' => 60.00],
            ['description' => 'T&T', 'amount' => 50.00],
            ['description' => 'T&T', 'amount' => 80.00],
            ['description' => 'Water', 'amount' => 40.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 11000.00,
        'remark' => 'Amount of GHS 11,000 was given to boss as drilling fee',
    ],

    // Report 9: 2025-10-16 - Takyiman
    [
        'date' => '2025-10-16',
        'site_name' => 'Takyiman',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Takyiman Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Kweku',
  2 => 'Godwin',
  3 => 'Rusta',
  4 => 'Chief',
  5 => 'Kweci',
),
        'start_time' => '15:50',
        'finish_time' => '07:00',
        'duration_minutes' => 910.00,
        'start_rpm' => 312.50,
        'finish_rpm' => 314.00,
        'total_rpm' => 1.50,
        'rods_used' => 10.00,
        'rod_length' => 4.50,
        'total_depth' => 45.00,
        'screen_pipes_used' => 4,
        'plain_pipes_used' => 11,
        'construction_depth' => 45.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 2213.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Fuel compressor', 'amount' => 43841.00],
            ['description' => 'T&T', 'amount' => 100.00],
            ['description' => 'T&T', 'amount' => 300.00],
            ['description' => 'Police', 'amount' => 60.00],
            ['description' => 'T&T', 'amount' => 100.00],
            ['description' => 'Water', 'amount' => 10.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 was given to boss at office',
    ],

    // Report 10: 2025-10-17 - Subi
    [
        'date' => '2025-10-17',
        'site_name' => 'Subi',
        'agent' => 'Anthony Emma',
        'agent_contact' => '0598040390',
        'client_name' => 'Anthony Emma',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Kweky',
  2 => 'Rusta',
  3 => 'Kwesi',
  4 => 'Giet',
),
        'start_time' => '12:00',
        'finish_time' => '22:00',
        'duration_minutes' => 600.00,
        'start_rpm' => 314.00,
        'finish_rpm' => 317.90,
        'total_rpm' => 3.90,
        'rods_used' => 11.00,
        'rod_length' => 4.50,
        'total_depth' => 50.00,
        'screen_pipes_used' => 4,
        'plain_pipes_used' => 13,
        'construction_depth' => 50.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 10693.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 20.00],
            ['description' => 'Balance', 'amount' => 500.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Drilling fee already paid to boss by agent on 8/4/2025 ¢4,00',
    ],

    // Report 11: 2025-10-18 - Boadua - main gate, Akordia-Gmp
    [
        'date' => '2025-10-18',
        'site_name' => 'Boadua - main gate, Akordia-Gmp',
        'agent' => 'BOSS',
        'agent_contact' => null,
        'client_name' => 'Boadua Client',
        'personnel' => array (
  0 => 'Linest',
  1 => 'Kweku',
  2 => 'Chief',
  3 => 'Rasta',
  4 => 'Kwesi',
),
        'start_time' => '09:00',
        'finish_time' => '15:00',
        'duration_minutes' => 360.00,
        'start_rpm' => 317.90,
        'finish_rpm' => 319.60,
        'total_rpm' => 1.70,
        'rods_used' => 10.00,
        'rod_length' => 4.50,
        'total_depth' => 45.00,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 12,
        'construction_depth' => 45.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 10173.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Fuel compressor', 'amount' => 3530.00],
            ['description' => 'Fuel truck', 'amount' => 3632.00],
            ['description' => 'Salary', 'amount' => 3000.00],
            ['description' => 'Bonus', 'amount' => 60.00],
            ['description' => 'Washing', 'amount' => 250.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of ¢ 9,000 was given to Boss as drilling fee by client',
    ],

    // Report 12: 2025-10-20 - Kade
    [
        'date' => '2025-10-20',
        'site_name' => 'Kade',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Kade Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'kweku',
  2 => 'kwasi',
  3 => 'Rasta',
  4 => 'thitf Mr. Owusu',
),
        'start_time' => '09:00',
        'finish_time' => '15:00',
        'duration_minutes' => 360.00,
        'start_rpm' => 319.60,
        'finish_rpm' => 321.90,
        'total_rpm' => 2.30,
        'rods_used' => 11.00,
        'rod_length' => 4.50,
        'total_depth' => 50.00,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 14,
        'construction_depth' => 50.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 6863.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 20.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of $9,000 was given to boss at site by agent',
    ],

    // Report 13: 2025-10-21 - Akim Klenchin
    [
        'date' => '2025-10-21',
        'site_name' => 'Akim Klenchin',
        'agent' => 'Mr. Nicholas',
        'agent_contact' => null,
        'client_name' => 'Akim Klenchin Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Kweku',
  2 => 'Rasta',
  3 => 'Mr. Owusu',
  4 => 'Kwasi',
),
        'start_time' => '09:30',
        'finish_time' => '15:00',
        'duration_minutes' => 330.00,
        'start_rpm' => 321.90,
        'finish_rpm' => 324.00,
        'total_rpm' => 2.10,
        'rods_used' => 16.00,
        'rod_length' => 4.50,
        'total_depth' => 70.00,
        'screen_pipes_used' => 0,
        'plain_pipes_used' => 14,
        'construction_depth' => 42.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 6843.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10000.00,
        'remark' => 'Amount of GHS 5,000 was given to boss at blocks factory 3:41pm',
    ],

    // Report 14: 2025-10-22 - Subi
    [
        'date' => '2025-10-22',
        'site_name' => 'Subi',
        'agent' => 'BOSS',
        'agent_contact' => null,
        'client_name' => 'Subi Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'kwerku',
  2 => 'Rasta',
  3 => 'Kwesi',
  4 => 'linef',
  5 => 'Mr. Owusu',
),
        'start_time' => '09:00',
        'finish_time' => '15:00',
        'duration_minutes' => 360.00,
        'start_rpm' => 324.00,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 11.00,
        'rod_length' => 4.50,
        'total_depth' => 50.00,
        'screen_pipes_used' => 4,
        'plain_pipes_used' => 13,
        'construction_depth' => 50.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 11843.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Gravel', 'amount' => 150.00],
            ['description' => 'Police', 'amount' => 15.00],
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Mattress', 'amount' => 25.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of ¢9,000 was given to boss by client as drilling fee',
    ],

    // Report 15: 2025-10-23 - Akwatia
    [
        'date' => '2025-10-23',
        'site_name' => 'Akwatia',
        'agent' => 'Boss work',
        'agent_contact' => null,
        'client_name' => 'Akwatia Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Mr. Owusu',
  2 => 'Rasta',
  3 => 'Kweku',
  4 => 'Kwesi',
  5 => 'Chief',
),
        'start_time' => '10:30',
        'finish_time' => '16:50',
        'duration_minutes' => 380.00,
        'start_rpm' => 326.20,
        'finish_rpm' => 328.20,
        'total_rpm' => 2.00,
        'rods_used' => 11.00,
        'rod_length' => 4.50,
        'total_depth' => 50.00,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 14,
        'construction_depth' => 50.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 10213.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Salary', 'amount' => 3000.00],
            ['description' => 'Bonus', 'amount' => 80.00],
            ['description' => 'Salary Kweku', 'amount' => 800.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of ¢ 9,000 has been paid to boss by Client',
    ],

    // Report 16: 2025-10-24 - Akyem Swedru
    [
        'date' => '2025-10-24',
        'site_name' => 'Akyem Swedru',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Akyem Swedru Client',
        'personnel' => array (
  0 => 'finest',
  1 => 'Mr. Owusu',
  2 => 'Rasta',
  3 => 'Iewesi',
  4 => 'Chief',
  5 => 'Kweky',
),
        'start_time' => '11:00',
        'finish_time' => '14:50',
        'duration_minutes' => 230.00,
        'start_rpm' => 328.20,
        'finish_rpm' => 333.10,
        'total_rpm' => 4.90,
        'rods_used' => 22.00,
        'rod_length' => 4.50,
        'total_depth' => 100.00,
        'screen_pipes_used' => 2,
        'plain_pipes_used' => 8,
        'construction_depth' => 30.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 4963.00,
        'rig_fee_charged' => 10500.00,
        'rig_fee_collected' => 10500.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10500.00,
        'expenses' => [
            ['description' => 'Police', 'amount' => 50.00],
            ['description' => 'Fuel compressor', 'amount' => 3004.00],
            ['description' => 'Fuel truck', 'amount' => 10.00],
            ['description' => 'Fuel compressor', 'amount' => 3780.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10500.00,
        'remark' => 'Amount of GHS 10,500 was given to boss at office',
    ],

    // Report 17: 2025-10-25 - Oda - Abuabo
    [
        'date' => '2025-10-25',
        'site_name' => 'Oda - Abuabo',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Oda Client',
        'personnel' => array (
  0 => 'frnest',
  1 => 'Kween',
  2 => 'Kwesi',
  3 => 'Rasta',
  4 => 'Mr. Owusu',
  5 => 'Chief',
),
        'start_time' => '10:30',
        'finish_time' => '14:00',
        'duration_minutes' => 210.00,
        'start_rpm' => 333.10,
        'finish_rpm' => 335.10,
        'total_rpm' => 2.00,
        'rods_used' => 13.00,
        'rod_length' => 4.50,
        'total_depth' => 60.00,
        'screen_pipes_used' => 4,
        'plain_pipes_used' => 16,
        'construction_depth' => 60.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 11623.00,
        'rig_fee_charged' => 10500.00,
        'rig_fee_collected' => 10500.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10500.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Police', 'amount' => 50.00],
            ['description' => 'Fuel compressor', 'amount' => 3780.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10500.00,
        'remark' => 'Amount of GHS 10,500 was given to boss as drilling fee',
    ],

    // Report 18: 2025-10-25 - Akyem - Swedru
    [
        'date' => '2025-10-25',
        'site_name' => 'Akyem - Swedru',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Akyem Swedru Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Mr. Owusu',
  2 => 'Kweka',
  3 => 'Kwesi',
  4 => 'Rasta',
  5 => 'Chief',
),
        'start_time' => '14:50',
        'finish_time' => '20:00',
        'duration_minutes' => 310.00,
        'start_rpm' => 335.10,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 18.00,
        'rod_length' => 4.50,
        'total_depth' => 80.00,
        'screen_pipes_used' => 2,
        'plain_pipes_used' => 11,
        'construction_depth' => 39.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 18283.00,
        'rig_fee_charged' => 10500.00,
        'rig_fee_collected' => 10500.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10500.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Police', 'amount' => 30.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10500.00,
        'remark' => 'Amount of GHS 10,500 was given to boss as drilling fee',
    ],

    // Report 19: 2025-10-29 - Akwatia
    [
        'date' => '2025-10-29',
        'site_name' => 'Akwatia',
        'agent' => 'Evans',
        'agent_contact' => '0244969224',
        'client_name' => 'Akwatia Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Chief',
  2 => 'MI. Owusu',
  3 => 'Kwesi',
  4 => 'Rasto',
),
        'start_time' => '10:40',
        'finish_time' => '15:50',
        'duration_minutes' => 310.00,
        'start_rpm' => 343.30,
        'finish_rpm' => 345.30,
        'total_rpm' => 2.00,
        'rods_used' => 10.00,
        'rod_length' => 4.50,
        'total_depth' => 45.00,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 12,
        'construction_depth' => 45.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 27997.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Fuel compressor', 'amount' => 4507.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of ¢9,000 was paid to boss by agent',
    ],

    // Report 20: 2025-10-30 - Okumani
    [
        'date' => '2025-10-30',
        'site_name' => 'Okumani',
        'agent' => 'Evans',
        'agent_contact' => '0244969224',
        'client_name' => 'Okumani Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Mr. Owusu',
  2 => 'Chief',
  3 => 'Rasta',
  4 => 'Kwesi',
),
        'start_time' => '07:30',
        'finish_time' => '13:50',
        'duration_minutes' => 380.00,
        'start_rpm' => 345.30,
        'finish_rpm' => 346.80,
        'total_rpm' => 1.50,
        'rods_used' => 13.00,
        'rod_length' => 4.50,
        'total_depth' => 60.00,
        'screen_pipes_used' => 0,
        'plain_pipes_used' => 15,
        'construction_depth' => 45.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 27987.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Police', 'amount' => 20.00],
            ['description' => 'Water', 'amount' => 10.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of $9,000 was given to boss by agent',
    ],

    // Report 21: 2025-10-30 - Akim Kusi
    [
        'date' => '2025-10-30',
        'site_name' => 'Akim Kusi',
        'agent' => 'Evans',
        'agent_contact' => '0244969224',
        'client_name' => 'Akim Kusi Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Chief',
  2 => 'Mr. Owusu',
  3 => 'Rasta',
  4 => 'Kwesi',
),
        'start_time' => '14:20',
        'finish_time' => '17:50',
        'duration_minutes' => 210.00,
        'start_rpm' => 346.80,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 11.00,
        'rod_length' => 4.50,
        'total_depth' => 50.00,
        'screen_pipes_used' => 0,
        'plain_pipes_used' => 10,
        'construction_depth' => 30.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 27957.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of ¢ 9,000 was given to boss by agent',
    ],

    // Report 22: 2025-10-31 - Alafia no.3
    [
        'date' => '2025-10-31',
        'site_name' => 'Alafia no.3',
        'agent' => 'Evans',
        'agent_contact' => '0244969224',
        'client_name' => 'Alafia no.3 Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Mr. Owusu',
  2 => 'Rasta',
  3 => 'Chief',
  4 => 'Godwin',
  5 => 'Kwesi',
),
        'start_time' => '14:30',
        'finish_time' => '17:00',
        'duration_minutes' => 150.00,
        'start_rpm' => 350.00,
        'finish_rpm' => 351.50,
        'total_rpm' => 1.50,
        'rods_used' => 13.00,
        'rod_length' => 4.50,
        'total_depth' => 60.00,
        'screen_pipes_used' => 0,
        'plain_pipes_used' => 10,
        'construction_depth' => 30.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 26987.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of $9,000 has been paid to BOSS by agent',
    ],

    // Report 23: 2025-11-01 - Truck Survey
    [
        'date' => '2025-11-01',
        'site_name' => 'Truck Survey',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Internal',
        'personnel' => array (
  0 => 'Internal',
),
        'start_time' => null,
        'finish_time' => null,
        'duration_minutes' => 0,
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 0,
        'rod_length' => 4.50,
        'total_depth' => 0.00,
        'screen_pipes_used' => 0,
        'plain_pipes_used' => 0,
        'construction_depth' => 0.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 26987.00,
        'rig_fee_charged' => 0.00,
        'rig_fee_collected' => 0.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 0.00,
        'expenses' => [
            ['description' => 'Unloading', 'amount' => 100.00],
            ['description' => 'Washing', 'amount' => 250.00],
            ['description' => 'Salary', 'amount' => 6531.00],
            ['description' => 'Bonus', 'amount' => 120.00],
            ['description' => 'Fuel filter', 'amount' => 3000.00],
            ['description' => 'Oil filter', 'amount' => 2000.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => 'Truck Survey',
    ],

    // Report 24: 2025-11-02 - Boadu - Topreman road
    [
        'date' => '2025-11-02',
        'site_name' => 'Boadu - Topreman road',
        'agent' => 'Ernest',
        'agent_contact' => '0542037991',
        'client_name' => 'Boadu Client',
        'personnel' => array (
  0 => 'Ernest',
  1 => 'Mr. Owusu',
  2 => 'Chief',
  3 => 'Rusta',
  4 => 'Godwin',
),
        'start_time' => '09:00',
        'finish_time' => '15:00',
        'duration_minutes' => 360.00,
        'start_rpm' => 351.70,
        'finish_rpm' => 355.70,
        'total_rpm' => 4.00,
        'rods_used' => 13.00,
        'rod_length' => 4.50,
        'total_depth' => 60.00,
        'screen_pipes_used' => 0,
        'plain_pipes_used' => 14,
        'construction_depth' => 42.00,
        'materials_provided_by' => 'company',
        'balance_bf' => 19986.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Money to Red', 'amount' => 1500.00],
            ['description' => 'Salary', 'amount' => 1075.00],
            ['description' => 'Bonus', 'amount' => 20.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of ¢9,000 has been paid to boss by agent',
    ],

];


// Extract unique clients, agents, and workers
$clients = [];
$agents = [];
$workers = [];
$regions = [];

foreach ($fieldReports as $report) {
    // Extract clients
    if (!empty($report['client_name']) && $report['client_name'] !== 'Internal Maintenance' && $report['client_name'] !== 'Internal') {
        $key = strtolower(trim($report['client_name']));
        if (!isset($clients[$key])) {
            $clients[$key] = [
                'name' => $report['client_name'],
                'contact_person' => $report['agent'] ?? null,
                'contact_number' => $report['agent_contact'] ?? null,
                'email' => null,
                'address' => null
            ];
        }
    }
    
    // Extract workers
    if (!empty($report['personnel'])) {
        foreach ($report['personnel'] as $worker) {
            $worker = trim($worker);
            if (!empty($worker) && !in_array($worker, $workers)) {
                $workers[] = $worker;
            }
        }
    }
    
    // Extract regions from site names
    if (!empty($report['site_name'])) {
        $siteParts = explode(' - ', $report['site_name']);
        $region = $siteParts[0];
        if (!in_array($region, $regions)) {
            $regions[] = $region;
        }
    }
}

// Start transaction
$pdo->beginTransaction();

try {
    echo "Starting data import for GREEN RIG...\n\n";
    
    // Step 1: Ensure GREEN RIG exists
    echo "1. Setting up GREEN RIG...\n";
    // Only look for GREEN RIG, not RED RIG
    $rigStmt = $pdo->prepare("SELECT id FROM rigs WHERE (rig_name LIKE '%GREEN%' OR rig_code LIKE '%GREEN%') AND rig_name NOT LIKE '%RED%' AND rig_code NOT LIKE '%RED%' LIMIT 1");
    $rigStmt->execute();
    $rig = $rigStmt->fetch();
    
    if (!$rig) {
        $rigInsert = $pdo->prepare("INSERT INTO rigs (rig_name, rig_code, status, current_rpm) VALUES (?, ?, ?, ?)");
        $rigInsert->execute(['GREEN RIG', 'GREEN-01', 'active', 0]);
        $greenRigId = $pdo->lastInsertId();
        echo "   Created GREEN RIG with ID: $greenRigId\n";
    } else {
        $greenRigId = $rig['id'];
        $rigUpdate = $pdo->prepare("UPDATE rigs SET rig_name = ?, rig_code = ?, status = 'active' WHERE id = ?");
        $rigUpdate->execute(['GREEN RIG', 'GREEN-01', $greenRigId]);
        echo "   Using existing GREEN RIG (ID: $greenRigId)\n";
    }
    
    // Step 2: Create/Update clients
    echo "\n2. Creating/updating clients...\n";
    $clientMap = [];
    foreach ($clients as $key => $clientData) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE LOWER(client_name) = ? LIMIT 1");
        $stmt->execute([strtolower($clientData['name'])]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $clientId = $existing['id'];
            $update = $pdo->prepare("UPDATE clients SET contact_person = ?, contact_number = ? WHERE id = ?");
            $update->execute([$clientData['contact_person'], $clientData['contact_number'], $clientId]);
        } else {
            $insert = $pdo->prepare("INSERT INTO clients (client_name, contact_person, contact_number) VALUES (?, ?, ?)");
            $insert->execute([$clientData['name'], $clientData['contact_person'], $clientData['contact_number']]);
            $clientId = $pdo->lastInsertId();
        }
        
        $clientMap[$key] = $clientId;
        echo "   Client: {$clientData['name']} (ID: $clientId)\n";
    }
    
    // Step 3: Create/Update workers
    echo "\n3. Creating/updating workers...\n";
    $workerMap = [];
    foreach ($workers as $workerName) {
        $workerName = trim($workerName);
        if (empty($workerName) || strlen($workerName) < 2) continue;
        
        $stmt = $pdo->prepare("SELECT id FROM workers WHERE worker_name = ? LIMIT 1");
        $stmt->execute([$workerName]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $workerId = $existing['id'];
        } else {
            // Determine role based on name patterns
            $role = 'Helper';
            if (stripos($workerName, 'Atta') !== false) $role = 'Operator';
            if (stripos($workerName, 'Isaac') !== false) $role = 'Assistant';
            if (stripos($workerName, 'Godwin') !== false) $role = 'Helper';
            if (stripos($workerName, 'Castro') !== false) $role = 'Helper';
            if (stripos($workerName, 'Asare') !== false) $role = 'Assistant';
            if (stripos($workerName, 'Peter') !== false) $role = 'Operator';
            
            $insert = $pdo->prepare("INSERT INTO workers (worker_name, role, default_rate, status) VALUES (?, ?, ?, ?)");
            $rate = 120.00; // Default rate
            if ($role === 'Operator') $rate = 150.00;
            if ($role === 'Assistant') $rate = 130.00;
            $insert->execute([$workerName, $role, $rate, 'active']);
            $workerId = $pdo->lastInsertId();
        }
        
        $workerMap[$workerName] = $workerId;
        echo "   Worker: $workerName (ID: $workerId)\n";
    }
    
    // Step 4: Delete existing field reports for GREEN RIG to avoid duplicates
    echo "\n4. Cleaning up existing GREEN RIG reports...\n";
    $deleteStmt = $pdo->prepare("DELETE FROM field_reports WHERE rig_id = ?");
    $deleteStmt->execute([$greenRigId]);
    echo "   Deleted existing reports for GREEN RIG\n";
    
    // Step 5: Import field reports
    echo "\n5. Importing field reports...\n";
    $reportCount = 0;
    
    foreach ($fieldReports as $index => $report) {
        // Get client ID
        $clientId = null;
        if (!empty($report['client_name']) && $report['client_name'] !== 'Internal Maintenance' && $report['client_name'] !== 'Internal') {
            $clientKey = strtolower(trim($report['client_name']));
            $clientId = $clientMap[$clientKey] ?? null;
        }
        
        // Calculate derived values
        $totalIncome = $report['cash_received'] + $report['materials_income'] + $report['rig_fee_collected'];
        $totalExpenses = array_sum(array_column($report['expenses'], 'amount'));
        $totalWages = 0;
        foreach ($report['expenses'] as $exp) {
            if (stripos($exp['description'], 'salary') !== false || stripos($exp['description'], 'workers') !== false) {
                $totalWages += $exp['amount'];
            }
        }
        $materialsCost = 0;
        foreach ($report['expenses'] as $exp) {
            if (stripos($exp['description'], 'pipe') !== false || stripos($exp['description'], 'material') !== false) {
                $materialsCost += $exp['amount'];
            }
        }
        $netProfit = $totalIncome - $totalExpenses;
        $totalMoneyBanked = $report['bank_deposit'];
        $outstandingRigFee = max(0, $report['rig_fee_charged'] - $report['rig_fee_collected']);
        $daysBalance = $totalIncome - ($report['momo_transfer'] + $report['cash_given'] + $report['bank_deposit']);
        
        // Generate report ID
        $reportId = 'GREEN-' . date('Ymd', strtotime($report['date'])) . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
        
        // Insert field report
        $insertStmt = $pdo->prepare("
            INSERT INTO field_reports (
                report_id, report_date, rig_id, job_type, site_name, plus_code, latitude, longitude,
                location_description, region, client_id, client_contact, start_time, finish_time,
                total_duration, start_rpm, finish_rpm, total_rpm, rod_length, rods_used, total_depth,
                screen_pipes_used, plain_pipes_used, gravel_used, construction_depth, materials_provided_by,
                supervisor, total_workers, remarks, incident_log, solution_log, recommendation_log,
                balance_bf, contract_sum, rig_fee_charged, rig_fee_collected, cash_received, materials_income,
                materials_cost, momo_transfer, cash_given, bank_deposit, total_income, total_expenses,
                total_wages, net_profit, total_money_banked, days_balance, outstanding_rig_fee, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $region = !empty($report['site_name']) ? explode(' - ', $report['site_name'])[0] : null;
        $locationDesc = !empty($report['site_name']) ? $report['site_name'] : null;
        
        $insertStmt->execute([
            $reportId,
            $report['date'],
            $greenRigId,
            'direct',
            $report['site_name'],
            null, // plus_code
            null, // latitude
            null, // longitude
            $locationDesc,
            $region,
            $clientId,
            $report['agent_contact'] ?? null,
            $report['start_time'] ?? null,
            $report['finish_time'] ?? null,
            $report['duration_minutes'] ?? 0,
            $report['start_rpm'],
            $report['finish_rpm'],
            $report['total_rpm'],
            $report['rod_length'] ?? 4.5,
            $report['rods_used'] ?? 0,
            $report['total_depth'] ?? 0,
            $report['screen_pipes_used'] ?? 0,
            $report['plain_pipes_used'] ?? 0,
            0, // gravel_used
            $report['construction_depth'] ?? 0,
            $report['materials_provided_by'] ?? 'company',
            null, // supervisor
            count($report['personnel'] ?? []),
            $report['remark'],
            null, // incident_log
            null, // solution_log
            null, // recommendation_log
            $report['balance_bf'] ?? 0,
            $report['contract_sum'] ?? 0,
            $report['rig_fee_charged'] ?? 0,
            $report['rig_fee_collected'] ?? 0,
            $report['cash_received'] ?? 0,
            $report['materials_income'] ?? 0,
            $materialsCost,
            $report['momo_transfer'] ?? 0,
            $report['cash_given'] ?? 0,
            $report['bank_deposit'] ?? 0,
            $totalIncome,
            $totalExpenses,
            $totalWages,
            $netProfit,
            $totalMoneyBanked,
            $daysBalance,
            $outstandingRigFee,
            $_SESSION['user_id'] ?? 1
        ]);
        
        $reportInsertId = $pdo->lastInsertId();
        $reportCount++;
        
        // Insert expenses
        foreach ($report['expenses'] as $expense) {
            if (!empty($expense['description']) && $expense['amount'] > 0) {
                $expStmt = $pdo->prepare("
                    INSERT INTO expense_entries (report_id, description, unit_cost, quantity, amount)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $unitCost = $expense['unit_price'] ?? $expense['amount'];
                $quantity = $expense['quantity'] ?? 1;
                $expStmt->execute([
                    $reportInsertId,
                    $expense['description'],
                    $unitCost,
                    $quantity,
                    $expense['amount']
                ]);
            }
        }
        
        // Insert payroll entries (from salary expenses)
        foreach ($report['expenses'] as $expense) {
            if (stripos($expense['description'], 'salary') !== false && stripos($expense['description'], 'workers') !== false) {
                // Extract number of points/workers
                preg_match('/(\d+)\s*points?/', $expense['description'], $matches);
                $numWorkers = isset($matches[1]) ? intval($matches[1]) : count($report['personnel'] ?? []);
                $amountPerWorker = $numWorkers > 0 ? $expense['amount'] / $numWorkers : 0;
                
                // Create payroll entries for each worker
                $workersForPayroll = array_slice($report['personnel'] ?? [], 0, $numWorkers);
                foreach ($workersForPayroll as $workerName) {
                    if (!empty($workerName)) {
                        $payrollStmt = $pdo->prepare("
                            INSERT INTO payroll_entries (report_id, worker_name, role, wage_type, units, pay_per_unit, amount, paid_today)
                            VALUES (?, ?, ?, 'daily', 1, ?, ?, 1)
                        ");
                        $role = 'Helper';
                        if (stripos($workerName, 'Atta') !== false) $role = 'Operator';
                        if (stripos($workerName, 'Isaac') !== false) $role = 'Assistant';
                        
                        $payrollStmt->execute([
                            $reportInsertId,
                            $workerName,
                            $role,
                            $amountPerWorker,
                            $amountPerWorker
                        ]);
                    }
                }
            }
            
            // Bonus for spanner boy
            if (stripos($expense['description'], 'bonus') !== false && stripos($expense['description'], 'spanner') !== false) {
                $bonusStmt = $pdo->prepare("
                    INSERT INTO payroll_entries (report_id, worker_name, role, wage_type, units, pay_per_unit, amount, paid_today, notes)
                    VALUES (?, ?, ?, 'custom', 1, ?, ?, 1, ?)
                ");
                $bonusStmt->execute([
                    $reportInsertId,
                    'Spanner Boy',
                    'Helper',
                    $expense['amount'],
                    $expense['amount'],
                    'Bonus'
                ]);
            }
        }
        
        echo "   Imported: $reportId - {$report['site_name']} ({$report['date']})\n";
    }
    
    // Step 6: Update rig RPM
    echo "\n6. Updating rig RPM...\n";
    $maxRpm = $pdo->query("SELECT MAX(finish_rpm) FROM field_reports WHERE rig_id = $greenRigId AND finish_rpm IS NOT NULL")->fetchColumn();
    if ($maxRpm) {
        $updateRig = $pdo->prepare("UPDATE rigs SET current_rpm = ? WHERE id = ?");
        $updateRig->execute([$maxRpm, $greenRigId]);
        echo "   Updated GREEN RIG current RPM to: $maxRpm\n";
    }
    
    // Step 7: Extract and create maintenance records
    echo "\n7. Extracting maintenance records...\n";
    $maintenanceCount = 0;
    
    // Check if maintenance tables exist
    $maintenanceTablesExist = false;
    try {
        $pdo->query("SELECT 1 FROM maintenance_records LIMIT 1");
        $pdo->query("SELECT 1 FROM maintenance_types LIMIT 1");
        $pdo->query("SELECT 1 FROM assets LIMIT 1");
        $maintenanceTablesExist = true;
    } catch (PDOException $e) {
        echo "   Maintenance tables not found, skipping maintenance record extraction...\n";
        $maintenanceTablesExist = false;
    }
    
    if (!$maintenanceTablesExist) {
        echo "   Skipping maintenance extraction (tables not available)\n";
    } else {
        // Maintenance keywords to look for
        $maintenanceKeywords = [
            'fuel filter' => 'Fuel Filter Replacement',
            'oil filter' => 'Oil Filter Replacement',
            'engine oil' => 'Engine Oil Change',
            'rig engine oil' => 'Engine Oil Change',
            'coolant' => 'Coolant Replacement',
            'washing' => 'Rig Washing',
            'wash' => 'Rig Washing',
            'carrier ring' => 'Carrier Ring Replacement',
            'carrier bar' => 'Carrier Bar Replacement',
            'gear oil' => 'Gear Oil Change',
            'hack saw' => 'Hack Saw Blade Replacement',
            'gasket' => 'Gasket Replacement',
            'air cleaner' => 'Air Cleaner Replacement',
            'welding' => 'Welding Work',
            'welder' => 'Welding Work'
        ];
        
        // Get all imported reports
        $importedReports = $pdo->query("
            SELECT fr.id, fr.report_id, fr.report_date, fr.finish_rpm,
                   GROUP_CONCAT(ee.description SEPARATOR ', ') as expenses,
                   fr.remarks
            FROM field_reports fr
            LEFT JOIN expense_entries ee ON fr.id = ee.report_id
            WHERE fr.rig_id = $greenRigId
            GROUP BY fr.id
            ORDER BY fr.report_date
        ")->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($importedReports as $importedReport) {
            $maintenanceFound = false;
            $maintenanceTypes = [];
            $maintenanceCost = 0;
            $maintenanceDescription = [];
            
            // Check expenses for maintenance activities
            if (!empty($importedReport['expenses'])) {
                $expenses = explode(', ', $importedReport['expenses']);
                foreach ($expenses as $exp) {
                    $expLower = strtolower($exp);
                    foreach ($maintenanceKeywords as $keyword => $type) {
                        if (stripos($expLower, $keyword) !== false) {
                            $maintenanceFound = true;
                            if (!in_array($type, $maintenanceTypes)) {
                                $maintenanceTypes[] = $type;
                            }
                            $maintenanceDescription[] = $exp;
                            break;
                        }
                    }
                }
            }
            
            // Check remarks for maintenance activities
            if (!empty($importedReport['remarks'])) {
                $remarksLower = strtolower($importedReport['remarks']);
                foreach ($maintenanceKeywords as $keyword => $type) {
                    if (stripos($remarksLower, $keyword) !== false) {
                        $maintenanceFound = true;
                        if (!in_array($type, $maintenanceTypes)) {
                            $maintenanceTypes[] = $type;
                        }
                        break;
                    }
                }
            }
            
            // Also check for "Washing of RIG" in expenses
            if (!empty($importedReport['expenses']) && 
                (stripos($importedReport['expenses'], 'Washing') !== false || 
                 stripos($importedReport['expenses'], 'washing') !== false)) {
                $maintenanceFound = true;
                if (!in_array('Rig Washing', $maintenanceTypes)) {
                    $maintenanceTypes[] = 'Rig Washing';
                }
            }
            
            // Create maintenance record if found
            if ($maintenanceFound) {
            
            // Get expense amounts for maintenance
            $maintenanceExpenses = $pdo->prepare("
                SELECT SUM(amount) as total_cost
                FROM expense_entries
                WHERE report_id = ?
                AND (
                    LOWER(description) LIKE '%filter%' OR
                    LOWER(description) LIKE '%oil%' OR
                    LOWER(description) LIKE '%coolant%' OR
                    LOWER(description) LIKE '%washing%' OR
                    LOWER(description) LIKE '%wash%' OR
                    LOWER(description) LIKE '%carrier%' OR
                    LOWER(description) LIKE '%gear%' OR
                    LOWER(description) LIKE '%gasket%' OR
                    LOWER(description) LIKE '%welding%' OR
                    LOWER(description) LIKE '%welder%'
                )
            ");
            $maintenanceExpenses->execute([$importedReport['id']]);
            $costData = $maintenanceExpenses->fetch();
            $maintenanceCost = $costData['total_cost'] ?? 0;
            
            // Create maintenance record for each type
            foreach ($maintenanceTypes as $maintType) {
                $maintenanceCode = 'MNT-GREEN-' . date('Ymd', strtotime($importedReport['report_date'])) . '-' . strtoupper(substr(uniqid(), -6));
                
                // Get or create maintenance type
                $maintTypeStmt = $pdo->prepare("SELECT id FROM maintenance_types WHERE type_name LIKE ? LIMIT 1");
                $maintTypeStmt->execute(['%' . $maintType . '%']);
                $maintTypeData = $maintTypeStmt->fetch();
                $maintTypeId = $maintTypeData['id'] ?? null;
                
                // Create maintenance type if it doesn't exist
                if (!$maintTypeId) {
                    try {
                        $createTypeStmt = $pdo->prepare("INSERT INTO maintenance_types (type_name, description, is_proactive, is_active) VALUES (?, ?, 1, 1)");
                        $createTypeStmt->execute([$maintType, "Auto-created maintenance type: $maintType"]);
                        $maintTypeId = $pdo->lastInsertId();
                    } catch (PDOException $e) {
                        // If we can't create type, skip this maintenance record
                        echo "   Warning: Could not create maintenance type '$maintType', skipping record\n";
                        continue;
                    }
                }
                
                // Get asset ID for GREEN RIG
                $assetStmt = $pdo->prepare("SELECT id FROM assets WHERE asset_type = 'rig' AND (asset_code = 'GREEN-01' OR asset_name LIKE '%GREEN%') LIMIT 1");
                $assetStmt->execute();
                $asset = $assetStmt->fetch();
                $assetId = $asset['id'] ?? null;
                
                // If no asset found, create a default one or use rig_id only
                if (!$assetId) {
                    // Try to create asset for GREEN RIG
                    try {
                        $createAssetStmt = $pdo->prepare("INSERT INTO assets (asset_name, asset_code, asset_type, status) VALUES (?, ?, 'rig', 'active')");
                        $createAssetStmt->execute(['GREEN RIG', 'GREEN-01']);
                        $assetId = $pdo->lastInsertId();
                    } catch (PDOException $e) {
                        // If asset table doesn't exist or we can't create, use a default or skip
                        // For now, we'll use rig_id only (asset_id might be nullable or we'll handle it)
                        $assetId = null;
                    }
                }
                
                // Skip if we still don't have asset_id (it's required)
                if (!$assetId) {
                    echo "   Warning: No asset found for GREEN RIG, skipping maintenance record\n";
                    continue;
                }
                
                // Insert maintenance record
                $maintStmt = $pdo->prepare("
                    INSERT INTO maintenance_records (
                        maintenance_code, maintenance_type_id, maintenance_category,
                        asset_id, rig_id, status, priority, rpm_at_maintenance,
                        description, cost, performed_by, created_by, created_at
                    ) VALUES (?, ?, 'proactive', ?, ?, 'completed', 'medium', ?, ?, ?, ?, ?, NOW())
                ");
                
                $description = "Auto-extracted from field report: {$importedReport['report_id']}. " . 
                              "Maintenance type: $maintType. " . 
                              (!empty($maintenanceDescription) ? "Activities: " . implode(', ', array_unique($maintenanceDescription)) : '');
                
                $userId = $_SESSION['user_id'] ?? 1;
                
                $maintStmt->execute([
                    $maintenanceCode,
                    $maintTypeId,
                    $assetId,
                    $greenRigId,
                    $importedReport['finish_rpm'] ?? null,
                    $description,
                    $maintenanceCost / count($maintenanceTypes), // Distribute cost
                    $userId, // performed_by
                    $userId  // created_by
                ]);
                
                $maintenanceCount++;
            }
            
            if (!empty($maintenanceTypes)) {
                echo "   Created maintenance record for {$importedReport['report_id']}: " . implode(', ', $maintenanceTypes) . "\n";
            }
        }
        }
    }
    
    echo "   Total maintenance records created: $maintenanceCount\n";
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Import completed successfully!\n";
    echo "\nSummary:\n";
    echo "  - Field Reports: $reportCount imported\n";
    echo "  - Clients: " . count($clients) . " created/updated\n";
    echo "  - Workers: " . count($workers) . " created/updated\n";
    echo "  - Maintenance Records: $maintenanceCount created\n";
    echo "  - Rig: GREEN RIG (ID: $greenRigId)\n";
    echo "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
    exit(1);
}

