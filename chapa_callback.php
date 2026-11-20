<?php
session_start();

// Handle both normal and encoded &amp; parameters safely
$contribution_id = intval($_GET['id'] ?? $_GET['contribution_id'] ?? 0);
$tx_ref = trim($_GET['tx_ref'] ?? $_GET['amp;tx_ref'] ?? '');

// Fallback: If contribution_id is missing, try to extract it from tx_ref string (e.g., LINTALA-timestamp-19)
if ($contribution_id <= 0 && !empty($tx_ref)) {
    $parts = explode('-', $tx_ref);
    $last_part = end($parts);
    if (is_numeric($last_part)) {
        $contribution_id = intval($last_part);
    }
}

if ($contribution_id <= 0) {
    die("Invalid callback parameters: Contribution ID could not be resolved.");
}

require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// 1. Optional lookup to ensure we have the correct username
$username = '';
$stmt_u = $conn->prepare("SELECT username FROM contributions WHERE id = ? LIMIT 1");
$stmt_u->bind_param("i", $contribution_id);
$stmt_u->execute();
$res_u = $stmt_u->get_result();
if ($row_u = $res_u->fetch_assoc()) {
    $username = $row_u['username'];
}
$stmt_u->close();

// 2. Begin database transaction to update statuses to 'Paid'
$conn->begin_transaction();

try {
    // Mark contribution as Paid
    $stmt1 = $conn->prepare("UPDATE contributions SET status = 'Paid', payment_date = NOW(), is_active = 0 WHERE id = ?");
    $stmt1->bind_param("i", $contribution_id);
    $stmt1->execute();
    $stmt1->close();

    // Update transaction log status to 'Paid'
    if (!empty($tx_ref)) {
        $stmt2 = $conn->prepare("UPDATE transactions SET status = 'Paid' WHERE tx_ref = ? OR contribution_id = ?");
        $stmt2->bind_param("si", $tx_ref, $contribution_id);
    } else {
        $stmt2 = $conn->prepare("UPDATE transactions SET status = 'Paid' WHERE contribution_id = ?");
        $stmt2->bind_param("i", $contribution_id);
    }
    $stmt2->execute();
    $stmt2->close();

    // Clear user's pending punishments/fines if username exists
    if (!empty($username)) {
        $stmt3 = $conn->prepare("UPDATE punishments SET status = 'Paid' WHERE username = ? AND status != 'Paid'");
        $stmt3->bind_param("s", $username);
        $stmt3->execute();
        $stmt3->close();
    }

    $conn->commit();
    $conn->close();

    // Success response and JavaScript redirect to prevent back-button loops
    // Success response and JavaScript redirect with lowercase 'members' folder path
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Payment Successful</title>
        <script>
            window.location.replace("members/contribution.html?payment=success");
        </script>
    </head>
    <body style="font-family: Arial, sans-serif; text-align: center; padding-top: 80px; background-color: #f9f9f9;">
        <div style="max-width: 400px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
            <h2 style="color: #0f766e;">Payment Successful! 🎉</h2>
            <p style="color: #666; margin-bottom: 20px;">Your contribution and transaction records have been updated to Paid.</p>
            <a href="members/contribution.html?payment=success" style="color: #0f766e; font-weight: bold;">Click here if not redirected automatically</a>
        </div>
    </body>
    </html>';
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    die("Database Error: " . $e->getMessage());
}
?>