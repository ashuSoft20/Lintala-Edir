<?php
session_start();
header('Content-Type: application/json');

// Security Check: Only Finance officers or Admins can access
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['finance', 'admin'])) {
    echo json_encode(["status" => "unauthorized", "message" => "Access denied."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// Get filter parameters from request (defaults to 'ALL')
$filter_year = $_GET['year'] ?? 'ALL';
$filter_month = $_GET['month'] ?? 'ALL';

// 1. Total Paid Fines / Punishments (Inflow) from contributions table
$fines_paid_res = $conn->query("SELECT SUM(punishment_price) AS total FROM contributions WHERE status = 'Paid'");
$total_paid_fines = floatval($fines_paid_res->fetch_assoc()['total'] ?? 0);

// 2. Total Paid Base Contributions (Inflow, excluding fines to avoid double counting)
$contrib_paid_res = $conn->query("SELECT SUM(amount - punishment_price) AS total FROM contributions WHERE status = 'Paid'");
$total_paid_contributions = floatval($contrib_paid_res->fetch_assoc()['total'] ?? 0);

// 3. Total Pending / Unpaid Fines (Receivable) from contributions table
$fines_unpaid_res = $conn->query("SELECT SUM(punishment_price) AS total FROM contributions WHERE status = 'UnPaid'");
$total_unpaid_fines = floatval($fines_unpaid_res->fetch_assoc()['total'] ?? 0);

// 4. Total Pending / Unpaid Base Contributions (Receivable, excluding fines)
$contrib_unpaid_res = $conn->query("SELECT SUM(amount - punishment_price) AS total FROM contributions WHERE status = 'UnPaid'");
$total_unpaid_contributions = floatval($contrib_unpaid_res->fetch_assoc()['total'] ?? 0);

// Pool Totals (Summing total actual collected amount and total receivables)
$total_pool_balance = $total_paid_contributions + $total_paid_fines;
$total_pending_receivables = $total_unpaid_contributions + $total_unpaid_fines;

// Fetch available years for the filter dropdown
$years_res = $conn->query("SELECT DISTINCT contribution_year FROM contributions ORDER BY contribution_year DESC");
$available_years = [];
while ($y_row = $years_res->fetch_assoc()) {
    $available_years[] = intval($y_row['contribution_year']);
}

// 5. Detailed Ledger History (All Paid Transactions from contributions with dynamic filters)
$ledger_sql = "
    SELECT username, 'Contribution Record' AS category, CONCAT(month_name, ' ', contribution_year) AS particulars, amount, payment_date AS tx_date 
    FROM contributions WHERE status = 'Paid'
";

if ($filter_year !== 'ALL') {
    $ledger_sql .= " AND contribution_year = " . intval($filter_year);
}
if ($filter_month !== 'ALL') {
    $ledger_sql .= " AND month_name = '" . $conn->real_escape_string($filter_month) . "'";
}

$ledger_sql .= " ORDER BY tx_date DESC";
$ledger_res = $conn->query($ledger_sql);

$ledger_transactions = [];
while ($row = $ledger_res->fetch_assoc()) {
    $ledger_transactions[] = $row;
}

echo json_encode([
    "status" => "success",
    "full_name" => $_SESSION['full_name'] ?? $_SESSION['username'],
    "total_paid_contributions" => $total_paid_contributions,
    "total_paid_fines" => $total_paid_fines,
    "total_pool_balance" => $total_pool_balance,
    "total_pending_receivables" => $total_pending_receivables,
    "available_years" => $available_years,
    "selected_year" => $filter_year,
    "selected_month" => $filter_month,
    "ledger_transactions" => $ledger_transactions
]);

$conn->close();
?>