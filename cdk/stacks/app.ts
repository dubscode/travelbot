import * as cdk from 'aws-cdk-lib';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as route53 from 'aws-cdk-lib/aws-route53';
import * as acm from 'aws-cdk-lib/aws-certificatemanager';
import * as targets from 'aws-cdk-lib/aws-route53-targets';
import { Construct } from 'constructs';
import { ApplicationNetworking } from '../constructs/networking/application-networking';
import { ContainerRegistry } from '../constructs/containers/container-registry';
import { ApplicationSecrets } from '../constructs/secrets/application-secrets';
import { ECSFargateService } from '../constructs/containers/ecs-fargate-service';
import { ApplicationLoadBalancer } from '../constructs/load-balancing/application-load-balancer';

export class AppStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    // Apply stack-level tags
    cdk.Tags.of(this).add('Stack', 'Application');
    cdk.Tags.of(this).add('Environment', 'Production');

    // Networking
    const networking = new ApplicationNetworking(this, 'Networking', {
      maxAzs: 2,
      natGateways: 0,
      cidrMask: 24,
      vpcNamePrefix: 'TravelBot',
    });

    // Container Registry
    const registry = new ContainerRegistry(this, 'Registry', {
      repositoryName: 'travelbot',
      maxImageCount: 10,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
    });

    // Application Secrets
    const secrets = new ApplicationSecrets(this, 'Secrets', {
      applicationName: 'travelbot',
      databaseSecret: {
        secretName: 'travelbot/database-url',
        description: 'Neon database URL for TravelBot',
        placeholderUrl: 'postgresql://placeholder:placeholder@placeholder.neon.tech/placeholder',
      },
      appSecret: {
        secretName: 'travelbot/app-secret',
        description: 'Symfony app secret for TravelBot',
        passwordLength: 32,
        excludeCharacters: ' "@/\\\\\'',
      },
      customSecrets: [{
        secretName: 'travelbot/bedrock-config',
        description: 'AWS Bedrock configuration for TravelBot',
        secretValue: {
          region: 'us-west-2',
          sonnet_model: 'us.anthropic.claude-sonnet-4-20250514-v1:0',
          haiku_model: 'us.anthropic.claude-3-5-haiku-20241022-v1:0',
        },
      }],
    });

    // ECS Fargate Service
    const ecsService = new ECSFargateService(this, 'ECSService', {
      vpc: networking.vpc,
      securityGroup: networking.ecsSecurityGroup,
      repository: registry.repository,
      serviceName: 'travelbot',
      cpu: 512,
      memoryLimitMiB: 1024,
      desiredCount: 1,
      environment: {
        APP_ENV: 'prod',
        APP_DEBUG: '0',
        AWS_REGION: this.region,
        MESSENGER_TRANSPORT_DSN: 'doctrine://default?auto_setup=0',
        MAILER_DSN: 'null://null',
        TRUSTED_PROXIES: '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
      },
      secrets: [
        {
          secretKey: 'DATABASE_URL',
          secret: secrets.dbSecret!,
          jsonKey: 'database_url',
        },
        {
          secretKey: 'APP_SECRET',
          secret: secrets.appSecret!,
          jsonKey: 'secret',
        },
        {
          secretKey: 'BEDROCK_CLAUDE_SONNET_MODEL',
          secret: secrets.customSecrets['bedrock-config'],
          jsonKey: 'sonnet_model',
        },
        {
          secretKey: 'BEDROCK_CLAUDE_HAIKU_MODEL',
          secret: secrets.customSecrets['bedrock-config'],
          jsonKey: 'haiku_model',
        },
      ],
      taskRolePolicyStatements: [
        new iam.PolicyStatement({
          effect: iam.Effect.ALLOW,
          actions: [
            'bedrock:InvokeModel',
            'bedrock:InvokeModelWithResponseStream',
            'bedrock:Converse',
            'bedrock:ConverseStream',
          ],
          resources: ['*'],
        }),
      ],
      autoScaling: {
        minCapacity: 1,
        maxCapacity: 10,
        cpuTargetUtilization: 70,
        memoryTargetUtilization: 80,
      },
      healthCheckPath: '/health',
      healthCheckGracePeriod: cdk.Duration.seconds(300),
    });

    // Application Load Balancer
    const loadBalancer = new ApplicationLoadBalancer(this, 'LoadBalancer', {
      vpc: networking.vpc,
      securityGroup: networking.albSecurityGroup,
      loadBalancerName: 'travelbot-alb',
      internetFacing: true,
    });

    // Create target group
    const targetGroup = loadBalancer.createTargetGroup('Main', 80, '/health');

    // Attach ECS service to target group
    ecsService.attachToTargetGroup(targetGroup);

    // Domain configuration
    const domainName = 'travelbot.tech';
    
    // Look up the existing hosted zone
    const hostedZone = route53.HostedZone.fromLookup(this, 'HostedZone', {
      domainName,
    });
    
    // Create ACM certificate for HTTPS
    const certificate = new acm.Certificate(this, 'Certificate', {
      domainName,
      validation: acm.CertificateValidation.fromDns(hostedZone),
    });
    
    // Add HTTPS listener to ALB
    loadBalancer.addHttpsListener(targetGroup, certificate);
    
    // Add HTTP to HTTPS redirect
    loadBalancer.addHttpRedirectToHttps();
    
    // Create Route53 A record pointing to ALB
    new route53.ARecord(this, 'ARecord', {
      zone: hostedZone,
      target: route53.RecordTarget.fromAlias(new targets.LoadBalancerTarget(loadBalancer.loadBalancer)),
    });

    // Outputs
    new cdk.CfnOutput(this, 'LoadBalancerURL', {
      value: `http://${loadBalancer.dnsName}`,
      description: 'URL of the load balancer (internal)',
    });

    new cdk.CfnOutput(this, 'WebsiteURL', {
      value: `https://${domainName}`,
      description: 'HTTPS URL of the website',
    });

    new cdk.CfnOutput(this, 'CertificateArn', {
      value: certificate.certificateArn,
      description: 'ARN of the SSL certificate',
    });

    new cdk.CfnOutput(this, 'ECRRepositoryURI', {
      value: registry.repository.repositoryUri,
      description: 'ECR Repository URI',
    });

    new cdk.CfnOutput(this, 'ClusterName', {
      value: ecsService.cluster.clusterName,
      description: 'ECS Cluster Name',
    });

    new cdk.CfnOutput(this, 'ServiceName', {
      value: ecsService.service.serviceName,
      description: 'ECS Service Name',
    });
  }
}