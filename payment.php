<?php
require_once 'db.php';
requireLogin();

$uid = $_SESSION['user_id'];
$error = ''; $success = '';

// ── GET BOOKING ──
$booking_id = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
if (!$booking_id) { header("Location: dashboard.php"); exit; }

// Load booking details
$booking = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT b.*, p.name pname, p.age, p.gender, p.journey_date,
           t.train_name, t.train_number, t.from_station, t.to_station,
           t.departure_time, t.arrival_time, u.name uname, u.email uemail, u.phone uphone
    FROM bookings b
    LEFT JOIN passengers p ON b.passenger_id = p.passenger_id
    LEFT JOIN trains t ON b.train_id = t.train_id
    LEFT JOIN users u ON b.user_id = u.user_id
    WHERE b.booking_id = $booking_id AND b.user_id = $uid
"));

if (!$booking) { header("Location: dashboard.php"); exit; }

// Check if already paid
$existing_pay = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM payments WHERE booking_id=$booking_id AND payment_status='Success'"));
$already_paid = $existing_pay ? true : false;

// ── PROCESS PAYMENT ──
$payment_done = false;
$payment_record = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_pay']) && !$already_paid) {
    $method    = clean($conn, $_POST['pay_method'] ?? 'Card');
    $amount    = (float)$booking['fare'];

    // Card details (for card payment)
    $card_num  = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_last4= strlen($card_num) >= 4 ? substr($card_num, -4) : '0000';
    $card_name = clean($conn, $_POST['card_name'] ?? '');
    $card_exp  = clean($conn, $_POST['card_exp'] ?? '');
    $card_cvv  = $_POST['card_cvv'] ?? '';

    // UPI
    $upi_id    = clean($conn, $_POST['upi_id'] ?? '');

    // Netbanking
    $bank      = clean($conn, $_POST['bank'] ?? '');

    // Basic validation
    $valid = true;
    if ($method === 'Card') {
        if (strlen($card_num) < 16) { $error = "Please enter a valid 16-digit card number."; $valid = false; }
        elseif (!$card_name)        { $error = "Please enter the cardholder name."; $valid = false; }
        elseif (!$card_exp)         { $error = "Please enter the card expiry date."; $valid = false; }
        elseif (strlen($card_cvv) < 3) { $error = "Please enter a valid CVV."; $valid = false; }
    } elseif ($method === 'UPI') {
        if (!$upi_id || !str_contains($upi_id, '@')) { $error = "Please enter a valid UPI ID (e.g. name@upi)."; $valid = false; }
    } elseif ($method === 'Netbanking') {
        if (!$bank) { $error = "Please select your bank."; $valid = false; }
    }

    if ($valid) {
        // Simulate payment — generate transaction ID
        $txn_id = 'TXN' . strtoupper(substr(md5(uniqid()), 0, 12));

        // Insert payment record
        mysqli_query($conn, "INSERT INTO payments (booking_id, user_id, amount, payment_method, payment_status, transaction_id, card_last4)
            VALUES ($booking_id, $uid, $amount, '$method', 'Success', '$txn_id', '$card_last4')");
        $pay_id = mysqli_insert_id($conn);

        // Update booking to Confirmed
        mysqli_query($conn, "UPDATE bookings SET booking_status='Confirmed' WHERE booking_id=$booking_id");

        // Reload payment record
        $payment_record = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM payments WHERE payment_id=$pay_id"));
        $booking['booking_status'] = 'Confirmed';
        $payment_done = true;
    }
}

// If already paid, load that record
if ($already_paid) {
    $payment_record = $existing_pay;
    $payment_done   = true;
}

$fare    = (float)$booking['fare'];
$taxes   = round($fare * 0.05, 2);   // 5% GST
$conv_fee= 25.00;                     // convenience fee
$total   = $fare + $taxes + $conv_fee;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RailMatrix — Payment</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700;900&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
  --bg:      #05080f;
  --srf:     #0b0f1a;
  --srf2:    #0f1623;
  --border:  rgba(255,255,255,0.07);
  --border2: rgba(255,255,255,0.13);
  --accent:  #6366f1;
  --accent2: #818cf8;
  --gold:    #f59e0b;
  --green:   #10b981;
  --red:     #ef4444;
  --text:    #f1f5f9;
  --muted:   #64748b;
}
html,body { min-height:100%; }
body {
  font-family:'Instrument Sans',sans-serif;
  background:var(--bg); color:var(--text);
  min-height:100vh;
}

body::before {
  content:'';
  position:fixed; inset:0;
  background:
    radial-gradient(ellipse at 20% 10%, rgba(99,102,241,0.08) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 90%, rgba(245,158,11,0.05) 0%, transparent 50%);
  pointer-events:none;
}

/* TOPBAR */
.topbar {
  height:62px; padding:0 40px;
  display:flex; align-items:center; justify-content:space-between;
  background:rgba(11,15,26,0.97); backdrop-filter:blur(12px);
  border-bottom:1px solid var(--border);
  position:sticky; top:0; z-index:100;
}
.tb-logo { display:flex; align-items:center; gap:12px; text-decoration:none; }
.tb-logo-icon { width:36px;height:36px; background:linear-gradient(135deg,var(--accent),#4f46e5); border-radius:8px; display:flex;align-items:center;justify-content:center; font-size:17px; }
.tb-logo-name { font-family:'Fraunces',serif; font-size:18px;font-weight:900; color:var(--text); letter-spacing:-0.5px; }
.tb-logo-name span { color:var(--accent2); }
.tb-right { display:flex; align-items:center; gap:14px; font-size:13px; color:var(--muted); }
.secure-pill { display:flex;align-items:center;gap:6px; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); color:#34d399; padding:5px 13px; border-radius:20px; font-size:12px; font-weight:600; }

/* MAIN LAYOUT */
.page-wrap {
  max-width:1060px; margin:0 auto;
  padding:36px 24px 60px;
  position:relative; z-index:1;
}

/* STEPS BAR */
.steps {
  display:flex; align-items:center; justify-content:center;
  gap:0; margin-bottom:36px;
}
.step {
  display:flex; align-items:center; gap:10px;
  font-size:13px; font-weight:600; color:var(--muted);
  padding:10px 20px; border-radius:8px;
}
.step.done { color:var(--green); }
.step.active { color:var(--accent2); }
.step-num {
  width:26px; height:26px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:12px; font-weight:700;
  background:rgba(255,255,255,0.06); border:1px solid var(--border2);
  flex-shrink:0;
}
.step.done .step-num  { background:rgba(16,185,129,0.15); border-color:rgba(16,185,129,0.3); color:var(--green); }
.step.active .step-num{ background:rgba(99,102,241,0.15); border-color:rgba(99,102,241,0.3); color:var(--accent2); }
.step-line { flex:1; height:1px; background:var(--border); max-width:60px; }

/* TWO COL */
.grid { display:grid; grid-template-columns:1fr 380px; gap:24px; align-items:start; }

/* CARD */
.card {
  background:var(--srf); border:1px solid var(--border);
  border-radius:16px; overflow:hidden;
}
.card-hd {
  padding:18px 22px 16px; border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
}
.card-title { font-family:'Fraunces',serif; font-size:17px;font-weight:700; }
.card-sub   { font-size:12px; color:var(--muted); margin-top:2px; }
.card-body  { padding:22px; }

/* BOOKING SUMMARY */
.summary-row {
  display:flex; align-items:flex-start; justify-content:space-between;
  padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.04);
  font-size:14px;
}
.summary-row:last-child { border-bottom:none; }
.sr-label { color:var(--muted); font-size:13px; }
.sr-val   { font-weight:600; color:var(--text); text-align:right; }

.route-display {
  background:rgba(99,102,241,0.06); border:1px solid rgba(99,102,241,0.15);
  border-radius:11px; padding:16px 18px; margin-bottom:16px;
  display:flex; align-items:center; gap:16px;
}
.rd-station { text-align:center; flex:1; }
.rd-name  { font-family:'Fraunces',serif; font-size:16px;font-weight:700; }
.rd-time  { font-size:12px; color:var(--muted); margin-top:3px; }
.rd-arrow { display:flex;flex-direction:column;align-items:center;gap:4px; flex-shrink:0; }
.rd-line  { height:1px; width:50px; background:rgba(99,102,241,0.4); }
.rd-icon  { font-size:18px; }

/* FARE BREAKDOWN */
.fare-row { display:flex;justify-content:space-between;align-items:center;padding:9px 0;font-size:13px; border-bottom:1px solid rgba(255,255,255,0.04); }
.fare-row:last-child { border-bottom:none; }
.fare-label { color:var(--muted); }
.fare-val   { font-weight:600; }
.fare-total { font-size:15px;font-weight:700; color:var(--gold); margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.08); display:flex;justify-content:space-between;align-items:center; }

/* PAYMENT METHOD TABS */
.pay-tabs { display:flex; gap:8px; margin-bottom:22px; }
.pay-tab-btn {
  flex:1; padding:11px 8px; border-radius:9px;
  background:rgba(255,255,255,0.03); border:1px solid var(--border2);
  color:var(--muted); font-family:'Instrument Sans',sans-serif;
  font-size:13px; font-weight:600; cursor:pointer; transition:all 0.15s;
  display:flex; flex-direction:column; align-items:center; gap:5px;
}
.pay-tab-btn:hover { color:var(--text); border-color:rgba(255,255,255,0.2); }
.pay-tab-btn.active { border-color:var(--accent); background:rgba(99,102,241,0.08); color:var(--accent2); }
.pay-tab-icon { font-size:20px; }

/* FORM */
.field { margin-bottom:15px; }
.field label { display:block;font-size:11px;font-weight:700;color:rgba(241,245,249,0.45);margin-bottom:7px;letter-spacing:0.7px;text-transform:uppercase; }
.field input, .field select {
  width:100%; background:rgba(255,255,255,0.03);
  border:1px solid var(--border2); border-radius:9px;
  color:var(--text); font-family:'Instrument Sans',sans-serif;
  font-size:14px; padding:11px 14px; outline:none; transition:all 0.2s;
  -webkit-appearance:none;
}
.field input:focus, .field select:focus { border-color:var(--accent); background:rgba(99,102,241,0.04); }
.field input::placeholder { color:var(--muted); }
.field select option { background:#0f1623; }
.two-field { display:grid;grid-template-columns:1fr 1fr;gap:12px; }

/* CARD PREVIEW */
.card-preview {
  background:linear-gradient(135deg, #1a1f3c 0%, #2d1b69 50%, #1a1f3c 100%);
  border:1px solid rgba(99,102,241,0.3); border-radius:14px;
  padding:22px 22px 18px; margin-bottom:20px; position:relative; overflow:hidden;
}
.card-preview::before {
  content:'';
  position:absolute; top:-40px; right:-40px;
  width:120px; height:120px; border-radius:50%;
  background:rgba(99,102,241,0.15);
}
.card-preview::after {
  content:'';
  position:absolute; bottom:-30px; left:-30px;
  width:90px; height:90px; border-radius:50%;
  background:rgba(245,158,11,0.08);
}
.cp-chip { width:40px; height:30px; background:linear-gradient(135deg,var(--gold),#d97706); border-radius:5px; margin-bottom:18px; }
.cp-num  { font-size:18px; font-weight:600; letter-spacing:3px; color:rgba(255,255,255,0.9); margin-bottom:16px; font-family:monospace; }
.cp-bottom { display:flex; justify-content:space-between; align-items:flex-end; }
.cp-label { font-size:10px; color:rgba(255,255,255,0.4); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px; }
.cp-value { font-size:13px; font-weight:600; color:rgba(255,255,255,0.85); }
.cp-brand { font-size:26px; }

/* PAY BUTTON */
.btn-pay {
  width:100%; padding:15px;
  background:linear-gradient(135deg, var(--accent), #4f46e5);
  border:none; border-radius:11px;
  font-family:'Instrument Sans',sans-serif; font-size:16px; font-weight:700;
  color:white; cursor:pointer; margin-top:8px; transition:all 0.2s;
  display:flex; align-items:center; justify-content:center; gap:10px;
}
.btn-pay:hover { transform:translateY(-2px); box-shadow:0 10px 30px rgba(99,102,241,0.4); }
.btn-pay:active { transform:translateY(0); }

.security-note {
  display:flex; align-items:center; gap:8px; justify-content:center;
  font-size:12px; color:var(--muted); margin-top:14px;
}

/* ALERT */
.alert { padding:13px 16px;border-radius:9px;font-size:13px;display:flex;align-items:center;gap:10px;margin-bottom:20px;animation:ain 0.3s ease; }
@keyframes ain{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.a-err { background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:var(--red); }

/* ── SUCCESS / RECEIPT ── */
.receipt-wrap { max-width:640px; margin:0 auto; }

.success-banner {
  background:linear-gradient(135deg, rgba(16,185,129,0.1), rgba(16,185,129,0.05));
  border:1px solid rgba(16,185,129,0.25);
  border-radius:16px; padding:32px; text-align:center; margin-bottom:24px;
  animation:popIn 0.5s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes popIn{from{opacity:0;transform:scale(0.85)}to{opacity:1;transform:scale(1)}}

.success-icon {
  width:72px; height:72px; border-radius:50%;
  background:rgba(16,185,129,0.15); border:2px solid rgba(16,185,129,0.3);
  display:flex; align-items:center; justify-content:center;
  font-size:34px; margin:0 auto 18px;
}
.success-title { font-family:'Fraunces',serif; font-size:28px;font-weight:900; color:var(--text); margin-bottom:8px; }
.success-sub   { font-size:14px; color:var(--muted); line-height:1.6; }
.txn-id {
  display:inline-block; margin-top:14px;
  background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.2);
  color:#34d399; padding:7px 18px; border-radius:20px;
  font-size:13px; font-weight:700; font-family:monospace; letter-spacing:0.5px;
}

/* RECEIPT CARD */
.receipt {
  background:var(--srf); border:1px solid var(--border);
  border-radius:16px; overflow:hidden;
  print-color-adjust:exact;
}

.receipt-header {
  padding:22px 28px; background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(99,102,241,0.05));
  border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
}

.receipt-logo { font-family:'Fraunces',serif; font-size:20px;font-weight:900; }
.receipt-logo span { color:var(--accent2); }
.receipt-badge { background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2); color:#34d399; padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700; }

.receipt-body { padding:28px; }

.receipt-route {
  background:rgba(99,102,241,0.05); border:1px solid rgba(99,102,241,0.12);
  border-radius:12px; padding:18px 22px; margin-bottom:22px;
  display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:16px;
}
.rr-st   { }
.rr-code { font-family:'Fraunces',serif; font-size:24px;font-weight:900; color:var(--text); }
.rr-name { font-size:12px; color:var(--muted); margin-top:2px; }
.rr-mid  { text-align:center; }
.rr-train-name { font-size:11px;color:var(--accent2);font-weight:600;margin-bottom:4px; }
.rr-arrow{ font-size:20px; }
.rr-right{ text-align:right; }

.receipt-grid {
  display:grid; grid-template-columns:1fr 1fr;
  gap:0;
}
.rg-cell {
  padding:13px 0; border-bottom:1px solid rgba(255,255,255,0.04);
}
.rg-cell:nth-child(odd)  { padding-right:20px; border-right:1px solid rgba(255,255,255,0.04); }
.rg-cell:nth-child(even) { padding-left:20px; }
.rg-label { font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px; }
.rg-val   { font-size:14px;font-weight:600;color:var(--text); }

.receipt-footer {
  padding:18px 28px; background:rgba(255,255,255,0.02);
  border-top:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
}

.amount-badge {
  background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(245,158,11,0.05));
  border:1px solid rgba(245,158,11,0.25); border-radius:10px;
  padding:10px 20px;
}
.ab-label { font-size:11px;color:var(--muted);margin-bottom:3px; }
.ab-amount{ font-family:'Fraunces',serif; font-size:26px;font-weight:700; color:var(--gold); }

.receipt-actions { display:flex; gap:10px; }

/* BUTTONS */
.btn { padding:10px 20px; border-radius:9px; font-family:'Instrument Sans',sans-serif; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex;align-items:center;gap:8px; transition:all 0.15s; border:none; }
.btn-outline { background:rgba(255,255,255,0.05);border:1px solid var(--border2);color:var(--muted); }
.btn-outline:hover { background:rgba(255,255,255,0.08);color:var(--text); }
.btn-primary { background:linear-gradient(135deg,var(--accent),#4f46e5); color:white; }
.btn-primary:hover { box-shadow:0 6px 20px rgba(99,102,241,0.3); }
.btn-green { background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#34d399; }
.btn-green:hover { background:rgba(16,185,129,0.18); }

/* UPI SECTION */
.upi-logos { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
.upi-logo {
  padding:8px 16px; border-radius:8px;
  background:rgba(255,255,255,0.04); border:1px solid var(--border2);
  font-size:13px; font-weight:600; color:var(--muted); cursor:pointer; transition:all 0.15s;
}
.upi-logo:hover { border-color:rgba(255,255,255,0.2); color:var(--text); }
.upi-logo.sel { border-color:var(--accent); color:var(--accent2); background:rgba(99,102,241,0.08); }

/* NETBANKING */
.bank-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px; }
.bank-opt { display:none; }
.bank-opt + label {
  display:flex;flex-direction:column;align-items:center;gap:5px;
  padding:12px 8px; background:rgba(255,255,255,0.03);
  border:1px solid var(--border2); border-radius:9px;
  cursor:pointer; transition:all 0.15s;
  font-size:12px;font-weight:600;color:var(--muted);text-align:center;
}
.bank-opt + label:hover { border-color:rgba(255,255,255,0.2);color:var(--text); }
.bank-opt:checked + label { border-color:var(--accent);background:rgba(99,102,241,0.08);color:var(--accent2); }
.bank-icon { font-size:24px; }

/* WALLET */
.wallet-grid { display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px; }
.wallet-opt { display:none; }
.wallet-opt + label {
  display:flex;align-items:center;gap:10px;
  padding:13px 14px; background:rgba(255,255,255,0.03);
  border:1px solid var(--border2); border-radius:9px;
  cursor:pointer; transition:all 0.15s;
  font-size:13px;font-weight:600;color:var(--muted);
}
.wallet-opt + label:hover { border-color:rgba(255,255,255,0.2);color:var(--text); }
.wallet-opt:checked + label { border-color:var(--accent);background:rgba(99,102,241,0.08);color:var(--accent2); }
.wallet-icon { font-size:22px; }

/* SCROLLBAR */
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.08);border-radius:4px;}

/* PRINT */
@media print {
  body { background:white; color:#000; }
  .topbar, .steps, .no-print { display:none !important; }
  .receipt { border:1px solid #ddd; background:white; }
  .receipt-header { background:#f8f9fa; }
  .card-hd, .card-body, .receipt-body { color:#000; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <a href="dashboard.php" class="tb-logo">
    <div class="tb-logo-icon">🚆</div>
    <div class="tb-logo-name">Rail<span>Matrix</span></div>
  </a>
  <div class="tb-right">
    <div class="secure-pill">🔒 256-bit SSL Secured</div>
    <span><?= date('d M Y') ?></span>
  </div>
</div>

<!-- STEPS -->
<div class="page-wrap">

  <div class="steps no-print">
    <div class="step done">
      <div class="step-num">✓</div> Booking Details
    </div>
    <div class="step-line"></div>
    <div class="step <?= $payment_done ? 'done' : 'active' ?>">
      <div class="step-num"><?= $payment_done ? '✓' : '2' ?></div> Payment
    </div>
    <div class="step-line"></div>
    <div class="step <?= $payment_done ? 'active' : '' ?>">
      <div class="step-num">3</div> Confirmation
    </div>
  </div>

  <!-- ════════════════ SUCCESS / RECEIPT ════════════════ -->
  <?php if ($payment_done): ?>

  <div class="receipt-wrap">

    <div class="success-banner">
      <div class="success-icon">✅</div>
      <div class="success-title">Payment Successful!</div>
      <p class="success-sub">Your ticket has been confirmed. Have a safe journey!</p>
      <div class="txn-id">TXN ID: <?= htmlspecialchars($payment_record['transaction_id']) ?></div>
    </div>

    <!-- RECEIPT -->
    <div class="receipt" id="receipt">
      <div class="receipt-header">
        <div>
          <div class="receipt-logo">Rail<span>Matrix</span></div>
          <div style="font-size:12px;color:var(--muted);margin-top:3px">E-Ticket / Tax Invoice</div>
        </div>
        <div style="text-align:right">
          <div class="receipt-badge">✓ CONFIRMED</div>
          <div style="font-size:11px;color:var(--muted);margin-top:6px">Booking #<?= $booking['booking_id'] ?></div>
        </div>
      </div>

      <div class="receipt-body">

        <!-- ROUTE -->
        <div class="receipt-route">
          <div class="rr-st">
            <div class="rr-code"><?= strtoupper(substr($booking['from_station'] ?? 'DEP', 0, 3)) ?></div>
            <div class="rr-name"><?= htmlspecialchars($booking['from_station'] ?? '—') ?></div>
            <div style="font-size:13px;color:var(--accent2);margin-top:4px;font-weight:600"><?= $booking['departure_time'] ? date('h:i A', strtotime($booking['departure_time'])) : '—' ?></div>
          </div>
          <div class="rr-mid">
            <div class="rr-train-name"><?= htmlspecialchars($booking['train_name'] ?? '') ?></div>
            <div class="rr-arrow">➜</div>
            <div style="font-size:11px;color:var(--muted);margin-top:3px"><?= htmlspecialchars($booking['train_number'] ?? '') ?></div>
          </div>
          <div class="rr-st rr-right">
            <div class="rr-code"><?= strtoupper(substr($booking['to_station'] ?? 'ARR', 0, 3)) ?></div>
            <div class="rr-name"><?= htmlspecialchars($booking['to_station'] ?? '—') ?></div>
            <div style="font-size:13px;color:var(--accent2);margin-top:4px;font-weight:600"><?= $booking['arrival_time'] ? date('h:i A', strtotime($booking['arrival_time'])) : '—' ?></div>
          </div>
        </div>

        <!-- PASSENGER & BOOKING DETAILS -->
        <div class="receipt-grid">
          <div class="rg-cell">
            <div class="rg-label">Passenger Name</div>
            <div class="rg-val"><?= htmlspecialchars($booking['pname'] ?? '—') ?></div>
          </div>
          <div class="rg-cell">
            <div class="rg-label">Age / Gender</div>
            <div class="rg-val"><?= $booking['age'] ?> / <?= htmlspecialchars($booking['gender'] ?? '') ?></div>
          </div>
          <div class="rg-cell">
            <div class="rg-label">Journey Date</div>
            <div class="rg-val"><?= $booking['journey_date'] ? date('D, d M Y', strtotime($booking['journey_date'])) : '—' ?></div>
          </div>
          <div class="rg-cell">
            <div class="rg-label">Seat Number</div>
            <div class="rg-val" style="color:var(--gold);font-family:monospace;font-size:16px"><?= htmlspecialchars($booking['seat_number'] ?? '—') ?></div>
          </div>
          <div class="rg-cell">
            <div class="rg-label">Booked By</div>
            <div class="rg-val"><?= htmlspecialchars($booking['uname'] ?? '—') ?></div>
          </div>
          <div class="rg-cell">
            <div class="rg-label">Contact</div>
            <div class="rg-val" style="font-size:12px"><?= htmlspecialchars($booking['uemail'] ?? '—') ?></div>
          </div>
          <div class="rg-cell">
            <div class="rg-label">Payment Method</div>
            <div class="rg-val"><?= htmlspecialchars($payment_record['payment_method']) ?><?= $payment_record['payment_method'] === 'Card' ? " ···· {$payment_record['card_last4']}" : '' ?></div>
          </div>
          <div class="rg-cell">
            <div class="rg-label">Payment Time</div>
            <div class="rg-val" style="font-size:12px"><?= date('d M Y, h:i A', strtotime($payment_record['paid_at'])) ?></div>
          </div>
        </div>

        <!-- FARE BREAKDOWN -->
        <div style="margin-top:20px;background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:10px;padding:16px 18px;">
          <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:12px;">Fare Breakdown</div>
          <div class="fare-row"><span class="fare-label">Base Fare</span><span class="fare-val">₹<?= number_format($fare, 2) ?></span></div>
          <div class="fare-row"><span class="fare-label">GST (5%)</span><span class="fare-val">₹<?= number_format($taxes, 2) ?></span></div>
          <div class="fare-row"><span class="fare-label">Convenience Fee</span><span class="fare-val">₹<?= number_format($conv_fee, 2) ?></span></div>
          <div class="fare-total"><span>Total Paid</span><span>₹<?= number_format($total, 2) ?></span></div>
        </div>

      </div>

      <div class="receipt-footer">
        <div class="amount-badge">
          <div class="ab-label">Amount Paid</div>
          <div class="ab-amount">₹<?= number_format($total, 2) ?></div>
        </div>
        <div class="receipt-actions no-print">
          <button class="btn btn-outline" onclick="window.print()">🖨 Print Ticket</button>
          <a href="dashboard.php?tab=bookings" class="btn btn-primary">My Bookings →</a>
        </div>
      </div>
    </div>

    <div style="text-align:center;margin-top:20px;font-size:12px;color:var(--muted)" class="no-print">
      A confirmation has been sent to <strong style="color:var(--text)"><?= htmlspecialchars($booking['uemail'] ?? '') ?></strong>
    </div>

  </div><!-- /receipt-wrap -->

  <?php else: ?>
  <!-- ════════════════ PAYMENT FORM ════════════════ -->

  <?php if ($error): ?>
  <div class="alert a-err">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="grid">

    <!-- LEFT: PAYMENT METHODS -->
    <div>
      <div class="card">
        <div class="card-hd">
          <div><div class="card-title">Choose Payment Method</div><div class="card-sub">All transactions are encrypted & secure</div></div>
        </div>
        <div class="card-body">
          <form method="POST" id="pay-form">
            <input type="hidden" name="do_pay" value="1">
            <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
            <input type="hidden" name="pay_method" id="pay_method_input" value="Card">

            <!-- METHOD TABS -->
            <div class="pay-tabs">
              <button type="button" class="pay-tab-btn active" onclick="switchMethod('Card',this)">
                <span class="pay-tab-icon">💳</span>Card
              </button>
              <button type="button" class="pay-tab-btn" onclick="switchMethod('UPI',this)">
                <span class="pay-tab-icon">📱</span>UPI
              </button>
              <button type="button" class="pay-tab-btn" onclick="switchMethod('Netbanking',this)">
                <span class="pay-tab-icon">🏦</span>Net Banking
              </button>
              <button type="button" class="pay-tab-btn" onclick="switchMethod('Wallet',this)">
                <span class="pay-tab-icon">👜</span>Wallet
              </button>
            </div>

            <!-- CARD SECTION -->
            <div id="section-Card">
              <div class="card-preview" id="card-preview">
                <div class="cp-chip"></div>
                <div class="cp-num" id="preview-num">•••• •••• •••• ••••</div>
                <div class="cp-bottom">
                  <div>
                    <div class="cp-label">Card Holder</div>
                    <div class="cp-value" id="preview-name">YOUR NAME</div>
                  </div>
                  <div>
                    <div class="cp-label">Expires</div>
                    <div class="cp-value" id="preview-exp">MM/YY</div>
                  </div>
                  <div class="cp-brand" id="preview-brand">💳</div>
                </div>
              </div>

              <div class="field">
                <label>Card Number</label>
                <input type="text" name="card_number" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCard(this)" autocomplete="cc-number">
              </div>
              <div class="field">
                <label>Cardholder Name</label>
                <input type="text" name="card_name" id="card_name" placeholder="As printed on card" oninput="updatePreview()" autocomplete="cc-name">
              </div>
              <div class="two-field">
                <div class="field">
                  <label>Expiry Date</label>
                  <input type="text" name="card_exp" id="card_exp" placeholder="MM/YY" maxlength="5" oninput="formatExp(this)" autocomplete="cc-exp">
                </div>
                <div class="field">
                  <label>CVV</label>
                  <input type="password" name="card_cvv" placeholder="•••" maxlength="4" autocomplete="cc-csc">
                </div>
              </div>
            </div>

            <!-- UPI SECTION -->
            <div id="section-UPI" style="display:none">
              <div style="margin-bottom:16px">
                <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">Quick select:</div>
                <div class="upi-logos">
                  <div class="upi-logo" onclick="selectUpi('GPay','name@okaxis')">🟢 GPay</div>
                  <div class="upi-logo" onclick="selectUpi('PhonePe','name@ybl')">🟣 PhonePe</div>
                  <div class="upi-logo" onclick="selectUpi('Paytm','name@paytm')">🔵 Paytm</div>
                  <div class="upi-logo" onclick="selectUpi('BHIM','name@upi')">🟠 BHIM</div>
                </div>
              </div>
              <div class="field">
                <label>UPI ID</label>
                <input type="text" name="upi_id" id="upi_id" placeholder="yourname@upi">
              </div>
              <div style="background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);border-radius:9px;padding:14px 16px;font-size:13px;color:var(--muted);margin-bottom:8px;">
                💡 You'll receive a payment request on your UPI app. Approve it to complete the payment.
              </div>
            </div>

            <!-- NETBANKING SECTION -->
            <div id="section-Netbanking" style="display:none">
              <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">Select your bank:</div>
              <div class="bank-grid">
                <?php
                $banks = [['SBI','🏛️'],['HDFC','🔵'],['ICICI','🟠'],['Axis','🔴'],['Kotak','🟡'],['PNB','🟢']];
                foreach($banks as [$b,$ic]):
                ?>
                <input type="radio" name="bank" id="bank_<?=$b?>" value="<?=$b?>" class="bank-opt">
                <label for="bank_<?=$b?>"><span class="bank-icon"><?=$ic?></span><?=$b?> Bank</label>
                <?php endforeach; ?>
              </div>
              <div class="field">
                <label>Other Bank</label>
                <select name="bank_other">
                  <option value="">Select if not listed above</option>
                  <?php foreach(['Yes Bank','IndusInd','IDFC First','Bank of Baroda','Canara Bank','Union Bank'] as $ob): ?>
                  <option value="<?=$ob?>"><?=$ob?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- WALLET SECTION -->
            <div id="section-Wallet" style="display:none">
              <div class="wallet-grid">
                <?php
                $wallets = [['Paytm Wallet','💙'],['Amazon Pay','🟡'],['Mobikwik','🔵'],['Freecharge','🟠']];
                foreach($wallets as [$w,$ic]):
                ?>
                <input type="radio" name="wallet" id="w_<?=str_replace(' ','_',$w)?>" value="<?=$w?>" class="wallet-opt">
                <label for="w_<?=str_replace(' ','_',$w)?>"><span class="wallet-icon"><?=$ic?></span><?=$w?></label>
                <?php endforeach; ?>
              </div>
              <div style="background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);border-radius:9px;padding:14px 16px;font-size:13px;color:var(--muted);">
                💡 Ensure your wallet has sufficient balance. You will be redirected to confirm.
              </div>
            </div>

            <button type="submit" class="btn-pay" id="pay-btn">
              🔒 Pay ₹<?= number_format($total, 2) ?>
            </button>

          </form>

          <div class="security-note">
            🔒 Your payment info is encrypted &nbsp;·&nbsp; PCI DSS Compliant &nbsp;·&nbsp; 100% Secure
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT: ORDER SUMMARY -->
    <div>
      <div class="card">
        <div class="card-hd">
          <div><div class="card-title">Order Summary</div><div class="card-sub">Booking #<?= $booking_id ?></div></div>
        </div>
        <div class="card-body">

          <!-- ROUTE -->
          <div class="route-display">
            <div class="rd-station">
              <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">From</div>
              <div class="rd-name"><?= htmlspecialchars($booking['from_station'] ?? '—') ?></div>
              <div class="rd-time"><?= $booking['departure_time'] ? date('h:i A', strtotime($booking['departure_time'])) : '' ?></div>
            </div>
            <div class="rd-arrow">
              <div class="rd-line"></div>
              <div class="rd-icon">🚆</div>
              <div class="rd-line"></div>
            </div>
            <div class="rd-station" style="text-align:right">
              <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">To</div>
              <div class="rd-name"><?= htmlspecialchars($booking['to_station'] ?? '—') ?></div>
              <div class="rd-time"><?= $booking['arrival_time'] ? date('h:i A', strtotime($booking['arrival_time'])) : '' ?></div>
            </div>
          </div>

          <!-- DETAILS -->
          <div class="summary-row">
            <span class="sr-label">Train</span>
            <span class="sr-val" style="font-size:13px"><?= htmlspecialchars($booking['train_name'] ?? '—') ?><br><span style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($booking['train_number'] ?? '') ?></span></span>
          </div>
          <div class="summary-row">
            <span class="sr-label">Passenger</span>
            <span class="sr-val"><?= htmlspecialchars($booking['pname'] ?? '—') ?></span>
          </div>
          <div class="summary-row">
            <span class="sr-label">Journey Date</span>
            <span class="sr-val"><?= $booking['journey_date'] ? date('D, d M Y', strtotime($booking['journey_date'])) : '—' ?></span>
          </div>
          <div class="summary-row">
            <span class="sr-label">Seat</span>
            <span class="sr-val" style="color:var(--gold);font-family:monospace;font-size:16px"><?= htmlspecialchars($booking['seat_number'] ?? '—') ?></span>
          </div>

          <!-- FARE BREAKDOWN -->
          <div style="margin-top:16px;background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:10px;padding:14px 16px;">
            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:10px;">Fare Details</div>
            <div class="fare-row"><span class="fare-label">Base Fare</span><span class="fare-val">₹<?= number_format($fare, 2) ?></span></div>
            <div class="fare-row"><span class="fare-label">GST (5%)</span><span class="fare-val">₹<?= number_format($taxes, 2) ?></span></div>
            <div class="fare-row"><span class="fare-label">Convenience Fee</span><span class="fare-val">₹<?= number_format($conv_fee, 2) ?></span></div>
            <div class="fare-total"><span>Total</span><span>₹<?= number_format($total, 2) ?></span></div>
          </div>

          <div style="margin-top:16px;font-size:12px;color:var(--muted);line-height:1.7;text-align:center">
            By proceeding, you agree to our <a href="#" style="color:var(--accent2)">Terms of Service</a> and <a href="#" style="color:var(--accent2)">Refund Policy</a>.
          </div>

        </div>
      </div>

      <a href="dashboard.php" style="display:block;text-align:center;margin-top:14px;font-size:13px;color:var(--muted);text-decoration:none;transition:color 0.15s" onmouseover="this.style.color='white'" onmouseout="this.style.color=''">
        ← Back to Dashboard
      </a>
    </div>

  </div><!-- /grid -->
  <?php endif; ?>

</div><!-- /page-wrap -->

<script>
// ── METHOD SWITCHER ──
function switchMethod(method, btn) {
  document.querySelectorAll('.pay-tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('pay_method_input').value = method;
  ['Card','UPI','Netbanking','Wallet'].forEach(m => {
    const s = document.getElementById('section-'+m);
    if (s) s.style.display = m === method ? 'block' : 'none';
  });
}

// ── CARD NUMBER FORMAT ──
function formatCard(input) {
  let v = input.value.replace(/\D/g,'').substring(0,16);
  input.value = v.replace(/(.{4})/g,'$1 ').trim();
  // Update preview
  const display = v.padEnd(16,'•');
  document.getElementById('preview-num').textContent =
    display.substring(0,4)+' '+display.substring(4,8)+' '+display.substring(8,12)+' '+display.substring(12,16);
  // Card brand
  const brand = document.getElementById('preview-brand');
  if (v.startsWith('4'))       brand.textContent = '💳 Visa';
  else if (v.startsWith('5'))  brand.textContent = '💳 MC';
  else if (v.startsWith('37')) brand.textContent = '💳 Amex';
  else if (v.startsWith('6'))  brand.textContent = '💳 Rupay';
  else brand.textContent = '💳';
}

function formatExp(input) {
  let v = input.value.replace(/\D/g,'');
  if (v.length >= 2) v = v.substring(0,2)+'/'+v.substring(2,4);
  input.value = v;
  document.getElementById('preview-exp').textContent = v || 'MM/YY';
}

function updatePreview() {
  const n = document.getElementById('card_name').value;
  document.getElementById('preview-name').textContent = n.toUpperCase() || 'YOUR NAME';
}

// ── UPI QUICK SELECT ──
function selectUpi(app, hint) {
  document.querySelectorAll('.upi-logo').forEach(l => l.classList.remove('sel'));
  event.target.closest('.upi-logo').classList.add('sel');
  document.getElementById('upi_id').placeholder = hint;
}

// ── LOADING STATE ON SUBMIT ──
document.getElementById('pay-form')?.addEventListener('submit', function(e) {
  const btn = document.getElementById('pay-btn');
  btn.innerHTML = '<span style="animation:spin 1s linear infinite;display:inline-block">⟳</span> Processing...';
  btn.disabled = true;
  // Re-enable after 15s as fallback
  setTimeout(() => { btn.disabled = false; btn.innerHTML = '🔒 Pay ₹<?= number_format($total,2) ?>'; }, 15000);
});
</script>
</body>
</html>