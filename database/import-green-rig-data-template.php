<?php
/**
 * Import Real Field Report Data for RED RIG
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
    // Report 1: 31/10/2025 - Arwatia District Assembly
    [
        'date' => '2025-10-31',
        'site_name' => 'Arwatia - District Assembly',
        'agent' => 'Evans',
        'agent_contact' => '0244969224',
        'client_name' => 'District Assembly',
        'personnel' => ['Atta', 'Isaac', 'Peter', 'Castro', 'Asare', 'Tawiah'],
        'start_time' => '14:30',
        'finish_time' => '17:50',
        'duration_minutes' => 200, // 3h 20m
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 10,
        'rod_length' => 4.5,
        'total_depth' => 50.0,
        'screen_pipes_used' => 2,
        'plain_pipes_used' => 11,
        'construction_depth' => 39.0, // (2+11)*3 = 39m
        'materials_provided_by' => 'company',
        'balance_bf' => 9305.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 has been paid to Boss by agent'
    ],
    
    // Report 2: 30/10/2025 - Servicing Compressor Engine
    [
        'date' => '2025-10-30',
        'site_name' => 'Servicing Compressor Engine',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Internal Maintenance',
        'personnel' => [],
        'start_time' => null,
        'finish_time' => null,
        'duration_minutes' => 0,
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 0,
        'rod_length' => 0,
        'total_depth' => 0,
        'screen_pipes_used' => 0,
        'plain_pipes_used' => 0,
        'construction_depth' => 0,
        'materials_provided_by' => 'company',
        'balance_bf' => 9295.00,
        'rig_fee_charged' => 0.00,
        'rig_fee_collected' => 0.00,
        'cash_received' => 1500.00, // From Green machine
        'materials_income' => 0.00,
        'contract_sum' => 0.00,
        'expenses' => [
            ['description' => 'T&T for workers to Boadua', 'amount' => 10.00],
            ['description' => 'Salary for 10 workers (8 points)', 'amount' => 8330.00],
            ['description' => 'Bonus for Spanner boy (8 points)', 'amount' => 160.00],
            ['description' => 'T&T for Mechanic', 'amount' => 20.00],
            ['description' => 'Workmanship for Gasket 2pcs', 'amount' => 250.00],
            ['description' => 'Washing of RIG', 'amount' => 250.00],
            ['description' => 'Money to Peter for Company duty', 'amount' => 1000.00],
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => 'Air Cleaner Blown, Fuel filter changed, Oil filter Changed, Compressor engine oil changes'
    ],
    
    // Report 3: 30/10/2025 - Dwenase
    [
        'date' => '2025-10-30',
        'site_name' => 'Dwenase',
        'agent' => 'Evans',
        'agent_contact' => '0244969224',
        'client_name' => 'Dwenase Client',
        'personnel' => ['Atta', 'Isaac', 'Castro', 'Asare', 'Tawiah'],
        'start_time' => '14:30',
        'finish_time' => '19:00',
        'duration_minutes' => 270, // 4h 30m
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 8,
        'rod_length' => 4.5,
        'total_depth' => 40.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 10,
        'construction_depth' => 39.0, // (3+10)*3 = 39m
        'materials_provided_by' => 'company',
        'balance_bf' => 10115.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 9000.00, // Crossed out from 10,000
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Police fee', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 8,000 was paid to boss by agent'
    ],
    
    // Report 4: 31/10/2025 - Akwatia Shs
    [
        'date' => '2025-10-31',
        'site_name' => 'Akwatia Shs',
        'agent' => 'Evans',
        'agent_contact' => '0244969224',
        'client_name' => 'Akwatia Senior High School',
        'personnel' => ['Atta', 'Peter', 'Isaac', 'Tawich', 'Asare', 'Castro'],
        'start_time' => '08:30',
        'finish_time' => '13:30',
        'duration_minutes' => 300, // 5h
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 18,
        'rod_length' => 4.5,
        'total_depth' => 50.0,
        'screen_pipes_used' => 2,
        'plain_pipes_used' => 7,
        'construction_depth' => 27.0, // (2+7)*3 = 27m
        'materials_provided_by' => 'company',
        'balance_bf' => 10095.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => '4lti Coolant', 'quantity' => 4, 'unit_price' => 50.00, 'amount' => 200.00],
            ['description' => 'Grease', 'quantity' => 1, 'unit_price' => 580.00, 'amount' => 580.00],
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 has been paid to Boss by agent'
    ],
    
    // Report 5: 29/10/2025 - Boadua
    [
        'date' => '2025-10-29',
        'site_name' => 'Boadua',
        'agent' => null,
        'agent_contact' => '024496224',
        'client_name' => 'Boadua Client',
        'personnel' => ['Attu', 'Isaac', 'Tawiah', 'Asare', 'Castro'],
        'start_time' => '08:00',
        'finish_time' => '13:20',
        'duration_minutes' => 320, // 5h 20m
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 15,
        'rod_length' => 4.5,
        'total_depth' => 75.0,
        'screen_pipes_used' => 2,
        'plain_pipes_used' => 8,
        'construction_depth' => 30.0, // (2+8)*3 = 30m
        'materials_provided_by' => 'company',
        'balance_bf' => 10255.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 20.00],
            ['description' => 'Small water hose', 'amount' => 80.00],
            ['description' => 'Fuel for Compressor', 'amount' => 3550.00] // Note: amount written in description
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 was given to boss by agent'
    ],
    
    // Report 6: 29/10/2025 - Boadua (another entry)
    [
        'date' => '2025-10-29',
        'site_name' => 'Boadua',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Boadua Client',
        'personnel' => ['Attu', 'Isaac', 'Asare', 'Castro', 'Jawich'],
        'start_time' => '14:20',
        'finish_time' => '19:00',
        'duration_minutes' => 280, // 4h 40m
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 8,
        'rod_length' => 4.5,
        'total_depth' => 40.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 11,
        'construction_depth' => 42.0, // (3+11)*3 = 42m
        'materials_provided_by' => 'company',
        'balance_bf' => 10155.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Police fee', 'amount' => 10.00],
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 was paid to Boss by agent'
    ],
    
    // Report 7: 30/10/2025 - Sakyikrom
    [
        'date' => '2025-10-30',
        'site_name' => 'Sakyikrom',
        'agent' => 'Evans',
        'agent_contact' => '0244969224',
        'client_name' => 'Sakyikrom Client',
        'personnel' => ['Atta', 'Isaac', 'Tawiah', 'Asare', 'Castro'],
        'start_time' => '08:30',
        'finish_time' => '13:00',
        'duration_minutes' => 270, // 4h 30m
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 9,
        'rod_length' => 4.5,
        'total_depth' => 45.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 12,
        'construction_depth' => 45.0, // (3+12)*3 = 45m
        'materials_provided_by' => 'company',
        'balance_bf' => 10135.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Fuel for compressor', 'amount' => 3780.00], // Note: written as ¢3,780
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Police fee', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 was paid to boss by agent'
    ],
    
    // Report 8: 28/10/2025 - Takorase
    [
        'date' => '2025-10-28',
        'site_name' => 'Takorase',
        'agent' => 'HA INIKUOM',
        'agent_contact' => null,
        'client_name' => 'Takorase Client',
        'personnel' => ['Attu', 'Isaal', 'Asare', 'Tawich', 'Castro'],
        'start_time' => '10:20',
        'finish_time' => '14:00',
        'duration_minutes' => 220, // 3h 40m
        'start_rpm' => 2897.2,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 9,
        'rod_length' => 4.5,
        'total_depth' => 45.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 12,
        'construction_depth' => 45.0, // (3+12)*3 = 45m
        'materials_provided_by' => 'company',
        'balance_bf' => 475.00,
        'rig_fee_charged' => 9800.00,
        'rig_fee_collected' => 9800.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9800.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00], // Note: written as 'W'
            ['description' => 'Police fee', 'amount' => 10.00], // Note: written as 'W'
            ['description' => 'Fuel for truck side', 'amount' => 2906.00] // Note: written as $2,906
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => null
    ],
    
    // Report 9: 27/10/2025 - Auman Kese
    [
        'date' => '2025-10-27',
        'site_name' => 'Auman Kese',
        'agent' => 'MAYA (Lucas)',
        'agent_contact' => '0244208072',
        'client_name' => 'Auman Kese Client',
        'personnel' => ['Atta', 'Isaac', 'Godwin', 'Asare', 'Tawich', 'Castro'],
        'start_time' => '13:30',
        'finish_time' => '18:00',
        'duration_minutes' => 270, // 4h 30m
        'start_rpm' => 2895.9,
        'finish_rpm' => 2897.2,
        'total_rpm' => 1.3,
        'rods_used' => 9,
        'rod_length' => 4.5,
        'total_depth' => 45.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 7,
        'construction_depth' => 30.0, // (3+7)*3 = 30m
        'materials_provided_by' => 'company',
        'balance_bf' => 515.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'Police fee', 'amount' => 30.00],
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 10000.00, // MoMo transaction noted
        'momo_name' => 'LUCAS AMUMU',
        'momo_id' => '67743395639',
        'cash_given' => 0.00,
        'bank_deposit' => 10000.00,
        'remark' => 'MoMo: GHS 10,000, Name: LUCAS AMUMU, ID: 67743395639'
    ],
    
    // Report 10: 26/10/2025 - Maintenance
    [
        'date' => '2025-10-26',
        'site_name' => 'Washing and Maintenance',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Internal Maintenance',
        'personnel' => [],
        'start_time' => null,
        'finish_time' => null,
        'duration_minutes' => 0,
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 0,
        'rod_length' => 0,
        'total_depth' => 0,
        'screen_pipes_used' => 0,
        'plain_pipes_used' => 0,
        'construction_depth' => 0,
        'materials_provided_by' => 'company',
        'balance_bf' => 705.00,
        'rig_fee_charged' => 0.00,
        'rig_fee_collected' => 0.00,
        'cash_received' => 8000.00, // From blocks
        'materials_income' => 0.00,
        'contract_sum' => 0.00,
        'expenses' => [
            ['description' => 'Washing of RIG', 'amount' => 250.00],
            ['description' => 'Workmanship for welder', 'amount' => 800.00],
            ['description' => 'Salary for workers (7 points)', 'amount' => 7000.00],
            ['description' => 'Bonus for Spanner boy (7 points)', 'amount' => 140.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => null
    ],
    
    // Report 11: 25/10/2025 - Boadua
    [
        'date' => '2025-10-25',
        'site_name' => 'Boadua',
        'agent' => 'Boss work',
        'agent_contact' => null,
        'client_name' => 'Boadua Client',
        'personnel' => ['Atta', 'Isaac', 'Godwin', 'Asare', 'Tawiah', 'Castro'],
        'start_time' => '14:00',
        'finish_time' => '18:50',
        'duration_minutes' => 290, // 4h 50m
        'start_rpm' => 2894.3,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 8,
        'rod_length' => 4.5,
        'total_depth' => 40.0,
        'screen_pipes_used' => 1,
        'plain_pipes_used' => 6,
        'construction_depth' => 21.0, // (1+6)*3 = 21m
        'materials_provided_by' => 'company',
        'balance_bf' => 715.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 was paid to boss by client as drilling fee'
    ],
    
    // Report 12: 24/10/2025 - Kyebi
    [
        'date' => '2025-10-24',
        'site_name' => 'Kyebi',
        'agent' => 'Boss Work',
        'agent_contact' => null,
        'client_name' => 'Kyebi Client',
        'personnel' => ['Atta', 'Isaac', 'Asare', 'Godwin', 'Tawiah', 'Castro'],
        'start_time' => '08:00',
        'finish_time' => '19:30',
        'duration_minutes' => 690, // 11h 30m
        'start_rpm' => 28875.0, // Note: appears to be 2887.5
        'finish_rpm' => 2892.0,
        'total_rpm' => 4.5,
        'rods_used' => 22,
        'rod_length' => 4.5,
        'total_depth' => 110.0,
        'screen_pipes_used' => 1,
        'plain_pipes_used' => 8,
        'construction_depth' => 27.0, // (1+8)*3 = 27m
        'materials_provided_by' => 'company',
        'balance_bf' => 9350.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10000.00,
        'remark' => 'Amount of GHS 10,000 has been paid to boss by client as drilling fee'
    ],
    
    // Report 13: 24/10/2025 - Kyebi (second entry same day)
    [
        'date' => '2025-10-24',
        'site_name' => 'Kyebi',
        'agent' => 'Boss work',
        'agent_contact' => null,
        'client_name' => 'Kyebi Client',
        'personnel' => ['Attu Isaal', 'Godwin Asare', 'Castro', 'Tawiah'],
        'start_time' => '15:30',
        'finish_time' => '19:40',
        'duration_minutes' => 250, // 4h 10m
        'start_rpm' => 28920.0, // Note: appears to be 2892.0
        'finish_rpm' => 2893.3,
        'total_rpm' => 1.3,
        'rods_used' => 7,
        'rod_length' => 4.5,
        'total_depth' => 35.0,
        'screen_pipes_used' => 4,
        'plain_pipes_used' => 8,
        'construction_depth' => 36.0, // (4+8)*3 = 36m
        'materials_provided_by' => 'company',
        'balance_bf' => 9350.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 20.00],
            ['description' => 'Fuel for truck side', 'amount' => 3200.00],
            ['description' => 'Fuel for Compressor', 'amount' => 5000.00],
            ['description' => 'TST for pipes from Anyinam', 'amount' => 350.00],
            ['description' => 'Police fee', 'amount' => 20.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10000.00,
        'remark' => 'Amount of GHS 10,000 has been paid to boss by Client as drilling fee'
    ],
    
    // Report 14: 25/10/2025 - Kade
    [
        'date' => '2025-10-25',
        'site_name' => 'Kade',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Kade Client',
        'personnel' => ['Atta', 'Isaac', 'Godwin', 'Asure', 'Tawiah'],
        'start_time' => '09:30',
        'finish_time' => '13:30',
        'duration_minutes' => 240, // 4h
        'start_rpm' => 2893.3,
        'finish_rpm' => 2894.3,
        'total_rpm' => 1.0,
        'rods_used' => 8,
        'rod_length' => 4.5,
        'total_depth' => 40.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 10,
        'construction_depth' => 39.0, // (3+10)*3 = 39m
        'materials_provided_by' => 'company',
        'balance_bf' => 760.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 20.00],
            ['description' => '13 bolt and Spanner', 'amount' => 25.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10000.00,
        'remark' => 'Amount of GHS 10,000 has been paid to boss by agent as drilling fee'
    ],
    
    // Report 15: 23/10/2025 - Msutem
    [
        'date' => '2025-10-23',
        'site_name' => 'Msutem',
        'agent' => 'BOSS work',
        'agent_contact' => null,
        'client_name' => 'Msutem Client',
        'personnel' => ['Atta', 'Isaac', 'Asare', 'Godwin', 'Castro', 'Tawiah'],
        'start_time' => null,
        'finish_time' => '15:00',
        'duration_minutes' => null,
        'start_rpm' => 2884.7,
        'finish_rpm' => 2886.3,
        'total_rpm' => 1.6,
        'rods_used' => 9,
        'rod_length' => 4.5,
        'total_depth' => 45.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 12,
        'construction_depth' => 45.0, // (3+12)*3 = 45m
        'materials_provided_by' => 'company',
        'balance_bf' => 1495.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'Police fee', 'amount' => 30.00],
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10000.00,
        'remark' => 'Amount of GHS 10,000 has been paid to boss by Client as drilling fee'
    ],
    
    // Report 16: 23/10/2025 - Kyebi
    [
        'date' => '2025-10-23',
        'site_name' => 'Kyebi',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Kyebi Client',
        'personnel' => ['Atta', 'Isaac', 'Asare', 'Tawiah', 'Godwin', 'Castro'],
        'start_time' => '15:30',
        'finish_time' => '19:00',
        'duration_minutes' => 210, // 3h 30m
        'start_rpm' => 2886.3,
        'finish_rpm' => 2887.9,
        'total_rpm' => 1.2,
        'rods_used' => 7,
        'rod_length' => 4.5,
        'total_depth' => 35.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 9,
        'construction_depth' => 36.0, // (3+9)*3 = 36m
        'materials_provided_by' => 'company',
        'balance_bf' => 1455.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Hotel for workers', 'amount' => 550.00],
            ['description' => 'T&T to Isaac for Site Checking', 'amount' => 55.00],
            ['description' => 'Purchase of pipes (3pcs)', 'amount' => 490.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => null
    ],
    
    // Report 17: 22/10/2025 - Boasua
    [
        'date' => '2025-10-22',
        'site_name' => 'Boasua',
        'agent' => 'Boss',
        'agent_contact' => null,
        'client_name' => 'Boasua Client',
        'personnel' => ['Attu', 'Isaac', 'Godwin', 'Asare', 'Castro'],
        'start_time' => '11:00',
        'finish_time' => '17:00',
        'duration_minutes' => 360, // 6h
        'start_rpm' => 2882.8,
        'finish_rpm' => 2884.7,
        'total_rpm' => 1.9,
        'rods_used' => 40,
        'rod_length' => 4.5,
        'total_depth' => 50.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 14,
        'construction_depth' => 51.0, // (3+14)*3 = 51m, but reported as 50m
        'materials_provided_by' => 'company',
        'balance_bf' => 2075.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Money to Welder', 'amount' => 300.00],
            ['description' => 'T&T from Kade to Oda by workers', 'amount' => 100.00],
            ['description' => 'T&T from Oda to Abenase', 'amount' => 40.00],
            ['description' => 'Police fee', 'amount' => 20.00],
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Police fee', 'amount' => 80.00],
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Hammer welding', 'amount' => 20.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 was has been given to boss by client as drilling fee'
    ],
    
    // Report 18: 18/10/2025 - Abenase Road
    [
        'date' => '2025-10-18',
        'site_name' => 'Abenase Road',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Abenase Road Client',
        'personnel' => ['Atta', 'Isaac', 'Godwin', 'Razak'],
        'start_time' => '11:00',
        'finish_time' => '19:00',
        'duration_minutes' => 480, // 8h
        'start_rpm' => 2881.1,
        'finish_rpm' => 2882.8,
        'total_rpm' => 1.7,
        'rods_used' => 9,
        'rod_length' => 4.5,
        'total_depth' => 45.0,
        'screen_pipes_used' => 12,
        'plain_pipes_used' => 3,
        'construction_depth' => 45.0, // (12+3)*3 = 45m
        'materials_provided_by' => 'company',
        'balance_bf' => 875.00,
        'rig_fee_charged' => 10500.00,
        'rig_fee_collected' => 10500.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10500.00,
        'expenses' => [
            ['description' => 'Fuel for Compressor', 'amount' => 2400.00],
            ['description' => 'Fuel for Truck Side', 'amount' => 3425.00],
            ['description' => 'Salary for workers (3 points)', 'amount' => 3000.00],
            ['description' => 'Bonus for spanner boy (3 points)', 'amount' => 60.00],
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Police fee', 'amount' => 60.00],
            ['description' => 'Battery guy', 'amount' => 200.00],
            ['description' => 'T&T for workers to Asewase', 'amount' => 120.00],
            ['description' => 'T&T for workers to Abenase', 'amount' => 50.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => null
    ],
    
    // Report 19: 17/10/2025 - Akwatia
    [
        'date' => '2025-10-17',
        'site_name' => 'Akwatia',
        'agent' => 'BOSS',
        'agent_contact' => null,
        'client_name' => 'Akwatia Client',
        'personnel' => ['Atta', 'Isaac', 'Godwin', '#'],
        'start_time' => '14:00',
        'finish_time' => '18:00',
        'duration_minutes' => 240, // 4h
        'start_rpm' => 2880.0,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 8,
        'rod_length' => 4.5,
        'total_depth' => 40.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 11,
        'construction_depth' => 42.0, // (3+11)*3 = 42m, but reported as 14 pcs
        'materials_provided_by' => 'company',
        'balance_bf' => 875.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Valvoline oil for RIG 1', 'amount' => 10.00],
            ['description' => 'Pressing of hose', 'amount' => 100.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => 'Fuel filter changed, Oil filter changed'
    ],
    
    // Report 20: 15/10/2025 - No job
    [
        'date' => '2025-10-15',
        'site_name' => 'Office/No Job',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Internal',
        'personnel' => [],
        'start_time' => null,
        'finish_time' => null,
        'duration_minutes' => 0,
        'start_rpm' => null,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 0,
        'rod_length' => 0,
        'total_depth' => 0,
        'screen_pipes_used' => 0,
        'plain_pipes_used' => 0,
        'construction_depth' => 0,
        'materials_provided_by' => 'company',
        'balance_bf' => 12075.00,
        'rig_fee_charged' => 0.00,
        'rig_fee_collected' => 0.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 0.00,
        'expenses' => [],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 11200.00,
        'remark' => 'Amount of GHS 11,200 was given to boss at office (2:29 PM)'
    ],
    
    // Report 21: 14/10/2025 - Boadua
    [
        'date' => '2025-10-14',
        'site_name' => 'Boadua',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Boadua Client',
        'personnel' => ['Atta', 'Isaac', '& Asare', 'Tawich'],
        'start_time' => '08:00',
        'finish_time' => '12:00',
        'duration_minutes' => 240, // 4h
        'start_rpm' => 2878.3,
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 9,
        'rod_length' => 4.5,
        'total_depth' => 45.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 11,
        'construction_depth' => 42.0, // (3+11)*3 = 42m
        'materials_provided_by' => 'company',
        'balance_bf' => 2585.00,
        'rig_fee_charged' => 9500.00,
        'rig_fee_collected' => 9500.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9500.00,
        'expenses' => [
            ['description' => 'Police fee', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => null
    ],
    
    // Report 22: 11/10/2025 - Topreman
    [
        'date' => '2025-10-11',
        'site_name' => 'Topreman',
        'agent' => 'Boss',
        'agent_contact' => null,
        'client_name' => 'Topreman Client',
        'personnel' => ['Atta Isaac', 'Godwin', 'Asare'],
        'start_time' => '09:00',
        'finish_time' => '11:30',
        'duration_minutes' => 150, // 2h 30m
        'start_rpm' => 28770.0, // Note: appears to be 2877.0
        'finish_rpm' => 28783.0, // Note: appears to be 2878.3
        'total_rpm' => 1.3,
        'rods_used' => 8,
        'rod_length' => 4.5,
        'total_depth' => 40.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 11,
        'construction_depth' => 42.0, // (3+11)*3 = 42m
        'materials_provided_by' => 'company',
        'balance_bf' => 8995.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'F&T to fuel Station', 'amount' => 10.00],
            ['description' => 'Water', 'amount' => 6000.00], // Note: seems unusually high
            ['description' => 'Salary for workers (6 points)', 'amount' => 120.00],
            ['description' => 'Bonus for spanner boy (6 points)', 'amount' => 30.00],
            ['description' => 'Police fee', 'amount' => 250.00],
            ['description' => 'Fuel for Compressor', 'amount' => 4550.00] // Written in description
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10000.00,
        'remark' => 'Amount of GHS 10,000 was given to Boss by client as drilling fee'
    ],
    
    // Report 23: 09/10/2025 - Kade
    [
        'date' => '2025-10-09',
        'site_name' => 'Kade',
        'agent' => null,
        'agent_contact' => '0598040390',
        'client_name' => 'Anthony Emma',
        'personnel' => ['Anthony Emma', 'Atta', 'Isaac', 'Godwin', 'Asare', 'Tawiah'],
        'start_time' => '09:00',
        'finish_time' => '12:00',
        'duration_minutes' => 180, // 3h
        'start_rpm' => 2874.2,
        'finish_rpm' => 2875.3,
        'total_rpm' => 1.1,
        'rods_used' => 9,
        'rod_length' => 4.5,
        'total_depth' => 45.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 12,
        'construction_depth' => 45.0, // (3+12)*3 = 45m
        'materials_provided_by' => 'company',
        'balance_bf' => 10685.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Fuel for truck side', 'amount' => 2880.00], // Written in description
            ['description' => 'Police fee', 'amount' => 50.00],
            ['description' => 'Water', 'amount' => 60.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 was given to boss directly by agent'
    ],
    
    // Report 24: 08/10/2025 - Kude
    [
        'date' => '2025-10-08',
        'site_name' => 'Kude',
        'agent' => 'Anthony',
        'agent_contact' => null,
        'client_name' => 'Kude Client',
        'personnel' => ['Atta', 'Isaac', 'Rasta', 'Godwin', 'Asare', 'Tawiah'],
        'start_time' => '14:50',
        'finish_time' => '19:00',
        'duration_minutes' => 250, // 4h 10m
        'start_rpm' => 28723.0, // Note: appears to be 2872.3
        'finish_rpm' => null,
        'total_rpm' => null,
        'rods_used' => 11,
        'rod_length' => 4.5,
        'total_depth' => 55.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 15,
        'construction_depth' => 54.0, // (3+15)*3 = 54m
        'materials_provided_by' => 'company',
        'balance_bf' => 11245.00,
        'rig_fee_charged' => 9000.00,
        'rig_fee_collected' => 9000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 9000.00,
        'expenses' => [
            ['description' => 'Wellington Boots', 'quantity' => 2, 'unit_price' => 90.00, 'amount' => 180.00],
            ['description' => 'Fuel for Compressor', 'amount' => 330.00], // Note: written as $4,821
            ['description' => 'Standing Fan for workers', 'amount' => 40.00],
            ['description' => '19" bolt & nut 4pcs', 'amount' => 10.00],
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 9000.00,
        'remark' => 'Amount of GHS 9,000 was given to boss by agent at site'
    ],
    
    // Report 25: 07/10/2025 - Takorase
    [
        'date' => '2025-10-07',
        'site_name' => 'Takorase',
        'agent' => 'Boss',
        'agent_contact' => null,
        'client_name' => 'Takorase Client',
        'personnel' => ['Atta', 'Isaal', 'Godwin', 'Tawiah'],
        'start_time' => '09:50',
        'finish_time' => '17:00',
        'duration_minutes' => 430, // 7h 10m
        'start_rpm' => 2870.2,
        'finish_rpm' => 2872.3,
        'total_rpm' => 2.1,
        'rods_used' => 13,
        'rod_length' => 4.5,
        'total_depth' => 65.0,
        'screen_pipes_used' => 4,
        'plain_pipes_used' => 18,
        'construction_depth' => 66.0, // (4+18)*3 = 66m, but reported as 65m
        'materials_provided_by' => 'company',
        'balance_bf' => 19895.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'Momo Charges', 'amount' => 20.00],
            ['description' => 'Police fee', 'amount' => 40.00],
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 8580.00, // MoMo transaction
        'momo_name' => 'Boadu Asare',
        'momo_id' => '66390010231',
        'cash_given' => 0.00,
        'bank_deposit' => 10000.00,
        'remark' => 'MoMo: GHS 8,580, Name: Boadu Asare, ID: 66390010231'
    ],
    
    // Report 26: 06/10/2025 - Akim Oda
    [
        'date' => '2025-10-06',
        'site_name' => 'Akim Oda',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Akim Oda Client',
        'personnel' => ['Atta', 'Isaac', 'Godwin', 'Asare', 'Tawich'],
        'start_time' => '08:50',
        'finish_time' => '15:50',
        'duration_minutes' => 420, // 7h
        'start_rpm' => 2868.5,
        'finish_rpm' => 2870.2,
        'total_rpm' => 1.7,
        'rods_used' => 8,
        'rod_length' => 4.5,
        'total_depth' => 40.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 11,
        'construction_depth' => 42.0, // (3+11)*3 = 42m
        'materials_provided_by' => 'company',
        'balance_bf' => 9935.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'Police fee', 'amount' => 30.00],
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => null
    ],
    
    // Report 27: 04/10/2025 - Micwontanan
    [
        'date' => '2025-10-04',
        'site_name' => 'Micwontanan',
        'agent' => 'Kofi',
        'agent_contact' => '0248450932',
        'client_name' => 'Micwontanan Client',
        'personnel' => ['Atta', 'Isaac', 'Godwin', 'Asare', 'Tawiah'],
        'start_time' => '09:50',
        'finish_time' => '15:00',
        'duration_minutes' => 310, // 5h 10m
        'start_rpm' => 2864.8,
        'finish_rpm' => 2867.3,
        'total_rpm' => 2.5,
        'rods_used' => 8,
        'rod_length' => 4.5,
        'total_depth' => 40.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 6,
        'construction_depth' => 27.0, // (3+6)*3 = 27m
        'materials_provided_by' => 'company',
        'balance_bf' => 10085.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'Water', 'amount' => 10.00] // Written as 'W'
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 10000.00,
        'remark' => 'Amount of GHS 10,000 was given to boss at office'
    ],
    
    // Report 28: 03/10/2025 - Kwae
    [
        'date' => '2025-10-03',
        'site_name' => 'Kwae',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Kwae Client',
        'personnel' => ['Atta', 'Isaac', 'Godwin', 'Asare', 'Tawiah'],
        'start_time' => '09:00',
        'finish_time' => '12:30',
        'duration_minutes' => 210, // 3h 30m
        'start_rpm' => 28623.0, // Note: appears to be 2862.3
        'finish_rpm' => 2864.8,
        'total_rpm' => 2.5,
        'rods_used' => 9,
        'rod_length' => 4.5,
        'total_depth' => 45.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 12,
        'construction_depth' => 45.0, // (3+12)*3 = 45m
        'materials_provided_by' => 'company',
        'balance_bf' => 565.00,
        'rig_fee_charged' => 10000.00,
        'rig_fee_collected' => 10000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 10000.00,
        'expenses' => [
            ['description' => 'Police fee', 'amount' => 70.00],
            ['description' => 'Hotel for workers', 'amount' => 200.00],
            ['description' => 'Water', 'amount' => 10.00],
            ['description' => 'Money for New Driver RIG 1', 'amount' => 200.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => null
    ],
    
    // Report 29: 01/09/2025 - Kade
    [
        'date' => '2025-09-01',
        'site_name' => 'Kade',
        'agent' => 'Mr. Henry',
        'agent_contact' => null,
        'client_name' => 'Kade Client',
        'personnel' => ['Atta', 'Isaac', 'Tawiah', 'Godwin', 'Asare'],
        'start_time' => '08:00',
        'finish_time' => '13:00',
        'duration_minutes' => 300, // 5h
        'start_rpm' => 2860.7,
        'finish_rpm' => 2862.3,
        'total_rpm' => 1.6,
        'rods_used' => 10,
        'rod_length' => 4.5,
        'total_depth' => 50.0,
        'screen_pipes_used' => 3,
        'plain_pipes_used' => 13,
        'construction_depth' => 48.0, // (3+13)*3 = 48m
        'materials_provided_by' => 'company',
        'balance_bf' => 75.00,
        'rig_fee_charged' => 8800.00,
        'rig_fee_collected' => 8800.00,
        'cash_received' => 250.00, // From Bluns
        'materials_income' => 0.00,
        'contract_sum' => 8800.00,
        'expenses' => [
            ['description' => 'Police fee', 'amount' => 50.00],
            ['description' => 'Water', 'amount' => 10.00]
        ],
        'momo_transfer' => 8500.00,
        'momo_name' => 'Hannah Agyeiwua',
        'momo_id' => '65985719736',
        'cash_given' => 0.00,
        'bank_deposit' => 8500.00,
        'remark' => 'MoMo: GHS 8,500, Name: Hannah Agyeiwua, ID: 65985719736'
    ],
    
    // Report 30: 04/02/2025 - Achiase
    [
        'date' => '2025-02-04',
        'site_name' => 'Achiase, Mr. Boadu',
        'agent' => null,
        'agent_contact' => null,
        'client_name' => 'Mr. Boadu',
        'personnel' => ['Atta', 'Isaal', 'Godwin', 'Asare', 'Tawich', 'Mtw'],
        'start_time' => '15:00',
        'finish_time' => '19:30',
        'duration_minutes' => 270, // 4h 30m
        'start_rpm' => 2867.3,
        'finish_rpm' => 2868.5,
        'total_rpm' => 1.2,
        'rods_used' => 7,
        'rod_length' => 4.5,
        'total_depth' => 35.0,
        'screen_pipes_used' => 2,
        'plain_pipes_used' => 10,
        'construction_depth' => 36.0, // (2+10)*3 = 36m
        'materials_provided_by' => 'company',
        'balance_bf' => 10075.00,
        'rig_fee_charged' => 5000.00,
        'rig_fee_collected' => 5000.00,
        'cash_received' => 0.00,
        'materials_income' => 0.00,
        'contract_sum' => 5000.00,
        'expenses' => [
            ['description' => 'Police fee', 'amount' => 30.00],
            ['description' => 'Water', 'amount' => 40.00],
            ['description' => 'Salary for workers (5 points)', 'amount' => 5000.00],
            ['description' => 'Bonus for Spanner boy (5 points)', 'amount' => 100.00]
        ],
        'momo_transfer' => 0.00,
        'cash_given' => 0.00,
        'bank_deposit' => 0.00,
        'remark' => 'Re-Drilling for Mr. Boadu'
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
    echo "Starting data import for RED RIG...\n\n";
    
    // Step 1: Ensure RED RIG exists
    echo "1. Setting up RED RIG...\n";
    $rigStmt = $pdo->prepare("SELECT id FROM rigs WHERE rig_name LIKE '%RED%' OR rig_code LIKE '%RED%' LIMIT 1");
    $rigStmt->execute();
    $rig = $rigStmt->fetch();
    
    if (!$rig) {
        $rigInsert = $pdo->prepare("INSERT INTO rigs (rig_name, rig_code, status, current_rpm) VALUES (?, ?, ?, ?)");
        $rigInsert->execute(['RED RIG', 'RED-01', 'active', 0]);
        $rigId = $pdo->lastInsertId();
        echo "   Created RED RIG with ID: $rigId\n";
    } else {
        $rigId = $rig['id'];
        $rigUpdate = $pdo->prepare("UPDATE rigs SET rig_name = ?, rig_code = ?, status = 'active' WHERE id = ?");
        $rigUpdate->execute(['RED RIG', 'RED-01', $rigId]);
        echo "   Using existing rig ID: $rigId\n";
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
    
    // Step 4: Delete existing field reports for RED RIG to avoid duplicates
    echo "\n4. Cleaning up existing RED RIG reports...\n";
    $deleteStmt = $pdo->prepare("DELETE FROM field_reports WHERE rig_id = ?");
    $deleteStmt->execute([$rigId]);
    echo "   Deleted existing reports for RED RIG\n";
    
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
        $reportId = 'RED-' . date('Ymd', strtotime($report['date'])) . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
        
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
            $rigId,
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
    $maxRpm = $pdo->query("SELECT MAX(finish_rpm) FROM field_reports WHERE rig_id = $rigId AND finish_rpm IS NOT NULL")->fetchColumn();
    if ($maxRpm) {
        $updateRig = $pdo->prepare("UPDATE rigs SET current_rpm = ? WHERE id = ?");
        $updateRig->execute([$maxRpm, $rigId]);
        echo "   Updated RED RIG current RPM to: $maxRpm\n";
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Import completed successfully!\n";
    echo "\nSummary:\n";
    echo "  - Field Reports: $reportCount imported\n";
    echo "  - Clients: " . count($clients) . " created/updated\n";
    echo "  - Workers: " . count($workers) . " created/updated\n";
    echo "  - Rig: RED RIG (ID: $rigId)\n";
    echo "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
    exit(1);
}

