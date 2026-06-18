#!/usr/bin/env bash
set -e

# `php artisan serve` only forwards an allow-listed set of env vars to its
# per-request workers, so compose `environment:` values (DB_HOST=db, etc.) reach
# this entrypoint but NOT the served requests. Bake them into .env so both the
# CLI commands here and the served workers read identical config.
set_env() {
    php -r '
        $k = $argv[1]; $v = $argv[2]; $f = ".env";
        $c = is_file($f) ? file_get_contents($f) : "";
        $line = $k."=".$v;
        if (preg_match("/^".preg_quote($k,"/")."=.*$/m", $c)) {
            $c = preg_replace("/^".preg_quote($k,"/")."=.*$/m", $line, $c);
        } else {
            $c .= (str_ends_with($c, "\n") || $c === "" ? "" : "\n").$line."\n";
        }
        file_put_contents($f, $c);
    ' "$1" "$2"
}

echo "[entrypoint] Writing runtime config into .env ..."
set_env DB_CONNECTION       "${DB_CONNECTION:-pgsql}"
set_env DB_HOST             "${DB_HOST:-db}"
set_env DB_PORT             "${DB_PORT:-5432}"
set_env DB_DATABASE         "${DB_DATABASE:-smart_queue}"
set_env DB_USERNAME         "${DB_USERNAME:-postgres}"
set_env DB_PASSWORD         "${DB_PASSWORD:-postgres}"
set_env BROADCAST_CONNECTION "${BROADCAST_CONNECTION:-log}"
set_env QUEUE_CONNECTION    "${QUEUE_CONNECTION:-sync}"
set_env CACHE_STORE         "${CACHE_STORE:-database}"
set_env SESSION_DRIVER      "${SESSION_DRIVER:-database}"
set_env APP_URL             "${APP_URL:-http://localhost:8000}"

php artisan config:clear >/dev/null 2>&1 || true

echo "[entrypoint] Waiting for Postgres at ${DB_HOST}:${DB_PORT} ..."
until php -r '
    try {
        new PDO(
            "pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
    } catch (Throwable $e) { exit(1); }
' 2>/dev/null; do
    sleep 2
done
echo "[entrypoint] Postgres is up."

# Fresh, fully-seeded demo state on every boot (data resets on restart).
echo "[entrypoint] Migrating + seeding ..."
php artisan migrate:fresh --seed --force

# Known demo login accounts (idempotent). Password for all three: "password".
echo "[entrypoint] Creating demo accounts ..."
php artisan tinker --execute='
    use App\Models\User; use Illuminate\Support\Facades\Hash;
    $demo = [
        ["Admin User",   "admin@smartqueue.test",   "admin",   null],
        ["Staff User",   "staff@smartqueue.test",   "staff",   null],
        ["Student User", "student@smartqueue.test", "student", "2026-0001"],
    ];
    foreach ($demo as [$name, $email, $role, $sn]) {
        User::updateOrCreate(["email" => $email], [
            "name" => $name, "role" => $role, "student_no" => $sn,
            "password" => Hash::make("password"),
        ]);
    }
    echo "demo accounts ready\n";
'

# Train the AI wait-time model so /api/queue/estimate returns model-based ETAs.
echo "[entrypoint] Seeding service history + training the wait-time model ..."
php artisan ml:seed-history 800 || echo "[entrypoint] ml:seed-history skipped"
php artisan ml:train || echo "[entrypoint] ml:train skipped"

echo "[entrypoint] Starting API on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
