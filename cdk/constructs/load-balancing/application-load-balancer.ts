import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as elbv2 from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import * as acm from 'aws-cdk-lib/aws-certificatemanager';
import { Construct } from 'constructs';

export interface TargetGroupConfig {
  /**
   * Target group name suffix
   */
  name: string;
  
  /**
   * Port for the target group
   * @default 80
   */
  port?: number;
  
  /**
   * Protocol for the target group
   * @default HTTP
   */
  protocol?: elbv2.ApplicationProtocol;
  
  /**
   * Health check configuration
   */
  healthCheck?: {
    path?: string;
    healthyHttpCodes?: string;
    interval?: cdk.Duration;
    timeout?: cdk.Duration;
    healthyThresholdCount?: number;
    unhealthyThresholdCount?: number;
  };
}

export interface ListenerConfig {
  /**
   * Listener port
   */
  port: number;
  
  /**
   * Listener protocol
   */
  protocol: elbv2.ApplicationProtocol;
  
  /**
   * Default target groups for this listener
   */
  defaultTargetGroups: elbv2.ApplicationTargetGroup[];
  
  /**
   * SSL certificate for HTTPS listeners
   */
  certificate?: acm.ICertificate;
}

export interface ApplicationLoadBalancerProps {
  /**
   * VPC to deploy the load balancer in
   */
  vpc: ec2.Vpc;
  
  /**
   * Security group for the load balancer
   */
  securityGroup: ec2.SecurityGroup;
  
  /**
   * Load balancer name
   */
  loadBalancerName: string;
  
  /**
   * Whether the load balancer is internet-facing
   * @default true
   */
  internetFacing?: boolean;
  
  /**
   * Target groups to create
   */
  targetGroups?: TargetGroupConfig[];
  
  /**
   * Listeners to create
   */
  listeners?: ListenerConfig[];
  
  /**
   * SSL certificate for HTTPS listeners
   */
  certificate?: acm.ICertificate;
}

export class ApplicationLoadBalancer extends Construct {
  public readonly loadBalancer: elbv2.ApplicationLoadBalancer;
  public readonly targetGroups: { [name: string]: elbv2.ApplicationTargetGroup } = {};

  constructor(scope: Construct, id: string, props: ApplicationLoadBalancerProps) {
    super(scope, id);

    const {
      vpc,
      securityGroup,
      loadBalancerName,
      internetFacing = true,
      targetGroups = [],
      listeners = []
    } = props;

    // Application Load Balancer
    this.loadBalancer = new elbv2.ApplicationLoadBalancer(this, 'LoadBalancer', {
      vpc,
      internetFacing,
      loadBalancerName,
    });

    // Add security group to load balancer
    this.loadBalancer.addSecurityGroup(securityGroup);

    // Add ALB tags
    cdk.Tags.of(this.loadBalancer).add('Critical', 'true');
    cdk.Tags.of(this.loadBalancer).add('Purpose', 'Load Balancing');

    // Create target groups
    targetGroups.forEach(targetGroupConfig => {
      const targetGroup = new elbv2.ApplicationTargetGroup(this, `TargetGroup${targetGroupConfig.name}`, {
        vpc,
        port: targetGroupConfig.port || 80,
        protocol: targetGroupConfig.protocol || elbv2.ApplicationProtocol.HTTP,
        targetType: elbv2.TargetType.IP,
        healthCheck: {
          enabled: true,
          path: targetGroupConfig.healthCheck?.path || '/health',
          healthyHttpCodes: targetGroupConfig.healthCheck?.healthyHttpCodes || '200',
          interval: targetGroupConfig.healthCheck?.interval || cdk.Duration.seconds(30),
          timeout: targetGroupConfig.healthCheck?.timeout || cdk.Duration.seconds(5),
          healthyThresholdCount: targetGroupConfig.healthCheck?.healthyThresholdCount || 2,
          unhealthyThresholdCount: targetGroupConfig.healthCheck?.unhealthyThresholdCount || 3,
        },
      });

      cdk.Tags.of(targetGroup).add('Purpose', `Target Group: ${targetGroupConfig.name}`);
      this.targetGroups[targetGroupConfig.name] = targetGroup;
    });

    // Create listeners
    listeners.forEach((listenerConfig, index) => {
      const listenerProps: any = {
        port: listenerConfig.port,
        protocol: listenerConfig.protocol,
        defaultTargetGroups: listenerConfig.defaultTargetGroups,
      };
      
      // Add certificate for HTTPS listeners
      if (listenerConfig.protocol === elbv2.ApplicationProtocol.HTTPS) {
        listenerProps.certificates = [listenerConfig.certificate || props.certificate];
      }
      
      this.loadBalancer.addListener(`Listener${index}`, listenerProps);
    });
  }

  /**
   * Create a simple HTTP listener with a single target group
   */
  public addSimpleHttpListener(targetGroup: elbv2.ApplicationTargetGroup): elbv2.ApplicationListener {
    return this.loadBalancer.addListener('HttpListener', {
      port: 80,
      protocol: elbv2.ApplicationProtocol.HTTP,
      defaultTargetGroups: [targetGroup],
    });
  }

  /**
   * Create a target group with default health check settings
   */
  public createTargetGroup(name: string, port: number = 80, healthCheckPath: string = '/health'): elbv2.ApplicationTargetGroup {
    const targetGroup = new elbv2.ApplicationTargetGroup(this, `TargetGroup${name}`, {
      vpc: this.loadBalancer.vpc!,
      port,
      protocol: elbv2.ApplicationProtocol.HTTP,
      targetType: elbv2.TargetType.IP,
      healthCheck: {
        enabled: true,
        path: healthCheckPath,
        healthyHttpCodes: '200',
        interval: cdk.Duration.seconds(30),
        timeout: cdk.Duration.seconds(5),
        healthyThresholdCount: 2,
        unhealthyThresholdCount: 3,
      },
    });

    cdk.Tags.of(targetGroup).add('Purpose', `Target Group: ${name}`);
    this.targetGroups[name] = targetGroup;
    
    return targetGroup;
  }

  /**
   * Get the load balancer DNS name
   */
  public get dnsName(): string {
    return this.loadBalancer.loadBalancerDnsName;
  }

  /**
   * Get the load balancer ARN
   */
  public get arn(): string {
    return this.loadBalancer.loadBalancerArn;
  }

  /**
   * Create an HTTPS listener with certificate
   */
  public addHttpsListener(
    targetGroup: elbv2.ApplicationTargetGroup,
    certificate: acm.ICertificate,
    port: number = 443
  ): elbv2.ApplicationListener {
    return this.loadBalancer.addListener('HttpsListener', {
      port,
      protocol: elbv2.ApplicationProtocol.HTTPS,
      certificates: [certificate],
      defaultTargetGroups: [targetGroup],
    });
  }

  /**
   * Add HTTP to HTTPS redirect listener
   */
  public addHttpRedirectToHttps(httpsPort: number = 443): elbv2.ApplicationListener {
    return this.loadBalancer.addListener('HttpRedirectListener', {
      port: 80,
      protocol: elbv2.ApplicationProtocol.HTTP,
      defaultAction: elbv2.ListenerAction.redirect({
        protocol: 'HTTPS',
        port: httpsPort.toString(),
        permanent: true,
      }),
    });
  }

  /**
   * Get the hosted zone ID for the load balancer (for Route53 alias records)
   */
  public get hostedZoneId(): string {
    return this.loadBalancer.loadBalancerCanonicalHostedZoneId;
  }
}