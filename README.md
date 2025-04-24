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
