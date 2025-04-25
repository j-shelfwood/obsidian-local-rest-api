import fs from 'fs';
import path from 'path';
import yaml from 'yaml';
import {
  INodeType,
  INodeTypeDescription,
  NodeConnectionType,
} from 'n8n-workflow';
import { N8NPropertiesBuilder, N8NPropertiesBuilderConfig } from '@devlikeapro/n8n-openapi-node';

// Load and parse OpenAPI spec
const specPath = path.resolve(__dirname, '../openapi.yaml');
const specContent = fs.readFileSync(specPath, 'utf8');
const openApiSpec = yaml.parse(specContent);

// Optional builder configuration
const builderConfig: N8NPropertiesBuilderConfig = {
  // no custom config needed for properties builder
};

// Build n8n properties from OpenAPI spec
const parser = new N8NPropertiesBuilder(openApiSpec, builderConfig);
const properties = parser.build();
console.log(`Loaded ${properties.length} operations`);

// Export the node class
export class ObsidianVaultRestApi implements INodeType {
  description: INodeTypeDescription = {
    displayName: 'Obsidian Vault REST API',
    name: 'obsidianVaultRestApi',
    icon: 'file:logo.svg',
    group: ['transform'],
    version: 1,
    subtitle: '={{$parameter["operation"] + ": " + $parameter["resource"]}}',
    description: 'Interact with Obsidian Vault via REST API',
    defaults: {
      name: 'Obsidian Vault',
    },
    inputs: [NodeConnectionType.Main],
    outputs: [NodeConnectionType.Main],
    credentials: [
      {
        name: 'bearerAuth',
        required: true,
      },
    ],
    requestDefaults: {
      baseURL: '={{$credentials.host}}/api',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    },
    properties,
  };
}
