import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';
import { GitHubOidc } from '../constructs/github-oidc';

export interface GitHubOidcStackProps extends cdk.StackProps {
  allowedBranches: string[];
  githubOrg: string;
  githubRepo: string;
}

export class GitHubOidcStack extends cdk.Stack {
  public readonly githubOidc: GitHubOidc;

  constructor(scope: Construct, id: string, props: GitHubOidcStackProps) {
    super(scope, id, props);

    // Apply stack-level tags
    cdk.Tags.of(this).add('Stack', 'CICD');
    cdk.Tags.of(this).add('Purpose', 'GitHubActionsOIDC');

    // Create GitHub OIDC construct
    this.githubOidc = new GitHubOidc(this, 'GitHubOidc', {
      allowedBranches: props.allowedBranches,
      githubOrg: props.githubOrg,
      githubRepo: props.githubRepo,
    });

    // Add construct-level tags
    cdk.Tags.of(this.githubOidc).add('Purpose', 'OIDC Authentication');
    cdk.Tags.of(this.githubOidc.githubActionsRole).add('Critical', 'true');
    cdk.Tags.of(this.githubOidc.githubActionsRole).add('Access', 'PowerUser');

    // Stack outputs for GitHub Actions
    new cdk.CfnOutput(this, 'GitHubActionsRoleArn', {
      value: this.githubOidc.githubActionsRole.roleArn,
      description: 'ARN of the GitHub Actions role for OIDC authentication',
      exportName: 'TravelBot-GitHubActionsRoleArn',
    });

    new cdk.CfnOutput(this, 'GitHubOidcProviderArn', {
      value: `arn:aws:iam::${this.account}:oidc-provider/token.actions.githubusercontent.com`,
      description: 'ARN of the GitHub OIDC provider',
      exportName: 'TravelBot-GitHubOidcProviderArn',
    });

    new cdk.CfnOutput(this, 'GitHubActionsRoleName', {
      value: this.githubOidc.githubActionsRole.roleName,
      description: 'Name of the GitHub Actions role',
      exportName: 'TravelBot-GitHubActionsRoleName',
    });

    new cdk.CfnOutput(this, 'GitHubActionsRoleArnParameter', {
      value: '/travelbot/github-actions-role-arn',
      description: 'SSM Parameter name containing the GitHub Actions role ARN',
      exportName: 'TravelBot-GitHubActionsRoleArnParameter',
    });
  }
}