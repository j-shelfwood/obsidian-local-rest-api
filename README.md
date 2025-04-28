# Obsidian Vault REST API

A Laravel-based REST API to interact with your local Obsidian vault. Provides endpoints to list, create, edit, and delete files and notes, as well as access and manage front matter metadata.

## Table of Contents

-   [Overview](#overview)
-   [API Usage](#api-usage)
-   [Local Development](#local-development)
-   [n8n Community Node](#n8n-community-node)
-   [Contributing](#contributing)
-   [License](#license)

## Overview

This project provides a REST API for managing your Obsidian vault using Laravel. It enables programmatic access to files and notes, supporting automation and integration scenarios.

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
cp .env.example .env
php artisan key:generate
php artisan serve  # starts API server on http://localhost:8000
```

## n8n Community Node

A dedicated n8n node is available for integrating this API into your n8n workflows. Find installation and usage instructions in the [n8n-nodes-obsidian-vault-rest-api repository](https://github.com/j-shelfwood/n8n-nodes-obsidian-vault-rest-api).

## Contributing

Contributions welcome. Open issues or PRs as needed.

## License

MIT
