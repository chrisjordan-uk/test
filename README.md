# Vinted Resell Manager

A web-based business management system for running a Vinted resale
operation: role-based login, an inventory overview, product management with
status tracking, and a profit ledger that calculates monthly/yearly profit
automatically.

Stack: **React + Vite + Tailwind CSS** (frontend), **Node.js + Express**
(backend API), **MySQL** (database), **JWT** authentication.

## Features

1. **Role-based login** — username/password sign-in. Every feature
   (Home, Inventory, Products, Profit, Users) has a per-role permission
   level (`none` / `view` / `manage`), so what a user can see and do
   depends on their role.
2. **Home** — an overview dashboard: total products, inventory value,
   profit this month/this year, a products-by-status chart, top brands,
   and recently updated products.
3. **Inventory** — a read-only, filterable/searchable table of every
   product (status, brand, search).
4. **Products** — add products and manage them: change status
   (Available, Listed, To Ship, Shipped, Sold, Returned, Rejected), and
   track brand, product number (SKU, auto-generated if left blank),
   size, condition, bought price, sold price, buyer, dates and notes.
5. **Profit** — a ledger of stock purchases, sales and expenses.
   Purchases and sales are added to it automatically whenever a product
   is created or marked sold; you can also add manual entries (e.g. a
   bulk stock purchase or a shipping expense). Shows monthly/yearly
   revenue, cost and net profit with a trend chart.

Default roles seeded out of the box:

| Role    | Dashboard | Inventory | Products | Profit  | Users   |
|---------|-----------|-----------|----------|---------|---------|
| Admin   | manage    | manage    | manage   | manage  | manage  |
| Manager | view      | manage    | manage   | manage  | none    |
| Staff   | view      | view      | manage   | none    | none    |

Roles and their permissions can be edited from the **Users & Roles**
page (Admin only), and custom roles can be added via the API.

## Project structure

```
backend/    Express API + MySQL access (routes, middleware, db schema/seed)
frontend/   React + Vite + Tailwind single-page app
```

## Getting started

### 1. Database

Create a database and load the schema:

```bash
mysql -u root -p -e "CREATE DATABASE vinted_resell CHARACTER SET utf8mb4;"
mysql -u root -p vinted_resell < backend/db/schema.sql
```

### 2. Backend API

`.env.example` already has everything filled in — copy it to `.env` and
change `DB_USER` / `DB_PASSWORD` to your MySQL account, nothing else needs
to be touched.

```bash
cd backend
cp .env.example .env    # then edit DB_USER and DB_PASSWORD
npm install
npm run seed             # creates default roles + the first admin user
npm run dev               # starts the API on http://localhost:4000
```

The seed script prints the admin username/password it created (from
`SEED_ADMIN_USERNAME` / `SEED_ADMIN_PASSWORD` in `.env`) — log in and change
it right away from the app once you're in.

### 3. Frontend

```bash
cd frontend
cp .env.example .env    # only needed if the API isn't proxied at /api
npm install
npm run dev               # starts the app on http://localhost:5173
```

In development, `npm run dev` proxies `/api/*` requests to
`http://localhost:4000` (see `frontend/vite.config.js`), so both servers
just need to be running side by side.

### 4. Production build

```bash
cd frontend && npm run build   # outputs static files to frontend/dist
cd backend && npm start         # run the API behind your reverse proxy of choice
```

Serve `frontend/dist` as static files (e.g. via Nginx) and point it at the
backend API's `/api` path.

## Environment variables

See `backend/.env.example` and `frontend/.env.example` for the full list.
Key ones:

- `JWT_SECRET` — change this to a long random string before going live.
- `SEED_ADMIN_USERNAME` / `SEED_ADMIN_PASSWORD` — used once by `npm run seed`
  to create the first Admin account.
- `CORS_ORIGIN` — set to your frontend's origin in production.

## Notes on the data model

- Every product automatically gets a matching "purchase" entry in the
  profit ledger when created, and a "sale" entry when its status is set
  to *Sold* with a sold price — so the Profit page always reflects what's
  in Products/Inventory without double entry.
- Manual ledger entries (stock bought in bulk, shipping/packaging
  expenses, etc.) are not tied to a specific product and can be added,
  edited or deleted freely from the Profit page.
