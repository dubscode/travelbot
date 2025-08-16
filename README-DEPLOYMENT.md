# TravelBot AWS ECS Fargate Deployment Guide

This guide walks you through deploying the TravelBot application to AWS ECS Fargate using CDK.

## Prerequisites

- AWS CLI configured with appropriate permissions
- Docker installed and running
- Node.js and npm installed (for CDK)
- Access to a Neon PostgreSQL database

## Required AWS Permissions

Your AWS user/role needs the following permissions:
- Full ECS, ECR, EC2, IAM, CloudFormation, Secrets Manager, and Bedrock access
- Ability to create VPCs, load balancers, and related networking components

## Step 1: Set Up CDK Infrastructure

1. Install CDK dependencies:
```bash
cd cdk
npm install
```

2. Bootstrap CDK (one-time setup per AWS account/region):
```bash
npx cdk bootstrap
```

3. Deploy the infrastructure:
```bash
npx cdk deploy
```

This creates:
- VPC with public/private subnets
- ECS Fargate cluster
- Application Load Balancer
- ECR repository
- IAM roles with Bedrock permissions
- CloudWatch log groups
- Secrets Manager placeholders

## Step 2: Configure Secrets

Run the secrets setup script to configure your environment variables:

```bash
./scripts/setup-secrets.sh
```

You'll be prompted for:
- **Neon database URL**: Your production database connection string
- **Symfony app secret**: Application secret key (auto-generated if not provided)
- **Bedrock configuration**: AWS region and Claude model IDs

## Step 3: Deploy Application

Deploy the application using the deployment script:

```bash
./scripts/deploy.sh [image-tag]
```

This script:
1. Builds the Docker image
2. Pushes to ECR
3. Updates the ECS service
4. Waits for deployment to complete
5. Shows the application URL

## Step 4: Configure DNS (Optional)

After deployment, you can:
1. Set up a custom domain name
2. Add SSL/TLS certificate via AWS Certificate Manager
3. Update ALB listener to use HTTPS

## Environment Variables

The application uses these environment variables in production:

- `APP_ENV=prod`
- `AWS_REGION` - Set by CDK
- `DATABASE_URL` - From Secrets Manager
- `APP_SECRET` - From Secrets Manager
- `BEDROCK_CLAUDE_SONNET_MODEL` - From Secrets Manager
- `BEDROCK_CLAUDE_HAIKU_MODEL` - From Secrets Manager

## Monitoring and Logs

- **Application logs**: CloudWatch `/ecs/travelbot` log group
- **Health checks**: Available at `/health` endpoint
- **Metrics**: ECS service and ALB metrics in CloudWatch
- **Auto-scaling**: Configured for CPU and memory utilization

## Database Setup in Production

To set up the database schema and seed data in production:

1. Connect to the ECS task:
```bash
aws ecs execute-command \
    --region us-west-2 \
    --cluster travelbot-cluster \
    --task <task-id> \
    --container travelbot \
    --command "/bin/sh" \
    --interactive
```

2. Run database migrations:
```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

3. Seed the database with destinations:
```bash
php bin/console app:seed-destinations
```

4. Create an admin user (optional):
```bash
php bin/console app:create-user admin@example.com --admin
```

**Note**: Get the task ID by running:
```bash
aws ecs list-tasks --cluster travelbot-cluster --service-name travelbot-service --region us-west-2
```

## Updating the Application

To deploy code changes:

1. Commit your changes
2. Run the deployment script:
```bash
./scripts/deploy.sh v1.1.0  # Use semantic versioning
```

## Troubleshooting

### Container Won't Start
- Check CloudWatch logs for errors
- Verify secrets are properly configured
- Ensure database connectivity

### Health Check Failures
- Verify `/health` endpoint returns 200
- Check nginx and PHP-FPM are running
- Review container resource limits

### Database Connection Issues
- Verify Neon database URL in secrets
- Check security group rules allow outbound HTTPS
- Ensure database allows connections from ECS IPs

### Bedrock API Errors
- Verify IAM role has Bedrock permissions
- Check model IDs are correct for your region
- Ensure AWS credentials are properly configured

## Cost Optimization

- ECS tasks scale down to 1 instance when idle
- Use Fargate Spot for development environments
- Monitor CloudWatch costs and set up billing alerts

## Security Notes

- All secrets are stored in AWS Secrets Manager
- ECS tasks run in private subnets
- Security groups restrict access appropriately
- Container runs as non-root user