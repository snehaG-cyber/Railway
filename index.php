<?php
require_once 'db.php';

if (isLoggedIn()) {
    header("Location: " . (isAdmin() ? "admin.php" : "dashboard.php"));
    exit;
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // login | register

// ── LOGIN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
    $email = clean($conn, $_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $error = "Please enter your email and password.";
    } else {
        $res = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' LIMIT 1");
        $user = mysqli_fetch_assoc($res);
        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email']= $user['email'];
            $_SESSION['role']      = $user['role'] ?? 'user';
            header("Location: " . ($user['role'] === 'admin' ? "admin.php" : "dashboard.php"));
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}

// ── REGISTER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_register'])) {
    $name  = clean($conn, $_POST['name']  ?? '');
    $email = clean($conn, $_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $phone = clean($conn, $_POST['phone'] ?? '');
    $mode  = 'register';

    if (!$name || !$email || !$pass) {
        $error = "Name, email, and password are required.";
    } elseif (strlen($pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM users WHERE email='$email'"));
        if ($exists) {
            $error = "An account with this email already exists.";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            mysqli_query($conn, "INSERT INTO users (name, email, password, phone, role) VALUES ('$name','$email','$hashed','$phone','user')");
            $success = "Account created! You can now sign in.";
            $mode = 'login';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RailMatrix — Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@700;900&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
  --bg:      #05080f;
  --surface: #0b0f1a;
  --border:  rgba(255,255,255,0.08);
  --accent:  #e8b86d;
  --accent2: #d4a55a;
  --text:    #eef2f7;
  --muted:   #6b7280;
  --error:   #f87171;
  --success: #6ee7b7;
}

html, body { height: 100%; }

body {
  font-family: 'Instrument Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  display: flex;
  min-height: 100vh;
  overflow: hidden;
}

/* LEFT PANEL */
.left-panel {
  flex: 1;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 60px;
  background: linear-gradient(160deg, #07112b 0%, #020810 100%);
}

.left-panel::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 30% 30%, rgba(232,184,109,0.08) 0%, transparent 60%),
    radial-gradient(ellipse at 70% 80%, rgba(59,130,246,0.06) 0%, transparent 50%);
  pointer-events: none;
}

/* TRACK LINES DECORATION */
.track-art {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  overflow: hidden;
  pointer-events: none;
}

.track-line {
  position: absolute;
  background: rgba(232,184,109,0.06);
  transform-origin: left center;
}

.tl1 { width: 200%; height: 1px; top: 35%; left: -10%; transform: rotate(-8deg); }
.tl2 { width: 200%; height: 1px; top: 37%; left: -10%; transform: rotate(-8deg); }
.tl3 { width: 200%; height: 1px; top: 55%; left: -10%; transform: rotate(-12deg); }
.tl4 { width: 200%; height: 1px; top: 57%; left: -10%; transform: rotate(-12deg); }
.tl5 { width: 200%; height: 2px; top: 20%; left: -10%; transform: rotate(-4deg); background: rgba(232,184,109,0.03); }

.crossbar {
  position: absolute;
  background: rgba(232,184,109,0.06);
  height: 40px;
  width: 1px;
}

/* LEFT CONTENT */
.left-logo {
  position: absolute;
  top: 50px;
  left: 60px;
  display: flex;
  align-items: center;
  gap: 14px;
}

.left-logo-mark {
  width: 44px;
  height: 44px;
  border: 2px solid rgba(232,184,109,0.5);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  background: rgba(232,184,109,0.05);
}

.left-logo-text {
  font-family: 'Fraunces', serif;
  font-size: 22px;
  font-weight: 900;
  letter-spacing: -0.5px;
  color: var(--text);
}

.left-logo-text span { color: var(--accent); }

.left-content { position: relative; z-index: 2; }

.left-headline {
  font-family: 'Fraunces', serif;
  font-size: 52px;
  font-weight: 900;
  line-height: 1.05;
  letter-spacing: -1.5px;
  color: var(--text);
  margin-bottom: 20px;
}

.left-headline em {
  font-style: normal;
  color: var(--accent);
  display: block;
}

.left-desc {
  font-size: 15px;
  color: var(--muted);
  line-height: 1.7;
  max-width: 380px;
  margin-bottom: 40px;
}

.left-stats {
  display: flex;
  gap: 32px;
}

.left-stat { border-left: 2px solid rgba(232,184,109,0.3); padding-left: 16px; }
.left-stat-num {
  font-family: 'Fraunces', serif;
  font-size: 28px;
  font-weight: 700;
  color: var(--accent);
  line-height: 1;
}
.left-stat-label { font-size: 12px; color: var(--muted); margin-top: 3px; }

/* RIGHT PANEL */
.right-panel {
  width: 480px;
  flex-shrink: 0;
  background: var(--surface);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 60px 50px;
  position: relative;
  overflow-y: auto;
}

.right-panel::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--accent), transparent);
}

.form-header { margin-bottom: 36px; }

.form-title {
  font-family: 'Fraunces', serif;
  font-size: 32px;
  font-weight: 900;
  letter-spacing: -0.8px;
  color: var(--text);
  margin-bottom: 8px;
}

.form-subtitle { font-size: 14px; color: var(--muted); line-height: 1.5; }

/* TABS */
.auth-tabs {
  display: flex;
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 4px;
  margin-bottom: 32px;
}

.auth-tab {
  flex: 1;
  padding: 10px;
  text-align: center;
  font-size: 14px;
  font-weight: 600;
  color: var(--muted);
  border-radius: 7px;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
  display: block;
}

.auth-tab.active {
  background: rgba(232,184,109,0.12);
  color: var(--accent);
  border: 1px solid rgba(232,184,109,0.25);
}

.auth-tab:hover:not(.active) { color: var(--text); }

/* FIELDS */
.field { margin-bottom: 18px; }
.field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: rgba(238,242,247,0.6);
  margin-bottom: 8px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.field input {
  width: 100%;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 9px;
  color: var(--text);
  font-family: 'Instrument Sans', sans-serif;
  font-size: 15px;
  padding: 13px 16px;
  outline: none;
  transition: all 0.2s;
}

.field input:focus {
  border-color: var(--accent);
  background: rgba(232,184,109,0.04);
  box-shadow: 0 0 0 3px rgba(232,184,109,0.08);
}

.field input::placeholder { color: rgba(107,114,128,0.8); }

.two-field { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* BUTTON */
.btn-auth {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, var(--accent) 0%, var(--accent2) 100%);
  border: none;
  border-radius: 10px;
  font-family: 'Instrument Sans', sans-serif;
  font-size: 15px;
  font-weight: 600;
  color: #0a0a0a;
  cursor: pointer;
  margin-top: 8px;
  transition: all 0.2s;
  letter-spacing: 0.2px;
}

.btn-auth:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 30px rgba(232,184,109,0.3);
}

.btn-auth:active { transform: translateY(0); }

/* ALERT */
.alert {
  padding: 13px 16px;
  border-radius: 9px;
  font-size: 13px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.alert-error   { background: rgba(248,113,113,0.1);  border: 1px solid rgba(248,113,113,0.25); color: var(--error); }
.alert-success { background: rgba(110,231,183,0.1); border: 1px solid rgba(110,231,183,0.25); color: var(--success); }

/* DIVIDER */
.or-divider {
  display: flex;
  align-items: center;
  gap: 14px;
  margin: 22px 0;
  font-size: 12px;
  color: var(--muted);
}
.or-divider::before, .or-divider::after {
  content:'';
  flex: 1;
  height: 1px;
  background: var(--border);
}

/* ADMIN HINT */
.admin-hint {
  margin-top: 28px;
  padding: 14px 16px;
  background: rgba(232,184,109,0.04);
  border: 1px solid rgba(232,184,109,0.15);
  border-radius: 9px;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.7;
}

.admin-hint strong { color: var(--accent); }

/* PASSWORD TOGGLE */
.pw-wrap { position: relative; }
.pw-toggle {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  font-size: 16px;
  padding: 0;
}

/* RESPONSIVE */
@media(max-width:900px){
  .left-panel { display: none; }
  .right-panel { width: 100%; border-left: none; }
}
</style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
  <div class="track-art">
    <div class="track-line tl1"></div>
    <div class="track-line tl2"></div>
    <div class="track-line tl3"></div>
    <div class="track-line tl4"></div>
    <div class="track-line tl5"></div>
    <?php for($i=0;$i<20;$i++): $left = rand(0,100); $top = rand(30,60); ?>
    <div class="crossbar" style="left:<?=$left?>%;top:<?=$top?>%;height:<?=rand(30,60)?>px;transform:rotate(<?=-8+rand(-3,3)?>deg)"></div>
    <?php endfor; ?>
  </div>

  <div class="left-logo">
    <div class="left-logo-mark">🚆</div>
    <div class="left-logo-text">Rail<span>Matrix</span></div>
  </div>

  <div class="left-content">
    <div class="left-headline">
      Move India,<em>On Schedule.</em>
    </div>
    <p class="left-desc">
      Enterprise-grade railway reservation platform. Book tickets, manage trains, track journeys — all in one unified system built for scale.
    </p>
    <div class="left-stats">
      <div class="left-stat">
        <div class="left-stat-num">500+</div>
        <div class="left-stat-label">Trains Daily</div>
      </div>
      <div class="left-stat">
        <div class="left-stat-num">2M+</div>
        <div class="left-stat-label">Passengers/Year</div>
      </div>
      <div class="left-stat">
        <div class="left-stat-num">99.8%</div>
        <div class="left-stat-label">Uptime</div>
      </div>
    </div>
  </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">

  <div class="form-header">
    <div class="form-title"><?= $mode === 'register' ? 'Create Account' : 'Welcome Back' ?></div>
    <p class="form-subtitle"><?= $mode === 'register' ? 'Set up your RailMatrix account to get started.' : 'Sign in to your account to continue.' ?></p>
  </div>

  <div class="auth-tabs">
    <a href="?mode=login"    class="auth-tab <?= $mode==='login'    ? 'active':'' ?>">Sign In</a>
    <a href="?mode=register" class="auth-tab <?= $mode==='register' ? 'active':'' ?>">Register</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- LOGIN FORM -->
  <?php if ($mode === 'login'): ?>
  <form method="POST">
    <input type="hidden" name="do_login" value="1">

    <div class="field">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
    </div>

    <div class="field">
      <label>Password</label>
      <div class="pw-wrap">
        <input type="password" name="password" id="pw1" placeholder="••••••••" autocomplete="current-password" required>
        <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)">👁</button>
      </div>
    </div>

    <button type="submit" class="btn-auth">Sign In →</button>
  </form>

  <!-- <div class="admin-hint">
    <strong>Admin Access:</strong><br>
    Email: <strong>admin@railsys.com</strong> &nbsp;|&nbsp; Password: <strong>admin123</strong>
  </div> -->

  <!-- REGISTER FORM -->
  <?php else: ?>
  <form method="POST">
    <input type="hidden" name="do_register" value="1">

    <div class="two-field">
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="name" placeholder="Ravi Kumar" required>
      </div>
      <div class="field">
        <label>Phone</label>
        <input type="tel" name="phone" placeholder="9876543210">
      </div>
    </div>

    <div class="field">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="you@example.com" required>
    </div>

    <div class="field">
      <label>Password</label>
      <div class="pw-wrap">
        <input type="password" name="password" id="pw2" placeholder="Min. 6 characters" required>
        <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)">👁</button>
      </div>
    </div>

    <button type="submit" class="btn-auth">Create Account →</button>
  </form>
  <?php endif; ?>

</div>

<script>
function togglePw(id, btn) {
  const input = document.getElementById(id);
  input.type = input.type === 'password' ? 'text' : 'password';
  btn.textContent = input.type === 'password' ? '👁' : '🔒';
}
</script>
</body>
</html>