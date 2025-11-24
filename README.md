# Tiny Headless CMS

## Features

- Easy installation and minimal dependencies.
- Easy-to-use interface for managing collections, singletons, and assets.
- Internationalization (i18n) support for multiple languages. Collections and singletons can have localized fields.
- Simple database schema, suitable for direct consumption by (front-end) applications.
- Properties are saved in a key-value like format for flexibility and simplicity.

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
        "title": "Inzicht in energie.",
        "summary": "CEMM biedt krachtige functies..."
      }
    }
    ```
- **Multiple locales** (e.g. `locale[0]=nl&locale[1]=en`):
  - Translatable fields are returned as objects keyed by locale:
    ```json
    {
      "data": {
        "id": 1,
        "title": { "nl": "Inzicht in energie.", "en": "Insight into energy." }
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
