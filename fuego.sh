#!/usr/bin/env bash
set -u

PROJECT_DIR="/home/cbm/nfc-api"
LARAVEL_HOST="127.0.0.1"
LARAVEL_PORT="8000"

PIDS=()

cleanup() {
    echo ""
    echo "🛑 Deteniendo servicios del entorno local..."
    for pid in "${PIDS[@]:-}"; do
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null || true
        fi
    done
    wait 2>/dev/null || true
    echo "✅ Entorno detenido limpiamente."
}

trap cleanup EXIT INT TERM

echo "🧠 Iniciando entorno de desarrollo para Neurofeedback Center (nfc-api)..."

if [ ! -d "$PROJECT_DIR" ] || [ ! -d "$PROJECT_DIR/public" ]; then
    echo "❌ Error crítico: No se encuentra $PROJECT_DIR o su carpeta public/."
    exit 1
fi

cd "$PROJECT_DIR" || exit 1

if [ ! -f "$PROJECT_DIR/.env" ]; then
    echo "❌ Error crítico: Falta el archivo .env en $PROJECT_DIR"
    exit 1
fi

for cmd in php npm; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "❌ Error crítico: '$cmd' no está disponible en el PATH."
        exit 1
    fi
done

# 1. Base de datos (MySQL / MariaDB)
if command -v systemctl >/dev/null 2>&1; then
    if ! systemctl is-active --quiet mysql && ! systemctl is-active --quiet mariadb; then
        echo "🗄️ Iniciando servicio de base de datos..."
        if ! sudo systemctl start mysql 2>/dev/null && ! sudo systemctl start mariadb 2>/dev/null; then
            echo "⚠️ No se pudo iniciar MySQL/MariaDB automáticamente. Verifica permisos sudo o el nombre del servicio."
        fi
    else
        echo "✅ Base de datos ya activa."
    fi
fi

# Liberar puerto de Laravel si quedó algún proceso previo colgado
if command -v fuser >/dev/null 2>&1; then
    fuser -k "${LARAVEL_PORT}/tcp" >/dev/null 2>&1 || true
fi

# 2. Servidor Laravel
php artisan serve --host="$LARAVEL_HOST" --port="$LARAVEL_PORT" &
PIDS+=("$!")

# 3. Compilador de assets (Vite / Tailwind / Filament)
npm run dev &
PIDS+=("$!")

# 4. Mailpit (opcional)
if command -v mailpit >/dev/null 2>&1; then
    mailpit &
    PIDS+=("$!")
else
    echo "ℹ️ Mailpit no está instalado; se omite."
fi

# 5. Worker de colas (opcional — descomenta si lo necesitas)
# php artisan queue:work --tries=3 --timeout=90 &
# PIDS+=("$!")

echo "🚀 Servicios activos en http://${LARAVEL_HOST}:${LARAVEL_PORT}. Presiona Ctrl+C para detenerlos."
wait