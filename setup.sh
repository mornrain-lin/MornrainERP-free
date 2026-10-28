#!/usr/bin/env bash
# ============================================================
#  港湾ERP - one-click setup (Linux / macOS)
#
#  Usage : ./setup.sh
#  Env   : PHP_BIN       path to php binary   (default: php)
#          COMPOSER_BIN  path to composer     (default: composer)
#          FORCE_SEED=1  re-run seeders even if data exists
#
#  Unlike the Windows .bat scripts this one is UTF-8 safe, so
#  Chinese messages are fine here. The .bat files stay ASCII
#  because cmd.exe decodes them with the OEM codepage.
# ============================================================
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

echo
echo "============================================================"
echo "  港湾ERP  -  one-click setup"
echo "============================================================"
echo

# ---------- 1. PHP ----------
if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "[ERROR] PHP not found: $PHP_BIN"
    echo "        Set PHP_BIN=/path/to/php and retry."
    exit 1
fi
echo "[1/7] PHP runtime  : $PHP_BIN"

if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION,"8.2.0",">=")?0:1);'; then
    echo "[ERROR] PHP >= 8.2 required, detected $("$PHP_BIN" -r 'echo PHP_VERSION;')"
    exit 1
fi

MISSING=$("$PHP_BIN" -r '
$m = ["pdo_sqlite","mbstring","openssl","fileinfo","curl","zip","intl"];
$miss = [];
foreach ($m as $e) { if (! extension_loaded($e)) { $miss[] = $e; } }
echo implode(", ", $miss);
')
if [ -n "$MISSING" ]; then
    echo "[ERROR] Missing PHP extensions: $MISSING"
    echo "        Install them, then run setup.sh again."
    exit 1
fi
echo "[2/7] PHP version  : ok (>= 8.2, extensions present)"

# ---------- 3. dependencies ----------
if [ -f vendor/autoload.php ]; then
    echo "[3/7] Dependencies: vendor/ present, skipping composer install"
else
    if ! command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
        echo "[ERROR] vendor/ missing and composer not found."
        echo "        Set COMPOSER_BIN=/path/to/composer and retry."
        exit 1
    fi
    echo "[3/7] Dependencies: installing via composer ..."
    "$COMPOSER_BIN" install --no-interaction --prefer-dist
fi

# ---------- 4. env file ----------
if [ ! -f .env ]; then
    if [ ! -f .env.example ]; then
        echo "[ERROR] Neither .env nor .env.example found."
        exit 1
    fi
    cp .env.example .env
    echo "[4/7] .env         : created from .env.example"
else
    echo "[4/7] .env         : already present, keeping existing config"
fi

# ---------- 5. app key ----------
if grep -q '^APP_KEY=base64:' .env; then
    echo "[5/7] APP_KEY      : already set"
else
    "$PHP_BIN" artisan key:generate --force
    echo "[5/7] APP_KEY      : generated"
fi

# ---------- 6. sqlite file ----------
DBCONN=$(grep '^DB_CONNECTION=' .env | cut -d= -f2- || echo "sqlite")
FRESHDB=0
if [ "${DBCONN:-sqlite}" = "sqlite" ]; then
    if [ ! -f database/database.sqlite ]; then
        mkdir -p database
        : > database/database.sqlite
        FRESHDB=1
        echo "[6/7] SQLite file  : database/database.sqlite created"
    else
        echo "[6/7] SQLite file  : database/database.sqlite present"
    fi
else
    echo "[6/7] Database     : $DBCONN (make sure the server is reachable)"
fi

# ---------- migrate ----------
echo
echo "---- Running migrations ----"
"$PHP_BIN" artisan migrate --force

# ---------- 7. seed ----------
DOSEED=0
[ "$FRESHDB" = "1" ] && DOSEED=1
[ "${FORCE_SEED:-0}" = "1" ] && DOSEED=1
if [ "$DOSEED" = "1" ]; then
    echo "---- Seeding demo data ----"
    "$PHP_BIN" artisan db:seed --force || echo "[WARN] Seeding failed, schema is ready anyway."
else
    echo "[7/7] Demo data    : skipped (database already has data)"
    echo "        Run FORCE_SEED=1 ./setup.sh to re-seed."
fi

# ---------- cache ----------
"$PHP_BIN" artisan config:clear >/dev/null 2>&1 || true
"$PHP_BIN" artisan cache:clear  >/dev/null 2>&1 || true
"$PHP_BIN" artisan route:clear  >/dev/null 2>&1 || true
"$PHP_BIN" artisan view:clear   >/dev/null 2>&1 || true

ADMINMAIL=$(grep '^INIT_ADMIN_EMAIL=' .env | cut -d= -f2- || echo "admin@gangwan.local")

echo
echo "============================================================"
echo "  Setup complete."
echo "============================================================"
echo "  Admin account : ${ADMINMAIL}"
echo "  Password      : the INIT_ADMIN_PASSWORD value in .env"
echo "                  (change it before any real use)"
echo
echo "  Start the app : php artisan serve --host=127.0.0.1 --port=8000"
echo "  Then open     : http://127.0.0.1:8000"
echo "============================================================"
echo
