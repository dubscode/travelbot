#!/bin/bash

# Simple deployment logging script
# Logs deployment status for monitoring and debugging

set -euo pipefail

DEPLOYMENT_STATUS="${1:-unknown}"
IMAGE_TAG="${2:-latest}"
ENVIRONMENT="${3:-production}"

# Determine message based on status
case "$DEPLOYMENT_STATUS" in
    "started")
        MESSAGE="🚀 Deployment started"
        ;;
    "success")
        MESSAGE="✅ Deployment completed successfully"
        ;;
    "failed")
        MESSAGE="❌ Deployment failed"
        ;;
    *)
        MESSAGE="ℹ️ Deployment status: $DEPLOYMENT_STATUS"
        ;;
esac

# Log deployment information
echo "=================================="
echo "TravelBot Deployment Status"
echo "=================================="
echo "Status: $MESSAGE"
echo "Environment: $ENVIRONMENT"
echo "Image Tag: $IMAGE_TAG"
echo "Repository: ${GITHUB_REPOSITORY:-N/A}"
echo "Commit: ${GITHUB_SHA:-N/A}"
echo "Timestamp: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "=================================="