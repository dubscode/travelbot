import * as cdk from 'aws-cdk-lib';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import { Construct } from 'constructs';

export interface DatabaseSecretConfig {
  secretName: string;
  description: string;
  placeholderUrl: string;
}

export interface AppSecretConfig {
  secretName: string;
  description: string;
  passwordLength?: number;
  excludeCharacters?: string;
}

export interface CustomSecretConfig {
  secretName: string;
  description: string;
  secretValue: any;
}

export interface ApplicationSecretsProps {
  /**
   * Application name prefix for secret naming
   */
  applicationName: string;
  
  /**
   * Database secret configuration
   */
  databaseSecret?: DatabaseSecretConfig;
  
  /**
   * Application secret configuration
   */
  appSecret?: AppSecretConfig;
  
  /**
   * Additional custom secrets
   */
  customSecrets?: CustomSecretConfig[];
}

export class ApplicationSecrets extends Construct {
  public readonly dbSecret?: secretsmanager.Secret;
  public readonly appSecret?: secretsmanager.Secret;
  public readonly customSecrets: { [name: string]: secretsmanager.Secret } = {};

  constructor(scope: Construct, id: string, props: ApplicationSecretsProps) {
    super(scope, id);

    const { applicationName, databaseSecret, appSecret, customSecrets = [] } = props;

    // Database Secret
    if (databaseSecret) {
      this.dbSecret = new secretsmanager.Secret(this, 'DatabaseSecret', {
        secretName: databaseSecret.secretName,
        description: databaseSecret.description,
        secretStringValue: cdk.SecretValue.unsafePlainText(JSON.stringify({
          database_url: databaseSecret.placeholderUrl
        })),
      });

      cdk.Tags.of(this.dbSecret).add('DataClassification', 'Sensitive');
      cdk.Tags.of(this.dbSecret).add('Purpose', 'Database Connection');
    }

    // Application Secret
    if (appSecret) {
      this.appSecret = new secretsmanager.Secret(this, 'AppSecret', {
        secretName: appSecret.secretName,
        description: appSecret.description,
        generateSecretString: {
          secretStringTemplate: JSON.stringify({}),
          generateStringKey: 'secret',
          passwordLength: appSecret.passwordLength || 32,
          excludeCharacters: appSecret.excludeCharacters || ' "@/\\\'',
        },
      });

      cdk.Tags.of(this.appSecret).add('DataClassification', 'Sensitive');
      cdk.Tags.of(this.appSecret).add('Purpose', 'Application Security');
    }

    // Custom Secrets
    customSecrets.forEach((secretConfig, index) => {
      const secret = new secretsmanager.Secret(this, `CustomSecret${index}`, {
        secretName: secretConfig.secretName,
        description: secretConfig.description,
        secretStringValue: cdk.SecretValue.unsafePlainText(JSON.stringify(secretConfig.secretValue)),
      });

      cdk.Tags.of(secret).add('DataClassification', 'Sensitive');
      cdk.Tags.of(secret).add('Purpose', `Custom Secret: ${secretConfig.secretName}`);

      // Store with a clean name based on the secret name
      const cleanName = secretConfig.secretName.split('/').pop() || `secret${index}`;
      this.customSecrets[cleanName] = secret;
    });
  }

  /**
   * Get all secrets for granting access permissions
   */
  public getAllSecrets(): secretsmanager.Secret[] {
    const secrets: secretsmanager.Secret[] = [];
    
    if (this.dbSecret) secrets.push(this.dbSecret);
    if (this.appSecret) secrets.push(this.appSecret);
    
    Object.values(this.customSecrets).forEach(secret => secrets.push(secret));
    
    return secrets;
  }

  /**
   * Get all secret ARNs for policy statements
   */
  public getAllSecretArns(): string[] {
    return this.getAllSecrets().map(secret => secret.secretArn);
  }
}