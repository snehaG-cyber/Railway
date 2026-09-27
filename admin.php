Content is user-generated and unverified.
<?php
require_once 'db.php';
requireLogin();
requireAdmin();

$error = ''; $success = '';
$tab   = $_GET['tab'] ?? 'dashboard';

// ══════════════════ TRAINS CRUD ══════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_add_train'])) {
    $tname  = clean($conn, $_POST['tname']  ?? '');
    $tnum   = clean($conn, $_POST['tnum']   ?? '');
    $from   = clean($conn, $_POST['from_st']  ?? '');
    $to     = clean($conn, $_POST['to_st']    ?? '');
    $dep    = clean($conn, $_POST['dep_time'] ?? '');
    $arr    = clean($conn, $_POST['arr_time'] ?? '');
    $seats  = (int)($_POST['seats']  ?? 72);
    $fare   = (float)($_POST['fare'] ?? 500);
    $status = clean($conn, $_POST['train_status'] ?? 'Active');
    $tab    = 'trains';

    if (!$tname || !$tnum || !$from || !$to) {
        $error = "Train name, number, from and to stations are required.";
    } else {
        mysqli_query($conn, "INSERT INTO trains (train_name,train_number,from_station,to_station,departure_time,arrival_time,total_seats,fare,status)
            VALUES ('$tname','$tnum','$from','$to','$dep','$arr',$seats,$fare,'$status')");
        $success = "Train '$tname' added successfully!";
    }
}

if (isset($_GET['del_train'])) {
    $id = (int)$_GET['del_train'];
    mysqli_query($conn, "DELETE FROM trains WHERE train_id=$id");
    header("Location: admin.php?tab=trains&msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_edit_train'])) {
    $id     = (int)$_POST['edit_id'];
    $tname  = clean($conn, $_POST['tname']  ?? '');
    $tnum   = clean($conn, $_POST['tnum']   ?? '');
    $from   = clean($conn, $_POST['from_st']  ?? '');
    $to     = clean($conn, $_POST['to_st']    ?? '');
    $dep    = clean($conn, $_POST['dep_time'] ?? '');
    $arr    = clean($conn, $_POST['arr_time'] ?? '');
    $seats  = (int)($_POST['seats']  ?? 72);
    $fare   = (float)($_POST['fare'] ?? 500);
    $status = clean($conn, $_POST['train_status'] ?? 'Active');
    $tab    = 'trains';
    mysqli_query($conn, "UPDATE trains SET train_name='$tname',train_number='$tnum',from_station='$from',to_station='$to',departure_time='$dep',arrival_time='$arr',total_seats=$seats,fare=$fare,status='$status' WHERE train_id=$id");
    $success = "Train updated!";
}

// ══════════════════ STATIONS CRUD ══════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_add_station'])) {
    $sname = clean($conn, $_POST['sname'] ?? '');
    $city  = clean($conn, $_POST['city']  ?? '');
    $tab   = 'stations';
    if (!$sname || !$city) { $error = "Station name and city required."; }
    else {
        mysqli_query($conn, "INSERT INTO stations (station_name,city) VALUES ('$sname','$city')");
        $success = "Station '$sname' added!";
    }
}

if (isset($_GET['del_station'])) {
    $id = (int)$_GET['del_station'];
    mysqli_query($conn, "DELETE FROM stations WHERE station_id=$id");
    header("Location: admin.php?tab=stations");
    exit;
}

// ══════════════════ BOOKINGS ══════════════════
if (isset($_GET['bstatus']) && isset($_GET['bid'])) {
    $bid    = (int)$_GET['bid'];
    $status = clean($conn, $_GET['bstatus']);
    if (in_array($status, ['Confirmed','Pending','Cancelled'])) {
        mysqli_query($conn, "UPDATE bookings SET booking_status='$status' WHERE booking_id=$bid");
    }
    header("Location: admin.php?tab=bookings");
    exit;
}

if (isset($_GET['del_booking'])) {
    $id = (int)$_GET['del_booking'];
    mysqli_query($conn, "DELETE FROM bookings WHERE booking_id=$id");
    header("Location: admin.php?tab=bookings");
    exit;
}

// ══════════════════ USERS ══════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_add_user'])) {
    $uname = clean($conn,$_POST['uname']??'');
    $email = clean($conn,$_POST['email']??'');
    $pass  = $_POST['password']??'';
    $phone = clean($conn,$_POST['phone']??'');
    $role  = clean($conn,$_POST['role']??'user');
    $tab   = 'users';
    if(!$uname||!$email||!$pass){ $error="Name, email and password required."; }
    elseif(mysqli_num_rows(mysqli_query($conn,"SELECT user_id FROM users WHERE email='$email'"))>0){ $error="Email already exists."; }
    else {
        $h = password_hash($pass, PASSWORD_DEFAULT);
        mysqli_query($conn,"INSERT INTO users (name,email,password,phone,role) VALUES ('$uname','$email','$h','$phone','$role')");
        $success = "User '$uname' created!";
    }
}

if (isset($_GET['del_user'])) {
    $id = (int)$_GET['del_user'];
    if ($id != $_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM users WHERE user_id=$id");
    }
    header("Location: admin.php?tab=users");
    exit;
}

if (isset($_GET['toggle_role'])) {
    $id = (int)$_GET['toggle_role'];
    $cur = mysqli_fetch_assoc(mysqli_query($conn,"SELECT role FROM users WHERE user_id=$id"));
    $new = $cur['role']==='admin'?'user':'admin';
    mysqli_query($conn,"UPDATE users SET role='$new' WHERE user_id=$id");
    header("Location: admin.php?tab=users");
    exit;
}

// ══════════════════ FETCH DATA ══════════════════
$trains   = mysqli_query($conn,"SELECT * FROM trains ORDER BY train_id DESC");
$stations = mysqli_query($conn,"SELECT * FROM stations ORDER BY station_id ASC");
$users    = mysqli_query($conn,"SELECT * FROM users ORDER BY user_id DESC");
$bookings = mysqli_query($conn,"
    SELECT b.*, p.name pname, p.age, p.gender, t.train_name, t.train_number, t.from_station, t.to_station, u.name uname, u.email uemail
    FROM bookings b
    LEFT JOIN passengers p ON b.passenger_id=p.passenger_id
    LEFT JOIN trains t ON b.train_id=t.train_id
    LEFT JOIN users u ON b.user_id=u.user_id
    ORDER BY b.booking_id DESC
");

// Stats
$st_trains    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM trains"))['c'];
$st_stations  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM stations"))['c'];
$st_users     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users"))['c'];
$st_bookings  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM bookings"))['c'];
$st_confirmed = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM bookings WHERE booking_status='Confirmed'"))['c'];
$st_pending   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM bookings WHERE booking_status='Pending'"))['c'];
$st_cancelled = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM bookings WHERE booking_status='Cancelled'"))['c'];
$st_revenue   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(fare) r FROM bookings WHERE booking_status='Confirmed'"))['r'] ?? 0;
$st_passengers= mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM passengers"))['c'];

// Payments stats
$st_total_revenue  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(amount) r FROM payments WHERE payment_status='Success'"))['r'] ?? 0;
$st_payments_count = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM payments"))['c'];

// Recent 5 bookings for dashboard
$recent_b = mysqli_query($conn,"
    SELECT b.*, p.name pname, t.train_name, u.name uname
    FROM bookings b
    LEFT JOIN passengers p ON b.passenger_id=p.passenger_id
    LEFT JOIN trains t ON b.train_id=t.train_id
    LEFT JOIN users u ON b.user_id=u.user_id
    ORDER BY b.booking_id DESC LIMIT 8
");

// Train being edited
$edit_train = null;
if (isset($_GET['edit_train'])) {
    $edit_train = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM trains WHERE train_id=".(int)$_GET['edit_train']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RailMatrix Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700;900&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
  --bg:      #030508;
  --srf:     #080c14;
  --srf2:    #0c1120;
  --border:  rgba(255,255,255,0.06);
  --border2: rgba(255,255,255,0.11);
  --accent:  #6366f1;
  --accent2: #818cf8;
  --gold:    #f59e0b;
  --green:   #10b981;
  --red:     #ef4444;
  --blue:    #3b82f6;
  --text:    #f1f5f9;
  --muted:   #64748b;
  --muted2:  #1e293b;
}
html,body{height:100%;}
body { font-family:'Instrument Sans',sans-serif; background:var(--bg); color:var(--text); display:flex; }

body::before {
  content:'';
  position:fixed;
  inset:0;
  background:
    radial-gradient(ellipse at 15% 15%, rgba(99,102,241,0.07) 0%, transparent 50%),
    radial-gradient(ellipse at 85% 85%, rgba(245,158,11,0.04) 0%, transparent 50%);
  pointer-events:none;
}

/* SIDEBAR */
.sidebar {
  width:256px; flex-shrink:0;
  background:var(--srf); border-right:1px solid var(--border);
  display:flex; flex-direction:column;
  position:fixed; top:0;left:0;bottom:0; z-index:100;
}

.s-logo { padding:24px 22px; border-bottom:1px solid var(--border); }
.s-logo-inner { display:flex; align-items:center; gap:12px; }
.s-icon { width:40px;height:40px; background:linear-gradient(135deg,var(--accent),#4f46e5); border-radius:10px; display:flex;align-items:center;justify-content:center; font-size:19px; }
.s-name { font-family:'Fraunces',serif; font-size:19px;font-weight:900; letter-spacing:-0.5px; }
.s-name span{color:var(--accent2);}
.s-role-pill { display:inline-flex;align-items:center;gap:5px; background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.2); color:var(--accent2); font-size:10px;font-weight:700; padding:3px 10px;border-radius:20px; margin-top:8px;letter-spacing:0.5px;text-transform:uppercase; }

.s-nav { flex:1; padding:18px 12px; overflow-y:auto; }
.nav-group { margin-bottom:22px; }
.nav-gl { font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase; color:var(--muted2);padding:0 10px;margin-bottom:7px; }
.nav-item {
  display:flex;align-items:center;gap:11px;
  padding:10px 13px; border-radius:9px;
  color:var(--muted); text-decoration:none;
  font-size:13.5px;font-weight:500; transition:all 0.15s; margin-bottom:2px;
}
.nav-item:hover { background:rgba(255,255,255,0.04); color:var(--text); }
.nav-item.active { background:rgba(99,102,241,0.12); color:var(--accent2); border:1px solid rgba(99,102,241,0.2); }
.nav-item .ni { font-size:16px;width:20px;text-align:center;flex-shrink:0; }
.nav-badge { margin-left:auto; background:rgba(99,102,241,0.15); color:var(--accent2); font-size:10px;font-weight:700; padding:2px 7px;border-radius:20px; }

.s-bottom { padding:18px 20px; border-top:1px solid var(--border); }
.s-user-row { display:flex;align-items:center;gap:10px; margin-bottom:12px; }
.s-av { width:34px;height:34px; background:linear-gradient(135deg,var(--accent),#4f46e5); border-radius:50%; display:flex;align-items:center;justify-content:center; font-size:14px;font-weight:700;color:white; }
.s-un { font-size:13px;font-weight:600; }
.s-ue { font-size:11px;color:var(--muted); }
.btn-logout { display:flex;align-items:center;gap:8px; width:100%;padding:9px 13px; border-radius:8px; background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.15); color:rgba(239,68,68,0.8);font-family:'Instrument Sans',sans-serif; font-size:13px;font-weight:500;text-decoration:none; transition:all 0.15s; }
.btn-logout:hover { background:rgba(239,68,68,0.12); color:var(--red); }

/* MAIN */
.main { margin-left:256px; flex:1; min-height:100vh; position:relative; z-index:1; }

/* TOPBAR */
.topbar {
  height:60px;padding:0 34px;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(8,12,20,0.96);backdrop-filter:blur(12px);
  border-bottom:1px solid var(--border);
  position:sticky;top:0;z-index:50;
}
.tb-left { display:flex;align-items:center;gap:14px; }
.tb-breadcrumb { font-size:12px;color:var(--muted); }
.tb-sep { color:var(--muted2); }
.tb-page { font-family:'Fraunces',serif;font-size:17px;font-weight:700; }
.tb-right { display:flex;align-items:center;gap:12px; }
.live-pill { display:flex;align-items:center;gap:6px; background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.18); color:#34d399;padding:5px 13px;border-radius:20px; font-size:11px;font-weight:700; }
.live-dot { width:6px;height:6px;background:#34d399;border-radius:50%;animation:blink 2s infinite; }
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.3}}

/* CONTENT */
.content { padding:28px 34px 60px; }

/* ALERTS */
.alert { padding:13px 16px;border-radius:9px;font-size:13px;display:flex;align-items:center;gap:10px; margin-bottom:22px;animation:ain 0.3s ease; }
@keyframes ain{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.a-ok  { background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);color:#34d399; }
.a-err { background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); color:var(--red); }

/* STATS GRID */
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:26px; }

.sc {
  background:var(--srf);border:1px solid var(--border);border-radius:13px;
  padding:20px 18px; transition:all 0.2s; position:relative; overflow:hidden;
}
.sc:hover { border-color:var(--border2);transform:translateY(-2px); }
.sc::after { content:''; position:absolute; top:-30px;right:-30px; width:90px;height:90px; border-radius:50%; opacity:0.05; }
.sc.c-purple::after { background:var(--accent); }
.sc.c-gold::after   { background:var(--gold); }
.sc.c-green::after  { background:var(--green); }
.sc.c-blue::after   { background:var(--blue); }
.sc.c-red::after    { background:var(--red); }

.sc-top { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px; }
.sc-icon { width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px; }
.i-p { background:rgba(99,102,241,0.15); }
.i-g { background:rgba(245,158,11,0.15); }
.i-gr{ background:rgba(16,185,129,0.15); }
.i-b { background:rgba(59,130,246,0.15); }
.i-r { background:rgba(239,68,68,0.15); }

.sc-num { font-family:'Fraunces',serif;font-size:30px;font-weight:700;line-height:1; }
.sc-label { font-size:12px;color:var(--muted);margin-top:4px; }
.sc-trend { font-size:11px;padding:2px 7px;border-radius:10px;font-weight:600; }

/* LAYOUT */
.two-col   { display:grid;grid-template-columns:460px 1fr;gap:20px;align-items:start; }
.three-col { display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px; }

/* CARD */
.card { background:var(--srf);border:1px solid var(--border);border-radius:14px;overflow:hidden; }
.card-hd { padding:18px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between; }
.card-title { font-family:'Fraunces',serif;font-size:16px;font-weight:700; }
.card-sub   { font-size:12px;color:var(--muted);margin-top:2px; }
.card-body  { padding:22px; }

/* FORM */
.field { margin-bottom:14px; }
.field label { display:block;font-size:11px;font-weight:700;color:rgba(241,245,249,0.45);margin-bottom:6px;letter-spacing:0.6px;text-transform:uppercase; }
.field input,.field select,.field textarea {
  width:100%;background:rgba(255,255,255,0.03);border:1px solid var(--border2);border-radius:8px;
  color:var(--text);font-family:'Instrument Sans',sans-serif;font-size:14px;padding:10px 13px;
  outline:none;transition:all 0.2s;-webkit-appearance:none;
}
.field input:focus,.field select:focus,.field textarea:focus { border-color:var(--accent);background:rgba(99,102,241,0.04); }
.field input::placeholder { color:var(--muted); }
.field select option { background:#0c1120; }
.two-field { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
.three-field { display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px; }
.sec-div { font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--accent2); margin:16px 0 10px;display:flex;align-items:center;gap:8px; }
.sec-div::after{content:'';flex:1;height:1px;background:rgba(99,102,241,0.15);}

/* BUTTONS */
.btn { padding:10px 20px;border-radius:8px;font-family:'Instrument Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all 0.15s;text-decoration:none;display:inline-flex;align-items:center;gap:7px; }
.btn-primary { background:linear-gradient(135deg,var(--accent),#4f46e5);color:white; }
.btn-primary:hover { box-shadow:0 6px 20px rgba(99,102,241,0.35);transform:translateY(-1px); }
.btn-sm { padding:5px 11px;border-radius:6px;font-family:'Instrument Sans',sans-serif;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:all 0.15s; }
.btn-danger { background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:var(--red); }
.btn-danger:hover { background:rgba(239,68,68,0.18); }
.btn-edit   { background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.2);color:var(--accent2); }
.btn-edit:hover { background:rgba(99,102,241,0.18); }
.btn-ok     { background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#34d399; }
.btn-ok:hover { background:rgba(16,185,129,0.18); }
.btn-warn   { background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);color:var(--gold); }
.btn-warn:hover { background:rgba(245,158,11,0.18); }
.full-btn { width:100%;padding:12px;border-radius:9px;font-family:'Instrument Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:all 0.2s;margin-top:6px;border:none; }
.full-btn-primary { background:linear-gradient(135deg,var(--accent),#4f46e5);color:white; }
.full-btn-primary:hover { box-shadow:0 8px 24px rgba(99,102,241,0.3);transform:translateY(-1px); }

/* TABLE */
.tbl-wrap { overflow-x:auto; }
table.tbl { width:100%;border-collapse:collapse;font-size:13px; }
.tbl thead th { text-align:left;padding:10px 14px;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted2);border-bottom:1px solid var(--border);white-space:nowrap; }
.tbl tbody tr { border-bottom:1px solid rgba(255,255,255,0.03);transition:background 0.1s; }
.tbl tbody tr:hover { background:rgba(255,255,255,0.02); }
.tbl td { padding:12px 14px;color:rgba(241,245,249,0.65);vertical-align:middle;white-space:nowrap; }
.tbl td strong { color:var(--text); }

/* CHIPS */
.chip  { background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:5px;padding:2px 8px;font-size:11px;font-family:monospace;color:var(--muted); }
.chip-t{ background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.2);color:#7cb3ff;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:600; }
.chip-s{ background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.2);color:var(--accent2);padding:3px 9px;border-radius:5px;font-size:12px;font-weight:700;font-family:monospace; }
.chip-r{ color:#34d399;font-weight:700;font-size:13px; }
.badge { display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600; }
.b-c { background:rgba(16,185,129,0.1);color:#34d399;border:1px solid rgba(16,185,129,0.2); }
.b-p { background:rgba(245,158,11,0.1);color:var(--gold);border:1px solid rgba(245,158,11,0.2); }
.b-x { background:rgba(239,68,68,0.1);color:var(--red);border:1px solid rgba(239,68,68,0.2); }
.b-a { background:rgba(16,185,129,0.1);color:#34d399;border:1px solid rgba(16,185,129,0.2); }
.b-i { background:rgba(239,68,68,0.1);color:var(--red);border:1px solid rgba(239,68,68,0.2); }
.b-admin { background:rgba(99,102,241,0.1);color:var(--accent2);border:1px solid rgba(99,102,241,0.2); }
.b-user  { background:rgba(255,255,255,0.05);color:var(--muted);border:1px solid var(--border2); }

/* SEARCH */
.search-input { background:rgba(255,255,255,0.04);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-family:'Instrument Sans',sans-serif;font-size:13px;padding:8px 14px;outline:none;transition:border-color 0.2s;width:240px; }
.search-input:focus { border-color:var(--accent); }
.search-input::placeholder { color:var(--muted); }

/* STATION CARDS */
.station-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:20px; }
.station-card { background:var(--srf2);border:1px solid var(--border);border-radius:11px;padding:16px;transition:all 0.2s;position:relative;overflow:hidden; }
.station-card:hover { border-color:var(--border2);transform:translateY(-2px); }
.station-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent),var(--blue));opacity:0;transition:opacity 0.2s;}
.station-card:hover::before{opacity:1;}
.st-num{font-size:10px;color:var(--muted);letter-spacing:1px;margin-bottom:5px;}
.st-name{font-family:'Fraunces',serif;font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px;line-height:1.3;}
.st-city{font-size:12px;color:var(--gold);margin-bottom:12px;}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:500;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:var(--srf);border:1px solid rgba(99,102,241,0.2);border-radius:16px;padding:32px;width:540px;max-width:95vw;max-height:90vh;overflow-y:auto;animation:min 0.25s cubic-bezier(0.34,1.56,0.64,1);}
@keyframes min{from{opacity:0;transform:scale(0.93)}to{opacity:1;transform:scale(1)}}
.modal-title{font-family:'Fraunces',serif;font-size:20px;font-weight:700;margin-bottom:22px;}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
.btn-cancel{padding:10px 18px;background:rgba(255,255,255,0.05);border:1px solid var(--border2);border-radius:8px;color:var(--muted);font-family:'Instrument Sans',sans-serif;font-size:13px;cursor:pointer;}

/* MINI CHART BAR */
.bar-row { display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:13px; }
.bar-label{width:90px;color:var(--muted);font-size:12px;flex-shrink:0;}
.bar-track{flex:1;height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden;}
.bar-fill{height:100%;border-radius:3px;transition:width 1s ease;}

/* EMPTY */
.empty{text-align:center;padding:44px 20px;color:var(--muted);}
.empty-icon{font-size:38px;margin-bottom:10px;opacity:0.3;}

::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.08);border-radius:4px;}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="s-logo">
    <div class="s-logo-inner">
      <div class="s-icon">🚆</div>
      <div>
        <div class="s-name">Rail<span>Matrix</span></div>
      </div>
    </div>
    <div class="s-role-pill">⚡ Admin Panel</div>
  </div>

  <div class="s-nav">
    <div class="nav-group">
      <div class="nav-gl">Overview</div>
      <a href="?tab=dashboard" class="nav-item <?=$tab==='dashboard'?'active':''?>"><span class="ni">📊</span> Dashboard</a>
    </div>
    <div class="nav-group">
      <div class="nav-gl">Management</div>
      <a href="?tab=trains"    class="nav-item <?=$tab==='trains'?'active':''?>"><span class="ni">🚄</span> Trains <span class="nav-badge"><?=$st_trains?></span></a>
      <a href="?tab=stations"  class="nav-item <?=$tab==='stations'?'active':''?>"><span class="ni">🏛️</span> Stations <span class="nav-badge"><?=$st_stations?></span></a>
      <a href="?tab=bookings"  class="nav-item <?=$tab==='bookings'?'active':''?>"><span class="ni">🎫</span> Bookings <span class="nav-badge"><?=$st_bookings?></span></a>
    </div>
    <div class="nav-group">
      <div class="nav-gl">People</div>
      <a href="?tab=users"     class="nav-item <?=$tab==='users'?'active':''?>"><span class="ni">👥</span> Users <span class="nav-badge"><?=$st_users?></span></a>
    </div>
    <div class="nav-group">
      <div class="nav-gl">Other</div>
      <a href="dashboard.php"  class="nav-item"><span class="ni">↩️</span> User View</a>
    </div>
  </div>

  <div class="s-bottom">
    <div class="s-user-row">
      <div class="s-av"><?=strtoupper(substr($_SESSION['user_name'],0,1))?></div>
      <div>
        <div class="s-un"><?=htmlspecialchars($_SESSION['user_name'])?></div>
        <div class="s-ue">Administrator</div>
      </div>
    </div>
    <a href="logout.php" class="btn-logout">🚪 Sign Out</a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <span class="tb-breadcrumb">Admin <span class="tb-sep">›</span></span>
      <span class="tb-page">
        <?php $pg=['dashboard'=>'Dashboard','trains'=>'Trains','stations'=>'Stations','bookings'=>'Bookings','users'=>'Users']; echo $pg[$tab]??'Dashboard'; ?>
      </span>
    </div>
    <div class="tb-right">
      <div class="live-pill"><div class="live-dot"></div> System Live</div>
      <span style="font-size:12px;color:var(--muted)"><?=date('D, d M Y H:i')?></span>
    </div>
  </div>

  <div class="content">

    <?php if($error): ?><div class="alert a-err">⚠ <?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if($success): ?><div class="alert a-ok">✓ <?=htmlspecialchars($success)?></div><?php endif; ?>

    <!-- ════════════ DASHBOARD ════════════ -->
    <?php if($tab==='dashboard'): ?>

    <div class="stats-grid">
      <div class="sc c-purple">
        <div class="sc-top"><div class="sc-icon i-p">🎫</div><span class="sc-trend" style="background:rgba(99,102,241,0.1);color:var(--accent2)">Total</span></div>
        <div class="sc-num" style="color:var(--accent2)"><?=$st_bookings?></div>
        <div class="sc-label">All Bookings</div>
      </div>
      <div class="sc c-green">
        <div class="sc-top"><div class="sc-icon i-gr">✅</div></div>
        <div class="sc-num" style="color:#34d399"><?=$st_confirmed?></div>
        <div class="sc-label">Confirmed</div>
      </div>
      <div class="sc c-gold">
        <div class="sc-top"><div class="sc-icon i-g">💰</div></div>
        <div class="sc-num" style="color:var(--gold);font-size:24px">₹<?=number_format($st_revenue,0)?></div>
        <div class="sc-label">Total Revenue</div>
      </div>
      <div class="sc c-blue">
        <div class="sc-top"><div class="sc-icon i-b">👥</div></div>
        <div class="sc-num" style="color:#60a5fa"><?=$st_users?></div>
        <div class="sc-label">Registered Users</div>
      </div>
    </div>

    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
      <div class="sc">
        <div class="sc-top"><div class="sc-icon i-b">🚄</div></div>
        <div class="sc-num" style="color:#60a5fa"><?=$st_trains?></div>
        <div class="sc-label">Active Trains</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon i-g">🏛️</div></div>
        <div class="sc-num" style="color:var(--gold)"><?=$st_stations?></div>
        <div class="sc-label">Stations</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon i-p">👤</div></div>
        <div class="sc-num" style="color:var(--accent2)"><?=$st_passengers?></div>
        <div class="sc-label">Total Passengers</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon i-r">❌</div></div>
        <div class="sc-num" style="color:var(--red)"><?=$st_cancelled?></div>
        <div class="sc-label">Cancelled</div>
      </div>
    </div>

    <!-- CHARTS ROW -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px">
      <!-- STATUS BREAKDOWN -->
      <div class="card">
        <div class="card-hd"><div><div class="card-title">Booking Status</div><div class="card-sub">Current distribution</div></div></div>
        <div class="card-body" style="padding:22px 24px">
          <?php
          $total_safe = max(1, $st_bookings);
          $cp = round(($st_confirmed/$total_safe)*100);
          $pp = round(($st_pending/$total_safe)*100);
          $xp = round(($st_cancelled/$total_safe)*100);
          ?>
          <div class="bar-row">
            <div class="bar-label">Confirmed</div>
            <div class="bar-track"><div class="bar-fill" style="width:<?=$cp?>%;background:#10b981"></div></div>
            <div style="font-size:12px;font-weight:700;color:#34d399;width:40px;text-align:right"><?=$cp?>%</div>
          </div>
          <div class="bar-row">
            <div class="bar-label">Pending</div>
            <div class="bar-track"><div class="bar-fill" style="width:<?=$pp?>%;background:var(--gold)"></div></div>
            <div style="font-size:12px;font-weight:700;color:var(--gold);width:40px;text-align:right"><?=$pp?>%</div>
          </div>
          <div class="bar-row">
            <div class="bar-label">Cancelled</div>
            <div class="bar-track"><div class="bar-fill" style="width:<?=$xp?>%;background:var(--red)"></div></div>
            <div style="font-size:12px;font-weight:700;color:var(--red);width:40px;text-align:right"><?=$xp?>%</div>
          </div>
          <div style="margin-top:20px;display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center">
            <div style="padding:12px;background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.15);border-radius:8px">
              <div style="font-size:20px;font-weight:700;color:#34d399"><?=$st_confirmed?></div>
              <div style="font-size:11px;color:var(--muted)">Confirmed</div>
            </div>
            <div style="padding:12px;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.15);border-radius:8px">
              <div style="font-size:20px;font-weight:700;color:var(--gold)"><?=$st_pending?></div>
              <div style="font-size:11px;color:var(--muted)">Pending</div>
            </div>
            <div style="padding:12px;background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.15);border-radius:8px">
              <div style="font-size:20px;font-weight:700;color:var(--red)"><?=$st_cancelled?></div>
              <div style="font-size:11px;color:var(--muted)">Cancelled</div>
            </div>
          </div>
        </div>
      </div>

      <!-- TOP TRAINS -->
      <div class="card">
        <div class="card-hd"><div><div class="card-title">Busiest Trains</div><div class="card-sub">By confirmed bookings</div></div></div>
        <div class="card-body">
          <?php
          $top_trains = mysqli_query($conn,"SELECT t.train_name, COUNT(b.booking_id) bc FROM trains t LEFT JOIN bookings b ON t.train_id=b.train_id AND b.booking_status='Confirmed' GROUP BY t.train_id ORDER BY bc DESC LIMIT 5");
          $max_tr = 1;
          $tt_rows = [];
          while($r=mysqli_fetch_assoc($top_trains)){ $tt_rows[]=$r; if($r['bc']>$max_tr)$max_tr=$r['bc']; }
          if(empty($tt_rows)): ?>
          <div class="empty"><div class="empty-icon">🚄</div><p>No data yet</p></div>
          <?php else:
          foreach($tt_rows as $r):
            $pct = $max_tr>0?round(($r['bc']/$max_tr)*100):0;
          ?>
          <div class="bar-row">
            <div class="bar-label" style="width:130px;overflow:hidden;text-overflow:ellipsis" title="<?=htmlspecialchars($r['train_name'])?>"><?=htmlspecialchars(substr($r['train_name'],0,15))?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?=$pct?>%;background:linear-gradient(90deg,var(--accent),var(--accent2))"></div></div>
            <div style="font-size:12px;font-weight:700;color:var(--accent2);width:30px;text-align:right"><?=$r['bc']?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- RECENT BOOKINGS -->
    <div class="card">
      <div class="card-hd">
        <div><div class="card-title">Recent Bookings</div><div class="card-sub">Latest reservations</div></div>
        <a href="?tab=bookings" class="btn btn-sm btn-edit">View All →</a>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>ID</th><th>Passenger</th><th>Train</th><th>User</th><th>Seat</th><th>Fare</th><th>Status</th><th>Time</th></tr></thead>
          <tbody>
            <?php while($b=mysqli_fetch_assoc($recent_b)): $bs=$b['booking_status']==='Confirmed'?'b-c':($b['booking_status']==='Pending'?'b-p':'b-x'); ?>
            <tr>
              <td><span class="chip">#<?=$b['booking_id']?></span></td>
              <td><strong><?=htmlspecialchars($b['pname']??'—')?></strong></td>
              <td><span class="chip-t"><?=htmlspecialchars($b['train_name']??'—')?></span></td>
              <td style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($b['uname']??'Guest')?></td>
              <td><?=$b['seat_number']?"<span class='chip-s'>{$b['seat_number']}</span>":'—'?></td>
              <td><span class="chip-r">₹<?=number_format($b['fare'],0)?></span></td>
              <td><span class="badge <?=$bs?>"><?=$b['booking_status']?></span></td>
              <td style="font-size:11px;color:var(--muted)"><?=date('d M, H:i',strtotime($b['booking_time']))?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; ?>

    <!-- ════════════ TRAINS ════════════ -->
    <?php if($tab==='trains'): ?>

    <div class="two-col">

      <!-- ADD / EDIT FORM -->
      <div class="card">
        <div class="card-hd">
          <div>
            <div class="card-title"><?=$edit_train?'Edit Train':'Add New Train'?></div>
            <div class="card-sub"><?=$edit_train?'Update train details':'Register a new train'?></div>
          </div>
          <?php if($edit_train): ?><a href="?tab=trains" class="btn-sm btn-edit">+ New</a><?php endif; ?>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="<?=$edit_train?'do_edit_train':'do_add_train'?>" value="1">
            <?php if($edit_train): ?><input type="hidden" name="edit_id" value="<?=$edit_train['train_id']?>"><?php endif; ?>

            <div class="sec-div">Train Identity</div>
            <div class="two-field">
              <div class="field"><label>Train Name *</label><input type="text" name="tname" value="<?=htmlspecialchars($edit_train['train_name']??'')?>" placeholder="Rajdhani Express" required></div>
              <div class="field"><label>Train Number *</label><input type="text" name="tnum" value="<?=htmlspecialchars($edit_train['train_number']??'')?>" placeholder="12301" required></div>
            </div>

            <div class="sec-div">Route</div>
            <div class="two-field">
              <div class="field"><label>From Station *</label><input type="text" name="from_st" value="<?=htmlspecialchars($edit_train['from_station']??'')?>" placeholder="New Delhi" required></div>
              <div class="field"><label>To Station *</label><input type="text" name="to_st" value="<?=htmlspecialchars($edit_train['to_station']??'')?>" placeholder="Mumbai CST" required></div>
            </div>
            <div class="two-field">
              <div class="field"><label>Departure Time</label><input type="time" name="dep_time" value="<?=$edit_train['departure_time']??''?>"></div>
              <div class="field"><label>Arrival Time</label><input type="time" name="arr_time" value="<?=$edit_train['arrival_time']??''?>"></div>
            </div>

            <div class="sec-div">Capacity & Fare</div>
            <div class="three-field">
              <div class="field"><label>Total Seats</label><input type="number" name="seats" value="<?=$edit_train['total_seats']??72?>" min="1" max="1000"></div>
              <div class="field"><label>Fare (₹)</label><input type="number" name="fare" value="<?=$edit_train['fare']??500?>" min="0" step="0.01"></div>
              <div class="field"><label>Status</label><select name="train_status"><option value="Active" <?=($edit_train['status']??'Active')==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=($edit_train['status']??'')==='Inactive'?'selected':''?>>Inactive</option></select></div>
            </div>

            <button type="submit" class="full-btn full-btn-primary"><?=$edit_train?'💾 Update Train':'🚄 Add Train'?></button>
          </form>
        </div>
      </div>

      <!-- TRAINS TABLE -->
      <div class="card">
        <div class="card-hd">
          <div><div class="card-title">All Trains</div><div class="card-sub"><?=$st_trains?> registered</div></div>
          <input type="text" class="search-input" placeholder="🔍 Search trains..." oninput="searchTable(this,'trains-tbl')">
        </div>
        <div class="tbl-wrap">
          <table class="tbl" id="trains-tbl">
            <thead><tr><th>ID</th><th>Name</th><th>Number</th><th>Route</th><th>Seats</th><th>Fare</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php
              mysqli_data_seek($trains,0);
              $found=false;
              while($t=mysqli_fetch_assoc($trains)):
                $found=true;
                $avail = $t['total_seats'] - (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM bookings WHERE train_id={$t['train_id']} AND booking_status='Confirmed'"))['c'];
              ?>
              <tr>
                <td><span class="chip">#<?=$t['train_id']?></span></td>
                <td><strong><?=htmlspecialchars($t['train_name'])?></strong></td>
                <td><span class="chip-t"><?=htmlspecialchars($t['train_number'])?></span></td>
                <td style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($t['from_station'])?> → <?=htmlspecialchars($t['to_station'])?></td>
                <td><span style="color:<?=$avail>10?'#34d399':($avail>0?'var(--gold)':'var(--red)')?>;font-weight:600"><?=$avail?>/<?=$t['total_seats']?></span></td>
                <td><span class="chip-r">₹<?=number_format($t['fare'],0)?></span></td>
                <td><span class="badge <?=$t['status']==='Active'?'b-a':'b-i'?>"><?=$t['status']?></span></td>
                <td>
                  <a href="?tab=trains&edit_train=<?=$t['train_id']?>" class="btn-sm btn-edit">Edit</a>
                  <a href="?tab=trains&del_train=<?=$t['train_id']?>" class="btn-sm btn-danger" onclick="return confirm('Delete train?')">Del</a>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php if(!$found): ?><tr><td colspan="8"><div class="empty"><div class="empty-icon">🚄</div><p>No trains yet.</p></div></td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /two-col -->

    <?php endif; ?>

    <!-- ════════════ STATIONS ════════════ -->
    <?php if($tab==='stations'): ?>

    <div class="card" style="margin-bottom:20px">
      <div class="card-hd"><div class="card-title">Add Station</div></div>
      <div class="card-body">
        <form method="POST" style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:flex-end">
          <input type="hidden" name="do_add_station" value="1">
          <div class="field" style="margin-bottom:0"><label>Station Name *</label><input type="text" name="sname" placeholder="e.g. Chhatrapati Shivaji Terminus" required></div>
          <div class="field" style="margin-bottom:0"><label>City *</label><input type="text" name="city" placeholder="e.g. Mumbai" required></div>
          <button type="submit" class="btn btn-primary" style="align-self:flex-end;height:41px">+ Add Station</button>
        </form>
      </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div style="font-family:'Fraunces',serif;font-size:18px;font-weight:700"><?=$st_stations?> Stations</div>
      <input type="text" class="search-input" placeholder="🔍 Search..." oninput="searchCards(this)">
    </div>

    <?php if(!mysqli_num_rows($stations)): ?>
    <div class="empty"><div class="empty-icon">🏛️</div><p>No stations added.</p></div>
    <?php else: ?>
    <div class="station-grid" id="station-cards">
      <?php while($s=mysqli_fetch_assoc($stations)): ?>
      <div class="station-card" data-search="<?=strtolower($s['station_name'].' '.$s['city'])?>">
        <div class="st-num">STN · #<?=str_pad($s['station_id'],3,'0',STR_PAD_LEFT)?></div>
        <div class="st-name"><?=htmlspecialchars($s['station_name'])?></div>
        <div class="st-city">📍 <?=htmlspecialchars($s['city'])?></div>
        <a href="?tab=stations&del_station=<?=$s['station_id']?>" class="btn-sm btn-danger" onclick="return confirm('Remove station?')">🗑 Remove</a>
      </div>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- ════════════ BOOKINGS ════════════ -->
    <?php if($tab==='bookings'): ?>

    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
      <div class="sc"><div class="sc-top"><div class="sc-icon i-p">📊</div></div><div class="sc-num" style="color:var(--accent2)"><?=$st_bookings?></div><div class="sc-label">Total</div></div>
      <div class="sc"><div class="sc-top"><div class="sc-icon i-gr">✅</div></div><div class="sc-num" style="color:#34d399"><?=$st_confirmed?></div><div class="sc-label">Confirmed</div></div>
      <div class="sc"><div class="sc-top"><div class="sc-icon i-g">⏳</div></div><div class="sc-num" style="color:var(--gold)"><?=$st_pending?></div><div class="sc-label">Pending</div></div>
      <div class="sc"><div class="sc-top"><div class="sc-icon i-r">❌</div></div><div class="sc-num" style="color:var(--red)"><?=$st_cancelled?></div><div class="sc-label">Cancelled</div></div>
    </div>

    <div class="card">
      <div class="card-hd">
        <div><div class="card-title">All Bookings</div><div class="card-sub"><?=$st_bookings?> total records</div></div>
        <input type="text" class="search-input" placeholder="🔍 Search bookings..." oninput="searchTable(this,'bookings-tbl')">
      </div>
      <div class="tbl-wrap">
        <table class="tbl" id="bookings-tbl">
          <thead><tr><th>ID</th><th>Passenger</th><th>Train</th><th>User</th><th>Travel Date</th><th>Seat</th><th>Fare</th><th>Status</th><th>Booked</th><th>Actions</th></tr></thead>
          <tbody>
            <?php
            $found=false;
            while($b=mysqli_fetch_assoc($bookings)):
              $found=true;
              $bs=$b['booking_status']==='Confirmed'?'b-c':($b['booking_status']==='Pending'?'b-p':'b-x');
            ?>
            <tr>
              <td><span class="chip">#<?=$b['booking_id']?></span></td>
              <td><strong><?=htmlspecialchars($b['pname']??'—')?></strong><br><span style="font-size:10px;color:var(--muted)"><?=$b['age']??''?> · <?=$b['gender']??''?></span></td>
              <td><span class="chip-t"><?=htmlspecialchars($b['train_name']??'—')?></span><br><span style="font-size:10px;color:var(--muted)"><?=htmlspecialchars(($b['from_station']??'').' → '.($b['to_station']??''))?></span></td>
              <td style="font-size:12px"><?=htmlspecialchars($b['uname']??'Guest')?><br><span style="color:var(--muted);font-size:10px"><?=htmlspecialchars($b['uemail']??'')?></span></td>
              <td><?=$b['travel_date']?date('d M Y',strtotime($b['travel_date'])):'—'?></td>
              <td><?=$b['seat_number']?"<span class='chip-s'>{$b['seat_number']}</span>":'—'?></td>
              <td><span class="chip-r">₹<?=number_format($b['fare'],0)?></span></td>
              <td><span class="badge <?=$bs?>"><?=$b['booking_status']?></span></td>
              <td style="font-size:11px;color:var(--muted)"><?=date('d M,H:i',strtotime($b['booking_time']))?></td>
              <td>
                <a href="?tab=bookings&bid=<?=$b['booking_id']?>&bstatus=Confirmed" class="btn-sm btn-ok" title="Confirm">✓</a>
                <a href="?tab=bookings&bid=<?=$b['booking_id']?>&bstatus=Pending"   class="btn-sm btn-warn" title="Pending">⏳</a>
                <a href="?tab=bookings&bid=<?=$b['booking_id']?>&bstatus=Cancelled" class="btn-sm btn-danger" title="Cancel" onclick="return confirm('Cancel?')">✗</a>
                <a href="?tab=bookings&del_booking=<?=$b['booking_id']?>" class="btn-sm btn-danger" title="Delete" onclick="return confirm('Delete permanently?')">🗑</a>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if(!$found): ?><tr><td colspan="10"><div class="empty"><div class="empty-icon">🎫</div><p>No bookings found.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; ?>

    <!-- ════════════ USERS ════════════ -->
    <?php if($tab==='users'): ?>

    <div class="two-col">

      <!-- ADD USER FORM -->
      <div class="card">
        <div class="card-hd"><div><div class="card-title">Add User</div><div class="card-sub">Register a new account</div></div></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="do_add_user" value="1">
            <div class="field"><label>Full Name *</label><input type="text" name="uname" placeholder="Full name" required></div>
            <div class="field"><label>Email *</label><input type="email" name="email" placeholder="email@example.com" required></div>
            <div class="two-field">
              <div class="field"><label>Password *</label><input type="password" name="password" placeholder="Min 6 chars" required></div>
              <div class="field"><label>Phone</label><input type="tel" name="phone" placeholder="9876543210"></div>
            </div>
            <div class="field"><label>Role</label>
              <select name="role"><option value="user">User — Passenger</option><option value="admin">Admin — System Admin</option></select>
            </div>
            <button type="submit" class="full-btn full-btn-primary">👤 Create User</button>
          </form>
        </div>
      </div>

      <!-- USERS TABLE -->
      <div class="card">
        <div class="card-hd">
          <div><div class="card-title">All Users</div><div class="card-sub"><?=$st_users?> registered</div></div>
          <input type="text" class="search-input" placeholder="🔍 Search..." oninput="searchTable(this,'users-tbl')">
        </div>
        <div class="tbl-wrap">
          <table class="tbl" id="users-tbl">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
            <tbody>
              <?php
              $found=false;
              while($u=mysqli_fetch_assoc($users)):
                $found=true;
              ?>
              <tr>
                <td><span class="chip">#<?=$u['user_id']?></span></td>
                <td><strong><?=htmlspecialchars($u['name'])?></strong></td>
                <td style="font-size:12px"><?=htmlspecialchars($u['email'])?></td>
                <td style="font-size:12px"><?=htmlspecialchars($u['phone']??'—')?></td>
                <td><span class="badge <?=$u['role']==='admin'?'b-admin':'b-user'?>"><?=$u['role']==='admin'?'⚡ Admin':'👤 User'?></span></td>
                <td style="font-size:11px;color:var(--muted)"><?=date('d M Y',strtotime($u['created_at']))?></td>
                <td>
                  <?php if($u['user_id']!=$_SESSION['user_id']): ?>
                  <a href="?tab=users&toggle_role=<?=$u['user_id']?>" class="btn-sm btn-edit" title="Toggle role">⇄ Role</a>
                  <a href="?tab=users&del_user=<?=$u['user_id']?>" class="btn-sm btn-danger" onclick="return confirm('Delete user?')">🗑</a>
                  <?php else: ?>
                  <span style="font-size:11px;color:var(--muted)">You</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php if(!$found): ?><tr><td colspan="7"><div class="empty"><div class="empty-icon">👥</div><p>No users.</p></div></td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <?php endif; ?>

  </div>

    <!-- ════════════ PAYMENTS ════════════ -->
    <?php if($tab==='payments'): ?>
    <?php
    $payments = mysqli_query($conn,"
        SELECT py.*, b.seat_number, p.name pname, t.train_name, u.name uname, u.email uemail
        FROM payments py
        LEFT JOIN bookings b ON py.booking_id=b.booking_id
        LEFT JOIN passengers p ON b.passenger_id=p.passenger_id
        LEFT JOIN trains t ON b.train_id=t.train_id
        LEFT JOIN users u ON py.user_id=u.user_id
        ORDER BY py.payment_id DESC
    ");
    $total_rev  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(amount) r FROM payments WHERE payment_status='Success'"))['r'] ?? 0;
    $total_pays = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM payments WHERE payment_status='Success'"))['c'];
    $avg_pay    = $total_pays > 0 ? round($total_rev/$total_pays,2) : 0;
    ?>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
      <div class="sc"><div class="sc-top"><div class="sc-icon i-g">💰</div></div><div class="sc-num" style="color:var(--gold);font-size:24px">₹<?=number_format($total_rev,0)?></div><div class="sc-label">Total Revenue</div></div>
      <div class="sc"><div class="sc-top"><div class="sc-icon i-gr">✅</div></div><div class="sc-num" style="color:#34d399"><?=$total_pays?></div><div class="sc-label">Successful Payments</div></div>
      <div class="sc"><div class="sc-top"><div class="sc-icon i-b">📊</div></div><div class="sc-num" style="color:#60a5fa;font-size:22px">₹<?=number_format($avg_pay,0)?></div><div class="sc-label">Avg. Transaction</div></div>
      <div class="sc"><div class="sc-top"><div class="sc-icon i-p">🧾</div></div><div class="sc-num" style="color:var(--accent2)"><?=mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM payments"))['c']?></div><div class="sc-label">Total Transactions</div></div>
    </div>

    <div class="card">
      <div class="card-hd">
        <div><div class="card-title">Payment Records</div><div class="card-sub">All payment transactions</div></div>
        <input type="text" class="search-input" placeholder="🔍 Search payments..." oninput="searchTable(this,'pay-tbl')">
      </div>
      <div class="tbl-wrap">
        <table class="tbl" id="pay-tbl">
          <thead><tr><th>Pay ID</th><th>Booking</th><th>Passenger</th><th>Train</th><th>User</th><th>Method</th><th>Amount</th><th>TXN ID</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php
            $found=false;
            while($py=mysqli_fetch_assoc($payments)):
              $found=true;
              $ps=$py['payment_status']==='Success'?'b-c':'b-x';
            ?>
            <tr>
              <td><span class="chip">#<?=$py['payment_id']?></span></td>
              <td><span class="chip">#<?=$py['booking_id']?></span></td>
              <td><strong><?=htmlspecialchars($py['pname']??'—')?></strong></td>
              <td><span class="chip-t"><?=htmlspecialchars($py['train_name']??'—')?></span></td>
              <td style="font-size:12px"><?=htmlspecialchars($py['uname']??'Guest')?></td>
              <td><?=htmlspecialchars($py['payment_method'])?><?=$py['payment_method']==='Card'?" ···· ".$py['card_last4']:''?></td>
              <td><span class="chip-r">₹<?=number_format($py['amount'],2)?></span></td>
              <td style="font-size:11px;color:var(--muted);font-family:monospace"><?=htmlspecialchars($py['transaction_id']??'—')?></td>
              <td><span class="badge <?=$ps?>"><?=$py['payment_status']?></span></td>
              <td style="font-size:11px;color:var(--muted)"><?=date('d M Y H:i',strtotime($py['paid_at']))?></td>
            </tr>
            <?php endwhile; ?>
            <?php if(!$found): ?><tr><td colspan="10"><div class="empty"><div class="empty-icon">💰</div><p>No payments yet.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
function searchTable(input, id) {
  const f = input.value.toLowerCase();
  document.querySelectorAll('#'+id+' tbody tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(f) ? '' : 'none';
  });
}

function searchCards(input) {
  const f = input.value.toLowerCase();
  document.querySelectorAll('#station-cards .station-card').forEach(c => {
    c.style.display = (c.dataset.search||'').includes(f) ? '' : 'none';
  });
}

document.querySelectorAll('.alert').forEach(el => {
  setTimeout(()=>{ el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }, 5000);
});

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});
</script>
</body>
</html>
