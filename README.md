# Obsidian Vault REST API & n8n Community Node

![Package Banner](https://raw.githubusercontent.com/j-shelfwood/prj-obsidian-local-rest-api/refs/heads/main/banner.webp)
An API to interact with your Obsidian vault, plus an n8n community node for seamless automation workflows.

## Table of Contents

-   [Overview](#overview)
-   [n8n Community Node](#n8n-community-node)
    -   [Installation](#installation)
    -   [Credentials](#credentials)
    -   [Usage](#usage)
-   [API Usage](#api-usage)
-   [Local Development](#local-development)
-   [Contributing](#contributing)
-   [License](#license)

## Overview

This project provides:

-   A **Laravel-based** REST API to list, create, edit, and delete files/notes in your local Obsidian vault.
-   A **n8n Community Node** (`n8n-nodes-obsidian-vault-rest-api`) that uses the OpenAPI spec to expose these endpoints as n8n operations.

## n8n Community Node

### Installation

In your n8n project or server environment, install the node from npm:

```bash
npm install n8n-nodes-obsidian-vault-rest-api
```

Restart n8n and you will see a new node:

-   **Display Name**: Obsidian Vault REST API

### Credentials

After installation, add a credential in n8n:

1. Go to **Credentials** → **New Credential** → **Obsidian Vault REST API**.
2. Fill in:
    - **Host**: The base URL of your API (e.g. `http://localhost:8000`)
    - **Access Token**: Your JWT bearer token
3. Save.

### Usage

1. Drag the **Obsidian Vault REST API** node into your workflow.
2. Select a **Resource** (Files, Notes, Metadata).
3. Choose an **Operation** (e.g. List Notes, Create File).
4. Provide any required parameters (path, front_matter, content).
5. Execute the node to see live results.

## API Usage

### Base URL

```
https://{host}/api
```

Replace `{host}` with your environment or hostname.

### Authentication

All endpoints require a **Bearer** token in the `Authorization` header:

```
Authorization: Bearer <JWT_TOKEN>
```

### Endpoints

-   **GET** `/files` - List vault files
-   **POST** `/files` - Create a file or directory
-   **GET** `/files/{path}` - Download file content
-   **PUT** `/files/{path}` - Update file
-   **DELETE** `/files/{path}` - Delete file

-   **GET** `/notes` - List notes with front matter
-   **POST** `/notes` - Create a new note
-   **GET** `/notes/{path}` - Get a note
-   **PUT** `/notes/{path}` - Replace a note
-   **PATCH** `/notes/{path}` - Partially update a note
-   **DELETE** `/notes/{path}` - Delete a note
-   **DELETE** `/bulk/notes/delete` - Bulk delete notes
-   **PATCH** `/bulk/notes/update` - Bulk update notes

-   **GET** `/metadata/keys` - List all front matter keys
-   **GET** `/metadata/values/{key}` - List unique values for a key

Refer to the [OpenAPI spec](openapi.yaml) for full details.

## Local Development

```bash
git clone https://github.com/j-shelfwood/prj-obsidian-local-rest-api.git
cd prj-obsidian-local-rest-api
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan serve  # starts API server on http://localhost:8000
npm src/index.ts   # confirms n8n properties load
npm tsc            # compile TypeScript node
```

## Contributing

Contributions welcome! Feel free to open issues or PRs.

## License

MIT
