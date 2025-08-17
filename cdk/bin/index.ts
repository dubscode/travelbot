#!/usr/bin/env node
import 'source-map-support/register';
import * as cdk from 'aws-cdk-lib';
import { AppStack } from '../stacks/app';
import { GitHubOidcStack } from '../stacks/github-oidc';

const app = new cdk.App();

// Common environment configuration
const environment = {
  account: process.env.CDK_DEFAULT_ACCOUNT,
  region: process.env.CDK_DEFAULT_REGION || 'us-west-2',
};

// Main application stack
const appStack = new AppStack(app, 'TravelbotStack', {
  env: environment,
  description: 'TravelBot application infrastructure including ECS, ALB, and CloudFront',
});

// GitHub OIDC stack for CI/CD
const githubOidcStack = new GitHubOidcStack(app, 'TravelbotGitHubOidcStack', {
  env: environment,
  description: 'GitHub Actions OIDC authentication for TravelBot CI/CD',
  allowedBranches: ['main'],
  githubOrg: 'dubscode',
  githubRepo: 'travelbot',
});

// Apply global tags to all resources
cdk.Tags.of(app).add('Project', 'TravelBot');
cdk.Tags.of(app).add('ManagedBy', 'CDK');
cdk.Tags.of(app).add('Repository', 'dubscode/travelbot');
cdk.Tags.of(app).add('Owner', 'Engineering');
cdk.Tags.of(app).add('CostCenter', 'RnD');

// Apply environment-specific tags
const envTags = {
  'Environment': 'Production',
  'Stage': 'Prod',
  'Backup': 'Required',
  'Monitoring': 'Enabled',
};

Object.entries(envTags).forEach(([key, value]) => {
  cdk.Tags.of(app).add(key, value);
});

// Output key information
console.log('📦 CDK App Configuration:');
console.log(`   Environment: ${environment.region} (${environment.account})`);
console.log(`   Stacks: ${appStack.stackName}, ${githubOidcStack.stackName}`);
console.log(`   GitHub Repo: ${githubOidcStack.node.tryGetContext('githubRepo') || 'dubscode/travelbot'}`);