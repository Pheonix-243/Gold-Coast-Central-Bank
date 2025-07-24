<?php
require_once('../includes/conn.php');

// Default fraud detection rules
$defaultRules = [
    [
        'name' => 'High Amount from Unusual Location',
        'description' => 'Flag transactions over GHS 500 from locations not in user profile',
        'conditions' => [
            'amount_greater_than' => 500,
            'unusual_location' => true
        ],
        'risk_score' => 70,
        'action' => 'require_verification'
    ],
    [
        'name' => 'Unusual Time Transaction',
        'description' => 'Flag transactions occurring between 2am and 4am',
        'conditions' => [
            'unusual_time' => true
        ],
        'risk_score' => 50,
        'action' => 'flag'
    ],
    [
        'name' => 'New Device Login',
        'description' => 'Flag logins from devices not previously seen for this user',
        'conditions' => [
            'new_device' => true
        ],
        'risk_score' => 40,
        'action' => 'flag'
    ],
    [
        'name' => 'High Velocity Transactions',
        'description' => 'Flag when user makes more than 5 transactions in 1 minute',
        'conditions' => [
            'velocity_high' => 5
        ],
        'risk_score' => 60,
        'action' => 'require_verification'
    ],
    [
        'name' => 'Blacklisted IP Address',
        'description' => 'Flag transactions or logins from known suspicious IPs',
        'conditions' => [
            'blacklisted_ip' => true
        ],
        'risk_score' => 80,
        'action' => 'freeze_account'
    ],
    [
        'name' => 'Geo-Blocked Country',
        'description' => 'Flag transactions or logins from blocked countries',
        'conditions' => [
            'geo_blocked_country' => true
        ],
        'risk_score' => 90,
        'action' => 'freeze_account'
    ]
];

// Insert default rules
foreach ($defaultRules as $rule) {
    $sql = "INSERT INTO fraud_detection_rules 
            (name, description, rule_condition, risk_score, action, is_active)
            VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = mysqli_prepare($con, $sql);
    $conditionsJson = json_encode($rule['conditions']);
    mysqli_stmt_bind_param($stmt, "sssis", 
        $rule['name'],
        $rule['description'],
        $conditionsJson,
        $rule['risk_score'],
        $rule['action']
    );
    mysqli_stmt_execute($stmt);
}

// Add some geo-blocking rules
$geoBlockedCountries = [
    ['NG', 'Nigeria', 'all'],
    ['RU', 'Russia', 'all'],
    ['CN', 'China', 'transfers'],
    ['US', 'United States', 'logins']
];

foreach ($geoBlockedCountries as $country) {
    $sql = "INSERT INTO geo_blocking_rules 
            (country_code, country_name, block_type, is_active)
            VALUES (?, ?, ?, 1)";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "sss", 
        $country[0],
        $country[1],
        $country[2]
    );
    mysqli_stmt_execute($stmt);
}

echo "Fraud detection system initialized with default rules and geo-blocking settings.";
?>