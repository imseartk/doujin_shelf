# doujin_shelf
寫給自己用的收藏書櫃，記錄藏書位置和資訊。

This is a personal doujinshi / manga shelf management system.

## Tech stack

- CodeIgniter 4
- PHP 8.3
- MariaDB 10.11
- Nginx
- No foreign keys in database schema
- Use `utf8mb4_unicode_ci`

## Planned tables

- `books`
- `book_sources`
- `shops`
- `locations`
- `story_tags`
- `series_tags`
- `character_tags`
- `book_story_tags`
- `book_series_tags`
- `book_character_tags`

## Coding preference

- Keep implementation simple
- Avoid overengineering
- Prioritize CRUD and search
- Do not add crawler features yet

## First setup on AWS

From the project root on the server:

```bash
composer install --no-dev --optimize-autoloader
cp env .env
```

Then edit `.env` for each deployment path:

```env
CI_ENVIRONMENT = development
app.baseURL = 'https://your-dev-url/'
database.default.hostname = localhost
database.default.database = your_database
database.default.username = your_user
database.default.password = your_password
database.default.DBDriver = MySQLi
database.default.charset = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci
```

Point Nginx document root to the `public/` directory.
