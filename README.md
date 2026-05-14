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

## Current schema

Core tables:

- `books`: one row per doujinshi, comic, or related book item
- `locations`: two-level storage locations using `parent_id`
- `shops`: known stores or marketplaces
- `book_sources`: per-book store availability, price, item URL, and last checked time

Classification tables:

- `tags`: general category tags
- `works`: source/original work tags
- `characters`: character tags, optionally tied to a work

Pivot tables:

- `book_tags`
- `book_works`
- `book_characters`

Important status values for `books.status`:

- `owned`: 已擁有 / Excel `〇`
- `blacklisted`: 黑名單、不要買 / Excel `✖`
- `ordered`: 已訂購，等待到貨 / Excel `✓`
- `wishlist`: 願望清單

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
php spark migrate
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
