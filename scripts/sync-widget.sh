#!/bin/bash
# scripts/sync-widget.sh [pull|push]

# Resolve directories relative to the script's location
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXT_DIR="$(dirname "$SCRIPT_DIR")"
MW_DIR="$(dirname "$(dirname "$EXT_DIR")")" # Points to /var/www/we

PHP="php5.6"
# Configuration
WIDGET_TITLES=(
    "Widget:Vote"
)

ACTION=$1

if [ "$ACTION" = "pull" ]; then
    for WIDGET_TITLE in "${WIDGET_TITLES[@]}"; do
        WIDGET_FILE="${EXT_DIR}/wiki/${WIDGET_TITLE}"
        mkdir -p "$(dirname "${WIDGET_FILE}")"
        "${PHP}" "${MW_DIR}/maintenance/getText.php" \
            "${WIDGET_TITLE}" > "${WIDGET_FILE}"
        echo "Successfully pulled '${WIDGET_TITLE}' to ${WIDGET_FILE}"
    done
elif [ "$ACTION" = "push" ]; then
    for WIDGET_TITLE in "${WIDGET_TITLES[@]}"; do
        WIDGET_FILE="${EXT_DIR}/wiki/${WIDGET_TITLE}"
        if [ ! -f "$WIDGET_FILE" ]; then
            echo "Error: Local file ${WIDGET_FILE} not found."
            exit 1
        fi
        "${PHP}" "${MW_DIR}/maintenance/edit.php" \
            --user="GitSync" --summary="Sync from git" --bot --no-rc \
            "${WIDGET_TITLE}" < "${WIDGET_FILE}"
        echo "Successfully pushed ${WIDGET_FILE} to '${WIDGET_TITLE}'"
    done
else
    echo "Usage: $0 [pull|push]"
    exit 1
fi

