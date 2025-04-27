"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.ObsidianVaultRestApi = void 0;
const fs_1 = __importDefault(require("fs"));
const path_1 = __importDefault(require("path"));
const yaml_1 = __importDefault(require("yaml"));
const n8n_openapi_node_1 = require("@devlikeapro/n8n-openapi-node");
// Load and parse OpenAPI spec
const specPath = path_1.default.resolve(__dirname, '../openapi.yaml');
const specContent = fs_1.default.readFileSync(specPath, 'utf8');
const openApiSpec = yaml_1.default.parse(specContent);
// Optional builder configuration
const builderConfig = {
// no custom config needed for properties builder
};
// Build n8n properties from OpenAPI spec
const parser = new n8n_openapi_node_1.N8NPropertiesBuilder(openApiSpec, builderConfig);
const properties = parser.build();
console.log(`Loaded ${properties.length} operations`);
// Export the node class
class ObsidianVaultRestApi {
    constructor() {
        this.description = {
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
            inputs: ["main" /* NodeConnectionType.Main */],
            outputs: ["main" /* NodeConnectionType.Main */],
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
}
exports.ObsidianVaultRestApi = ObsidianVaultRestApi;
