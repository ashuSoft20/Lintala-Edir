<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting to catch and output debugging issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_role = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['username']) || !in_array($user_role, ['finance', 'admin'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$month_name        = isset($input['month_name']) ? trim($input['month_name']) : '';
$contribution_year = isset($input['contribution_year']) ? intval($input['contribution_year']) : intval(date('Y'));
$activate          = isset($input['activate']) ? (bool)$input['activate'] : false;

if (empty($month_name) || $contribution_year <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid month or year selected."]);
    exit();
}

$action = $_GET['action'] ?? '';

if ($action === 'toggle_all') {
    if ($activate) {
        // 1. Select members strictly by role
        $users_query = "SELECT username FROM users WHERE LOWER(role) IN ('member', 'members')";
        $users_result = $conn->query($users_query);

        if (!$users_result || $users_result->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "No regular members found in the 'users' table."]);
            exit();
        }

        // 2. Fetch dynamic association settings strictly from database (no hardcoding)
        $settings_query = "SELECT monthly_contribution, late_punishment_fee FROM association_settings LIMIT 1";
        $settings_res = $conn->query($settings_query);
        
        $base_fee = 0.00;
        $late_punishment_fee = 0.00;

        if ($settings_res && $settings_res->num_rows > 0) {
            $settings = $settings_res->fetch_assoc();
            if (isset($settings['monthly_contribution']) && floatval($settings['monthly_contribution']) >= 0) {
                $base_fee = floatval($settings['monthly_contribution']);
            }
            if (isset($settings['late_punishment_fee']) && floatval($settings['late_punishment_fee']) >= 0) {
                $late_punishment_fee = floatval($settings['late_punishment_fee']);
            }
        }

        $processed_count = 0;

        while ($user = $users_result->fetch_assoc()) {
            $username = $user['username'];

            // 3. Count truly active past unpaid months for punishment calculation
            $count_stmt = $conn->prepare("SELECT COUNT(DISTINCT month_name) AS unpaid_count FROM contributions WHERE username = ? AND status = 'UnPaid' AND NOT (month_name = ? AND contribution_year = ?)");
            $count_stmt->bind_param("ssi", $username, $month_name, $contribution_year);
            $count_stmt->execute();
            $count_res = $count_stmt->get_result()->fetch_assoc();
            $unpaid_count = intval($count_res['unpaid_count']);
            $count_stmt->close();

            // 4. Calculate total unique punishment price based strictly on past unpaid count
            $punishment_price = $unpaid_count * $late_punishment_fee;

            // 5. Fetch the exact total previous unpaid amount from actual active past unpaid rows
            $sum_stmt = $conn->prepare("SELECT SUM(amount) AS total_prev_amount FROM contributions WHERE username = ? AND status = 'UnPaid' AND NOT (month_name = ? AND contribution_year = ?)");
            $sum_stmt->bind_param("ssi", $username, $month_name, $contribution_year);
            $sum_stmt->execute();
            $sum_res = $sum_stmt->get_result()->fetch_assoc();
            $prev_unpaid_total = floatval($sum_res['total_prev_amount'] ?? 0);
            $sum_stmt->close();

            // 6. Total required amount = Current Month Base Fee + Carried Forward Total Past Unpaid Amount + Punishment Price
            $total_required_amount = $base_fee + $prev_unpaid_total + $punishment_price;

            // 7. Update previous active unpaid records to 'XXX' now that they are bundled/rolled forward into this active window
            if ($unpaid_count > 0) {
                $update_old_stmt = $conn->prepare("UPDATE contributions SET status = 'XXX' WHERE username = ? AND status = 'UnPaid' AND NOT (month_name = ? AND contribution_year = ?)");
                $update_old_stmt->bind_param("ssi", $username, $month_name, $contribution_year);
                $update_old_stmt->execute();
                $update_old_stmt->close();
            }

            // 8. Check if current window month contribution record exists for this member
            $check_stmt = $conn->prepare("SELECT id FROM contributions WHERE username = ? AND month_name = ? AND contribution_year = ?");
            $check_stmt->bind_param("ssi", $username, $month_name, $contribution_year);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($existing) {
                $update_stmt = $conn->prepare("UPDATE contributions SET is_active = 1, amount = ?, punishment_price = ? WHERE id = ?");
                $update_stmt->bind_param("ddi", $total_required_amount, $punishment_price, $existing['id']);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO contributions (username, month_name, contribution_year, amount, punishment_price, status, is_active, payment_date) VALUES (?, ?, ?, ?, ?, 'UnPaid', 1, NULL)");
                $insert_stmt->bind_param("ssidd", $username, $month_name, $contribution_year, $total_required_amount, $punishment_price);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            $processed_count++;
        }

        echo json_encode([
            "status" => "success",
            "message" => "Successfully activated contribution window for $month_name $contribution_year ($processed_count member records updated)."
        ]);
    } else {
        $deactivate_stmt = $conn->prepare("UPDATE contributions SET is_active = 0 WHERE month_name = ? AND contribution_year = ?");
        $deactivate_stmt->bind_param("si", $month_name, $contribution_year);
        $deactivate_stmt->execute();
        $affected = $deactivate_stmt->affected_rows;
        $deactivate_stmt->close();

        echo json_encode([
            "status" => "success",
            "message" => "Deactivated contribution window for $month_name $contribution_year ($affected records updated)."
        ]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid action requested."]);
}

$conn->close();
?>