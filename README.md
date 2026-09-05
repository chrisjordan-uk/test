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

## Deploying to shared hosting (cPanel)

This assumes a cPanel host with **"Setup Node.js App"** (Phusion Passenger /
CloudLinux NodeJS Selector) and **MySQL Databases** — most Bulgarian hosts
(SuperHosting, Hosting.bg, etc.) have both on their business plans. Layout:
the main domain serves the static frontend, a subdomain runs the Node API.

### 1. Check Node support & pick a layout

In cPanel, look for an icon called **"Setup Node.js App"**. If it's not
there, this plan can't run the backend — you'd need a VPS or a platform like
Railway/Render for the API instead (the frontend can still go on the same
shared hosting as static files).

Decide on:
- `yourdomain.com` → frontend (static files)
- `api.yourdomain.com` → backend (Node app) — create this subdomain first
  under **Domains → Create A New Domain**

### 2. Create the database

**MySQL Databases** in cPanel:
1. Create a database, e.g. `vinted_resell` (cPanel will prefix it, e.g.
   `cpaneluser_vinted_resell`).
2. Create a database user + password (also prefixed, e.g.
   `cpaneluser_vinted`).
3. Add that user to the database with **All Privileges**.

Load the schema via **phpMyAdmin**: open the new database → **Import** tab →
choose `backend/db/schema.sql` from this repo → Go.

### 3. Upload and configure the backend

1. Upload the `backend/` folder somewhere **outside** `public_html` (e.g.
   `/home/cpaneluser/vinted-api`) — via File Manager (zip it, upload, extract)
   or `git clone` if you have SSH/Terminal access. Skip `node_modules` and
   `.env` — you'll create those next.
2. **Setup Node.js App** → Create Application:
   - Node.js version: 18.x or newer
   - Application mode: Production
   - Application root: the folder from step 1 (e.g. `vinted-api`)
   - Application URL: `api.yourdomain.com`
   - Application startup file: `server.js`
3. In the same screen, add **Environment Variables** (this replaces the
   `.env` file — no need to upload one):
   - `DB_HOST=localhost`
   - `DB_PORT=3306`
   - `DB_USER=cpaneluser_vinted` (your real prefixed DB user)
   - `DB_PASSWORD=...`
   - `DB_NAME=cpaneluser_vinted_resell` (your real prefixed DB name)
   - `JWT_SECRET=` a long random string (generate one with
     `node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"`)
   - `JWT_EXPIRES_IN=8h`
   - `CORS_ORIGIN=https://yourdomain.com`
   - `SEED_ADMIN_USERNAME`, `SEED_ADMIN_PASSWORD`, `SEED_ADMIN_EMAIL` — only
     needed for the one-time seed step below.
4. Click **Run NPM Install** in the same screen (installs `package.json`
   dependencies inside the app's virtual environment).
5. Run the seed script once, using the "Enter to virtual environment"
   command cPanel shows you (via **Terminal** in cPanel, or SSH), e.g.:
   ```bash
   source /home/cpaneluser/nodevenv/vinted-api/18/bin/activate
   cd /home/cpaneluser/vinted-api
   npm run seed
   ```
   This prints the admin login it created.
6. Click **Restart** on the Node app. Test it:
   `https://api.yourdomain.com/api/health` should return `{"ok":true}`.

### 4. Build and upload the frontend

Build it **locally** (on your own computer), pointing it at the live API:

```bash
cd frontend
echo "VITE_API_URL=https://api.yourdomain.com/api" > .env
npm install
npm run build
```

Upload everything **inside** `frontend/dist/` (not the folder itself) to
`public_html` for `yourdomain.com`, via File Manager or FTP. The included
`.htaccess` (copied into `dist/` automatically) makes page refreshes on
routes like `/products` work correctly.

### 5. Enable HTTPS

Under **SSL/TLS Status** or **AutoSSL**, issue a free Let's Encrypt
certificate for both `yourdomain.com` and `api.yourdomain.com`. Once both
have valid HTTPS, everything above (CORS_ORIGIN, VITE_API_URL) already uses
`https://`, so no further changes are needed.

Log in, change the seeded admin password from the app, and you're live.

## Notes on the data model

- Every product automatically gets a matching "purchase" entry in the
  profit ledger when created, and a "sale" entry when its status is set
  to *Sold* with a sold price — so the Profit page always reflects what's
  in Products/Inventory without double entry.
- Manual ledger entries (stock bought in bulk, shipping/packaging
  expenses, etc.) are not tied to a specific product and can be added,
  edited or deleted freely from the Profit page.
