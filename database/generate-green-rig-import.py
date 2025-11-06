#!/usr/bin/env python3
"""
Generate GREEN RIG import script from extracted field report data
"""
import json

# GREEN RIG field reports extracted from images
reports = [
    # Report 0000835: 31/10/2025 - Sua be
    {
        'report_id': '0000835',
        'date': '2025-10-31',
        'site_name': 'Sua be',
        'agent': 'Boss',
        'agent_contact': None,
        'client_name': 'Sua be Client',
        'personnel': ['Boss', 'Kweku', 'Chief', 'Rasta', 'Godfred'],
        'start_time': '11:00',
        'finish_time': '17:00',
        'duration_minutes': 360,
        'start_rpm': None,
        'finish_rpm': None,
        'total_rpm': 281.7,
        'rods_used': 0,
        'rod_length': 4.5,
        'total_depth': 35.0,
        'screen_pipes_used': 4,
        'plain_pipes_used': 8,
        'construction_depth': 36.0,
        'balance_bf': 2455.00,
        'rig_fee_charged': 9000.00,
        'rig_fee_collected': 9000.00,
        'cash_received': 0.00,
        'contract_sum': 9000.00,
        'expenses': [
            {'description': 'T&T for Chief & Rasta to Asamanese', 'amount': 100.00}
        ],
        'bank_deposit': 9000.00,
        'remark': 'Amount of GHS 9,000 was given to Sass by client'
    },
    # Add more reports here...
]

print(f"Total reports to process: {len(reports)}")
print("Script generation ready - will compile all data")
