import { Duration, Stack } from 'aws-cdk-lib';
import * as iam from 'aws-cdk-lib/aws-iam';
import { Construct } from 'constructs';

type GitHubOidcProps = {
  allowedBranches: string[];
  githubOrg: string;
  githubRepo: string;
};

export class GitHubOidc extends Construct {
  public readonly githubActionsRole: iam.Role;
  public readonly githubOidcProvider: iam.IOpenIdConnectProvider;

  constructor(stack: Stack, id: string, props: GitHubOidcProps) {
    super(stack, id);

    const identifier = `${stack.stackName}-prod`;

    const { allowedBranches, githubOrg, githubRepo } = props;

    // Import existing GitHub OIDC provider (account-wide resource)
    const githubOidcProvider = iam.OpenIdConnectProvider.fromOpenIdConnectProviderArn(
      stack,
      'github-oidc-provider',
      `arn:aws:iam::${stack.account}:oidc-provider/token.actions.githubusercontent.com`
    );

    const oidcSubs = allowedBranches.map((branch) => `repo:${githubOrg}/${githubRepo}:ref:refs/heads/${branch}`);

    // Create IAM role for GitHub Actions
    const githubActionsRole = new iam.Role(stack, 'github-actions-role', {
      roleName: `${identifier}-github-actions`,
      assumedBy: new iam.OpenIdConnectPrincipal(githubOidcProvider, {
        StringEquals: {
          'token.actions.githubusercontent.com:aud': 'sts.amazonaws.com',
        },
        StringLike: {
          'token.actions.githubusercontent.com:sub': [...oidcSubs, `repo:${githubOrg}/${githubRepo}:pull_request`],
        },
      }),
      description: 'Role for GitHub Actions to deploy infrastructure and applications',
      maxSessionDuration: Duration.hours(2),
    });

    // Add permissions for CDK deployments
    githubActionsRole.addManagedPolicy(iam.ManagedPolicy.fromAwsManagedPolicyName('PowerUserAccess'));

    // Add specific permissions that PowerUserAccess doesn't include
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        effect: iam.Effect.ALLOW,
        actions: [
          'iam:CreateRole',
          'iam:DeleteRole',
          'iam:AttachRolePolicy',
          'iam:DetachRolePolicy',
          'iam:PutRolePolicy',
          'iam:DeleteRolePolicy',
          'iam:CreatePolicy',
          'iam:DeletePolicy',
          'iam:CreatePolicyVersion',
          'iam:DeletePolicyVersion',
          'iam:GetRole',
          'iam:GetRolePolicy',
          'iam:GetPolicy',
          'iam:GetPolicyVersion',
          'iam:ListAttachedRolePolicies',
          'iam:ListRoles',
          'iam:ListPolicies',
          'iam:ListPolicyVersions',
          'iam:PassRole',
          'iam:TagRole',
          'iam:UntagRole',
          'iam:CreateServiceLinkedRole',
          'iam:DeleteServiceLinkedRole',
        ],
        resources: ['*'],
      }),
    );

    // PowerUserAccess covers most services, but we need to add specific IAM permissions
    // that PowerUserAccess doesn't include for CDK deployments

    this.githubActionsRole = githubActionsRole;
    this.githubOidcProvider = githubOidcProvider;
  }
}
