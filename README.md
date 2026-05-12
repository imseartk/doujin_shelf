# doujin_shelf
寫給自己用的收藏書櫃，紀錄藏書位置和資訊
This is a personal doujinshi / manga shelf management system.

Tech stack:
- CodeIgniter 4
- PHP 8.3
- MariaDB 10.11
- Nginx
- No foreign keys in database schema
- Use utf8mb4_unicode_ci
- Tables: books, book_sources, shops, locations, story_tags, series_tags, character_tags, book_story_tags, book_series_tags, book_character_tags

Coding preference:
- Keep implementation simple
- Avoid overengineering
- Prioritize CRUD and search
- Do not add crawler features yet
