"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.ObsidianVaultRestApi = void 0;
var fs_1 = require("fs");
var path_1 = require("path");
var yaml_1 = require("yaml");
var n8n_openapi_node_1 = require("@devlikeapro/n8n-openapi-node");
// Load and parse OpenAPI spec
var specPath = path_1.default.resolve(__dirname, '../openapi.yaml');
var specContent = fs_1.default.readFileSync(specPath, 'utf8');
var openApiSpec = yaml_1.default.parse(specContent);
// Optional builder configuration
var builderConfig = {
// no custom config needed for properties builder
};
// Build n8n properties from OpenAPI spec
var parser = new n8n_openapi_node_1.N8NPropertiesBuilder(openApiSpec, builderConfig);
var properties = parser.build();
console.log("Loaded ".concat(properties.length, " operations"));
// Export the node class
var ObsidianVaultRestApi = /** @class */ (function () {
    function ObsidianVaultRestApi() {
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
            properties: properties,
        };
    }
    return ObsidianVaultRestApi;
}());
exports.ObsidianVaultRestApi = ObsidianVaultRestApi;
