# App API

Private JSON endpoints for a future Android client.

These endpoints expose data already stored in Doujin Shelf. They do not call the Circle.ms API directly and do not expose Circle.ms OAuth credentials.

## Authentication

Send one of the following headers:

```http
X-App-Passcode: your-passcode
```

or:

```http
Authorization: Bearer your-passcode
```

The server verifies the passcode against `app.apiKeyHash` when configured. If `app.apiKeyHash` is empty, it falls back to `admin.passwordHash`.

## Endpoints

### C108 Summary

```http
GET /api/app/c108/summary
```

Returns summary counts, map options, and unread notices.

### C108 Maps

```http
GET /api/app/c108/maps
```

Returns day/map options and counts.

### C108 Map

```http
GET /api/app/c108/map?day=1&map=E123&relation=known&priority=
```

Query parameters:

- `day`: `1` or `2`. Default: `1`.
- `map`: `E123`, `E7`, `S12`, or `W12`.
- `relation`: `known`, `tracked`, `unknown`, or `all`. Default: `known`.
- `priority`: `normal`, `high`, or `must`.
- `q`: optional search text.

Returns the selected map image, circle rows, local tracking fields, owned book samples, and marker positions.

### C108 Notices

```http
GET /api/app/c108/notices?limit=50
```

Returns recent C108 update notices. `limit` is capped at 100.
