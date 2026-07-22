#!/bin/sh
set -eu

: "${POSTGRES_HOST:=postgres}"
: "${POSTGRES_PORT:=5432}"
: "${POSTGRES_DB:?POSTGRES_DB is required}"
: "${POSTGRES_USER:?POSTGRES_USER is required}"
: "${POSTGRES_PASSWORD:?POSTGRES_PASSWORD is required}"
: "${WHATSAPP_GATEWAY_DB_USER:?WHATSAPP_GATEWAY_DB_USER is required}"
: "${WHATSAPP_GATEWAY_DB_PASSWORD:?WHATSAPP_GATEWAY_DB_PASSWORD is required}"

case "$WHATSAPP_GATEWAY_DB_USER" in
    *[!a-zA-Z0-9_]*)
        echo "WHATSAPP_GATEWAY_DB_USER contém caracteres inválidos" >&2
        exit 2
        ;;
esac

export PGPASSWORD="$POSTGRES_PASSWORD"

until pg_isready -q -h "$POSTGRES_HOST" -p "$POSTGRES_PORT" -U "$POSTGRES_USER" -d "$POSTGRES_DB"; do
    sleep 1
done

psql --quiet --no-psqlrc --set=ON_ERROR_STOP=1 \
    --host="$POSTGRES_HOST" \
    --port="$POSTGRES_PORT" \
    --username="$POSTGRES_USER" \
    --dbname="$POSTGRES_DB" \
    --set=gateway_user="$WHATSAPP_GATEWAY_DB_USER" \
    --set=gateway_password="$WHATSAPP_GATEWAY_DB_PASSWORD" <<'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'gateway_user', :'gateway_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'gateway_user')
\gexec
SELECT format('ALTER ROLE %I WITH LOGIN PASSWORD %L', :'gateway_user', :'gateway_password')
\gexec
SELECT format('CREATE SCHEMA IF NOT EXISTS whatsapp_gateway AUTHORIZATION %I', :'gateway_user')
\gexec
SELECT format('ALTER SCHEMA whatsapp_gateway OWNER TO %I', :'gateway_user')
\gexec
SELECT format('GRANT USAGE, CREATE ON SCHEMA whatsapp_gateway TO %I', :'gateway_user')
\gexec
-- Migrate() do gateway faz CREATE SCHEMA IF NOT EXISTS; no Postgres isso exige CREATE no database.
SELECT format('GRANT CONNECT, CREATE ON DATABASE %I TO %I', current_database(), :'gateway_user')
\gexec
SELECT format('ALTER ROLE %I IN DATABASE %I SET search_path = whatsapp_gateway, public', :'gateway_user', current_database())
\gexec
SQL
