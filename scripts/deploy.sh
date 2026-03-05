#!/bin/bash
# =============================================================
# PECE Platform Deploy Script
# =============================================================
# Pulls latest code from GitHub and clears caches.
#
# Usage:
#   From the VM:    sudo bash /var/www/html/wordpress/wp-content/pece-deploy/scripts/deploy.sh
#   From local:     ssh user@VM_IP "sudo bash /var/www/html/wordpress/wp-content/pece-deploy/scripts/deploy.sh"
#
# Prerequisites:
#   - Git repo cloned at /var/www/html/wordpress/wp-content/pece-deploy/
#   - Symlinks set up per the GCP Setup Guide (Step 10)
#   - WP Super Cache installed
# =============================================================

set -e

# Configuration
DEPLOY_DIR="/var/www/html/wordpress/wp-content/pece-deploy"
WP_DIR="/var/www/html/wordpress"
WEB_USER="www-data"
BRANCH="main"

echo "============================================="
echo "PECE Platform Deploy"
echo "$(date '+%Y-%m-%d %H:%M:%S')"
echo "============================================="

# Check we're in the right place
if [ ! -d "$DEPLOY_DIR/.git" ]; then
    echo "ERROR: Git repo not found at $DEPLOY_DIR"
    echo "Clone your repo there first. See GCP Setup Guide Step 10."
    exit 1
fi

# Pull latest code
echo ""
echo ">> Pulling latest from origin/$BRANCH..."
cd "$DEPLOY_DIR"
git fetch origin
git reset --hard origin/$BRANCH
echo "   Done. Now at commit: $(git log --oneline -1)"

# Set file ownership
echo ""
echo ">> Setting file ownership to $WEB_USER..."
chown -R "$WEB_USER:$WEB_USER" "$DEPLOY_DIR"
echo "   Done."

# Clear WP Super Cache
echo ""
echo ">> Clearing WP Super Cache..."
CACHE_DIR="$WP_DIR/wp-content/cache/supercache"
if [ -d "$CACHE_DIR" ]; then
    rm -rf "$CACHE_DIR"/*
    echo "   Cache cleared."
else
    echo "   Cache directory not found (WP Super Cache may not be installed yet). Skipping."
fi

# Clear OPcache (if PHP-FPM is running)
echo ""
echo ">> Restarting PHP-FPM to clear OPcache..."
if systemctl is-active --quiet php8.3-fpm; then
    systemctl reload php8.3-fpm
    echo "   PHP 8.3 FPM reloaded."
elif systemctl is-active --quiet php8.2-fpm; then
    systemctl reload php8.2-fpm
    echo "   PHP 8.2 FPM reloaded."
else
    echo "   No PHP-FPM service found running. Skipping."
fi

echo ""
echo "============================================="
echo "Deploy complete!"
echo "============================================="
