# Tiny Headless CMS

A simple and lightweight headless CMS built with PHP and MySQL/MariaDB. It provides an easy way to manage collections, singletons, and assets, along with internationalization (i18n) support.

## Features

- Easy installation and minimal dependencies.
- Easy-to-use interface for managing collections, singletons, and assets.
- Internationalization (i18n) support for multiple languages. Collections and singletons can have localized fields.
- Simple database schema, suitable for direct consumption by (front-end) applications.

1. **Collections**: Structured data similar to database tables. Each collection has a defined schema and contains multiple entries.
2. **Singletons**: Unique data entities that exist as a single instance, such as site settings or homepage content.
3. **Assets**: Media files like images, videos, and documents that can be uploaded and managed within the CMS.

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.2 or higher
- Apache or nginx web server

## Installation (Apache/nginx)

1. **Clone or upload** the repository to your server.
2. **Set document root** to the `/public` folder (only this should be web‑accessible).
3. **Copy `.env.example` → `.env`** and edit with your database credentials.
4. **Create database** and run the SQL schema in `/sql/schema.sql`.
5. **Ensure `/uploads` is writable** (`chmod 755 uploads`).
6. Visit `/` or `/index.php` to log in and manage content.
7. Access API via `/api.php` (see options below) or query the database directly from your application.

### Security

- Keep `.env` and `/app` outside the web root.
- If you cannot change document root, use the included `.htaccess` to block access to sensitive files.
- When visiting the CMS for the first time, create a new admin user. Any additional users can be created via Settings → Users. If you forget your password, you can reset it via the database (bcrypt).


## Installation (Docker)
TODO


## Data types

The CMS lets you manage three types of data:

1. **Collections**: Structured data similar to database tables. Each collection has a defined schema and contains multiple entries.
2. **Singletons**: Unique data entities that exist as a single instance, such as site settings or homepage content.
3. **Assets**: Media files like images, videos, and documents that can be uploaded and managed within the CMS.

### Collections & Singletons

Collections and singletons are made up of fields. Fields can be of various types, including:
- String
- Text
- Rich Text
- Number
- Boolean
- Date
- Select (dropdown)
- Multi-select

#### Collections

Have a unique ID assigned by the CMS. The fields of the collection are defined via the CMS. Each entry in the collection adheres to the defined schema. The schema can be changed at any time, and existing entries will adapt to the new schema.

Example Collection: "Blog Posts"

- Fields:
    - Title (string)
    - Content (rich text)
    - Author (string)
    - Published Date (date)

#### Singletons

Use cases for singletons include:

- Content for a single page
- Translation strings for menus, footers, etc.
- Site-wide settings such as colors, logos and metadata

### Assets


## HTTP API

api.php?singleton=book
api.php?singleton=book&locale=en
api.php?singleton=book&locale[0]=en&locale[1]=nl
api.php?collection=books
api.php?collection=books&locale=en&limit=10&offset=20

### fields
api.php?collection=news&fields=title,slug

### extraLocales
api.php?collection=news&locale=nl&extraLocales[slug]=*

### filter
Supports a single equals filter (in specific locale)
api.php?collection=news&filter[field]=slug&filter[locale]=nl&filter[value]=test

### sort
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


## Rich Text Image Asset Block
A new custom Editor.js tool `imageAsset` lets you insert images already uploaded to the CMS asset library.

### Usage
1. In a Rich Text field, click the Image icon in the Editor.js toolbox.
2. Click Select Image (or Change Image) to open the asset picker modal.
3. Search by filename (press Enter or click Search) and click an asset to insert.
4. Optionally fill Alt text (for accessibility) and Caption.

### Data Shape
Each image block is stored as:
```
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

An example of a content_type is:
- name: news
- is_singleton: 0
- schema: `{"fields":[{"name":"title","type":"string", "is_translatable": true}, {"name":"date","type":"date"},{"name":"summary","type":"text","is_translatable": true}]}`.

The order of the fields array in the schema determines the order in which they are displayed in the CMS or returned via the API.

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
What this example demonstrates is that the translatable fields are stored in a separate table, with a foreign key to the main table.

