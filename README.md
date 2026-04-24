# Smoker Shell v4.0

Advanced PHP web shell built for authorized penetration testing and red team engagements.

## Features

| Module | Capabilities |
|---|---|
| **Dashboard** | System stats, server info, PHP configuration overview |
| **File Manager** | Browse, view, edit, upload, download, hex view, chmod, create, rename, delete |
| **Terminal** | Command execution with session history, multi-method exec fallback |
| **System Recon** | OS info, PHP config, users, processes, cron jobs, environment, network interfaces |
| **Network Tools** | TCP port scanner, reverse shell generator (11 types), back connect, URL fetcher, DNS lookup |
| **Database** | MySQL and SQLite client with query execution |
| **Priv-Esc** | SUID/SGID finder, writable dirs, capabilities, kernel exploit heuristics, sudo check, container detection |
| **Encoding** | Base64, Hex, URL, HTML encode/decode, ROT13, MD5/SHA1/SHA256/SHA512/CRC32 |
| **Stealth** | Log management, timestomping, shell history wipe, self-destruct |

## Deployment

Drop `shell.php` into any PHP-enabled web server directory.

```
# Quick local test
php -S 0.0.0.0:8888 -t .
```

Default password: `admin` (change the `$CONF['passwd']` value at the top of the file).

## AV Evasion

For deployment on hosts with active antivirus (e.g. Windows Defender), wrap with the included `__halt_compiler()` loader technique — keeps the payload in-memory only, never touching disk in cleartext.

## Disclaimer

This tool is intended **exclusively** for authorized security assessments and penetration testing under a valid contract or written authorization. Unauthorized use against systems you do not own or have explicit permission to test is illegal. The author assumes no liability for misuse.
