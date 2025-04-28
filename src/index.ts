import {
    INodeType,
    INodeTypeDescription,
    NodeConnectionType,
} from 'n8n-workflow';
import { N8NPropertiesBuilder, N8NPropertiesBuilderConfig } from '@devlikeapro/n8n-openapi-node';
import * as openApiSpec from './openapi.json';

const builderConfig: N8NPropertiesBuilderConfig = {};
const parser = new N8NPropertiesBuilder(openApiSpec, builderConfig);
const properties = parser.build();

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
        inputs: ['main'],
        outputs: ['main'],
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
