#!/bin/bash

# TravelBot Secrets Setup Script
# This script sets up the required secrets in AWS Secrets Manager

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
AWS_PROFILE=${AWS_PROFILE:-anny-prod}
REGION=${AWS_REGION:-us-west-2}

echo -e "${GREEN}Setting up TravelBot secrets...${NC}"

# Function to print status
print_status() {
    echo -e "${YELLOW}>>> $1${NC}"
}

# Function to create or update secret
create_or_update_secret() {
    local secret_name=$1
    local secret_value=$2
    local description=$3
    
    print_status "Setting up secret: $secret_name"
    
    # Check if secret exists
    if aws secretsmanager describe-secret --profile $AWS_PROFILE --secret-id "$secret_name" --region $REGION >/dev/null 2>&1; then
        echo "Secret $secret_name already exists, updating..."
        aws secretsmanager update-secret \
            --profile $AWS_PROFILE \
            --secret-id "$secret_name" \
            --secret-string "$secret_value" \
            --region $REGION
    else
        echo "Creating new secret: $secret_name"
        aws secretsmanager create-secret \
            --profile $AWS_PROFILE \
            --name "$secret_name" \
            --description "$description" \
            --secret-string "$secret_value" \
            --region $REGION
    fi
}

# Database URL secret
read -p "Enter your Neon database URL: " db_url
if [ -z "$db_url" ]; then
    echo -e "${RED}Database URL is required${NC}"
    exit 1
fi

create_or_update_secret "travelbot/database-url" \
    "{\"database_url\": \"$db_url\"}" \
    "Neon database URL for TravelBot"

# App secret
read -p "Enter Symfony app secret (or press Enter to generate): " app_secret
if [ -z "$app_secret" ]; then
    app_secret=$(openssl rand -hex 32)
    echo "Generated app secret: $app_secret"
fi

create_or_update_secret "travelbot/app-secret" \
    "{\"secret\": \"$app_secret\"}" \
    "Symfony app secret for TravelBot"

# Bedrock configuration
read -p "Enter AWS region for Bedrock (default: us-east-1): " bedrock_region
bedrock_region=${bedrock_region:-us-east-1}

read -p "Enter Claude Sonnet model ID (default: anthropic.claude-3-5-sonnet-20241022-v2:0): " sonnet_model
sonnet_model=${sonnet_model:-anthropic.claude-3-5-sonnet-20241022-v2:0}

read -p "Enter Claude Haiku model ID (default: anthropic.claude-3-haiku-20240307-v1:0): " haiku_model
haiku_model=${haiku_model:-anthropic.claude-3-haiku-20240307-v1:0}

create_or_update_secret "travelbot/bedrock-config" \
    "{\"region\": \"$bedrock_region\", \"sonnet_model\": \"$sonnet_model\", \"haiku_model\": \"$haiku_model\"}" \
    "AWS Bedrock configuration for TravelBot"

echo -e "${GREEN}All secrets have been set up successfully!${NC}"
echo -e "${YELLOW}Note: Make sure your ECS task role has permission to read these secrets.${NC}"