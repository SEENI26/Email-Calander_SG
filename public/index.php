<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Singapore');

$departments = ['Design Center', 'QC', 'Account'];
$shifts = ['morning' => '7:30 AM – 12:00 PM', 'afternoon' => '12:00 PM – 5:00 PM'];
$storageDir = dirname(__DIR__) . '/storage';
if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    http_response_code(500);
    exit('Could not create the storage directory. Check the folder permissions.');
}
$db = new PDO('sqlite:' . $storageDir . '/email_counts.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE IF NOT EXISTS email_counts (
    entry_date TEXT NOT NULL,
    department TEXT NOT NULL,
    morning_count INTEGER NOT NULL DEFAULT 0 CHECK (morning_count >= 0),
    afternoon_count INTEGER NOT NULL DEFAULT 0 CHECK (afternoon_count >= 0),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (entry_date, department)
)');

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function validDate(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$today = new DateTimeImmutable('today');
$selectedDate = isset($_REQUEST['date']) && validDate((string)$_REQUEST['date']) ? (string)$_REQUEST['date'] : $today->format('Y-m-d');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department = (string)($_POST['department'] ?? '');
    $morning = filter_var($_POST['morning_count'] ?? null, FILTER_VALIDATE_INT);
    $afternoon = filter_var($_POST['afternoon_count'] ?? null, FILTER_VALIDATE_INT);
    if (!validDate($selectedDate)) {
        $error = 'Choose a valid date.';
    } elseif (!in_array($department, $departments, true)) {
        $error = 'Choose one of the listed departments.';
    } elseif ($morning === false || $afternoon === false || $morning < 0 || $afternoon < 0) {
        $error = 'Enter whole numbers of zero or more for both shifts.';
    } else {
        $save = $db->prepare('INSERT INTO email_counts (entry_date, department, morning_count, afternoon_count, updated_at)
            VALUES (:date, :department, :morning, :afternoon, CURRENT_TIMESTAMP)
            ON CONFLICT(entry_date, department) DO UPDATE SET
              morning_count = excluded.morning_count,
              afternoon_count = excluded.afternoon_count,
              updated_at = CURRENT_TIMESTAMP');
        $save->execute([':date' => $selectedDate, ':department' => $department, ':morning' => $morning, ':afternoon' => $afternoon]);
        header('Location: ?date=' . urlencode($selectedDate) . '&department=' . urlencode($department) . '&saved=1');
        exit;
    }
}
if (isset($_GET['saved'])) $message = 'Counts saved.';

$selectedDepartment = in_array((string)($_GET['department'] ?? ''), $departments, true) ? (string)$_GET['department'] : $departments[0];
$getEntry = $db->prepare('SELECT morning_count, afternoon_count FROM email_counts WHERE entry_date = ? AND department = ?');
$getEntry->execute([$selectedDate, $selectedDepartment]);
$currentEntry = $getEntry->fetch(PDO::FETCH_ASSOC) ?: ['morning_count' => '', 'afternoon_count' => ''];

$chosen = new DateTimeImmutable($selectedDate);
$weekStart = $chosen->modify('monday this week');
$weekDates = [];
for ($i = 0; $i < 6; $i++) $weekDates[] = $weekStart->modify('+' . $i . ' days');
$weekEnd = $weekDates[5];
$from = $weekStart->format('Y-m-d');
$to = $weekEnd->format('Y-m-d');
$query = $db->prepare('SELECT entry_date, department, morning_count, afternoon_count FROM email_counts WHERE entry_date BETWEEN ? AND ?');
$query->execute([$from, $to]);
$records = [];
foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $records[$row['entry_date']][$row['department']] = $row;

$dailyTotals = [];
$grandTotal = 0;
foreach ($weekDates as $date) {
    $key = $date->format('Y-m-d');
    $morningTotal = 0;
    $afternoonTotal = 0;
    foreach ($departments as $dept) {
        $row = $records[$key][$dept] ?? ['morning_count' => 0, 'afternoon_count' => 0];
        $morningTotal += (int)$row['morning_count'];
        $afternoonTotal += (int)$row['afternoon_count'];
    }
    $dailyTotals[$key] = ['morning' => $morningTotal, 'afternoon' => $afternoonTotal, 'total' => $morningTotal + $afternoonTotal];
    $grandTotal += $dailyTotals[$key]['total'];
}
$weeklyByDepartment = [];
foreach ($departments as $dept) {
    $weeklyByDepartment[$dept] = ['morning' => 0, 'afternoon' => 0, 'total' => 0];
    foreach ($weekDates as $date) {
        $row = $records[$date->format('Y-m-d')][$dept] ?? ['morning_count' => 0, 'afternoon_count' => 0];
        $weeklyByDepartment[$dept]['morning'] += (int)$row['morning_count'];
        $weeklyByDepartment[$dept]['afternoon'] += (int)$row['afternoon_count'];
    }
    $weeklyByDepartment[$dept]['total'] = $weeklyByDepartment[$dept]['morning'] + $weeklyByDepartment[$dept]['afternoon'];
}
$weekLabel = $weekStart->format('j M Y') . ' – ' . $weekEnd->format('j M Y');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email Inward Count Tracker</title>
  <style>
    :root { color-scheme: light; --navy:#17324d; --blue:#2563a6; --pale:#eef5fc; --line:#d7e0e9; --ink:#1d2935; --yellow:#ffea00; }
    * { box-sizing:border-box }
    body { margin:0; background:#f3f6f9; color:var(--ink); font:15px/1.45 Arial,Helvetica,sans-serif }
    header { background:var(--navy); color:#fff; padding:25px max(20px,calc((100% - 1200px)/2)) }
    header h1 { margin:0 0 5px; font-size:24px } header p { margin:0; color:#d7e7f5 }
    main { max-width:1200px; margin:25px auto; padding:0 16px 40px }
    .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 3px 12px #162e4410 }
    h2 { color:var(--navy); font-size:19px; margin:0 0 16px }
    .entry-grid { display:grid; grid-template-columns:1.1fr 1fr 1fr 1fr auto; gap:14px; align-items:end }
    label { display:block; font-weight:700; margin-bottom:6px }
    input,select,button { font:inherit; min-height:42px; border:1px solid #b9c7d5; border-radius:7px; padding:8px 10px; width:100%; background:#fff; color:var(--ink) }
    button { width:auto; min-width:110px; background:var(--blue); border-color:var(--blue); color:#fff; font-weight:700; cursor:pointer }
    button:hover { background:#184d82 }
    input:focus-visible,select:focus-visible,button:focus-visible,.btn-secondary:focus-visible { outline:3px solid var(--yellow); outline-offset:2px; border-color:var(--navy) }
    .dd { position:relative }
    .dd summary { list-style:none; position:relative; display:flex; align-items:center; min-height:42px; padding:8px 40px 8px 12px; border:1px solid #b9c7d5; border-radius:7px; background:#fff; color:var(--ink); cursor:pointer; user-select:none }
    .dd summary::-webkit-details-marker { display:none }
    .dd summary:hover { border-color:var(--blue) }
    .dd summary:focus-visible { outline:3px solid var(--yellow); outline-offset:2px }
    .dd[open] summary { border-color:var(--navy); box-shadow:0 0 0 3px #2563a633 }
    .dd summary::after { content:""; position:absolute; right:16px; top:50%; width:8px; height:8px; border-right:2px solid var(--navy); border-bottom:2px solid var(--navy); transform:translateY(-70%) rotate(45deg); transition:transform .15s }
    .dd[open] summary::after { transform:translateY(-30%) rotate(225deg) }
    .dd-menu { position:absolute; z-index:10; top:calc(100% + 6px); left:0; right:0; background:#fff; border:1px solid var(--line); border-radius:10px; box-shadow:0 12px 28px #162e4420; padding:6px }
    .dd-opt { display:flex; align-items:center; gap:10px; margin:0; padding:10px 12px; border-radius:7px; font-weight:400; cursor:pointer }
    .dd-opt:hover { background:var(--pale) }
    .dd-opt:has(input:checked) { background:#dceafa; color:var(--navy); font-weight:700 }
    .dd-opt input[type=radio] { width:auto; min-height:0; margin:0; padding:0; border:0; accent-color:var(--blue) }
    .dd-opt:focus-within { outline:3px solid var(--yellow); outline-offset:-3px }
    .btn-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap }
    .btn-secondary { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:8px 14px; border:1px solid var(--blue); border-radius:7px; background:#fff; color:var(--blue); font-weight:700; text-decoration:none }
    .btn-secondary:hover { background:var(--pale) }
    .span-2 { grid-column:span 2 }
    .summary { background:var(--pale); border:1px solid var(--line); border-radius:7px; padding:10px 12px; font-weight:700; color:var(--navy); min-height:42px; display:flex; align-items:center }
    .note { color:#5c6b79; font-size:13px; margin:12px 0 0 }
    .alert { padding:11px 14px; border-radius:7px; margin-bottom:16px }
    .success { background:#e8f7ee; color:#17623a } .error { background:#fff0ed; color:#9b2c1d }
    .week-heading { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap }
    .week-heading h2 { margin:0 } .week-heading p { margin:0; color:#566574 }
    .table-wrap { overflow-x:auto; border:1px solid var(--line); border-radius:8px }
    table { border-collapse:collapse; width:100%; min-width:860px; background:#fff; font-size:14px }
    th,td { border:1px solid #25313b; padding:9px 10px; text-align:center }
    thead tr:first-child th { background:#ffc400; color:#111; font-size:17px }
    thead tr:nth-child(2) th { background:var(--yellow); color:#111 }
    th:first-child,td:first-child { text-align:left; font-weight:700; min-width:145px }
    .day-head { display:block; font-weight:400; font-size:12px; margin-top:3px }
    .dept-row th { text-align:center; background:#dceafa; color:var(--navy); font-size:15px; padding:8px 11px }
    .row-label { font-weight:400 !important }
    .total-row td,.total-row th { background:#f0f4f7; font-weight:700 }
    .week-total { font-weight:700; background:#fff9d6 }
    tfoot th,tfoot td { background:#eaf0f5; font-weight:700 }
    .small { font-size:12px; color:#637282 }
    @media(max-width:800px) { .entry-grid { grid-template-columns:1fr 1fr } .entry-grid button { width:100% } main { margin-top:16px } .card { padding:16px } }
    @media(max-width:480px) { .entry-grid { grid-template-columns:1fr } .span-2 { grid-column:auto } header h1 { font-size:21px } }
    @media print { body { background:#fff } header { padding:12px 0; color:#111; background:#fff; border-bottom:2px solid #111 } header p { color:#333 } main { max-width:none; margin:12px 0; padding:0 } .entry-card,.no-print { display:none } .card { box-shadow:none; border:0; padding:0 } .table-wrap { overflow:visible; border:0 } table { min-width:0; font-size:10px } th,td { padding:5px } }
  </style>
</head>
<body>
<header><h1>Email Inward Count Tracker</h1><p>Daily counts for Design Center, QC and Account · weekly totals calculated automatically</p></header>
<main>
  <section class="card entry-card">
    <h2>Enter or update daily counts</h2>
    <?php if ($message): ?><div class="alert success"><?=h($message)?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>
    <form method="get" class="entry-grid" id="entry-select-form">
      <div><label for="date">Date</label><input type="date" id="date" name="date" value="<?=h($selectedDate)?>" required></div>
      <div><label id="department-label">User / department</label><details class="dd" id="department-dd"><summary aria-labelledby="department-label"><span class="dd-value"><?=h($selectedDepartment)?></span></summary><div class="dd-menu" role="radiogroup" aria-labelledby="department-label"><?php foreach($departments as $dept): ?><label class="dd-opt"><input type="radio" name="department" value="<?=h($dept)?>" <?=$selectedDepartment === $dept ? 'checked' : ''?>><span><?=h($dept)?></span></label><?php endforeach; ?></div></details></div>
      <div><label for="morning-preview">Selected record</label><input id="morning-preview" value="<?=h((string)$currentEntry['morning_count'])?> morning · <?=h((string)$currentEntry['afternoon_count'])?> afternoon" readonly aria-label="Current saved counts"></div>
      <div><label for="week-view">Report week (Monday–Saturday)</label><input id="week-view" value="<?=h($weekLabel)?>" readonly></div>
      <div class="btn-row"><button type="submit">Load date</button><a class="btn-secondary" href="?date=<?=h($today->format('Y-m-d'))?>&amp;department=<?=urlencode($selectedDepartment)?>">Today</a></div>
    </form>
    <form method="post" class="entry-grid" style="margin-top:15px">
      <input type="hidden" name="date" value="<?=h($selectedDate)?>">
      <input type="hidden" name="department" value="<?=h($selectedDepartment)?>">
      <div><label for="morning_count">Morning (7:30 AM–12:00 PM)</label><input type="number" id="morning_count" name="morning_count" min="0" step="1" inputmode="numeric" autocomplete="off" value="<?=h((string)$currentEntry['morning_count'])?>" required></div>
      <div><label for="afternoon_count">Afternoon (12:00 PM–5:00 PM)</label><input type="number" id="afternoon_count" name="afternoon_count" min="0" step="1" inputmode="numeric" autocomplete="off" value="<?=h((string)$currentEntry['afternoon_count'])?>" required></div>
      <div class="span-2"><label id="saving-to-label">Saving to</label><div class="summary" aria-labelledby="saving-to-label"><?=h($selectedDepartment)?> · <?=h((new DateTimeImmutable($selectedDate))->format('l, d/m/Y'))?></div></div>
      <div><button type="submit">Save counts</button></div>
    </form>
    <p class="note">Saved records remain in the SQLite database. Re-enter a date and department to correct or update their counts. The weekly report covers Monday through Saturday.</p>
  </section>

  <section class="card">
    <div class="week-heading"><div><h2>Weekly inward count</h2><p>Week ending Saturday, <?=h($weekEnd->format('j F Y'))?> <span class="small">(<?=h($weekLabel)?>)</span></p></div><button type="button" class="no-print" onclick="window.print()">Print report</button></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Department / shift</th><?php foreach($weekDates as $date): ?><th><?=h($date->format('d/m/Y'))?><span class="day-head"><?=h($date->format('l'))?></span></th><?php endforeach; ?><th>Weekly total</th></tr></thead>
        <tbody>
        <?php foreach($departments as $dept): ?>
          <tr class="dept-row"><th colspan="8"><?=h($dept)?></th></tr>
          <?php foreach($shifts as $shiftKey => $shiftLabel): $rowTotal = 0; ?>
            <tr><th class="row-label"><?=h($shiftLabel)?></th>
            <?php foreach($weekDates as $date): $key = $date->format('Y-m-d'); $count = (int)(($records[$key][$dept][$shiftKey . '_count'] ?? 0)); $rowTotal += $count; ?><td><?=$count?></td><?php endforeach; ?>
            <td class="week-total"><?=$rowTotal?></td></tr>
          <?php endforeach; ?>
          <tr class="total-row"><th><?=h($dept)?> daily total</th>
            <?php foreach($weekDates as $date): $key = $date->format('Y-m-d'); $row = $records[$key][$dept] ?? ['morning_count'=>0,'afternoon_count'=>0]; ?><td><?=(int)$row['morning_count'] + (int)$row['afternoon_count']?></td><?php endforeach; ?>
            <td><?=$weeklyByDepartment[$dept]['total']?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th>All departments</th><?php foreach($weekDates as $date): ?><td><?=$dailyTotals[$date->format('Y-m-d')]['total']?></td><?php endforeach; ?><td><?=$grandTotal?></td></tr></tfoot>
      </table>
    </div>
    <p class="note">Each date column shows the combined count for that day. The final column is the automatic Monday–Saturday total. Blank-entry days count as 0.</p>
  </section>
</main>
<script>
  (function () {
    var dd = document.getElementById('department-dd');
    if (!dd) return;
    var value = dd.querySelector('.dd-value');
    dd.querySelectorAll('input[type=radio]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        value.textContent = radio.value;
        dd.open = false;
        dd.querySelector('summary').focus();
      });
    });
    document.addEventListener('click', function (event) {
      if (dd.open && !dd.contains(event.target)) dd.open = false;
    });
  })();
</script>
</body>
</html>
