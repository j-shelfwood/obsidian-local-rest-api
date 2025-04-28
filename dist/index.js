"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __importStar = (this && this.__importStar) || (function () {
    var ownKeys = function(o) {
        ownKeys = Object.getOwnPropertyNames || function (o) {
            var ar = [];
            for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) ar[ar.length] = k;
            return ar;
        };
        return ownKeys(o);
    };
    return function (mod) {
        if (mod && mod.__esModule) return mod;
        var result = {};
        if (mod != null) for (var k = ownKeys(mod), i = 0; i < k.length; i++) if (k[i] !== "default") __createBinding(result, mod, k[i]);
        __setModuleDefault(result, mod);
        return result;
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
exports.ObsidianVaultRestApi = void 0;
const n8n_openapi_node_1 = require("@devlikeapro/n8n-openapi-node");
const openApiSpec = __importStar(require("./openapi.json"));
const builderConfig = {};
const parser = new n8n_openapi_node_1.N8NPropertiesBuilder(openApiSpec, builderConfig);
const properties = parser.build();
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
}
exports.ObsidianVaultRestApi = ObsidianVaultRestApi;
