# Tiny Headless CMS

Tiny Headless CMS is a small, database‑driven headless CMS built with plain PHP and MySQL/MariaDB. It gives you just enough UI to manage content (collections, singletons, assets) and a simple HTTP API you can consume directly from your frontend or backend.

It’s designed for developers who want:
- A lightweight alternative to large, hosted headless CMS platforms
- A schema you can understand at a glance and query directly
- No framework lock‑in, minimal dependencies, and easy self‑hosting

## Key features

- **Lightweight & framework‑free** – Plain PHP 7.4+ with a simple MySQL/MariaDB schema.
- **Collections & singletons** – Model lists (e.g. blog posts) and single documents (e.g. homepage, settings).
- **Assets library** – Upload and manage images and files; integrate directly into rich text via a custom Editor.js tool.
- **Internationalization (i18n)** – Localized fields stored in dedicated tables, with flexible locale shaping in the API.
- **Simple HTTP API** – Fetch collections and singletons via `api.php` with pagination, filtering, sorting, and locale options.
- **Direct DB access** – Schema is intentionally simple so your apps can query the database directly if preferred.
- **Docker‑ready** – Optional Docker setup for local development or deployment.

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.2 or higher
- Apache or nginx web server


## Getting started

### Option 1: Classic (Apache/nginx)

1. **Clone or upload** the repository to your server.
2. **Set document root** to the `/public` folder (only this should be web‑accessible).
3. **Copy `.env.example` → `.env`** and edit with your database credentials.
4. **Create database** and run the SQL schema in `/sql/schema.sql`.
5. **Ensure `/uploads` is writable** (`chmod 755 uploads`).
6. Visit `/` or `/index.php` to log in and manage content.
7. Access API via `/api.php` (see options below) or query the database directly from your application.

### Option 2: Docker

A Docker setup is included for local development.

- Make sure Docker and Docker Compose are installed.
- From the project root, start the stack:
  - `docker compose up -d`
- The web UI and API will be exposed according to the ports configured in `docker-compose.yml` (typically `http://localhost` or a mapped port).
- The MariaDB data and uploads are persisted under the `data/` and `uploads/` directories.

> Note: Adjust environment variables in `.env` and any Docker‑related config (such as `docker-compose.yml`) as needed for your environment.


## Security & access control

- Keep `.env` and `/app` outside the web root.
- If you cannot change document root, use the included `.htaccess` to block access to sensitive files.
- On first visit, create an admin user. Any additional users can be created via **Settings → Users**.
- If you forget your password, you can reset it via the database (passwords are stored using bcrypt).


## Content model

The CMS lets you manage three types of data:

1. **Collections** – Structured data similar to database tables. Each collection has a defined schema and contains multiple entries.
2. **Singletons** – Unique data entities that exist as a single instance, such as site settings or homepage content.
3. **Assets** – Media files like images, videos, and documents that can be uploaded and managed within the CMS.

### Collections & singletons

Collections and singletons are made up of fields. Fields can be of various types, including:

- String
- Text
- Rich Text (via Editor.js with custom image plugin)
- Boolean
- Integer
- Decimal
- Date
- Datetime
- Single image/asset
- Multiple images/assets

#### Collections

Collections have a unique ID assigned by the CMS. The fields of the collection are defined via the CMS. Each entry in the collection adheres to the defined schema. The schema can be changed at any time, and existing entries will adapt to the new schema.

Example collection: `Blog Posts`

- Fields:
  - Title (string)
  - Content (rich text)
  - Author (string)
  - Published Date (date)

#### Singletons

Singletons are used for content that should only exist once, for example:

- Content for a single page
- Translation strings for menus, footers, etc.
- Site-wide settings such as colors, logos, and metadata

### Assets

Assets are stored under `/uploads` and managed through the CMS interface. You can use assets as standalone files (e.g. downloads) or embed them into rich text content (see below).


## HTTP API

All read access to content is done via `public/api.php`. You can query singletons and collections, optionally with pagination, locales, filters, and sorting.

### Singletons

```text
api.php?singleton=book
api.php?singleton=book&locale=en
api.php?singleton=book&locale[0]=en&locale[1]=nl
```

### Collections

```text
api.php?collection=books
api.php?collection=books&locale=en&limit=10&offset=20
```

#### fields

Limit the fields returned:

```text
api.php?collection=news&fields=title,slug
```

#### extraLocales

Request additional locale values under an `extraLocales` key:

```text
api.php?collection=news&locale=nl&extraLocales[slug]=*
```

#### filter

Supports a single equals filter (in a specific locale):

```text
api.php?collection=news&filter[field]=slug&filter[locale]=nl&filter[value]=test
```

#### sort

Sort collections by id or a field value (in a specific locale). Defaults to newest first.

- By id descending:
  - `api.php?collection=news&sort[field]=id&sort[direction]=desc`
- By title ascending in `nl`:
  - `api.php?collection=news&locale=nl&sort[field]=title&sort[direction]=asc`

### Locale shaping

The shape of localized fields depends on how many locales you request:

- **Single locale** (e.g. `locale=nl`):
  - Translatable fields are **flattened** to simple values.
  - Example:

    ```json
    {
      "data": {
        "id": 1,
        "title": "A title",
        "summary": "A description..."
      }
    }
    ```

- **Multiple locales** (e.g. `locale[0]=nl&locale[1]=en`):
  - Translatable fields are returned as objects keyed by locale:

    ```json
    {
      "data": {
        "id": 1,
        "title": { "nl": "Een titel.", "en": "A title." }
      }
    }
    ```

Any `extraLocales[field]=...` options are exposed under an `extraLocales` object alongside the flattened or per-locale fields.


## Rich text & media

### Rich Text Image Asset Block

A custom Editor.js tool, `imageAsset`, lets you insert images already uploaded to the CMS asset library into rich text fields.

#### Usage

1. In a Rich Text field, click the Image icon in the Editor.js toolbox.
2. Click **Select Image** (or **Change Image**) to open the asset picker modal.
3. Search by filename (press Enter or click **Search**) and click an asset to insert.
4. Optionally fill **Alt** text (for accessibility) and a **Caption**.

#### Data shape

Each image block is stored as:

```json
{
  "type": "imageAsset",
  "data": {
    "assetId": 123,
    "url": "/uploads/path/to/file.jpg",
    "filename": "file.jpg",
    "alt": "Description",
    "caption": "Optional caption"
  }
}
```


## Database structure

An example of a `content_type` is:

- name: `news`
- is_singleton: `0`
- schema: `{"fields":[{"name":"title","type":"string", "is_translatable": true}, {"name":"date","type":"date"},{"name":"summary","type":"text","is_translatable": true}]}`

The order of the `fields` array in the schema determines the order in which they are displayed in the CMS or returned via the API.

The CMS will create the following tables for such a content type:

```sql
CREATE TABLE `news`
(
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `date` datetime NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `news_localized`
(
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `locale` varchar(255) NOT NULL,
    `title` varchar(255) NOT NULL,
    `summary` text NOT NULL,
    PRIMARY KEY (`id`,`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

This demonstrates that the translatable fields are stored in a separate table, with a foreign key to the main table.


## Extensibility
You can easily add your own field types by adding your own file to `/app/fields/` that extends the base `FieldType` class. See existing field classes for examples.

## License

Tiny Headless CMS is open-source software licensed under the [MIT license](./LICENSE).
