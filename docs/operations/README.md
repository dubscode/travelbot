# TravelBot Operations Guide

## Deployment and Operations

This guide covers the operational aspects of TravelBot, including deployment procedures, monitoring, troubleshooting, and maintenance tasks.

## Environment Overview

### Production Environment
- **URL**: https://travelbot.tech
- **Infrastructure**: AWS ECS Fargate
- **Database**: RDS PostgreSQL 17
- **Monitoring**: CloudWatch Logs + ECS metrics
- **SSL**: AWS Certificate Manager with Route53

### Staging Environment
- **URL**: https://staging.travelbot.tech
- **Infrastructure**: Scaled-down ECS setup
- **Database**: Shared RDS instance
- **Purpose**: Pre-production testing

### Development Environment
- **Setup**: Docker Compose
- **Database**: Local PostgreSQL container
- **Purpose**: Local development and testing

## Deployment Procedures

### Automated Deployment (Recommended)

#### Production Deployment via GitHub Actions
Deployments are automatically triggered on pushes to the `main` branch:

```bash
# Automatic deployment flow
git push origin main
# → Triggers CI/CD pipeline
# → Runs security scans
# → Builds Docker image
# → Deploys to ECS with rolling updates
```

#### Manual Deployment via GitHub Actions
For emergency deployments or specific versions:

1. **Go to GitHub Actions**
   - Navigate to repository → Actions tab
   - Select "🚀 Production Deployment" workflow

2. **Run Workflow**
   - Click "Run workflow"
   - Select branch (usually `main`)
   - Optionally force deployment or specify rollback version

3. **Monitor Deployment**
   - Watch workflow progress in real-time
   - Check ECS service health in AWS console

#### Rollback Procedure
```bash
# Via GitHub Actions
# 1. Go to "🚀 Production Deployment" workflow
# 2. Click "Run workflow"
# 3. Enter previous image tag in "rollback_version" field
# 4. Run deployment

# Via AWS CLI (emergency)
aws ecs update-service \
  --cluster travelbot-cluster \
  --service travelbot-service \
  --task-definition travelbot-task-definition:PREVIOUS_REVISION
```

### Infrastructure Deployment

#### CDK Stack Updates
When infrastructure changes are needed:

```bash
# From the cdk directory
cd cdk

# Install dependencies
npm install

# Review changes
npx cdk diff

# Deploy infrastructure
npx cdk deploy --all

# Deploy specific stack
npx cdk deploy TravelbotStack
```

#### Environment-Specific Deployments
```bash
# Development
npx cdk deploy --context environment=development

# Staging
npx cdk deploy --context environment=staging

# Production (requires confirmation)
npx cdk deploy --context environment=production --require-approval broadening
```

## Monitoring and Alerting

### System Monitoring

#### Built-in Monitoring
Available through AWS Console and CLI:

**Key Metrics:**
- ECS Service CPU/Memory utilization (via CloudWatch)
- Application Load Balancer health checks and metrics
- RDS database performance and connections
- CloudWatch Logs for application and container logs

#### ECS Service Monitoring
```bash
# View service status
aws ecs describe-services \
  --cluster travelbot-cluster \
  --services travelbot-service

# View task status
aws ecs list-tasks \
  --cluster travelbot-cluster \
  --service-name travelbot-service

# View task details
aws ecs describe-tasks \
  --cluster travelbot-cluster \
  --tasks TASK-ARN
```

### Log Management

#### Application Logs
```bash
# View recent logs
aws logs tail /aws/ecs/travelbot --follow

# Filter logs by time
aws logs tail /aws/ecs/travelbot \
  --since 2024-01-01T00:00:00 \
  --until 2024-01-01T23:59:59

# Search for specific patterns
aws logs filter-log-events \
  --log-group-name /aws/ecs/travelbot \
  --filter-pattern "ERROR"
```

#### Database Logs
```bash
# RDS logs
aws rds describe-db-log-files \
  --db-instance-identifier travelbot-database

# Download log file
aws rds download-db-log-file-portion \
  --db-instance-identifier travelbot-database \
  --log-file-name postgresql.log
```

### Health Monitoring

#### Built-in Health Checks
ECS and ALB provide automated health monitoring:
- **ECS Task Health**: Container-level health monitoring
- **ALB Target Health**: Application endpoint health checks
- **Auto Scaling**: CPU and memory-based scaling triggers
- **Application Logs**: Centralized logging via CloudWatch

#### Alert Response Procedures
1. **Immediate Response** (5 minutes)
   - Check AWS console for service status
   - Review recent deployments
   - Check application logs for errors

2. **Investigation** (15 minutes)
   - Identify root cause
   - Determine if rollback is needed
   - Check database performance

3. **Resolution** (30 minutes)
   - Implement fix or rollback
   - Monitor service recovery
   - Document incident

## Database Operations

### Backup Management

#### Automated Backups
- **Frequency**: Daily automated backups
- **Retention**: 7 days
- **Backup Window**: 03:00-04:00 UTC
- **Storage**: Encrypted RDS backups

#### Manual Backup
```bash
# Create manual snapshot
aws rds create-db-snapshot \
  --db-instance-identifier travelbot-database \
  --db-snapshot-identifier travelbot-manual-$(date +%Y%m%d-%H%M%S)

# List snapshots
aws rds describe-db-snapshots \
  --db-instance-identifier travelbot-database
```

#### Backup Restoration
```bash
# Restore from snapshot (creates new instance)
aws rds restore-db-instance-from-db-snapshot \
  --db-instance-identifier travelbot-database-restored \
  --db-snapshot-identifier SNAPSHOT-ID

# Point-in-time recovery
aws rds restore-db-instance-to-point-in-time \
  --target-db-instance-identifier travelbot-database-pit \
  --source-db-instance-identifier travelbot-database \
  --restore-time 2024-01-01T12:00:00Z
```

### Database Maintenance

#### Migrations
```bash
# Run pending migrations
docker exec -it travelbot-app php bin/console doctrine:migrations:migrate

# Check migration status
docker exec -it travelbot-app php bin/console doctrine:migrations:status

# Generate new migration
docker exec -it travelbot-app php bin/console doctrine:migrations:generate
```

#### Performance Monitoring
```bash
# Check database performance
aws rds describe-db-instances \
  --db-instance-identifier travelbot-database \
  --query 'DBInstances[0].{Status:DBInstanceStatus,CPU:ProcessorFeatures,Connections:DbInstancePort}'

# Monitor slow queries
aws logs filter-log-events \
  --log-group-name /aws/rds/instance/travelbot-database/postgresql \
  --filter-pattern "[timestamp, duration > 1000]"
```

## Security Operations

### SSL Certificate Management

#### Certificate Renewal
Certificates are automatically renewed by AWS Certificate Manager:

```bash
# Check certificate status
aws acm list-certificates \
  --certificate-statuses ISSUED \
  --query 'CertificateSummaryList[?DomainName==`travelbot.tech`]'

# Describe certificate details
aws acm describe-certificate \
  --certificate-arn CERTIFICATE-ARN
```

### Security Monitoring

#### Security Scanning
Automated security scans run on every deployment:
- **Container scanning**: Trivy vulnerability scanner
- **Code scanning**: GitHub CodeQL
- **Secret scanning**: TruffleHog
- **Dependency scanning**: GitHub Dependabot

#### Security Incident Response
1. **Detection**: Automated alerts or manual reporting
2. **Assessment**: Determine severity and impact
3. **Containment**: Isolate affected systems
4. **Investigation**: Identify root cause and scope
5. **Recovery**: Restore normal operations
6. **Lessons Learned**: Update procedures and monitoring

### Access Management

#### Emergency Access
```bash
# Emergency database access (break-glass)
aws rds modify-db-instance \
  --db-instance-identifier travelbot-database \
  --publicly-accessible

# Create temporary security group rule
aws ec2 authorize-security-group-ingress \
  --group-id sg-XXXXXXXX \
  --protocol tcp \
  --port 5432 \
  --cidr 0.0.0.0/0

# Remember to revoke after emergency
```

## Performance Optimization

### Application Performance

#### Caching Strategy
```php
// Redis cache configuration (if implemented)
REDIS_URL=redis://travelbot-cache.xxx.cache.amazonaws.com:6379

// Application cache clearing
php bin/console cache:clear --env=prod
```

#### Database Query Optimization
```bash
# Enable query logging temporarily
aws rds modify-db-parameter-group \
  --db-parameter-group-name travelbot-params \
  --parameters ParameterName=log_statement,ParameterValue=all

# Analyze slow queries
docker exec -it travelbot-app php bin/console doctrine:query:sql \
  "SELECT query, mean_time, calls FROM pg_stat_statements ORDER BY mean_time DESC LIMIT 10"
```

### Infrastructure Scaling

#### Auto Scaling Configuration
ECS service automatically scales based on:
- CPU utilization target: 50%
- Memory utilization target: 50%
- Min capacity: 1 task
- Max capacity: 10 tasks

#### Manual Scaling
```bash
# Scale ECS service
aws ecs update-service \
  --cluster travelbot-cluster \
  --service travelbot-service \
  --desired-count 3

# Scale database (requires downtime)
aws rds modify-db-instance \
  --db-instance-identifier travelbot-database \
  --db-instance-class db.t3.small \
  --apply-immediately
```

## Troubleshooting

### Common Issues

#### Application Won't Start
**Symptoms**: ECS tasks failing to start or immediately stopping

**Troubleshooting Steps:**
1. Check ECS service events
2. Review application logs
3. Verify environment variables
4. Check secrets manager access
5. Validate database connectivity

```bash
# Check ECS service events
aws ecs describe-services \
  --cluster travelbot-cluster \
  --services travelbot-service \
  --query 'services[0].events[0:5]'

# Check task definition
aws ecs describe-task-definition \
  --task-definition travelbot-task-definition:LATEST
```

#### Database Connection Issues
**Symptoms**: Application errors related to database connectivity

**Troubleshooting Steps:**
1. Check database instance status
2. Verify security group rules
3. Test network connectivity
4. Check database credentials
5. Review connection pool settings

```bash
# Check database status
aws rds describe-db-instances \
  --db-instance-identifier travelbot-database \
  --query 'DBInstances[0].DBInstanceStatus'

# Test connectivity from ECS task
aws ecs execute-command \
  --cluster travelbot-cluster \
  --task TASK-ARN \
  --container travelbot \
  --command "pg_isready -h DATABASE-ENDPOINT -p 5432"
```

#### High Memory Usage
**Symptoms**: Tasks being killed due to memory limits

**Troubleshooting Steps:**
1. Monitor memory metrics in CloudWatch
2. Analyze memory usage patterns
3. Check for memory leaks in application
4. Consider increasing task memory allocation

```bash
# Update task definition with more memory
aws ecs register-task-definition \
  --family travelbot-task-definition \
  --memory 2048 \
  --cpu 1024
```

#### SSL/TLS Issues
**Symptoms**: Certificate errors or HTTPS redirects not working

**Troubleshooting Steps:**
1. Check certificate status in ACM
2. Verify Route53 DNS records
3. Test certificate validation
4. Check ALB listener configuration

### Emergency Procedures

#### Service Recovery
```bash
# Force new deployment (redeploy current version)
aws ecs update-service \
  --cluster travelbot-cluster \
  --service travelbot-service \
  --force-new-deployment

# Stop all tasks (will be automatically replaced)
aws ecs list-tasks \
  --cluster travelbot-cluster \
  --service-name travelbot-service | \
jq -r '.taskArns[]' | \
xargs -I {} aws ecs stop-task --cluster travelbot-cluster --task {}
```

#### Database Emergency
```bash
# Create immediate snapshot before any changes
aws rds create-db-snapshot \
  --db-instance-identifier travelbot-database \
  --db-snapshot-identifier emergency-snapshot-$(date +%s)

# If database is unresponsive, reboot
aws rds reboot-db-instance \
  --db-instance-identifier travelbot-database
```

## Maintenance Windows

### Scheduled Maintenance
- **Application updates**: Automated via CI/CD (any time)
- **Database maintenance**: Configured maintenance window (Sunday 03:00-04:00 UTC)
- **Infrastructure updates**: Planned during low-traffic periods

### Pre-maintenance Checklist
1. [ ] Notify stakeholders
2. [ ] Create database snapshot
3. [ ] Verify rollback procedures
4. [ ] Monitor application metrics
5. [ ] Prepare incident response team

### Post-maintenance Verification
1. [ ] Verify application accessibility
2. [ ] Check key functionality (login, chat, recommendations)
3. [ ] Monitor error rates and response times
4. [ ] Validate database integrity
5. [ ] Update documentation if needed

## Cost Optimization

### Resource Monitoring
```bash
# Check current costs
aws ce get-cost-and-usage \
  --time-period Start=2024-01-01,End=2024-01-31 \
  --granularity MONTHLY \
  --metrics BlendedCost \
  --group-by Type=DIMENSION,Key=SERVICE

# Analyze ECS costs
aws ce get-cost-and-usage \
  --time-period Start=2024-01-01,End=2024-01-31 \
  --granularity DAILY \
  --metrics BlendedCost \
  --group-by Type=DIMENSION,Key=USAGE_TYPE \
  --filter file://ecs-filter.json
```

### Cost Optimization Strategies
- **Right-sizing**: Monitor and adjust ECS task sizes
- **Auto-scaling**: Ensure proper scaling policies
- **Reserved instances**: Consider RDS reserved instances for stable workloads
- **Log retention**: Optimize CloudWatch log retention periods
- **Unused resources**: Regular cleanup of unused snapshots and images

This operations guide ensures reliable, secure, and efficient operation of the TravelBot application across all environments.