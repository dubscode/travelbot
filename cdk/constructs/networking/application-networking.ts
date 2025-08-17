import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import { Construct } from 'constructs';

export interface ApplicationNetworkingProps {
  /**
   * Maximum number of Availability Zones to use
   * @default 2
   */
  maxAzs?: number;
  
  /**
   * Number of NAT Gateways
   * @default 0
   */
  natGateways?: number;
  
  /**
   * CIDR mask for subnets
   * @default 24
   */
  cidrMask?: number;
  
  /**
   * VPC name prefix for tagging
   * @default 'Application'
   */
  vpcNamePrefix?: string;
}

export class ApplicationNetworking extends Construct {
  public readonly vpc: ec2.Vpc;
  public readonly albSecurityGroup: ec2.SecurityGroup;
  public readonly ecsSecurityGroup: ec2.SecurityGroup;

  constructor(scope: Construct, id: string, props: ApplicationNetworkingProps = {}) {
    super(scope, id);

    const {
      maxAzs = 2,
      natGateways = 0,
      cidrMask = 24,
      vpcNamePrefix = 'Application'
    } = props;

    // VPC
    this.vpc = new ec2.Vpc(this, 'Vpc', {
      maxAzs,
      natGateways,
      subnetConfiguration: [
        {
          cidrMask,
          name: 'public',
          subnetType: ec2.SubnetType.PUBLIC,
        },
      ],
    });

    // Add VPC tags
    cdk.Tags.of(this.vpc).add('Name', `${vpcNamePrefix}-VPC`);
    cdk.Tags.of(this.vpc).add('Purpose', 'Application');

    // ALB Security Group
    this.albSecurityGroup = new ec2.SecurityGroup(this, 'ALBSecurityGroup', {
      vpc: this.vpc,
      description: 'Security group for Application Load Balancer',
      allowAllOutbound: true,
    });

    this.albSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(),
      ec2.Port.tcp(80),
      'Allow HTTP traffic'
    );

    this.albSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(),
      ec2.Port.tcp(443),
      'Allow HTTPS traffic'
    );

    // ECS Security Group
    this.ecsSecurityGroup = new ec2.SecurityGroup(this, 'ECSSecurityGroup', {
      vpc: this.vpc,
      description: 'Security group for ECS tasks',
      allowAllOutbound: true,
    });

    this.ecsSecurityGroup.addIngressRule(
      this.albSecurityGroup,
      ec2.Port.tcp(80),
      'Allow traffic from ALB'
    );

    // Add security group tags
    cdk.Tags.of(this.albSecurityGroup).add('Purpose', 'ALB Security');
    cdk.Tags.of(this.ecsSecurityGroup).add('Purpose', 'ECS Security');
  }
}