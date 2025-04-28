import {
    ICredentialType,
    IAuthenticateGeneric,
    INodeProperties,
} from 'n8n-workflow';

export class BearerAuth implements ICredentialType {
    name = 'bearerAuth';
    displayName = 'Bearer Token';
    properties: INodeProperties[] = [
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

    authenticate: IAuthenticateGeneric = {
        type: 'generic',
        properties: {
            headers: {
                Authorization: 'Bearer {{$credentials.token}}',
            },
        },
    };
}
