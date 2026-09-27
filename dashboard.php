<?php
require_once 'db.php';
requireLogin();

$uid  = $_SESSION['user_id'];
$uname= $_SESSION['user_name'];

$error = ''; $success = '';
$tab   = $_GET['tab'] ?? 'book';

// ── BOOK TICKET ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_book'])) {
    $name      = clean($conn, $_POST['pname']   ?? '');
    $age       = (int)($_POST['age']    ?? 0);
    $gender    = clean($conn, $_POST['gender']  ?? '');
    $train_id  = (int)($_POST['train_id'] ?? 0);
    $date      = clean($conn, $_POST['date']    ?? '');
    $seat_pref = clean($conn, $_POST['seat_pref'] ?? '');
    $seat_num  = clean($conn, $_POST['seat_num'] ?? '');

    $tab = 'book';

    if (!$name || !$age || !$date || !$train_id) {
        $error = "Please fill all required fields.";
    } else {
        // Get train info
        $tr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM trains WHERE train_id=$train_id"));
        if (!$tr) { $error = "Invalid train selected."; }
        else {
            // Check seat availability
            $booked = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM bookings WHERE passenger_id IN (SELECT passenger_id FROM passengers WHERE journey_date='$date') AND booking_status != 'Cancelled'"))['c'];

            if ($booked >= $tr['total_seats']) {
                $error = "No seats available on this train for selected date.";
            } else {
                // Auto assign seat
                if (!$seat_num) {
                    $prefix = ['Lower'=>'L','Middle'=>'M','Upper'=>'U','Window'=>'W','Aisle'=>'A','Side Lower'=>'SL','Side Upper'=>'SU'];
                    $p = $prefix[$seat_pref] ?? 'S';
                    $existing = [];
                    $sres = mysqli_query($conn, "SELECT seat_number FROM bookings WHERE booking_status != 'Cancelled'");
                    while ($sr = mysqli_fetch_assoc($sres)) $existing[] = $sr['seat_number'];
                    for ($i = 1; $i <= 72; $i++) {
                        $candidate = $p . $i;
                        if (!in_array($candidate, $existing)) { $seat_num = $candidate; break; }
                    }
                    if (!$seat_num) $seat_num = $p . rand(73,99);
                }

                // Insert passenger
                mysqli_query($conn, "INSERT INTO passengers (name,age,gender,train_name,journey_date) VALUES ('$name',$age,'$gender','" . clean($conn,$tr['train_name']) . "','$date')");
                $pid = mysqli_insert_id($conn);

                // Insert booking with Pending status until paid
                $fare = $tr['fare'];
                mysqli_query($conn, "INSERT INTO bookings (user_id,train_id,passenger_id,travel_date,seat_number,booking_status,fare) VALUES ($uid,$train_id,$pid,'$date','$seat_num','Pending',$fare)");
                $bid = mysqli_insert_id($conn);
                // Redirect to payment page
                header("Location: payment.php?booking_id=$bid");
                exit;
            }
        }
    }
}

// ── CANCEL BOOKING ──
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $bid = (int)$_GET['cancel'];
    // Ensure booking belongs to this user
    $own = mysqli_fetch_assoc(mysqli_query($conn, "SELECT booking_id FROM bookings WHERE booking_id=$bid AND user_id=$uid"));
    if ($own) {
        mysqli_query($conn, "UPDATE bookings SET booking_status='Cancelled' WHERE booking_id=$bid");
        $success = "Booking #$bid has been cancelled.";
    }
    $tab = 'bookings';
}

// FETCH TRAINS
$status_col = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM trains LIKE 'status'")) > 0;
$trains = mysqli_query($conn, 'SELECT * FROM trains' . ($status_col ? " WHERE status='Active'" : '') . ' ORDER BY train_name');

// FETCH USER BOOKINGS
$my_bookings = mysqli_query($conn, "
    SELECT b.*, p.name AS pname, p.age, p.gender, t.train_name, t.train_number, t.from_station, t.to_station, t.departure_time, t.arrival_time
    FROM bookings b
    LEFT JOIN passengers p ON b.passenger_id = p.passenger_id
    LEFT JOIN trains t ON b.train_id = t.train_id
    WHERE b.user_id = $uid
    ORDER BY b.booking_id DESC
");

$my_total     = mysqli_num_rows($my_bookings);
$confirmed_ct = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM bookings WHERE user_id=$uid AND booking_status='Confirmed'"))['c'];
$upcoming_ct  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM bookings b LEFT JOIN passengers p ON b.passenger_id=p.passenger_id WHERE b.user_id=$uid AND p.journey_date >= CURDATE() AND b.booking_status='Confirmed'"))['c'];

// OCCUPIED SEATS
$occupied = [];
$sres = mysqli_query($conn, "SELECT seat_number FROM bookings WHERE booking_status != 'Cancelled'");
while ($sr = mysqli_fetch_assoc($sres)) $occupied[] = $sr['seat_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RailMatrix — My Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700;900&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
  --bg:       #05080f;
  --srf:      #0b0f1a;
  --srf2:     #0f1520;
  --border:   rgba(255,255,255,0.07);
  --border2:  rgba(255,255,255,0.12);
  --accent:   #e8b86d;
  --accent2:  #d4a55a;
  --blue:     #4f8ef7;
  --green:    #34d399;
  --red:      #f87171;
  --yellow:   #fbbf24;
  --text:     #eef2f7;
  --muted:    #6b7280;
  --muted2:   #374151;
}
html, body { height: 100%; }
body { font-family: 'Instrument Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; }

/* SIDEBAR */
.sidebar {
  width: 260px;
  flex-shrink: 0;
  background: var(--srf);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
}

.s-logo {
  padding: 28px 24px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px;
}

.s-logo-icon {
  width: 42px; height: 42px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px;
}

.s-logo-name {
  font-family: 'Fraunces', serif;
  font-size: 20px; font-weight: 900;
  letter-spacing: -0.5px;
}
.s-logo-name span { color: var(--accent); }

.s-user {
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px;
}

.s-avatar {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, var(--blue), #2563eb);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; font-weight: 700;
  color: white; flex-shrink: 0;
}

.s-user-name { font-size: 14px; font-weight: 600; }
.s-user-role { font-size: 11px; color: var(--muted); }

.s-nav { flex: 1; padding: 16px 14px; }

.s-nav-label {
  font-size: 10px; font-weight: 700;
  letter-spacing: 1.5px; text-transform: uppercase;
  color: var(--muted2); padding: 0 10px; margin-bottom: 6px;
}

.s-nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 14px; border-radius: 9px;
  color: var(--muted); text-decoration: none;
  font-size: 14px; font-weight: 500;
  transition: all 0.15s; margin-bottom: 2px; cursor: pointer;
}
.s-nav-item:hover { background: rgba(255,255,255,0.04); color: var(--text); }
.s-nav-item.active { background: rgba(232,184,109,0.1); color: var(--accent); border: 1px solid rgba(232,184,109,0.18); }
.s-nav-item .si { font-size: 17px; width: 22px; text-align: center; }

.s-badge {
  margin-left: auto; background: rgba(232,184,109,0.15);
  color: var(--accent); font-size: 11px; font-weight: 700;
  padding: 2px 8px; border-radius: 20px;
}

.s-bottom {
  padding: 20px 22px;
  border-top: 1px solid var(--border);
}

.s-logout {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border-radius: 9px;
  color: var(--muted); font-size: 14px; font-weight: 500;
  text-decoration: none; transition: all 0.15s;
}
.s-logout:hover { background: rgba(248,113,113,0.08); color: var(--red); }

/* MAIN */
.main { margin-left: 260px; flex: 1; min-height: 100vh; }

/* TOPBAR */
.topbar {
  height: 62px; padding: 0 36px;
  display: flex; align-items: center; justify-content: space-between;
  background: rgba(11,15,26,0.95); backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  position: sticky; top: 0; z-index: 50;
}

.topbar-title { font-family: 'Fraunces', serif; font-size: 18px; font-weight: 700; }

.topbar-right { display: flex; align-items: center; gap: 14px; }

.db-pill {
  display: flex; align-items: center; gap: 7px;
  background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.2);
  color: #6ee7b7; padding: 5px 14px; border-radius: 20px;
  font-size: 12px; font-weight: 600;
}
.db-dot { width: 7px; height: 7px; background: #6ee7b7; border-radius: 50%; animation: blink 2s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

/* CONTENT */
.content { padding: 32px 36px 60px; }

/* ALERTS */
.alert {
  padding: 14px 18px; border-radius: 10px; font-size: 14px;
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 24px; animation: fadein 0.3s ease;
}
@keyframes fadein { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.a-ok  { background: rgba(52,211,153,0.08);  border: 1px solid rgba(52,211,153,0.2);  color: #6ee7b7; }
.a-err { background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.2); color: var(--red); }

/* STAT CARDS */
.stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 28px; }

.stat-card {
  background: var(--srf); border: 1px solid var(--border);
  border-radius: 14px; padding: 22px 20px; transition: all 0.2s;
}
.stat-card:hover { border-color: var(--border2); transform: translateY(-1px); }

.sc-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.sc-icon { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 19px; }
.i-gold   { background: rgba(232,184,109,0.12); }
.i-green  { background: rgba(52,211,153,0.12); }
.i-blue   { background: rgba(79,142,247,0.12); }
.sc-num { font-family: 'Fraunces', serif; font-size: 34px; font-weight: 700; line-height: 1; }
.sc-label { font-size: 12px; color: var(--muted); margin-top: 4px; }

/* TWO COL */
.two-col { display: grid; grid-template-columns: 420px 1fr; gap: 22px; align-items: start; }

/* CARD */
.card {
  background: var(--srf); border: 1px solid var(--border);
  border-radius: 16px; overflow: hidden;
}

.card-hd {
  padding: 20px 24px 18px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}

.card-title { font-family: 'Fraunces', serif; font-size: 17px; font-weight: 700; }
.card-sub   { font-size: 12px; color: var(--muted); margin-top: 2px; }
.card-body  { padding: 24px; }

/* FORM */
.field { margin-bottom: 16px; }
.field label {
  display: block; font-size: 11px; font-weight: 700;
  color: rgba(238,242,247,0.5); margin-bottom: 7px;
  letter-spacing: 0.7px; text-transform: uppercase;
}
.field input, .field select {
  width: 100%; background: rgba(255,255,255,0.03);
  border: 1px solid var(--border2); border-radius: 9px;
  color: var(--text); font-family: 'Instrument Sans', sans-serif;
  font-size: 14px; padding: 11px 14px; outline: none; transition: all 0.2s;
  -webkit-appearance: none;
}
.field input:focus, .field select:focus {
  border-color: var(--accent); background: rgba(232,184,109,0.03);
}
.field input::placeholder { color: var(--muted); }
.field select option { background: #0f1520; }

.two-field { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.sec-label {
  font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
  text-transform: uppercase; color: var(--accent);
  margin: 20px 0 12px; display: flex; align-items: center; gap: 10px;
}
.sec-label::after { content:''; flex:1; height:1px; background: rgba(232,184,109,0.15); }

/* SEAT PREFS */
.seat-prefs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 7px; margin-bottom: 14px; }
.sp-opt { display: none; }
.sp-opt + label {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 10px 6px; background: rgba(255,255,255,0.02);
  border: 1px solid var(--border2); border-radius: 9px;
  cursor: pointer; transition: all 0.15s;
  font-size: 11px; font-weight: 600; color: var(--muted); text-align: center;
}
.sp-opt + label:hover { border-color: rgba(232,184,109,0.4); color: var(--text); }
.sp-opt:checked + label { border-color: var(--accent); background: rgba(232,184,109,0.08); color: var(--accent); }
.sp-opt + label .sp-icon { font-size: 20px; }

/* SUBMIT BTN */
.btn-submit {
  width: 100%; padding: 13px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border: none; border-radius: 10px;
  font-family: 'Instrument Sans', sans-serif;
  font-size: 15px; font-weight: 600; color: #09090b;
  cursor: pointer; margin-top: 4px; transition: all 0.2s;
}
.btn-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(232,184,109,0.25); }

/* TABLE */
.tbl-wrap { overflow-x: auto; }
table.tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
.tbl thead th {
  text-align: left; padding: 11px 16px;
  font-size: 10px; font-weight: 700; letter-spacing: 1px;
  text-transform: uppercase; color: var(--muted2);
  border-bottom: 1px solid var(--border); white-space: nowrap;
}
.tbl tbody tr { border-bottom: 1px solid rgba(255,255,255,0.03); transition: background 0.12s; }
.tbl tbody tr:hover { background: rgba(255,255,255,0.02); }
.tbl td { padding: 13px 16px; color: rgba(238,242,247,0.7); vertical-align: middle; white-space: nowrap; }
.tbl td strong { color: var(--text); }

/* CHIPS */
.chip-id    { background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 5px; padding: 2px 8px; font-size: 11px; font-family: monospace; color: var(--muted); }
.chip-train { background: rgba(79,142,247,0.1); border: 1px solid rgba(79,142,247,0.2); color: #7cb3ff; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.chip-seat  { background: rgba(232,184,109,0.1); border: 1px solid rgba(232,184,109,0.2); color: var(--accent); padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; font-family: monospace; }
.chip-fare  { color: var(--green); font-weight: 700; font-size: 13px; }

.badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.b-c  { background: rgba(52,211,153,0.1);  color: #6ee7b7; border: 1px solid rgba(52,211,153,0.2); }
.b-p  { background: rgba(251,191,36,0.1);  color: var(--yellow); border: 1px solid rgba(251,191,36,0.2); }
.b-x  { background: rgba(248,113,113,0.1); color: var(--red); border: 1px solid rgba(248,113,113,0.2); }

.btn-cancel-sm {
  padding: 5px 12px; border-radius: 6px;
  background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.2);
  color: var(--red); font-family: 'Instrument Sans', sans-serif;
  font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none;
  transition: all 0.15s;
}
.btn-cancel-sm:hover { background: rgba(248,113,113,0.15); }

/* TRAIN SELECTOR */
.train-card {
  border: 1px solid var(--border2); border-radius: 10px;
  padding: 14px 16px; margin-bottom: 8px; cursor: pointer;
  transition: all 0.15s; position: relative;
}
.train-card:hover { border-color: rgba(232,184,109,0.4); background: rgba(232,184,109,0.03); }
.train-card.selected { border-color: var(--accent); background: rgba(232,184,109,0.06); }
.train-card input[type=radio] { position: absolute; opacity: 0; }

.tc-name { font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 5px; }
.tc-route { font-size: 12px; color: var(--muted); }
.tc-meta { display: flex; gap: 14px; margin-top: 8px; font-size: 12px; }
.tc-meta span { color: var(--muted); }
.tc-meta strong { color: var(--accent); }

.empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty-icon  { font-size: 40px; margin-bottom: 12px; opacity: 0.3; }

/* SCROLLBAR */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 5px; }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="s-logo">
    <div class="s-logo-icon">🚆</div>
    <div class="s-logo-name">Rail<span>Matrix</span></div>
  </div>

  <div class="s-user">
    <div class="s-avatar"><?= strtoupper(substr($uname,0,1)) ?></div>
    <div>
      <div class="s-user-name"><?= htmlspecialchars($uname) ?></div>
      <div class="s-user-role">Passenger</div>
    </div>
  </div>

  <div class="s-nav">
    <div class="s-nav-label">Navigation</div>
    <a href="?tab=book"     class="s-nav-item <?= $tab==='book'     ?'active':'' ?>"><span class="si">🎫</span> Book Ticket</a>
    <a href="?tab=bookings" class="s-nav-item <?= $tab==='bookings' ?'active':'' ?>"><span class="si">📋</span> My Bookings <span class="s-badge"><?= $my_total ?></span></a>
    <a href="?tab=trains"   class="s-nav-item <?= $tab==='trains'   ?'active':'' ?>"><span class="si">🚄</span> View Trains</a>
    <a href="?tab=profile"  class="s-nav-item <?= $tab==='profile'  ?'active':'' ?>"><span class="si">👤</span> My Profile</a>
  </div>

  <div class="s-bottom">
    <a href="logout.php" class="s-logout"><span>🚪</span> Sign Out</a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title">
      <?php $titles=['book'=>'Book a Ticket','bookings'=>'My Bookings','trains'=>'Available Trains','profile'=>'My Profile']; echo $titles[$tab]??'Dashboard'; ?>
    </div>
    <div class="topbar-right">
      <div class="db-pill"><div class="db-dot"></div> Live</div>
      <span style="font-size:13px;color:var(--muted)"><?= date('D, d M Y') ?></span>
    </div>
  </div>

  <div class="content">

    <?php if ($error): ?><div class="alert a-err">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert a-ok">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- ═══ BOOK ═══ -->
    <?php if ($tab === 'book'): ?>

    <div class="stats-row">
      <div class="stat-card">
        <div class="sc-top"><div class="sc-icon i-gold">🎫</div></div>
        <div class="sc-num" style="color:var(--accent)"><?= $my_total ?></div>
        <div class="sc-label">Total Bookings</div>
      </div>
      <div class="stat-card">
        <div class="sc-top"><div class="sc-icon i-green">✅</div></div>
        <div class="sc-num" style="color:var(--green)"><?= $confirmed_ct ?></div>
        <div class="sc-label">Confirmed</div>
      </div>
      <div class="stat-card">
        <div class="sc-top"><div class="sc-icon i-blue">🗓</div></div>
        <div class="sc-num" style="color:var(--blue)"><?= $upcoming_ct ?></div>
        <div class="sc-label">Upcoming Journeys</div>
      </div>
    </div>

    <div class="two-col">
      <!-- FORM -->
      <div class="card">
        <div class="card-hd">
          <div><div class="card-title">New Reservation</div><div class="card-sub">Book your railway ticket</div></div>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="do_book" value="1">

            <div class="sec-label">Passenger Details</div>

            <div class="field">
              <label>Passenger Name *</label>
              <input type="text" name="pname" placeholder="Full name" required>
            </div>
            <div class="two-field">
              <div class="field">
                <label>Age *</label>
                <input type="number" name="age" placeholder="25" min="1" max="120" required>
              </div>
              <div class="field">
                <label>Gender</label>
                <select name="gender">
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>

            <div class="sec-label">Journey Details</div>

            <div class="field">
              <label>Select Train *</label>
              <select name="train_id" required>
                <option value="">— Choose Train —</option>
                <?php while($tr = mysqli_fetch_assoc($trains)): ?>
                <option value="<?= $tr['train_id'] ?>"><?= htmlspecialchars($tr['train_name']) ?> (<?= $tr['train_number'] ?>) — <?= $tr['from_station'] ?> → <?= $tr['to_station'] ?> — ₹<?= number_format($tr['fare'],0) ?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="field">
              <label>Journey Date *</label>
              <input type="date" name="date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="sec-label">Seat Preference</div>

            <div class="seat-prefs">
              <?php
              $seats = [['Lower','🪑','L'],['Middle','🔲','M'],['Upper','⬆️','U'],['Window','🪟','W'],['Aisle','↔️','A'],['Side Lower','↙️','SL'],['Side Upper','↗️','SU'],['No Pref','🎲','']];
              foreach($seats as $i => [$label,$icon,$pref]):
              ?>
              <input type="radio" name="seat_pref" id="sp<?=$i?>" value="<?=$label?>" class="sp-opt" <?= $i===0?'checked':'' ?>>
              <label for="sp<?=$i?>"><span class="sp-icon"><?=$icon?></span><?=$label?></label>
              <?php endforeach; ?>
            </div>

            <div class="field">
              <label>Specific Seat (optional)</label>
              <select name="seat_num">
                <option value="">— Auto Assign —</option>
                <?php
                $berths = ['L'=>'Lower','M'=>'Middle','U'=>'Upper','W'=>'Window','A'=>'Aisle','SL'=>'Side Lower','SU'=>'Side Upper'];
                foreach ($berths as $pre => $bl):
                  echo "<optgroup label=\"$bl\">";
                  for ($i=1;$i<=12;$i++) {
                    $sn = $pre.$i;
                    $taken = in_array($sn,$occupied);
                    echo "<option value=\"$sn\"" . ($taken?" disabled":'') . ">$sn — $bl $i" . ($taken?' [Occupied]':'') . "</option>";
                  }
                  echo "</optgroup>";
                endforeach;
                ?>
              </select>
            </div>

            <button type="submit" class="btn-submit">🎫 Confirm Booking</button>
          </form>
        </div>
      </div>

      <!-- RECENT BOOKINGS -->
      <div class="card">
        <div class="card-hd">
          <div><div class="card-title">Recent Bookings</div><div class="card-sub">Your last 8 tickets</div></div>
        </div>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>ID</th><th>Train</th><th>Date</th><th>Seat</th><th>Fare</th><th>Status</th></tr></thead>
            <tbody>
              <?php
              mysqli_data_seek($my_bookings,0);
              $cnt=0;
              while($b=mysqli_fetch_assoc($my_bookings)){
                if($cnt++>=8) break;
                $bs=$b['booking_status']==='Confirmed'?'b-c':($b['booking_status']==='Pending'?'b-p':'b-x');
                echo "<tr>
                  <td><span class='chip-id'>#{$b['booking_id']}</span></td>
                  <td><span class='chip-train'>".htmlspecialchars($b['train_name']??'—')."</span></td>
                  <td>".($b['travel_date']?date('d M Y',strtotime($b['travel_date'])):'—')."</td>
                  <td>".($b['seat_number']?"<span class='chip-seat'>{$b['seat_number']}</span>":'—')."</td>
                  <td><span class='chip-fare'>₹".number_format($b['fare'],0)."</span></td>
                  <td><span class='badge $bs'>{$b['booking_status']}</span></td>
                </tr>";
              }
              if($cnt===0) echo "<tr><td colspan='6'><div class='empty-state'><div class='empty-icon'>🎫</div><p>No bookings yet</p></div></td></tr>";
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php endif; ?>

    <!-- ═══ MY BOOKINGS ═══ -->
    <?php if ($tab === 'bookings'): ?>
    <div class="card">
      <div class="card-hd">
        <div><div class="card-title">My Bookings</div><div class="card-sub"><?= $my_total ?> total reservations</div></div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Booking ID</th><th>Passenger</th><th>Train</th><th>Route</th><th>Date</th><th>Seat</th><th>Fare</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php
            mysqli_data_seek($my_bookings,0);
            $found=false;
            while($b=mysqli_fetch_assoc($my_bookings)):
              $found=true;
              $bs=$b['booking_status']==='Confirmed'?'b-c':($b['booking_status']==='Pending'?'b-p':'b-x');
            ?>
            <tr>
              <td><span class="chip-id">#<?=$b['booking_id']?></span></td>
              <td><strong><?=htmlspecialchars($b['pname']??'—')?></strong><br><span style="font-size:11px;color:var(--muted)"><?=$b['age']??' '?> · <?=$b['gender']??''?></span></td>
              <td><span class="chip-train"><?=htmlspecialchars($b['train_name']??'—')?></span></td>
              <td style="font-size:12px;color:var(--muted)"><?=htmlspecialchars(($b['from_station']??'').' → '.($b['to_station']??''))?></td>
              <td><?=$b['travel_date']?date('d M Y',strtotime($b['travel_date'])):'—'?></td>
              <td><?=$b['seat_number']?"<span class='chip-seat'>{$b['seat_number']}</span>":'—'?></td>
              <td><span class="chip-fare">₹<?=number_format($b['fare'],0)?></span></td>
              <td><span class="badge <?=$bs?>"><?=$b['booking_status']?></span></td>
              <td>
                <?php if($b['booking_status']!=='Cancelled'): ?>
                <a href="?tab=bookings&cancel=<?=$b['booking_id']?>" class="btn-cancel-sm" onclick="return confirm('Cancel this booking?')">Cancel</a>
                <?php else: ?>
                <span style="font-size:11px;color:var(--muted)">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if(!$found): ?><tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📋</div><p>No bookings found.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ═══ TRAINS ═══ -->
    <?php if ($tab === 'trains'): ?>
    <div class="card">
      <div class="card-hd"><div><div class="card-title">Available Trains</div><div class="card-sub">All active trains on the network</div></div></div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>#</th><th>Train</th><th>Number</th><th>From</th><th>To</th><th>Departs</th><th>Arrives</th><th>Seats</th><th>Fare</th></tr></thead>
          <tbody>
            <?php
            $all_trains = mysqli_query($conn, "SELECT * FROM trains WHERE status='Active' ORDER BY train_name");
            $found=false;
            while($t=mysqli_fetch_assoc($all_trains)):
              $found=true;
              $avail = $t['total_seats'] - (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM bookings WHERE train_id={$t['train_id']} AND booking_status='Confirmed'"))['c'];
            ?>
            <tr>
              <td><span class="chip-id">#<?=$t['train_id']?></span></td>
              <td><strong><?=htmlspecialchars($t['train_name'])?></strong></td>
              <td><span class="chip-train"><?=htmlspecialchars($t['train_number'])?></span></td>
              <td><?=htmlspecialchars($t['from_station'])?></td>
              <td><?=htmlspecialchars($t['to_station'])?></td>
              <td><?=$t['departure_time']?date('h:i A',strtotime($t['departure_time'])):'—'?></td>
              <td><?=$t['arrival_time']?date('h:i A',strtotime($t['arrival_time'])):'—'?></td>
              <td><span style="color:<?=$avail>10?'var(--green)':($avail>0?'var(--yellow)':'var(--red)')?>;font-weight:700"><?=$avail?>/<?=$t['total_seats']?></span></td>
              <td><span class="chip-fare">₹<?=number_format($t['fare'],0)?></span></td>
            </tr>
            <?php endwhile; ?>
            <?php if(!$found): ?><tr><td colspan="9"><div class="empty-state"><div class="empty-icon">🚄</div><p>No trains available.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ═══ PROFILE ═══ -->
    <?php if ($tab === 'profile'): ?>
    <?php $me = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE user_id=$uid")); ?>
    <div style="max-width:480px">
      <div class="card">
        <div class="card-hd"><div class="card-title">Account Details</div></div>
        <div class="card-body">
          <div style="display:flex;align-items:center;gap:18px;margin-bottom:28px;padding-bottom:22px;border-bottom:1px solid var(--border)">
            <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--blue),#2563eb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;color:white;flex-shrink:0"><?=strtoupper(substr($uname,0,1))?></div>
            <div>
              <div style="font-family:'Fraunces',serif;font-size:20px;font-weight:700"><?=htmlspecialchars($me['name'])?></div>
              <div style="font-size:13px;color:var(--muted);margin-top:3px"><?=htmlspecialchars($me['email'])?></div>
              <div style="margin-top:6px"><span class="badge b-c">Passenger</span></div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px">User ID</div><div style="font-weight:600">#<?=$me['user_id']?></div></div>
            <div><div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px">Phone</div><div style="font-weight:600"><?=htmlspecialchars($me['phone']??'—')?></div></div>
            <div><div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px">Member Since</div><div style="font-weight:600"><?=date('d M Y',strtotime($me['created_at']))?></div></div>
            <div><div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px">Total Bookings</div><div style="font-weight:600;color:var(--accent)"><?=$my_total?></div></div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

<script>
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => { el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }, 5000);
});
</script>
</body>
</html>