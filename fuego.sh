#!/usr/bin/env bash

# Salir limpiamente y matar todos los procesos en segundo plano al presionar Ctrl+C
trap 'kill 0' EXIT

# Definir la ruta base del proyecto de forma absoluta
PROJECT_DIR="/home/cbm/nfc-api"

echo "🧠 Iniciando entorno de desarrollo para Neurofeedback Center (nfc-api)..."

# Validar que la carpeta public exista antes de arrancar
if [ ! -d "$PROJECT_DIR/public" ]; then
    echo "❌ Error crítico: No se encuentra la carpeta public en $PROJECT_DIR"
    exit 1
fi

# Movernos al directorio del proyecto
cd "$PROJECT_DIR" || exit 1

# 1. Servidor de desarrollo Laravel (Forzando la ruta raíz del proyecto)
php artisan serve --host=127.0.0.1 --port=8000 &

# 2. Compilador de assets (Vite / Tailwind / Panel de Filament)
npm run dev &

# 3. Capturador y servidor de correos de prueba (Mailpit)
if command -v mailpit &> /dev/null; then
    mailpit &
else
    echo "⚠️ Mailpit no está instalado o no se encuentra en el PATH."
fi

# 4. (Opcional) Worker de colas para correos, notificaciones o tareas asíncronas
# php artisan queue:work &

echo "🚀 Todos los servicios han sido lanzados. Presiona Ctrl+C para detenerlos."

# Mantener el script en ejecución para mostrar logs unificados en la consola
wait