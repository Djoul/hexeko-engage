#!/bin/bash

# Script to fix storage directory permissions for Laravel in Docker

# Ensure the script is run as root
if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

# Get the UID and GID from environment variables or use defaults
DOCKER_UID=${UID:-501}
DOCKER_GID=${GID:-20}

# Set the correct paths
APP_DIR="/var/www/html"
STORAGE_DIR="${APP_DIR}/storage"
LOG_DIR="${STORAGE_DIR}/logs"

# Create directories if they don't exist
mkdir -p ${LOG_DIR}

# Set ownership recursively
chown -R ${DOCKER_UID}:${DOCKER_GID} ${STORAGE_DIR}

# Set permissions recursively
chmod -R 775 ${STORAGE_DIR}

echo "Storage directory permissions fixed successfully!"
