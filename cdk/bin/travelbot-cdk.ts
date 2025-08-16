#!/usr/bin/env node
import 'source-map-support/register';
import * as cdk from 'aws-cdk-lib';
import { TravelbotStack } from '../lib/travelbot-stack';

const app = new cdk.App();

new TravelbotStack(app, 'TravelbotStack', {
  // CDK will automatically look up account and region from AWS credentials
});