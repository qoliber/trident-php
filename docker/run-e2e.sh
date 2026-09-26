#!/bin/bash
#
# Run E2E tests for Trident PHP Library
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Trident PHP Library E2E Test Suite"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check if containers are running
if ! docker compose ps --status running | grep -q trident-php-test; then
    echo "[INFO] Starting Docker containers..."
    docker compose up -d --build
fi

# Wait for Trident to be healthy
echo "[INFO] Waiting for Trident to be ready..."
for i in {1..30}; do
    if docker compose exec -T trident curl -sf -H "Authorization: Bearer test-admin-key-12345" http://localhost:9100/admin/health > /dev/null 2>&1; then
        echo "[INFO] Trident is ready!"
        break
    fi
    if [ $i -eq 30 ]; then
        echo "[ERROR] Trident failed to start"
        docker compose logs trident
        exit 1
    fi
    sleep 1
    echo -n "."
done
echo ""

# Wait for nginx/PHP to be ready
echo "[INFO] Waiting for backend to be ready..."
for i in {1..30}; do
    if docker compose exec -T php-app curl -sf http://nginx/health > /dev/null 2>&1; then
        echo "[INFO] Backend is ready!"
        break
    fi
    if [ $i -eq 30 ]; then
        echo "[ERROR] Backend failed to start"
        docker compose logs nginx php-app
        exit 1
    fi
    sleep 1
    echo -n "."
done
echo ""

# Install composer dependencies if needed
echo "[INFO] Checking composer dependencies..."
docker compose exec -T php-app bash -c "cd /var/www && composer install --no-interaction --quiet 2>/dev/null || composer install --no-interaction"

# Run E2E tests
echo ""
echo "[INFO] Running E2E tests..."
echo ""

docker compose exec -T \
    -e TRIDENT_ADMIN_URL=http://trident:9100 \
    -e TRIDENT_ADMIN_KEY=test-admin-key-12345 \
    -e TRIDENT_PROXY_URL=http://trident:8080 \
    -e BACKEND_URL=http://nginx:80 \
    php-app php /var/www/html/tests/E2ETestRunner.php

TEST_RESULT=$?

# Show summary
if [ $TEST_RESULT -eq 0 ]; then
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  ✓ All E2E tests passed!"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
else
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  ✗ Some E2E tests failed!"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "Trident logs:"
    docker compose logs --tail=20 trident
fi

exit $TEST_RESULT
