import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as ecs from 'aws-cdk-lib/aws-ecs';
import * as ecr from 'aws-cdk-lib/aws-ecr';
import * as elbv2 from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as logs from 'aws-cdk-lib/aws-logs';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import * as cloudfront from 'aws-cdk-lib/aws-cloudfront';
import * as origins from 'aws-cdk-lib/aws-cloudfront-origins';
import { Construct } from 'constructs';

export class TravelbotStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    // VPC
    const vpc = new ec2.Vpc(this, 'TravelbotVpc', {
      maxAzs: 2,
      natGateways: 0,
      subnetConfiguration: [
        {
          cidrMask: 24,
          name: 'public',
          subnetType: ec2.SubnetType.PUBLIC,
        },
      ],
    });

    // ECR Repository
    const ecrRepository = new ecr.Repository(this, 'TravelbotRepository', {
      repositoryName: 'travelbot',
      removalPolicy: cdk.RemovalPolicy.DESTROY,
      lifecycleRules: [{
        maxImageCount: 10,
      }],
    });

    // ECS Cluster
    const cluster = new ecs.Cluster(this, 'TravelbotCluster', {
      vpc,
      clusterName: 'travelbot-cluster',
      containerInsights: true,
    });

    // CloudWatch Log Group
    const logGroup = new logs.LogGroup(this, 'TravelbotLogGroup', {
      logGroupName: '/ecs/travelbot',
      retention: logs.RetentionDays.ONE_WEEK,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
    });

    // Secrets for environment variables
    const dbSecret = new secretsmanager.Secret(this, 'DatabaseSecret', {
      secretName: 'travelbot/database-url',
      description: 'Neon database URL for TravelBot',
      secretStringValue: cdk.SecretValue.unsafePlainText(JSON.stringify({
        database_url: 'postgresql://placeholder:placeholder@placeholder.neon.tech/placeholder'
      })),
    });

    const appSecret = new secretsmanager.Secret(this, 'AppSecret', {
      secretName: 'travelbot/app-secret',
      description: 'Symfony app secret for TravelBot',
      generateSecretString: {
        secretStringTemplate: JSON.stringify({}),
        generateStringKey: 'secret',
        passwordLength: 32,
        excludeCharacters: ' "@/\\\'',
      },
    });

    const bedrockSecret = new secretsmanager.Secret(this, 'BedrockSecret', {
      secretName: 'travelbot/bedrock-config',
      description: 'AWS Bedrock configuration for TravelBot',
      secretStringValue: cdk.SecretValue.unsafePlainText(JSON.stringify({
        region: 'us-west-2',
        sonnet_model: 'us.anthropic.claude-sonnet-4-20250514-v1:0',
        haiku_model: 'us.anthropic.claude-3-5-haiku-20241022-v1:0'
      })),
    });

    // IAM Role for ECS Task
    const taskRole = new iam.Role(this, 'TravelbotTaskRole', {
      assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
      description: 'Role for TravelBot ECS task',
    });

    // Add Bedrock permissions to task role
    taskRole.addToPolicy(new iam.PolicyStatement({
      effect: iam.Effect.ALLOW,
      actions: [
        'bedrock:InvokeModel',
        'bedrock:InvokeModelWithResponseStream',
        'bedrock:Converse',
        'bedrock:ConverseStream',
      ],
      resources: ['*'],
    }));

    // Add secrets access to task role
    taskRole.addToPolicy(new iam.PolicyStatement({
      effect: iam.Effect.ALLOW,
      actions: [
        'secretsmanager:GetSecretValue',
      ],
      resources: [
        dbSecret.secretArn,
        appSecret.secretArn,
        bedrockSecret.secretArn,
      ],
    }));

    // ECS Task Execution Role
    const executionRole = new iam.Role(this, 'TravelbotExecutionRole', {
      assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
      managedPolicies: [
        iam.ManagedPolicy.fromAwsManagedPolicyName('service-role/AmazonECSTaskExecutionRolePolicy'),
      ],
    });

    // Grant execution role access to ECR and secrets
    executionRole.addToPolicy(new iam.PolicyStatement({
      effect: iam.Effect.ALLOW,
      actions: [
        'secretsmanager:GetSecretValue',
      ],
      resources: [
        dbSecret.secretArn,
        appSecret.secretArn,
        bedrockSecret.secretArn,
      ],
    }));

    // ECS Task Definition
    const taskDefinition = new ecs.FargateTaskDefinition(this, 'TravelbotTaskDefinition', {
      memoryLimitMiB: 1024,
      cpu: 512,
      taskRole,
      executionRole,
    });

    // Container Definition
    const container = taskDefinition.addContainer('travelbot', {
      image: ecs.ContainerImage.fromEcrRepository(ecrRepository, 'latest'),
      logging: ecs.LogDrivers.awsLogs({
        streamPrefix: 'travelbot',
        logGroup,
      }),
      environment: {
        APP_ENV: 'prod',
        APP_DEBUG: '0',
        AWS_REGION: this.region,
        MESSENGER_TRANSPORT_DSN: 'doctrine://default?auto_setup=0',
        MAILER_DSN: 'null://null',
        TRUSTED_PROXIES: 'REMOTE_ADDR',
      },
      secrets: {
        DATABASE_URL: ecs.Secret.fromSecretsManager(dbSecret, 'database_url'),
        APP_SECRET: ecs.Secret.fromSecretsManager(appSecret, 'secret'),
        BEDROCK_CLAUDE_SONNET_MODEL: ecs.Secret.fromSecretsManager(bedrockSecret, 'sonnet_model'),
        BEDROCK_CLAUDE_HAIKU_MODEL: ecs.Secret.fromSecretsManager(bedrockSecret, 'haiku_model'),
      },
      healthCheck: {
        command: ['CMD-SHELL', 'curl -f http://localhost/health || exit 1'],
        interval: cdk.Duration.seconds(30),
        timeout: cdk.Duration.seconds(5),
        retries: 3,
        startPeriod: cdk.Duration.seconds(60),
      },
    });

    container.addPortMappings({
      containerPort: 80,
      protocol: ecs.Protocol.TCP,
    });

    // Application Load Balancer
    const alb = new elbv2.ApplicationLoadBalancer(this, 'TravelbotALB', {
      vpc,
      internetFacing: true,
      loadBalancerName: 'travelbot-alb',
    });

    // ALB Security Group
    const albSecurityGroup = new ec2.SecurityGroup(this, 'ALBSecurityGroup', {
      vpc,
      description: 'Security group for TravelBot ALB',
      allowAllOutbound: true,
    });

    albSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(),
      ec2.Port.tcp(80),
      'Allow HTTP traffic'
    );

    albSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(),
      ec2.Port.tcp(443),
      'Allow HTTPS traffic'
    );

    alb.addSecurityGroup(albSecurityGroup);

    // ECS Security Group
    const ecsSecurityGroup = new ec2.SecurityGroup(this, 'ECSSecurityGroup', {
      vpc,
      description: 'Security group for TravelBot ECS tasks',
      allowAllOutbound: true,
    });

    ecsSecurityGroup.addIngressRule(
      albSecurityGroup,
      ec2.Port.tcp(80),
      'Allow traffic from ALB'
    );

    // ECS Service
    const service = new ecs.FargateService(this, 'TravelbotService', {
      cluster,
      taskDefinition,
      serviceName: 'travelbot-service',
      desiredCount: 1,
      securityGroups: [ecsSecurityGroup],
      vpcSubnets: {
        subnetType: ec2.SubnetType.PUBLIC,
      },
      assignPublicIp: true,
      healthCheckGracePeriod: cdk.Duration.seconds(300),
    });

    // Target Group
    const targetGroup = new elbv2.ApplicationTargetGroup(this, 'TravelbotTargetGroup', {
      vpc,
      port: 80,
      protocol: elbv2.ApplicationProtocol.HTTP,
      targetType: elbv2.TargetType.IP,
      healthCheck: {
        enabled: true,
        path: '/health',
        healthyHttpCodes: '200',
        interval: cdk.Duration.seconds(30),
        timeout: cdk.Duration.seconds(5),
        healthyThresholdCount: 2,
        unhealthyThresholdCount: 3,
      },
    });

    // ALB Listener
    alb.addListener('TravelbotListener', {
      port: 80,
      protocol: elbv2.ApplicationProtocol.HTTP,
      defaultTargetGroups: [targetGroup],
    });

    // Register ECS Service with Target Group
    service.attachToApplicationTargetGroup(targetGroup);

    // Auto Scaling
    const scaling = service.autoScaleTaskCount({
      minCapacity: 1,
      maxCapacity: 10,
    });

    scaling.scaleOnCpuUtilization('CpuScaling', {
      targetUtilizationPercent: 70,
      scaleInCooldown: cdk.Duration.seconds(300),
      scaleOutCooldown: cdk.Duration.seconds(300),
    });

    scaling.scaleOnMemoryUtilization('MemoryScaling', {
      targetUtilizationPercent: 80,
      scaleInCooldown: cdk.Duration.seconds(300),
      scaleOutCooldown: cdk.Duration.seconds(300),
    });


    // CloudFront Distribution
    const distribution = new cloudfront.Distribution(this, 'TravelbotDistribution', {
      defaultBehavior: {
        origin: new origins.LoadBalancerV2Origin(alb, {
          protocolPolicy: cloudfront.OriginProtocolPolicy.HTTP_ONLY,
          httpPort: 80,
        }),
        viewerProtocolPolicy: cloudfront.ViewerProtocolPolicy.REDIRECT_TO_HTTPS,
        allowedMethods: cloudfront.AllowedMethods.ALLOW_ALL,
        cachedMethods: cloudfront.CachedMethods.CACHE_GET_HEAD_OPTIONS,
        cachePolicy: cloudfront.CachePolicy.CACHING_DISABLED,
        originRequestPolicy: cloudfront.OriginRequestPolicy.ALL_VIEWER,
        compress: true,
      },
      httpVersion: cloudfront.HttpVersion.HTTP2_AND_3,
      minimumProtocolVersion: cloudfront.SecurityPolicyProtocol.TLS_V1_2_2021,
      enableLogging: false,
      comment: 'TravelBot CloudFront Distribution',
    });

    // Outputs
    new cdk.CfnOutput(this, 'LoadBalancerURL', {
      value: `http://${alb.loadBalancerDnsName}`,
      description: 'URL of the load balancer (internal)',
    });

    new cdk.CfnOutput(this, 'CloudFrontURL', {
      value: `https://${distribution.distributionDomainName}`,
      description: 'CloudFront distribution URL (use this for HTTPS access)',
    });

    new cdk.CfnOutput(this, 'ECRRepositoryURI', {
      value: ecrRepository.repositoryUri,
      description: 'ECR Repository URI',
    });

    new cdk.CfnOutput(this, 'ClusterName', {
      value: cluster.clusterName,
      description: 'ECS Cluster Name',
    });

    new cdk.CfnOutput(this, 'ServiceName', {
      value: service.serviceName,
      description: 'ECS Service Name',
    });
  }
}