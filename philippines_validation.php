<?php
/**
 * ========================================
 * PHILIPPINES VALIDATION LIBRARY
 * Enhanced validators for PH-specific data
 * ========================================
 * 
 * Purpose:
 * Comprehensive validation for:
 * - Philippine ID numbers (SSS, TIN, UMID)
 * - Address components (Postal codes, Barangays)
 * - Age with civil status rules
 * - Income based on occupation & region
 * - Phone numbers with carrier detection
 * 
 * Usage:
 * require_once 'philippines_validation.php';
 * 
 * Then use:
 * - validateSSS($sss)
 * - validateTIN($tin)
 * - validatePostalCode($code)
 * - validatePHAddress($house, $street, $barangay, $municipality, $province, $postal)
 * - validateAgeWithCivilStatus($birthDate, $civilStatus)
 * - validateIncomeByOccupation($income, $occupation, $region)
 * - getPhoneCarrier($phoneNumber)
 * 
 * ========================================
 */

// ========== SSS NUMBER VALIDATION ==========
/**
 * Validate Philippine SSS Number
 * Format: XX-XXXXXXXXX-X (11 digits total)
 * Example: 01-1234567-8
 * 
 * @param mixed $sss The SSS number to validate
 * @return string|false Valid formatted SSS or false
 */
function validateSSS($sss) {
    if (empty($sss)) {
        return false;
    }
    
    // Remove hyphens and whitespace
    $sss = preg_replace('/[^0-9]/', '', (string)$sss);
    
    // Check length - must be 11 digits
    if (strlen($sss) !== 11) {
        error_log("SSS validation failed: Invalid length (got " . strlen($sss) . ", expected 11)");
        return false;
    }
    
    // All must be digits
    if (!ctype_digit($sss)) {
        error_log("SSS validation failed: Contains non-digits");
        return false;
    }
    
    // Validate region code (01-18)
    $region = (int)substr($sss, 0, 2);
    if ($region < 1 || $region > 18) {
        error_log("SSS validation failed: Invalid region code ($region)");
        return false;
    }
    
    // Validate check digit using Luhn algorithm
    $checkDigit = (int)$sss[10];
    $sum = 0;
    
    for ($i = 0; $i < 10; $i++) {
        $digit = (int)$sss[$i];
        
        // Double every other digit
        if ($i % 2 === 0) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        
        $sum += $digit;
    }
    
    $calculatedCheck = (10 - ($sum % 10)) % 10;
    
    if ($checkDigit !== $calculatedCheck) {
        error_log("SSS validation failed: Invalid check digit (expected $calculatedCheck, got $checkDigit)");
        return false;
    }
    
    // Format: XX-XXXXXXXXX-X
    $formatted = substr($sss, 0, 2) . '-' .
                 substr($sss, 2, 8) . '-' .
                 $sss[10];
    
    return $formatted;
}

// ========== TIN VALIDATION ==========
/**
 * Validate Philippine TIN (Tax Identification Number)
 * Format: XXX-XXX-XXX-XXX (12 digits in 4 groups)
 * Example: 123-456-789-012
 * Issued by Bureau of Internal Revenue (BIR)
 * 
 * @param mixed $tin The TIN to validate
 * @return string|false Valid formatted TIN or false
 */
function validateTIN($tin) {
    if (empty($tin)) {
        return false;
    }
    
    // Remove hyphens and whitespace
    $tin = preg_replace('/[^0-9]/', '', (string)$tin);
    
    // Check format - must be 12 digits
    if (!preg_match('/^\d{12}$/', $tin)) {
        error_log("TIN validation failed: Invalid format (got $tin)");
        return false;
    }
    
    // Format: XXX-XXX-XXX-XXX
    $formatted = substr($tin, 0, 3) . '-' .
                 substr($tin, 3, 3) . '-' .
                 substr($tin, 6, 3) . '-' .
                 substr($tin, 9, 3);
    
    return $formatted;
}

// ========== UMID VALIDATION ==========
/**
 * Validate Philippine UMID (Unified Multi-Purpose ID)
 * Format: XXXX-XXXX-XXXX (12 digits in 3 groups)
 * Example: 1234-5678-9012
 * Issued by SSS
 * 
 * @param mixed $umid The UMID to validate
 * @return string|false Valid formatted UMID or false
 */
function validateUMID($umid) {
    if (empty($umid)) {
        return false;
    }
    
    // Remove hyphens and whitespace
    $umid = preg_replace('/[^0-9]/', '', (string)$umid);
    
    // Check format - must be 12 digits
    if (!preg_match('/^\d{12}$/', $umid)) {
        error_log("UMID validation failed: Invalid format");
        return false;
    }
    
    // Format: XXXX-XXXX-XXXX
    $formatted = substr($umid, 0, 4) . '-' .
                 substr($umid, 4, 4) . '-' .
                 substr($umid, 8, 4);
    
    return $formatted;
}

// ========== POSTAL CODE VALIDATION ==========
/**
 * Validate Philippine Postal Code
 * Format: 4 digits (XXXX)
 * Range: 1000 to 8800
 * Example: 1012 (Manila)
 * 
 * @param mixed $code The postal code to validate
 * @return string|false Valid postal code or false
 */
function validatePostalCode($code) {
    if (empty($code)) {
        return false;
    }
    
    $code = trim((string)$code);
    
    // Must be exactly 4 digits
    if (!preg_match('/^\d{4}$/', $code)) {
        error_log("Postal code validation failed: Invalid format ($code)");
        return false;
    }
    
    $numeric = (int)$code;
    
    // Valid range for PH: 1000-8800
    if ($numeric < 1000 || $numeric > 8800) {
        error_log("Postal code validation failed: Out of range ($numeric)");
        return false;
    }
    
    return $code;
}

// ========== ADDRESS VALIDATION ==========
/**
 * Comprehensive Philippine Address Validation
 * Validates all components together
 * 
 * @param string $house House/Building number
 * @param string $street Street name
 * @param string $barangay Barangay name
 * @param string $municipality Municipality/City name
 * @param string $province Province name
 * @param string $postalCode Postal code
 * @param string $region Region (NCR, I, II, etc)
 * @return array|false Valid address array or false
 */
function validatePHAddress($house, $street, $barangay, $municipality, $province, $postalCode, $region = '') {
    $errors = [];
    
    // Street is required
    $street = trim((string)$street);
    if (strlen($street) < 2 || strlen($street) > 100) {
        $errors[] = "Street name must be 2-100 characters";
    }
    
    // Barangay is required
    $barangay = trim((string)$barangay);
    if (strlen($barangay) < 2 || strlen($barangay) > 100) {
        $errors[] = "Barangay name must be 2-100 characters";
    }
    
    // Municipality is required
    $municipality = trim((string)$municipality);
    if (strlen($municipality) < 2 || strlen($municipality) > 100) {
        $errors[] = "Municipality/City name must be 2-100 characters";
    }
    
    // Province is required
    $province = trim((string)$province);
    if (strlen($province) < 2 || strlen($province) > 50) {
        $errors[] = "Province name must be 2-50 characters";
    }
    
    // Postal code validation
    $validPostal = validatePostalCode($postalCode);
    if ($validPostal === false) {
        $errors[] = "Invalid postal code (must be 4 digits, 1000-8800)";
    } else {
        $postalCode = $validPostal;
    }
    
    if (!empty($errors)) {
        return false;
    }
    
    // Construct full address
    $house = trim((string)$house);
    $subdivision = isset($subdivision) ? trim((string)$subdivision) : '';
    
    $fullAddress = trim(
        $house . ' ' .
        $street . ', ' .
        ($subdivision ? $subdivision . ', ' : '') .
        $barangay . ', ' .
        $municipality . ', ' .
        $province . ' ' .
        $postalCode
    );
    
    return [
        'house_number' => $house,
        'street' => $street,
        'barangay' => $barangay,
        'municipality' => $municipality,
        'province' => $province,
        'postal_code' => $postalCode,
        'region' => $region,
        'full_address' => $fullAddress
    ];
}

// ========== AGE WITH CIVIL STATUS VALIDATION ==========
/**
 * Validate Age Based on Philippine Civil Code Requirements
 * Different civil statuses have different age requirements
 * 
 * @param string $birthDate Birth date in YYYY-MM-DD format
 * @param string $civilStatus Civil status: Single, Married, Widowed, Divorced, Separated
 * @return array|false ['age' => int, 'valid' => bool, 'message' => string] or false
 */
function validateAgeWithCivilStatus($birthDate, $civilStatus) {
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
        error_log("Invalid birth date format: $birthDate");
        return false;
    }
    
    try {
        $birth = new DateTime($birthDate);
        $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
        
        // Check if birth date is in the future
        if ($birth > $today) {
            return false;
        }
        
        // Calculate age
        $interval = $today->diff($birth);
        $age = $interval->y;
        
        // Determine minimum age based on civil status
        // Per Philippine Civil Code
        $minAge = match($civilStatus) {
            'Single' => 21,        // Requires parental consent below 21
            'Married' => 18,       // Age of consent
            'Widowed' => 18,       // Widow/widower
            'Divorced' => 18,      // May happen at 18 (with restrictions)
            'Separated' => 18,     // Legal separation at 18
            default => 21
        };
        
        $valid = $age >= $minAge;
        
        $message = match($civilStatus) {
            'Single' => "Single applicants must be 21 years old (current age: $age)",
            'Married' => "Married applicants must be 18 years old (current age: $age)",
            'Widowed' => "Widowed applicants must be 18 years old (current age: $age)",
            'Divorced' => "Divorced applicants must be 18 years old (current age: $age)",
            'Separated' => "Separated applicants must be 18 years old (current age: $age)",
            default => "Age requirement not met (required: $minAge, current: $age)"
        };
        
        return [
            'age' => $age,
            'valid' => $valid,
            'message' => $message,
            'min_age' => $minAge,
            'civil_status' => $civilStatus
        ];
        
    } catch (Exception $e) {
        error_log("Age validation error: " . $e->getMessage());
        return false;
    }
}

// ========== INCOME BY OCCUPATION VALIDATION ==========
/**
 * Occupation & Income Categories
 * Standardized list for Philippines
 */
$PH_OCCUPATION_TYPES = [
    // Agriculture
    'farmer' => [
        'category' => 'Agriculture',
        'subcategory' => 'Crops',
        'min_income' => 3000,
        'requires_documentation' => ['Land Title', 'Harvest Records'],
        'seasonal' => true
    ],
    'fisherman' => [
        'category' => 'Agriculture',
        'subcategory' => 'Fishery',
        'min_income' => 2500,
        'requires_documentation' => ['Fishing License', 'Catch Records'],
        'seasonal' => true
    ],
    'livestock_raiser' => [
        'category' => 'Agriculture',
        'subcategory' => 'Livestock',
        'min_income' => 3000,
        'requires_documentation' => ['Animal Health Records', 'Sales Records'],
        'seasonal' => true
    ],
    
    // Retail/Wholesale
    'sari_sari_owner' => [
        'category' => 'Retail',
        'subcategory' => 'Small Store',
        'min_income' => 4000,
        'requires_documentation' => ['Business Permit', 'Sales Records'],
        'seasonal' => false
    ],
    'market_vendor' => [
        'category' => 'Retail',
        'subcategory' => 'Market',
        'min_income' => 3500,
        'requires_documentation' => ['Market Stall License', 'Daily Sales'],
        'seasonal' => false
    ],
    'wholesaler' => [
        'category' => 'Wholesale',
        'subcategory' => 'Distributor',
        'min_income' => 5000,
        'requires_documentation' => ['SEC Registration', 'Business Permit'],
        'seasonal' => false
    ],
    
    // Professional
    'teacher' => [
        'category' => 'Professional',
        'subcategory' => 'Education',
        'min_income' => 6500,
        'requires_documentation' => ['Employment Contract', 'Pay Slip'],
        'seasonal' => false
    ],
    'nurse' => [
        'category' => 'Professional',
        'subcategory' => 'Healthcare',
        'min_income' => 5500,
        'requires_documentation' => ['License', 'Employment Contract'],
        'seasonal' => false
    ],
    'engineer' => [
        'category' => 'Professional',
        'subcategory' => 'Technical',
        'min_income' => 7000,
        'requires_documentation' => ['License', 'Employment Contract'],
        'seasonal' => false
    ],
    'accountant' => [
        'category' => 'Professional',
        'subcategory' => 'Finance',
        'min_income' => 6000,
        'requires_documentation' => ['License', 'Employment Contract'],
        'seasonal' => false
    ],
    'lawyer' => [
        'category' => 'Professional',
        'subcategory' => 'Legal',
        'min_income' => 8000,
        'requires_documentation' => ['License', 'Employment Contract'],
        'seasonal' => false
    ],
    'doctor' => [
        'category' => 'Professional',
        'subcategory' => 'Healthcare',
        'min_income' => 10000,
        'requires_documentation' => ['License', 'Employment Contract'],
        'seasonal' => false
    ],
    
    // Service
    'tricycle_driver' => [
        'category' => 'Service',
        'subcategory' => 'Transportation',
        'min_income' => 2500,
        'requires_documentation' => ['PUJ Permit', 'Daily Log'],
        'seasonal' => false
    ],
    'jeepney_driver' => [
        'category' => 'Service',
        'subcategory' => 'Transportation',
        'min_income' => 2500,
        'requires_documentation' => ['PUJ Permit', 'Daily Log'],
        'seasonal' => false
    ],
    'taxi_driver' => [
        'category' => 'Service',
        'subcategory' => 'Transportation',
        'min_income' => 3000,
        'requires_documentation' => ['Franchise', 'Daily Log'],
        'seasonal' => false
    ],
    'construction_worker' => [
        'category' => 'Service',
        'subcategory' => 'Construction',
        'min_income' => 3000,
        'requires_documentation' => ['Project Contracts', 'Receipts'],
        'seasonal' => true
    ],
    'electrician' => [
        'category' => 'Service',
        'subcategory' => 'Technical',
        'min_income' => 3500,
        'requires_documentation' => ['License', 'Job Records'],
        'seasonal' => false
    ],
    'plumber' => [
        'category' => 'Service',
        'subcategory' => 'Technical',
        'min_income' => 3500,
        'requires_documentation' => ['License', 'Job Records'],
        'seasonal' => false
    ],
    'beauty_salon' => [
        'category' => 'Service',
        'subcategory' => 'Beauty',
        'min_income' => 3000,
        'requires_documentation' => ['Business Permit', 'Sales Records'],
        'seasonal' => false
    ],
    
    // Business/Entrepreneur
    'entrepreneur' => [
        'category' => 'Business',
        'subcategory' => 'Business Owner',
        'min_income' => 5000,
        'requires_documentation' => ['Business Permit', 'SEC Registration', 'Tax Returns'],
        'seasonal' => false
    ],
    'retailer' => [
        'category' => 'Business',
        'subcategory' => 'Retail Business',
        'min_income' => 4500,
        'requires_documentation' => ['Business Permit', 'Sales Records'],
        'seasonal' => false
    ],
    
    // Self-employed
    'freelancer' => [
        'category' => 'Self-employed',
        'subcategory' => 'Contract Work',
        'min_income' => 4000,
        'requires_documentation' => ['Contracts', 'Bank Statements'],
        'seasonal' => false
    ],
    'consultant' => [
        'category' => 'Self-employed',
        'subcategory' => 'Professional',
        'min_income' => 5000,
        'requires_documentation' => ['Client Contracts', 'Bank Statements'],
        'seasonal' => false
    ],
    
    // Other
    'unemployed' => [
        'category' => 'Unemployed',
        'subcategory' => 'Job Seeker',
        'min_income' => 0,
        'requires_documentation' => ['Dependent Documentation'],
        'seasonal' => false
    ],
    'retired' => [
        'category' => 'Retired',
        'subcategory' => 'Pensioner',
        'min_income' => 2000,
        'requires_documentation' => ['Pension Slip', 'ID'],
        'seasonal' => false
    ],
    'housewife' => [
        'category' => 'Housewife',
        'subcategory' => 'Homemaker',
        'min_income' => 0,
        'requires_documentation' => ['Spouse Income Documentation'],
        'seasonal' => false
    ],
    'student' => [
        'category' => 'Student',
        'subcategory' => 'Education',
        'min_income' => 0,
        'requires_documentation' => ['School ID', 'Dependent Documentation'],
        'seasonal' => false
    ]
];

/**
 * Validate Income Against Occupation & Region
 * 
 * @param float $income Monthly income in PHP
 * @param string $occupation Occupation type
 * @param string $region Philippine region (NCR, I, II, III, etc)
 * @return array|false Validation result or false
 */
function validateIncomeByOccupation($income, $occupation, $region = 'NCR') {
    global $PH_OCCUPATION_TYPES;
    
    $income = (float)$income;
    $occupation = strtolower(trim($occupation));
    $region = strtoupper(trim($region));
    
    // Check if occupation exists
    if (!isset($PH_OCCUPATION_TYPES[$occupation])) {
        error_log("Unknown occupation: $occupation");
        return false;
    }
    
    $occupationData = $PH_OCCUPATION_TYPES[$occupation];
    $minIncome = $occupationData['min_income'];
    
    // Adjust minimum income by region
    $regionalMultiplier = match($region) {
        'NCR' => 1.0,           // Metro Manila - baseline
        'I' => 0.7,              // Ilocos - lower cost of living
        'II' => 0.65,             // Cagayan Valley
        'III' => 0.8,             // Central Luzon
        'IV-A' => 0.85,           // Calabarzon
        'V' => 0.6,               // Bicol
        'VI' => 0.7,              // Western Visayas
        'VII' => 0.75,            // Central Visayas
        'VIII' => 0.65,           // Eastern Visayas
        'IX' => 0.6,              // Zamboanga Peninsula
        'X' => 0.7,               // Northern Mindanao
        'XI' => 0.75,             // Davao Region
        'XII' => 0.65,            // SOCCSKSARGEN
        'XIII' => 0.6,            // CARAGA
        'ARMM' => 0.55,           // Autonomous Region
        default => 1.0
    };
    
    $adjustedMinIncome = $minIncome * $regionalMultiplier;
    $valid = $income >= $adjustedMinIncome;
    
    return [
        'occupation' => $occupation,
        'category' => $occupationData['category'],
        'income' => $income,
        'min_income' => $minIncome,
        'adjusted_min_income' => round($adjustedMinIncome, 2),
        'region' => $region,
        'regional_multiplier' => $regionalMultiplier,
        'valid' => $valid,
        'message' => $valid ? 
            "Income ₱" . number_format($income, 2) . " meets minimum requirement" :
            "Income ₱" . number_format($income, 2) . " below minimum ₱" . number_format($adjustedMinIncome, 2),
        'requires_documentation' => $occupationData['requires_documentation'],
        'is_seasonal' => $occupationData['seasonal']
    ];
}

// ========== PHONE CARRIER DETECTION ==========
/**
 * Detect Philippine Mobile Carrier from Phone Number
 * Helps with OTP delivery routing
 * 
 * @param string $phoneNumber Phone number in any format
 * @return array|false Carrier information or false
 */
function getPhoneCarrier($phoneNumber) {
    // Normalize to 09XXXXXXXXX format
    $phone = preg_replace('/[^0-9]/', '', (string)$phoneNumber);
    
    if (strlen($phone) < 11) {
        return false;
    }
    
    // Get last 10 digits if phone has country code
    if (strlen($phone) > 11) {
        $phone = substr($phone, -11);
    }
    
    // Ensure it starts with 09
    if (substr($phone, 0, 2) !== '09') {
        return false;
    }
    
    // Get the carrier prefix (3 digits after 09)
    $prefix = substr($phone, 0, 4);  // e.g., "0917"
    
    // Globe & TM prefixes
    $globe = ['0917', '0918', '0919', '0909', '0908', '0907', '0906'];
    
    // Smart & TNT prefixes
    $smart = ['0921', '0920', '0910', '0912', '0913', '0922'];
    
    // SUN Cellular prefixes
    $sun = ['0923', '0930', '0931', '0932', '0933', '0934', '0935', '0936', '0937', '0938'];
    
    // DITO prefixes
    $dito = ['0905', '0904'];
    
    if (in_array($prefix, $globe)) {
        return [
            'carrier' => 'Globe',
            'prefix' => $prefix,
            'sms_gateway' => 'globe.com.ph',
            'otp_provider' => 'SMS',
            'reliability' => 'High'
        ];
    } elseif (in_array($prefix, $smart)) {
        return [
            'carrier' => 'Smart/TNT',
            'prefix' => $prefix,
            'sms_gateway' => 'smart.com.ph',
            'otp_provider' => 'SMS',
            'reliability' => 'High'
        ];
    } elseif (in_array($prefix, $sun)) {
        return [
            'carrier' => 'SUN Cellular',
            'prefix' => $prefix,
            'sms_gateway' => 'sun.com.ph',
            'otp_provider' => 'SMS',
            'reliability' => 'Medium'
        ];
    } elseif (in_array($prefix, $dito)) {
        return [
            'carrier' => 'DITO',
            'prefix' => $prefix,
            'sms_gateway' => 'dito.ph',
            'otp_provider' => 'SMS',
            'reliability' => 'Medium'
        ];
    }
    
    return [
        'carrier' => 'Unknown',
        'prefix' => $prefix,
        'sms_gateway' => 'unknown',
        'otp_provider' => 'SMS',
        'reliability' => 'Low'
    ];
}

// ========== HELPER FUNCTIONS ==========
/**
 * Get all PH regions
 */
function getPHRegions() {
    return [
        'NCR' => 'National Capital Region (Metro Manila)',
        'CAR' => 'Cordillera Administrative Region',
        'I' => 'Ilocos Region',
        'II' => 'Cagayan Valley',
        'III' => 'Central Luzon',
        'IV-A' => 'CALABARZON',
        'IV-B' => 'MIMAROPA',
        'V' => 'Bicol Region',
        'VI' => 'Western Visayas',
        'VII' => 'Central Visayas',
        'VIII' => 'Eastern Visayas',
        'IX' => 'Zamboanga Peninsula',
        'X' => 'Northern Mindanao',
        'XI' => 'Davao Region',
        'XII' => 'SOCCSKSARGEN',
        'XIII' => 'CARAGA',
        'ARMM' => 'Autonomous Region in Muslim Mindanao'
    ];
}

/**
 * Get minimum income by region
 */
function getRegionalMinimumIncome($region = 'NCR', $occupationType = 'general') {
    $baseIncome = match($occupationType) {
        'farmer' => 3000,
        'fisher' => 2500,
        'professional' => 6500,
        'businessperson' => 5000,
        'worker' => 3000,
        'general' => 4000,
        default => 4000
    };
    
    $multiplier = match($region) {
        'NCR' => 1.0,
        'IV-A', 'IV-B' => 0.95,
        'VI', 'VII', 'XI' => 0.85,
        'I', 'II', 'III', 'V', 'VIII', 'IX', 'X', 'XII', 'XIII' => 0.75,
        'ARMM' => 0.65,
        default => 0.8
    };
    
    return $baseIncome * $multiplier;
}

?>
