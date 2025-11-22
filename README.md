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

### API / Rendering
Currently blocks remain raw Editor.js output. You can render `imageAsset` blocks server-side as:
```
<figure class="cms-image"><img src="/uploads/..." alt="..."> <figcaption>...</figcaption></figure>
```
If an asset is missing, render a placeholder figure.

### Edge Cases
- No assets: picker shows "No assets found".
- Large list: pagination via Load More button (40 items per batch).
- Deleted asset: existing blocks keep URL; you may add validation later.

### Extensibility
Potential enhancements:
- Inline resizing / alignment controls.
- Image optimization / responsive srcset generation.
- Cropping / variants.
- Drag-drop reordering in picker.

### Development Notes
- JSON endpoint: `admin.php?page=assets-json` with `q`, `limit`, `offset` params.
- Requires authentication (same session as admin panel).
- JS Tool file: `public/assets/editorjs-image-tool.js`.
- Styles appended in `public/assets/style.css`.
