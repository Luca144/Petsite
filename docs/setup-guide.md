# Felkyo Creatures — Setup Guide

This guide gets the project running on a fresh Windows machine, from nothing to a
working "hello" page and a passing test suite. It is written for someone who is
new to this — follow it top to bottom.

> **About the paths in this guide.** On this machine, PHP and MySQL come from
> **XAMPP** and are **not on the system PATH**. That means you cannot just type
> `php` or `mysql` — you have to use their full paths inside XAMPP. This guide
> uses those full paths everywhere so the commands "just work". If you would
> rather type short commands, see [Optional: add PHP to your PATH](#optional-add-php-to-your-path)
> at the end.

The full paths on this machine are:

| Tool | Full path |
| --- | --- |
| PHP (command line) | `C:\xampp\php\php.exe` |
| MySQL / MariaDB client | `C:\xampp\mysql\bin\mysql.exe` |
| Composer (installed into this project) | `C:\xampp\php\php.exe composer.phar` |

---

## 1. Prerequisites

You need these installed once. On this machine they are already present:

- **XAMPP** (provides PHP 8.2 and MariaDB). Download: <https://www.apachefriends.org/>
- **Git** and **Git LFS**. Git LFS stores our large art files efficiently.
  Install it once per machine with:

  ```powershell
  git lfs install
  ```

You do **not** need to install Composer separately — a local copy
(`composer.phar`) already lives in the project folder.

---

## 2. Start the database server

The project needs MariaDB running. Start it from the **XAMPP Control Panel**
(click *Start* next to *MySQL*), or from PowerShell:

```powershell
C:\xampp\mysql_start.bat
```

You can check it is up with:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT 'database is up';"
```

---

## 3. Get the code and configure it

From the project folder (`...\PythonProjects\Petpage`):

1. **Copy the example environment file to a real one.** The real `.env` holds
   your database details and is never committed to Git.

   ```powershell
   Copy-Item .env.example .env
   ```

   On a default XAMPP install the values already work (root user, empty
   password). Open `.env` and adjust only if your setup differs.

2. **Install the PHP dependencies** (libraries the project uses). This reads
   `composer.json` and fills the `vendor/` folder:

   ```powershell
   C:\xampp\php\php.exe composer.phar install
   ```

---

## 4. Create the databases

The project uses two databases: `felkyo` (the real one) and `felkyo_test` (used
only by the automated tests, so tests can never harm real data).

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS felkyo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS felkyo_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

> The database **tables** are created by migrations (increment 0.2 onward). This
> guide will gain a "run the migrations" step when they exist.

---

## 5. Run the site

Use PHP's built-in web server, pointed at the `public/` folder (the site's front
door):

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```

Then open <http://127.0.0.1:8000/> in your browser. You should see the Felkyo
welcome page. Press `Ctrl+C` in the terminal to stop the server.

---

## 6. Run the tests

The automated tests prove the project works. Run the whole suite with:

```powershell
C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit
```

A successful run ends with a line like `OK (2 tests, 3 assertions)`. The tests
use the separate `felkyo_test` database, so running them never touches your real
data.

---

## Optional: add PHP to your PATH

If you would like to type `php` instead of the full path, add XAMPP's PHP folder
to your PATH for the current PowerShell session:

```powershell
$env:Path = "C:\xampp\php;C:\xampp\mysql\bin;" + $env:Path
```

After that, `php`, `composer.phar`, and `mysql` work as short commands **in that
window**. To make it permanent, add `C:\xampp\php` and `C:\xampp\mysql\bin` to
your user PATH via *Settings → System → About → Advanced system settings →
Environment Variables*. This is entirely optional — every command in this guide
works without it.

---

## Troubleshooting

- **"no .env file found"** — you skipped step 3.1. Copy `.env.example` to `.env`.
- **"Can't connect to MySQL server"** — MariaDB is not running. Do step 2.
- **Composer says "zip extension … missing"** — enable it by removing the `;`
  in front of `extension=zip` in `C:\xampp\php\php.ini`, then retry.
