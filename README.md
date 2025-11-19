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
6. Visit `/admin.php` to log in and manage content.
7. Access API via `/index.php?endpoint=content` or query the database directly from your application.

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

index.php?singleton=book
index.php?singleton=book&locale=en
index.php?singleton=book&locale[0]=en&locale[1]=nl
index.php?collection=books
index.php?collection=books&locale=en&limit=10&offset=20
