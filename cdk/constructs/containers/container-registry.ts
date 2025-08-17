import * as cdk from 'aws-cdk-lib';
import * as ecr from 'aws-cdk-lib/aws-ecr';
import { Construct } from 'constructs';

export interface ContainerRegistryProps {
  /**
   * Name of the ECR repository
   */
  repositoryName: string;
  
  /**
   * Maximum number of images to keep
   * @default 10
   */
  maxImageCount?: number;
  
  /**
   * Removal policy for the repository
   * @default RemovalPolicy.DESTROY
   */
  removalPolicy?: cdk.RemovalPolicy;
}

export class ContainerRegistry extends Construct {
  public readonly repository: ecr.Repository;

  constructor(scope: Construct, id: string, props: ContainerRegistryProps) {
    super(scope, id);

    const {
      repositoryName,
      maxImageCount = 10,
      removalPolicy = cdk.RemovalPolicy.DESTROY
    } = props;

    // ECR Repository
    this.repository = new ecr.Repository(this, 'Repository', {
      repositoryName,
      removalPolicy,
      lifecycleRules: [{
        maxImageCount,
      }],
    });

    // Add ECR tags
    cdk.Tags.of(this.repository).add('Critical', 'true');
    cdk.Tags.of(this.repository).add('Purpose', 'Container Images');
  }
}