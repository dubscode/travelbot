#!/bin/bash

# TravelBot Deployment Script
# This script builds the Docker image, pushes it to ECR, and deploys to ECS

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
AWS_PROFILE=${AWS_PROFILE:-anny-prod}
REGION=${AWS_REGION:-us-west-2}
ACCOUNT_ID=$(aws sts get-caller-identity --profile $AWS_PROFILE --query Account --output text)
ECR_REPOSITORY="travelbot"
CLUSTER_NAME="travelbot-cluster"
SERVICE_NAME="travelbot-service"
IMAGE_TAG=${1:-latest}

echo -e "${GREEN}Starting TravelBot deployment...${NC}"

# Function to print status
print_status() {
    echo -e "${YELLOW}>>> $1${NC}"
}

print_status "Building Docker image for linux/amd64..."
docker build --platform linux/amd64 -t $ECR_REPOSITORY:$IMAGE_TAG .

print_status "Logging in to ECR..."
aws ecr get-login-password --profile $AWS_PROFILE --region $REGION | docker login --username AWS --password-stdin $ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com

print_status "Tagging image for ECR..."
docker tag $ECR_REPOSITORY:$IMAGE_TAG $ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com/$ECR_REPOSITORY:$IMAGE_TAG

print_status "Pushing image to ECR..."
docker push $ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com/$ECR_REPOSITORY:$IMAGE_TAG

print_status "Updating ECS service..."
aws ecs update-service \
    --profile $AWS_PROFILE \
    --region $REGION \
    --cluster $CLUSTER_NAME \
    --service $SERVICE_NAME \
    --force-new-deployment

print_status "Waiting for deployment to complete..."
aws ecs wait services-stable \
    --profile $AWS_PROFILE \
    --region $REGION \
    --cluster $CLUSTER_NAME \
    --services $SERVICE_NAME

echo -e "${GREEN}Deployment completed successfully!${NC}"

# Get the load balancer URL
print_status "Getting load balancer URL..."
ALB_DNS=$(aws elbv2 describe-load-balancers \
    --profile $AWS_PROFILE \
    --region $REGION \
    --names travelbot-alb \
    --query 'LoadBalancers[0].DNSName' \
    --output text)

echo -e "${GREEN}Application is available at: http://$ALB_DNS${NC}"