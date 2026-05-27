<?php
/**
 * Fetch barangays from PSGC API for Calamba City
 * PSGC Code for Calamba City (Laguna): 045134000
 */

header('Content-Type: application/json');

// Cache the barangays to avoid excessive API calls
$cache_file = __DIR__ . '/cache/calamba_barangays.json';
$cache_time = 86400 * 7; // Cache for 7 days

// Create cache directory if it doesn't exist
if (!is_dir(__DIR__ . '/cache')) {
    mkdir(__DIR__ . '/cache', 0755, true);
}

// Check if cache exists and is still valid
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    echo file_get_contents($cache_file);
    exit;
}

// Calamba City PSGC Code (Region IV-A, Laguna Province)
// Primary PSGC: 045134000
$psgc_code = '045134000';

try {
    // Fetch from PSGC API
    $api_url = "https://psgc.gitlab.io/api/cities/{$psgc_code}/barangays/";

    $context = stream_context_create([
        'http' => [
            'timeout' => 5
        ]
    ]);

    $response = @file_get_contents($api_url, false, $context);

    if ($response === false) {
        // Fallback to hardcoded list if API is unreachable
        $barangays = [
            ['name' => 'Bagong Kalsada', 'code' => '045134001'],
            ['name' => 'Banadero', 'code' => '045134002'],
            ['name' => 'Banlic', 'code' => '045134003'],
            ['name' => 'Batino', 'code' => '045134004'],
            ['name' => 'Bubuyan', 'code' => '045134005'],
            ['name' => 'Bucal', 'code' => '045134006'],
            ['name' => 'Butong', 'code' => '045134007'],
            ['name' => 'Canlubang', 'code' => '045134008'],
            ['name' => 'Halang', 'code' => '045134009'],
            ['name' => 'Hornalan', 'code' => '045134010'],
            ['name' => 'Kay-Anlog', 'code' => '045134011'],
            ['name' => 'La Mesa', 'code' => '045134012'],
            ['name' => 'Laguerta', 'code' => '045134013'],
            ['name' => 'Lingga', 'code' => '045134014'],
            ['name' => 'Looc', 'code' => '045134015'],
            ['name' => 'Majada Out', 'code' => '045134016'],
            ['name' => 'Makiling', 'code' => '045134017'],
            ['name' => 'Mapagong', 'code' => '045134018'],
            ['name' => 'Masili', 'code' => '045134019'],
            ['name' => 'Maunong', 'code' => '045134020'],
            ['name' => 'Mayapa', 'code' => '045134021'],
            ['name' => 'Milagrosa', 'code' => '045134022'],
            ['name' => 'Paciano Rizal', 'code' => '045134023'],
            ['name' => 'Palo-Alto', 'code' => '045134024'],
            ['name' => 'Pansol', 'code' => '045134025'],
            ['name' => 'Parian', 'code' => '045134026'],
            ['name' => 'Puting Lupa', 'code' => '045134027'],
            ['name' => 'Puypuy', 'code' => '045134028'],
            ['name' => 'Real', 'code' => '045134029'],
            ['name' => 'Sainan', 'code' => '045134030'],
            ['name' => 'Sampiruhan', 'code' => '045134031'],
            ['name' => 'San Cristobal', 'code' => '045134032'],
            ['name' => 'San Jose', 'code' => '045134033'],
            ['name' => 'San Juan', 'code' => '045134034'],
            ['name' => 'Sirang Lupa', 'code' => '045134035'],
            ['name' => 'Sucol', 'code' => '045134036'],
            ['name' => 'Tulo', 'code' => '045134037'],
            ['name' => 'Turbina', 'code' => '045134038'],
            ['name' => 'Ulango', 'code' => '045134039']
        ];
    } else {
        $api_data = json_decode($response, true);
        $barangays = [];

        if (is_array($api_data)) {
            foreach ($api_data as $barangay) {
                $barangays[] = [
                    'name' => $barangay['name'] ?? '',
                    'code' => $barangay['code'] ?? ''
                ];
            }
        }

        // If API returned empty, use fallback
        if (empty($barangays)) {
            $barangays = [
                ['name' => 'Bagong Kalsada', 'code' => '045134001'],
                ['name' => 'Banadero', 'code' => '045134002'],
                ['name' => 'Banlic', 'code' => '045134003'],
                ['name' => 'Batino', 'code' => '045134004'],
                ['name' => 'Bubuyan', 'code' => '045134005'],
                ['name' => 'Bucal', 'code' => '045134006'],
                ['name' => 'Butong', 'code' => '045134007'],
                ['name' => 'Canlubang', 'code' => '045134008'],
                ['name' => 'Halang', 'code' => '045134009'],
                ['name' => 'Hornalan', 'code' => '045134010'],
                ['name' => 'Kay-Anlog', 'code' => '045134011'],
                ['name' => 'La Mesa', 'code' => '045134012'],
                ['name' => 'Laguerta', 'code' => '045134013'],
                ['name' => 'Lingga', 'code' => '045134014'],
                ['name' => 'Looc', 'code' => '045134015'],
                ['name' => 'Majada Out', 'code' => '045134016'],
                ['name' => 'Makiling', 'code' => '045134017'],
                ['name' => 'Mapagong', 'code' => '045134018'],
                ['name' => 'Masili', 'code' => '045134019'],
                ['name' => 'Maunong', 'code' => '045134020'],
                ['name' => 'Mayapa', 'code' => '045134021'],
                ['name' => 'Milagrosa', 'code' => '045134022'],
                ['name' => 'Paciano Rizal', 'code' => '045134023'],
                ['name' => 'Palo-Alto', 'code' => '045134024'],
                ['name' => 'Pansol', 'code' => '045134025'],
                ['name' => 'Parian', 'code' => '045134026'],
                ['name' => 'Puting Lupa', 'code' => '045134027'],
                ['name' => 'Puypuy', 'code' => '045134028'],
                ['name' => 'Real', 'code' => '045134029'],
                ['name' => 'Sainan', 'code' => '045134030'],
                ['name' => 'Sampiruhan', 'code' => '045134031'],
                ['name' => 'San Cristobal', 'code' => '045134032'],
                ['name' => 'San Jose', 'code' => '045134033'],
                ['name' => 'San Juan', 'code' => '045134034'],
                ['name' => 'Sirang Lupa', 'code' => '045134035'],
                ['name' => 'Sucol', 'code' => '045134036'],
                ['name' => 'Tulo', 'code' => '045134037'],
                ['name' => 'Turbina', 'code' => '045134038'],
                ['name' => 'Ulango', 'code' => '045134039']
            ];
        }
    }

    // Sort by name
    usort($barangays, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    $output = json_encode(['success' => true, 'data' => $barangays]);

    // Save to cache
    file_put_contents($cache_file, $output, LOCK_EX);

    echo $output;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch barangays: ' . $e->getMessage()
    ]);
}
?>