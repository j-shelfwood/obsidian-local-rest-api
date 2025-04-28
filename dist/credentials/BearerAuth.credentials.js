"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.BearerAuth = void 0;
class BearerAuth {
    constructor() {
        this.name = 'bearerAuth';
        this.displayName = 'Bearer Token';
        this.properties = [
            {
                displayName: 'Host',
                name: 'host',
                type: 'string',
                default: 'http://localhost:8000',
            },
            {
                displayName: 'Access Token',
                name: 'token',
                type: 'string',
                typeOptions: { password: true },
                default: '',
            },
        ];
        this.authenticate = {
            type: 'generic',
            properties: {
                headers: {
                    Authorization: 'Bearer {{$credentials.token}}',
                },
            },
        };
    }
}
exports.BearerAuth = BearerAuth;
