#!/bin/bash
#
# Run PHP library integration tests
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "=== Trident PHP Library Integration Tests ==="
echo ""

# Build images
echo "1. Building Docker images..."
docker compose build --quiet

# Start services
echo "2. Starting services..."
docker compose up -d

# Wait for Trident to be healthy
echo "3. Waiting for Trident to be ready..."
for i in {1..30}; do
    if curl -sf http://localhost:9100/admin/health > /dev/null 2>&1; then
        echo "   Trident is ready!"
        break
    fi
    if [ $i -eq 30 ]; then
        echo "   ERROR: Trident failed to start"
        docker compose logs trident
        docker compose down
        exit 1
    fi
    sleep 1
done

# Run API tests
echo "4. Running API tests..."
docker compose exec -T php-app php /var/www/html/test-api.php
TEST_RESULT=$?

# Show logs if failed
if [ $TEST_RESULT -ne 0 ]; then
    echo ""
    echo "=== Trident Logs ==="
    docker compose logs trident
fi

# Cleanup
echo ""
echo "5. Cleaning up..."
docker compose down

if [ $TEST_RESULT -eq 0 ]; then
    echo ""
    echo "=== All tests passed! ==="
else
    echo ""
    echo "=== Tests failed! ==="
    exit 1
fi
