# Oxygen Supply Management Dashboard

Web-based dashboard starter for Afghan Oxygen Supply using:

- PHP (core, modular structure)
- MySQL
- HTML + Tailwind CSS + JavaScript

## Modules

- Dashboard
- Customers
- Services
- Inventory
- Invoices
- Payments / Ledger
- Reports
- Settings

## Quick Start

1. Create a MySQL database (example: `afghan_oxygen`)
2. Import `database/schema.sql`
3. Update DB credentials in `config/database.php`
4. Serve with Apache/XAMPP and open `public/index.php`

## Structure

- `config/` database and app config
- `core/` reusable helpers and layout
- `modules/` feature pages by domain
- `public/` app entrypoint and assets
- `database/` SQL schema and seed data
