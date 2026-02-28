<?php
include 'db.php';

// Script to clean up database tables for Truck Insurance
// Use ?execute=true to run the queries. Otherwise it just prints them.

$run = isset($_GET['execute']) && $_GET['execute'] === 'true';

echo "<h1>Database Schema Update Script</h1>";
echo "<p>Use <code>?execute=true</code> in the URL to run these queries.</p>";
echo "<hr>";

$queries = [];

// ---------------------------------------------------------
// Table: truck_insurance (Single Truck)
// ---------------------------------------------------------

// Columns to KEEP: 
// id, full_name, address, contact_number, email, company_name, occupation, vehicle_type, type_goods_carried, sum_insured
// Columns to ADD:
// coverage_options, additional_info
// Columns to DROP:
// renewal_date, carrying_capacity, trailer_cover_required, truck_details, base_operation, public_liability_cover, radius_operation, marine_cover, registration_number, require_finance, business_established, current_insurer, driver_age, year_continuously_insured, number_truck_insurance, year_truck_licence_held, driving_convictions

$queries[] = "-- Table: truck_insurance";
$queries[] = "ALTER TABLE truck_insurance ADD COLUMN coverage_options TEXT AFTER type_goods_carried;";
$queries[] = "ALTER TABLE truck_insurance ADD COLUMN additional_info TEXT AFTER coverage_options;";
// Only drop if they exist.. MySQL doesn't have "DROP COLUMN IF EXISTS" in older versions usually, but we assume these exist from previous code.
$dropCols1 = [
    'renewal_date',
    'carrying_capacity',
    'trailer_cover_required',
    'truck_details',
    'base_operation',
    'public_liability_cover',
    'radius_operation',
    'marine_cover',
    'registration_number',
    'require_finance',
    'business_established',
    'current_insurer',
    'driver_age',
    'year_continuously_insured',
    'number_truck_insurance',
    'year_truck_licence_held',
    'driving_convictions'
];

foreach ($dropCols1 as $col) {
    // We wrap in a block to avoid stopping if col doesn't exist (if running blindly), 
    // but for the output script we just list them.
    $queries[] = "ALTER TABLE truck_insurance DROP COLUMN $col;";
}


// ---------------------------------------------------------
// Table: truck_insurance_multiple (Multiple Truck)
// ---------------------------------------------------------

// Columns to KEEP:
// id, full_name, address, contact_number, email, company_name, number_of_trucks
// Columns to ADD:
// coverage_options, additional_info
// Columns to DROP:
// occupation, renewal_date, base_operation_postcode, trailer_cover_required, vehicle_type, radius_operation, public_liability_cover, years_business_established, truck_insurance_claims, driving_convictions

$queries[] = "-- Table: truck_insurance_multiple";
$queries[] = "ALTER TABLE truck_insurance_multiple ADD COLUMN coverage_options TEXT AFTER number_of_trucks;";
$queries[] = "ALTER TABLE truck_insurance_multiple ADD COLUMN additional_info TEXT AFTER coverage_options;";

$dropCols2 = [
    'occupation',
    'renewal_date',
    'base_operation_postcode',
    'trailer_cover_required',
    'vehicle_type',
    'radius_operation',
    'public_liability_cover',
    'years_business_established',
    'truck_insurance_claims',
    'driving_convictions'
];

foreach ($dropCols2 as $col) {
    $queries[] = "ALTER TABLE truck_insurance_multiple DROP COLUMN $col;";
}

// ---------------------------------------------------------
// Execution / Output
// ---------------------------------------------------------

echo "<pre>";
foreach ($queries as $q) {
    echo htmlspecialchars($q) . "\n";
    if ($run && !str_starts_with($q, '--')) {
        try {
            $pdo->query($q);
            echo " [OK]\n";
        } catch (Exception $e) {
            echo " [ERROR: " . $e->getMessage() . "]\n";
        }
    }
}
echo "</pre>";

if ($run) {
    echo "<h3>Execution Completed.</h3>";
}
?>