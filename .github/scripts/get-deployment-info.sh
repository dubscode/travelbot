#!/bin/bash

# Get deployment information script
# Extracts useful deployment metadata for workflows

set -euo pipefail

CLUSTER_NAME="${1:-}"
SERVICE_NAME="${2:-}"

if [[ -z "$CLUSTER_NAME" || -z "$SERVICE_NAME" ]]; then
    echo "Usage: $0 <cluster-name> <service-name>"
    exit 1
fi

# Get service information
SERVICE_INFO=$(aws ecs describe-services \
    --cluster "$CLUSTER_NAME" \
    --services "$SERVICE_NAME" \
    --query 'services[0]' \
    --output json)

# Extract useful information
TASK_DEFINITION=$(echo "$SERVICE_INFO" | jq -r '.taskDefinition')
RUNNING_COUNT=$(echo "$SERVICE_INFO" | jq -r '.runningCount')
DESIRED_COUNT=$(echo "$SERVICE_INFO" | jq -r '.desiredCount')
STATUS=$(echo "$SERVICE_INFO" | jq -r '.status')

# Get task definition details
TASK_DEF_INFO=$(aws ecs describe-task-definition \
    --task-definition "$TASK_DEFINITION" \
    --query 'taskDefinition' \
    --output json)

IMAGE_URI=$(echo "$TASK_DEF_INFO" | jq -r '.containerDefinitions[0].image')
CPU=$(echo "$TASK_DEF_INFO" | jq -r '.cpu')
MEMORY=$(echo "$TASK_DEF_INFO" | jq -r '.memory')

# Output as GitHub Actions format
echo "task_definition=$TASK_DEFINITION" >> "$GITHUB_OUTPUT"
echo "running_count=$RUNNING_COUNT" >> "$GITHUB_OUTPUT"
echo "desired_count=$DESIRED_COUNT" >> "$GITHUB_OUTPUT"
echo "service_status=$STATUS" >> "$GITHUB_OUTPUT"
echo "image_uri=$IMAGE_URI" >> "$GITHUB_OUTPUT"
echo "cpu=$CPU" >> "$GITHUB_OUTPUT"
echo "memory=$MEMORY" >> "$GITHUB_OUTPUT"

echo "Deployment info extracted for $SERVICE_NAME in $CLUSTER_NAME"