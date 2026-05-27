<?php
/**
 * CYCLOAN Database Population Script
 * This script generates realistic sample data to demonstrate the full potential of the system
 * 
 * WARNING: This will add sample data to your database. Run only in development/demo environments.
 */

require_once 'CYCLOAN_db.php';

// Configuration
$NUM_USERS = 50;
$NUM_LOANS = 80;
$NUM_PAYMENTS = 300;

echo "<!DOCTYPE html>
<html>
<head>
    <title>CYCLOAN - Database Population</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2e7d32; border-bottom: 3px solid #2e7d32; padding-bottom: 10px; }
        .success { color: #2e7d32; background: #e8f5e9; padding: 10px; border-radius: 6px; margin: 10px 0; }
        .error { color: #c62828; background: #ffebee; padding: 10px; border-radius: 6px; margin: 10px 0; }
        .info { color: #1565c0; background: #e3f2fd; padding: 10px; border-radius: 6px; margin: 10px 0; }
        .progress { background: #e0e0e0; border-radius: 10px; height: 20px; margin: 10px 0; overflow: hidden; }
        .progress-bar { background: linear-gradient(90deg, #2e7d32, #66bb6a); height: 100%; transition: width 0.3s; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #2e7d32, #1b5e20); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 32px; font-weight: bold; }
        .stat-label { font-size: 14px; opacity: 0.9; }
    </style>
</head>
<body>
<div class='container'>
<h1>🚀 CYCLOAN Database Population</h1>
";

// Sample data arrays
$first_names = [
    'Juan',
    'Maria',
    'Jose',
    'Ana',
    'Pedro',
    'Rosa',
    'Miguel',
    'Carmen',
    'Luis',
    'Isabel',
    'Carlos',
    'Elena',
    'Ricardo',
    'Sofia',
    'Fernando',
    'Patricia',
    'Roberto',
    'Lucia',
    'Diego',
    'Gabriela',
    'Antonio',
    'Monica',
    'Manuel',
    'Teresa',
    'Rafael',
    'Beatriz',
    'Javier',
    'Cristina',
    'Alejandro',
    'Daniela'
];

$last_names = [
    'Santos',
    'Reyes',
    'Cruz',
    'Bautista',
    'Garcia',
    'Mendoza',
    'Rodriguez',
    'Gonzales',
    'Flores',
    'Torres',
    'Rivera',
    'Ramos',
    'Dela Cruz',
    'Aquino',
    'Villanueva',
    'Castro',
    'Fernandez',
    'Pascual',
    'Lopez',
    'Morales',
    'Domingo',
    'Santiago',
    'Romero',
    'Salazar',
    'Hernandez',
    'Gutierrez',
    'Perez',
    'Martinez',
    'Alvarez',
    'Diaz'
];

$barangays = [
    'San Isidro',
    'Santa Cruz',
    'Poblacion',
    'San Jose',
    'Barangay 1',
    'Barangay 2',
    'San Antonio',
    'San Pedro',
    'Maligaya',
    'Riverside',
    'Central',
    'East District',
    'West District',
    'North Village',
    'South Village'
];

$streets = [
    'Main Street',
    'Rizal Avenue',
    'Mabini Street',
    'Del Pilar Street',
    'Luna Street',
    'Bonifacio Avenue',
    'Aguinaldo Road',
    'Quezon Boulevard',
    'Roxas Highway',
    'Osmena Street'
];

$occupations = [
    'Business Owner',
    'Farmer',
    'Teacher',
    'Driver',
    'Vendor',
    'Employee',
    'Contractor',
    'Retailer',
    'Service Provider',
    'Freelancer',
    'Skilled Worker',
    'Technician',
    'Sales Representative'
];

$loan_purposes = ['Start-up Capital', 'Business Expansion', 'Equipment Purchase', 'Working Capital', 'Inventory Purchase'];
$project_types = ['Non-Agricultural', 'Agricultural-based'];

echo "<div class='info'>📊 Generating comprehensive sample data for demonstration...</div>";

$stats = [
    'users_created' => 0,
    'loans_created' => 0,
    'payments_created' => 0,
    'errors' => 0
];

// Start transaction
$conn->begin_transaction();

try {
    echo "<h2>👥 Creating Sample Users...</h2>";

    for ($i = 0; $i < $NUM_USERS; $i++) {
        $first_name = $first_names[array_rand($first_names)];
        $last_name = $last_names[array_rand($last_names)];
        $middle_name = $last_names[array_rand($last_names)];

        $email = strtolower($first_name . '.' . $last_name . $i . '@example.com');
        $contact = '09' . rand(100000000, 999999999);
        $birthday = date('Y-m-d', strtotime('-' . rand(25, 60) . ' years'));
        $age = date('Y') - date('Y', strtotime($birthday));

        $barangay = $barangays[array_rand($barangays)];
        $street = $streets[array_rand($streets)];
        $house_no = rand(1, 999);
        $address = "$house_no $street, $barangay";

        $occupation = $occupations[array_rand($occupations)];
        $password = password_hash('password123', PASSWORD_DEFAULT);

        // Insert user
        $stmt = $conn->prepare("INSERT INTO users1 (first_name, middle_name, last_name, birthday, age, birth_place, 
                                civil_status, contact, email, res_house_no, res_street, res_subdivision, res_barangay, 
                                res_address, bus_barangay, bus_address, house_ownership, occupation, reg_voter, 
                                year_resident, password, is_active) 
                                VALUES (?, ?, ?, ?, ?, 'Philippines', ?, ?, ?, ?, ?, '', ?, ?, '', '', 'Owned', ?, 'Yes', ?, ?, 1)");

        $civil_status = ['Single', 'Married', 'Widowed'][rand(0, 2)];
        $years_resident = rand(5, 30);

        $stmt->bind_param(
            "ssssisssisssiss",
            $first_name,
            $middle_name,
            $last_name,
            $birthday,
            $age,
            $civil_status,
            $contact,
            $email,
            $house_no,
            $street,
            $barangay,
            $address,
            $occupation,
            $years_resident,
            $password
        );

        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            $stats['users_created']++;

            // Insert financial info
            $net_income = rand(15000, 80000);
            $business_income = rand(10000, 50000);
            $salary_income = rand(5000, 30000);
            $total_expenditures = rand(8000, 35000);
            $remaining_income = $net_income - $total_expenditures;

            $stmt_fin = $conn->prepare("INSERT INTO financial_info (user_id, business_income, salary_income, 
                                        net_income, total_expenditures, expected_monthly_amortization, remaining_income) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
            $expected_amort = round($remaining_income * 0.4);
            $stmt_fin->bind_param(
                "iiiiiii",
                $user_id,
                $business_income,
                $salary_income,
                $net_income,
                $total_expenditures,
                $expected_amort,
                $remaining_income
            );
            $stmt_fin->execute();

            echo "<div class='success'>✓ Created user: $first_name $last_name (ID: $user_id)</div>";
        }
    }

    echo "<h2>💰 Creating Sample Loan Applications...</h2>";

    // Get all user IDs
    $result = $conn->query("SELECT id FROM users1 ORDER BY id DESC LIMIT $NUM_USERS");
    $user_ids = [];
    while ($row = $result->fetch_assoc()) {
        $user_ids[] = $row['id'];
    }

    // Get loan type ID
    $loan_type_result = $conn->query("SELECT loan_type_id FROM loan_types LIMIT 1");
    $loan_type_id = $loan_type_result->fetch_assoc()['loan_type_id'] ?? 1;

    $months_ago_range = 12; // Create loans from the past 12 months

    for ($i = 0; $i < $NUM_LOANS; $i++) {
        if (empty($user_ids))
            break;

        $user_id = $user_ids[array_rand($user_ids)];
        $loan_amount = rand(10, 100) * 1000; // 10k to 100k
        $term_length = [6, 12, 18, 24][rand(0, 3)];
        $interest_rate = rand(5, 8);
        $purpose = $loan_purposes[array_rand($loan_purposes)];
        $project_type = $project_types[array_rand($project_types)];

        // Create applications from past months
        $months_ago = rand(0, $months_ago_range);
        $created_date = date('Y-m-d H:i:s', strtotime("-$months_ago months"));

        $status = ['Approved', 'Active', 'Pending', 'Under Review'][rand(0, 3)];
        if ($i < $NUM_LOANS * 0.7) { // 70% approved/active
            $status = ['Approved', 'Active'][rand(0, 1)];
        }

        $credit_status = ['Approved', 'Pending', 'Not Required'][rand(0, 2)];

        $stmt = $conn->prepare("INSERT INTO loan_applications (user_id, loan_type_id, final_loan_amount, term_length, 
                                interest_rate, repayment_frequency, purpose, project_type, loan_category, status, 
                                credit_investigation_status, created_at) 
                                VALUES (?, ?, ?, ?, ?, 'Monthly', ?, ?, 'Individual', ?, ?, ?)");

        $stmt->bind_param(
            "iididssss",
            $user_id,
            $loan_type_id,
            $loan_amount,
            $term_length,
            $interest_rate,
            $purpose,
            $project_type,
            $status,
            $credit_status,
            $created_date
        );

        if ($stmt->execute()) {
            $application_id = $conn->insert_id;
            $stats['loans_created']++;

            // If approved/active, create actual loan
            if ($status === 'Approved' || $status === 'Active') {
                $monthly_rate = $interest_rate / 100 / 12;
                $num_payments = $term_length;

                // Calculate monthly payment using loan amortization formula
                if ($monthly_rate > 0) {
                    $monthly_payment = $loan_amount * ($monthly_rate * pow(1 + $monthly_rate, $num_payments)) /
                        (pow(1 + $monthly_rate, $num_payments) - 1);
                } else {
                    $monthly_payment = $loan_amount / $num_payments;
                }

                $total_interest = ($monthly_payment * $num_payments) - $loan_amount;
                $total_principal = $loan_amount;

                $loan_status = ($status === 'Active') ? 'active' : 'active';
                $remaining_balance = $loan_amount;

                $stmt_loan = $conn->prepare("INSERT INTO loans (application_id, user_id, amount, duration, 
                                             monthly_payment, remaining_balance, total_paid, status, created_at, 
                                             payment_frequency, payment_amount, total_interest, total_principal) 
                                             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 'monthly', ?, ?, ?)");

                $stmt_loan->bind_param(
                    "iidddsssddd",
                    $application_id,
                    $user_id,
                    $loan_amount,
                    $term_length,
                    $monthly_payment,
                    $remaining_balance,
                    $loan_status,
                    $created_date,
                    $monthly_payment,
                    $total_interest,
                    $total_principal
                );

                if ($stmt_loan->execute()) {
                    $loan_id = $conn->insert_id;

                    // Create payment schedule
                    $due_date = new DateTime($created_date);
                    $due_date->modify('+1 month');

                    for ($p = 0; $p < $term_length; $p++) {
                        $interest_portion = $remaining_balance * $monthly_rate;
                        $principal_portion = $monthly_payment - $interest_portion;

                        $due_date_str = $due_date->format('Y-m-d');
                        $schedule_status = 'pending';
                        $amount_paid = 0;
                        $paid_at = null;

                        // Randomly mark some as paid
                        if ($p < ($term_length * rand(20, 70) / 100) && strtotime($due_date_str) < time()) {
                            $schedule_status = 'Paid';
                            $amount_paid = $monthly_payment;
                            $paid_at = date('Y-m-d H:i:s', strtotime($due_date_str . ' +' . rand(0, 5) . ' days'));
                            $stats['payments_created']++;
                        }

                        $stmt_sched = $conn->prepare("INSERT INTO payment_schedules (loan_id, due_date, amount, status, 
                                                      paid_at, amount_paid, interest_amount, principal_amount, 
                                                      interest_paid, principal_paid) 
                                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        $interest_paid_amount = ($schedule_status === 'Paid' ? $interest_portion : 0);
                        $principal_paid_amount = ($schedule_status === 'Paid' ? $principal_portion : 0);

                        $stmt_sched->bind_param(
                            "isdssddddd",
                            $loan_id,
                            $due_date_str,
                            $monthly_payment,
                            $schedule_status,
                            $paid_at,
                            $amount_paid,
                            $interest_portion,
                            $principal_portion,
                            $interest_paid_amount,
                            $principal_paid_amount
                        );

                        $stmt_sched->execute();
                        $due_date->modify('+1 month');
                        $remaining_balance -= $principal_portion;
                    }

                    echo "<div class='success'>✓ Created loan #$loan_id for user #$user_id - ₱" . number_format($loan_amount, 2) . " ($term_length months)</div>";
                }
            }
        }
    }

    // Commit transaction
    $conn->commit();

    echo "<h2>✅ Database Population Complete!</h2>";
    echo "<div class='stats'>";
    echo "<div class='stat-card'><div class='stat-number'>{$stats['users_created']}</div><div class='stat-label'>Users Created</div></div>";
    echo "<div class='stat-card'><div class='stat-number'>{$stats['loans_created']}</div><div class='stat-label'>Loans Created</div></div>";
    echo "<div class='stat-card'><div class='stat-number'>{$stats['payments_created']}</div><div class='stat-label'>Payments Created</div></div>";
    echo "</div>";

    echo "<div class='success' style='font-size: 16px; margin-top: 30px;'>
            <strong>🎉 Success!</strong> Your database has been populated with realistic sample data.<br><br>
            <strong>Test Credentials:</strong><br>
            • Any generated email (e.g., juan.santos0@example.com)<br>
            • Password: <code>password123</code><br><br>
            <a href='reports_record.php' style='display: inline-block; margin-top: 10px; padding: 12px 24px; background: #2e7d32; color: white; text-decoration: none; border-radius: 6px;'>
                📊 View Reports Dashboard
            </a>
          </div>";

} catch (Exception $e) {
    $conn->rollback();
    echo "<div class='error'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
    $stats['errors']++;
}

echo "</div></body></html>";

$conn->close();
?>