import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as ecs from 'aws-cdk-lib/aws-ecs';
import * as ecr from 'aws-cdk-lib/aws-ecr';
import * as elbv2 from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as logs from 'aws-cdk-lib/aws-logs';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import { Construct } from 'constructs';

export interface ContainerSecretConfig {
  secretKey: string;
  secret: secretsmanager.Secret;
  jsonKey: string;
}

export interface ECSFargateServiceProps {
  /**
   * VPC to deploy the service in
   */
  vpc: ec2.Vpc;
  
  /**
   * Security group for the ECS tasks
   */
  securityGroup: ec2.SecurityGroup;
  
  /**
   * ECR repository containing the container image
   */
  repository: ecr.Repository;
  
  /**
   * Service name prefix
   */
  serviceName: string;
  
  /**
   * Log group name
   * @default '/ecs/{serviceName}'
   */
  logGroupName?: string;
  
  /**
   * Log retention period
   * @default ONE_WEEK
   */
  logRetention?: logs.RetentionDays;
  
  /**
   * Task CPU
   * @default 512
   */
  cpu?: number;
  
  /**
   * Task memory in MiB
   * @default 1024
   */
  memoryLimitMiB?: number;
  
  /**
   * Container port
   * @default 80
   */
  containerPort?: number;
  
  /**
   * Desired number of tasks
   * @default 1
   */
  desiredCount?: number;
  
  /**
   * Environment variables for the container
   */
  environment?: { [key: string]: string };
  
  /**
   * Secrets for the container
   */
  secrets?: ContainerSecretConfig[];
  
  /**
   * Additional IAM policy statements for the task role
   */
  taskRolePolicyStatements?: iam.PolicyStatement[];
  
  /**
   * Auto scaling configuration
   */
  autoScaling?: {
    minCapacity?: number;
    maxCapacity?: number;
    cpuTargetUtilization?: number;
    memoryTargetUtilization?: number;
  };
  
  /**
   * Health check path
   * @default '/health'
   */
  healthCheckPath?: string;
  
  /**
   * Health check grace period
   * @default 300 seconds
   */
  healthCheckGracePeriod?: cdk.Duration;
  
  /**
   * Container image tag
   * @default 'latest'
   */
  imageTag?: string;
}

export class ECSFargateService extends Construct {
  public readonly cluster: ecs.Cluster;
  public readonly service: ecs.FargateService;
  public readonly taskDefinition: ecs.FargateTaskDefinition;
  public readonly logGroup: logs.LogGroup;
  public readonly taskRole: iam.Role;
  public readonly executionRole: iam.Role;

  constructor(scope: Construct, id: string, props: ECSFargateServiceProps) {
    super(scope, id);

    const {
      vpc,
      securityGroup,
      repository,
      serviceName,
      logGroupName = `/ecs/${serviceName}`,
      logRetention = logs.RetentionDays.ONE_WEEK,
      cpu = 512,
      memoryLimitMiB = 1024,
      containerPort = 80,
      desiredCount = 1,
      environment = {},
      secrets = [],
      taskRolePolicyStatements = [],
      autoScaling,
      healthCheckPath = '/health',
      healthCheckGracePeriod = cdk.Duration.seconds(300),
      imageTag = 'latest'
    } = props;

    // ECS Cluster
    this.cluster = new ecs.Cluster(this, 'Cluster', {
      vpc,
      clusterName: `${serviceName}-cluster`,
      containerInsights: true,
    });

    cdk.Tags.of(this.cluster).add('Critical', 'true');
    cdk.Tags.of(this.cluster).add('Purpose', 'Container Orchestration');

    // CloudWatch Log Group
    this.logGroup = new logs.LogGroup(this, 'LogGroup', {
      logGroupName,
      retention: logRetention,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
    });

    cdk.Tags.of(this.logGroup).add('Purpose', 'Application Logs');
    cdk.Tags.of(this.logGroup).add('DataRetention', this.getRetentionLabel(logRetention));

    // IAM Role for ECS Task
    this.taskRole = new iam.Role(this, 'TaskRole', {
      assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
      description: `Role for ${serviceName} ECS task`,
    });

    // Add custom policy statements to task role
    taskRolePolicyStatements.forEach((statement, index) => {
      this.taskRole.addToPolicy(statement);
    });

    // Add secrets access to task role if secrets are provided
    if (secrets.length > 0) {
      this.taskRole.addToPolicy(new iam.PolicyStatement({
        effect: iam.Effect.ALLOW,
        actions: ['secretsmanager:GetSecretValue'],
        resources: secrets.map(s => s.secret.secretArn),
      }));
    }

    // ECS Task Execution Role
    this.executionRole = new iam.Role(this, 'ExecutionRole', {
      assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
      managedPolicies: [
        iam.ManagedPolicy.fromAwsManagedPolicyName('service-role/AmazonECSTaskExecutionRolePolicy'),
      ],
    });

    // Grant execution role access to secrets if provided
    if (secrets.length > 0) {
      this.executionRole.addToPolicy(new iam.PolicyStatement({
        effect: iam.Effect.ALLOW,
        actions: ['secretsmanager:GetSecretValue'],
        resources: secrets.map(s => s.secret.secretArn),
      }));
    }

    cdk.Tags.of(this.taskRole).add('Purpose', 'ECS Task Execution');
    cdk.Tags.of(this.executionRole).add('Purpose', 'ECS Task Role');

    // ECS Task Definition
    this.taskDefinition = new ecs.FargateTaskDefinition(this, 'TaskDefinition', {
      memoryLimitMiB,
      cpu,
      taskRole: this.taskRole,
      executionRole: this.executionRole,
    });

    // Prepare secrets for container
    const containerSecrets: { [key: string]: ecs.Secret } = {};
    secrets.forEach(secretConfig => {
      containerSecrets[secretConfig.secretKey] = ecs.Secret.fromSecretsManager(
        secretConfig.secret,
        secretConfig.jsonKey
      );
    });

    // Container Definition
    const container = this.taskDefinition.addContainer(serviceName, {
      image: ecs.ContainerImage.fromEcrRepository(repository, imageTag),
      logging: ecs.LogDrivers.awsLogs({
        streamPrefix: serviceName,
        logGroup: this.logGroup,
      }),
      environment,
      secrets: containerSecrets,
      healthCheck: {
        command: ['CMD-SHELL', `curl -f http://localhost${healthCheckPath} || exit 1`],
        interval: cdk.Duration.seconds(30),
        timeout: cdk.Duration.seconds(5),
        retries: 3,
        startPeriod: cdk.Duration.seconds(60),
      },
    });

    container.addPortMappings({
      containerPort,
      protocol: ecs.Protocol.TCP,
    });

    // ECS Service
    this.service = new ecs.FargateService(this, 'Service', {
      cluster: this.cluster,
      taskDefinition: this.taskDefinition,
      serviceName: `${serviceName}-service`,
      desiredCount,
      securityGroups: [securityGroup],
      vpcSubnets: {
        subnetType: ec2.SubnetType.PUBLIC,
      },
      assignPublicIp: true,
      healthCheckGracePeriod,
    });

    cdk.Tags.of(this.service).add('Critical', 'true');
    cdk.Tags.of(this.service).add('Purpose', 'Application Service');

    // Auto Scaling (if configured)
    if (autoScaling) {
      const scaling = this.service.autoScaleTaskCount({
        minCapacity: autoScaling.minCapacity || 1,
        maxCapacity: autoScaling.maxCapacity || 10,
      });

      if (autoScaling.cpuTargetUtilization) {
        scaling.scaleOnCpuUtilization('CpuScaling', {
          targetUtilizationPercent: autoScaling.cpuTargetUtilization,
          scaleInCooldown: cdk.Duration.seconds(300),
          scaleOutCooldown: cdk.Duration.seconds(300),
        });
      }

      if (autoScaling.memoryTargetUtilization) {
        scaling.scaleOnMemoryUtilization('MemoryScaling', {
          targetUtilizationPercent: autoScaling.memoryTargetUtilization,
          scaleInCooldown: cdk.Duration.seconds(300),
          scaleOutCooldown: cdk.Duration.seconds(300),
        });
      }
    }
  }

  /**
   * Attach the service to an Application Load Balancer target group
   */
  public attachToTargetGroup(targetGroup: elbv2.ApplicationTargetGroup): void {
    this.service.attachToApplicationTargetGroup(targetGroup);
  }

  private getRetentionLabel(retention: logs.RetentionDays): string {
    switch (retention) {
      case logs.RetentionDays.ONE_DAY: return '1day';
      case logs.RetentionDays.THREE_DAYS: return '3days';
      case logs.RetentionDays.FIVE_DAYS: return '5days';
      case logs.RetentionDays.ONE_WEEK: return '7days';
      case logs.RetentionDays.TWO_WEEKS: return '14days';
      case logs.RetentionDays.ONE_MONTH: return '30days';
      case logs.RetentionDays.TWO_MONTHS: return '60days';
      case logs.RetentionDays.THREE_MONTHS: return '90days';
      case logs.RetentionDays.FOUR_MONTHS: return '120days';
      case logs.RetentionDays.FIVE_MONTHS: return '150days';
      case logs.RetentionDays.SIX_MONTHS: return '180days';
      case logs.RetentionDays.ONE_YEAR: return '365days';
      case logs.RetentionDays.THIRTEEN_MONTHS: return '400days';
      case logs.RetentionDays.EIGHTEEN_MONTHS: return '545days';
      case logs.RetentionDays.TWO_YEARS: return '731days';
      case logs.RetentionDays.THREE_YEARS: return '1096days';
      case logs.RetentionDays.FIVE_YEARS: return '1827days';
      case logs.RetentionDays.SIX_YEARS: return '2192days';
      case logs.RetentionDays.SEVEN_YEARS: return '2557days';
      case logs.RetentionDays.EIGHT_YEARS: return '2922days';
      case logs.RetentionDays.NINE_YEARS: return '3287days';
      case logs.RetentionDays.TEN_YEARS: return '3653days';
      case logs.RetentionDays.INFINITE: return 'infinite';
      default: return 'custom';
    }
  }
}