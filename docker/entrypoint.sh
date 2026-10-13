#!/usr/bin/env bash
#
# Fullbright College - Student Management System
# Container entrypoint: bind Apache to $PORT, wait for MySQL, optionally
# import the schema once, then start Apache.
#
set -euo pipefail

# ------------------------------------------------------------------
# 1. Bind Apache to the platform-provided port (Railway/Render/Fly set $PORT).
# ------------------------------------------------------------------
PORT="${PORT:-80}"
if [ "$PORT" != "80" ]; then
    sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -i "s/:80/:${PORT}/" /etc/apache2/sites-available/000-default.conf
fi
echo "[entrypoint] Apache will listen on port ${PORT}"

# ------------------------------------------------------------------
# 2. Resolve database connection (DATABASE_URL / DB_* / XAMPP defaults)
#    using the same rules as includes/config.php.
# ------------------------------------------------------------------
DBH="localhost"; DBP="3306"; DBU="root"; DBPW=""; DBN="fullbright_college"

# Read newline-separated values so empty fields (e.g. an empty password)
# are preserved rather than collapsed by word splitting.
DBVALS_RAW="$(php -r '
    require "/var/www/html/includes/config.php";
    echo DB_HOST . "\n" . DB_PORT . "\n" . DB_USER . "\n" . DB_PASS . "\n" . DB_NAME;
' 2>/dev/null || true)"

if [ -n "$DBVALS_RAW" ]; then
    DBVALS=()
    while IFS= read -r line; do
        DBVALS+=("$line")
    done <<< "$DBVALS_RAW"
    DBH="${DBVALS[0]:-$DBH}"
    DBP="${DBVALS[1]:-$DBP}"
    DBU="${DBVALS[2]:-$DBU}"
    if [ "${#DBVALS[@]}" -ge 5 ]; then
        DBPW="${DBVALS[3]}"
        DBN="${DBVALS[4]}"
    fi
fi

echo "[entrypoint] Waiting for database at ${DBH}:${DBP} (db: ${DBN})..."
for i in $(seq 1 30); do
    if mysqladmin ping -h "$DBH" -P "$DBP" -u "$DBU" ${DBPW:+-p"$DBPW"} --silent >/dev/null 2>&1; then
        echo "[entrypoint] Database is up."
        DB_UP=1
        break
    fi
    sleep 2
done

if [ "${DB_UP:-0}" != "1" ]; then
    echo "[entrypoint] WARNING: database not reachable yet; starting Apache anyway."
else
    # --------------------------------------------------------------
    # 3. One-time schema import for a fresh database (opt-in).
    #    schema.sql is destructive (drops tables), so we only run it when
    #    the 'students' table does not exist yet.
    # --------------------------------------------------------------
    if [ "${AUTO_IMPORT_SCHEMA:-false}" = "true" ]; then
        HAS_STUDENTS=$(mysql -h "$DBH" -P "$DBP" -u "$DBU" ${DBPW:+-p"$DBPW"} -N -B \
            -e "SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema='${DBN}' AND table_name='students';" 2>/dev/null || echo "0")
        if [ "${HAS_STUDENTS:-0}" = "0" ]; then
            echo "[entrypoint] Fresh database detected: importing schema.sql..."
            # Strip the CREATE DATABASE ... ; block and USE statements so the
            # schema loads into the platform-provided database.
            awk '
                /^CREATE DATABASE/ { skip=1 }
                skip { if ($0 ~ /;/) skip=0; next }
                /^USE / { next }
                { print }
            ' /var/www/html/schema.sql > /tmp/fullbright-schema.sql
            mysql -h "$DBH" -P "$DBP" -u "$DBU" ${DBPW:+-p"$DBPW"} "$DBN" < /tmp/fullbright-schema.sql
            echo "[entrypoint] Schema imported."
        else
            echo "[entrypoint] Existing tables found: skipping schema import."
        fi
    fi
fi

# ------------------------------------------------------------------
# 4. Ensure runtime dirs exist and are writable.
# ------------------------------------------------------------------
mkdir -p /var/www/html/uploads /var/www/html/digital_id
chown -R www-data:www-data /var/www/html/uploads /var/www/html/digital_id

exec "$@"
