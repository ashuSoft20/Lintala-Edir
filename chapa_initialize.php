<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$contribution_id = intval($input['id'] ?? 0);
$username = $_SESSION['username'];

if ($contribution_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid contribution ID."]);
    exit();
}

require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

$stmt = $conn->prepare("SELECT id, amount, month_name, contribution_year FROM contributions WHERE id = ? AND username = ?");
$stmt->bind_param("is", $contribution_id, $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $conn->close();
    echo json_encode(["status" => "error", "message" => "Billing record not found."]);
    exit();
}

$tx_ref = "LINTALA-" . time() . "-" . $row['id'];
$amount = floatval($row['amount']);
$email = filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : "member" . $row['id'] . "@lintalaedir.com";
$first_name = $_SESSION['full_name'] ?? 'Member';
$chapa_secret_key = "CHASECK_TEST-b91QPXMj6kW5u5GkMqqAtQP2LYtI74hM";

// Log pending/initiated transaction in database
$log_stmt = $conn->prepare("INSERT INTO transactions (username, contribution_id, tx_ref, amount, status) VALUES (?, ?, ?, ?, 'Pending')");
$log_stmt->bind_param("sisi", $username, $contribution_id, $tx_ref, $amount);
$log_stmt->execute();
$log_stmt->close();
$conn->close();

// CRITICAL FIX: Point return_url to the verification/callback script FIRST so it updates the database
// Use your exact filename (chapa_callback.php) and clean '&' symbols
$callback_url = "http://localhost/LintalaEdir/chapa_callback.php?id=" . $row['id'] . "&tx_ref=" . $tx_ref;
$return_url   = "http://localhost/LintalaEdir/chapa_callback.php?id=" . $row['id'] . "&tx_ref=" . $tx_ref;
$payload = [
    'amount'        => $amount,
    'currency'      => 'ETB',
    'email'         => $email,
    'first_name'    => $first_name,
    'tx_ref'        => $tx_ref,
    'callback_url'  => $callback_url,
    'return_url'    => $return_url,
    'customization' => [
        'title'       => 'Lintala Edir',
        'description' => 'Payment for ' . $row['month_name'] . ' ' . $row['contribution_year']
    ]
];

$ch = curl_init('https://api.chapa.co/v1/transaction/initialize');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $chapa_secret_key,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['status']) && $result['status'] === 'success') {
    echo json_encode([
        "status"       => "success",
        "checkout_url" => $result['data']['checkout_url']
    ]);
} else {
    echo json_encode([
        "status"  => "error",
        "message" => $result['message'] ?? 'Failed to initialize payment gateway.'
    ]);
}
exit();
?>