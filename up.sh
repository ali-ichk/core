#=============================================================
# Gibbon Local Development — Docker helper
# =============================================================
# Usage:
#   ./up.sh          Start (or rebuild) the dev environment
#   ./up.sh down     Stop containers and remove volumes (resets DB)
#   ./up.sh logs     Tail live logs from all containers
# =============================================================
set -euo pipefail

## Ensure the script is always run from the project root
if [ ! -f "ops/docker-compose.yaml" ]; then
    echo "Error: Run this script from the project root (where up.sh lives)."
    exit 1
fi

## Ensure the local environment file exists
if [ ! -f ".env" ]; then
    if [ ! -f "ops/.env-example" ]; then
        echo "Error: .env was not found and ops/.env-example is missing."
        exit 1
    fi

    cp ops/.env-example .env
    echo "Created .env from ops/.env-example"
    echo "Review .env to customize local settings if needed."
fi

## Ensure Docker is installed and the daemon is running
if ! command -v docker >/dev/null 2>&1; then
    echo "Error: Docker is not installed or not available on PATH."
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Error: Docker is not running. Start Docker Desktop and try again."
    exit 1
fi

COMPOSE_FILES="--project-directory . -f ops/docker-compose.yaml -f ops/docker-compose.dev.yaml"

case "${1:-up}" in
    up)
        echo "Starting Gibbon dev environment..."
        docker compose $COMPOSE_FILES up -d --build
        echo ""
        echo "Gibbon is running at: http://localhost:8080"
        echo "To follow logs:       ./up.sh logs"
        echo "To stop:              ./up.sh down"
        ;;
    down)
        echo "Stopping Gibbon dev environment and removing volumes..."
        docker compose $COMPOSE_FILES down -v
        ;;
    logs)
        docker compose $COMPOSE_FILES logs -f
        ;;
    *)
        echo "Usage: $0 [up|down|logs]"
        exit 1
        ;;
esac
