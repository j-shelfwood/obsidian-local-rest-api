# Obsidian local REST API

This is a simple laravel project to create a local REST API for querying and mutating your Obsidian vault.

## Installation

```bash
git clone https://github.com/j-shelfwood/obsidian-local-rest-api.git
cd obsidian-local-rest-api
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Don't forget to set the `OBSIDIAN_VAULT_PATH` environment variable to the path of your Obsidian vault.

## Usage

Make sure your `.env` has `OBSIDIAN_VAULT_PATH` set to your vault directory, for example:

```bash
export OBSIDIAN_VAULT_PATH="/path/to/your/vault"
```

Start the local API server:

```bash
php artisan serve
```

Run the test suite:

```bash
php artisan test
```

## API Endpoints

| Method | Endpoint                       | Description                                                            |
| ------ | ------------------------------ | ---------------------------------------------------------------------- |
| GET    | /api/files/tree                | Get hierarchical tree of vault files and directories                   |
| GET    | /api/files                     | List all files in the vault                                            |
| GET    | /api/files/raw                 | Retrieve raw content of a file (provide `path` query param)            |
| GET    | /api/notes                     | List all markdown notes (.md) with parsed front matter                 |
| GET    | /api/notes/{path}              | Retrieve a note's content and front matter                             |
| GET    | /api/notes/search              | Search notes by front matter fields (`field`, `value` query params)    |
| POST   | /api/notes                     | Create a new note with content and front matter                        |
| PUT    | /api/notes/{path}              | Replace a note's content and front matter                              |
| PATCH  | /api/notes/{path}              | Update specific parts of a note (content or front matter)              |
| DELETE | /api/notes/{path}              | Delete a single note                                                   |
| POST   | /api/notes/bulk-delete         | Delete multiple notes (provide `paths` array)                          |
| POST   | /api/notes/bulk-update         | Bulk update notes (provide array of `path`, `content`, `front_matter`) |
| GET    | /api/front-matter/keys         | List all front matter keys across notes                                |
| GET    | /api/front-matter/values/{key} | List unique values for a front matter key                              |
