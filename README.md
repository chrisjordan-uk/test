# Vinted Resell Manager

A web-based business management system for running a Vinted resale
operation: role-based login, an inventory overview, product management with
status tracking, and a profit ledger that calculates monthly/yearly profit
automatically.

Two versions of the same app live in this repo:

| | `php-app/` | `backend/` + `frontend/` |
|---|---|---|
| Stack | Plain PHP + MySQL | Node.js/Express API + React SPA |
| Setup | Upload files, edit one config, done | Node.js hosting, build step, `npm install` |
| Best for | **Ordinary shared hosting** (Hostinger, cPanel, etc.) | A VPS, or a platform that runs Node.js |

**If you're deploying to shared hosting, use `php-app/` — see
[`php-app/README.md`](php-app/README.md) for the 5-step setup.** It has no
build step and no dependencies: upload the folder, point it at a MySQL
database, visit one URL once to create the admin account, and it works.

The Node/React version is documented further down, for anyone who does have
a VPS or a Node-capable host and wants the SPA version instead.

## Features (both versions)

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

Roles and their permissions can be edited from the **Users & Roles** page by
anyone whose role has `manage` on `users`.

## Notes on the data model (both versions)

- Every product automatically gets a matching "purchase" entry in the
  profit ledger when created, and a "sale" entry when its status is set
  to *Sold* with a sold price — so the Profit page always reflects what's
  in Products/Inventory without double entry.
- Manual ledger entries (stock bought in bulk, shipping/packaging
  expenses, etc.) are not tied to a specific product and can be added,
  edited or deleted freely from the Profit page.

---

## Advanced: the Node.js / React version

Stack: **React + Vite + Tailwind CSS** (frontend), **Node.js + Express**
(backend API), **MySQL** (database), **JWT** authentication. Use this if you
have a VPS, or a host that can run a persistent Node.js process (Railway,
Render, a cPanel/Hostinger plan with "Node.js App" support, etc.) and you
want the single-page-app version.

```
backend/    Express API + MySQL access (routes, middleware, db schema/seed)
frontend/   React + Vite + Tailwind single-page app
```

### 1. Database

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

### 3. Frontend

```bash
cd frontend
npm install
npm run dev               # starts the app on http://localhost:5173, proxying /api to :4000
```

### 4. Production build

```bash
cd frontend && npm run build   # outputs static files to frontend/dist
cd backend && npm start         # run the API behind your reverse proxy of choice
```

Serve `frontend/dist` as static files and point them at the backend API's
`/api` path — see the environment variables note below for `CORS_ORIGIN`
and `VITE_API_URL` when the two are on different domains.

### Environment variables

See `backend/.env.example` and `frontend/.env.example`. Key ones:
`JWT_SECRET` (change before going live), `SEED_ADMIN_USERNAME` /
`SEED_ADMIN_PASSWORD` (used once by `npm run seed`), `CORS_ORIGIN` (set to
the frontend's origin in production), `VITE_API_URL` (set to the backend's
full URL when it's not served from the same domain/path as the frontend).

### Deploying to Hostinger (hPanel)

Hostinger's shared/business plans use their own **hPanel**, not cPanel.
Layout: main domain serves the static frontend, a subdomain runs the Node
API.

1. **Check Node support.** In hPanel, open **Advanced → Node.js**. Not
   there? This plan can't run the backend directly — use `php-app/`
   instead, or Hostinger's VPS plan / Railway / Render for the API.
2. **Create the API subdomain**: **Domains → Subdomains** → create
   `api.yourdomain.com`.
3. **Create the database**: **Databases → MySQL Databases** → create a
   database and a user (Hostinger prefixes both, e.g. `u123456789_...`) →
   attach the user with all privileges. Then **Databases → phpMyAdmin** →
   open the database → **Import** → choose `backend/db/schema.sql` → Go.
4. **Upload the backend**: zip `backend/` (skip `node_modules`, `.env`),
   upload via **Files → File Manager** into the application root, extract.
5. **Create the Node.js app**: **Advanced → Node.js → Create Application**
   — Node 18+, application root = the folder from step 4, application URL
   = `api.yourdomain.com`, startup file = `server.js`. Add the environment
   variables listed above (or upload a `.env` file into the app root if
   there's no env-vars field), then click **Run NPM Install**.
6. **Seed the database**: if **Advanced → SSH Access** is enabled, SSH in,
   enter the app's virtual environment (command shown on the Node.js
   screen), run `npm run seed`. No SSH? See "Seeding without SSH" below.
7. **Restart** the app, test `https://api.yourdomain.com/api/health`.
8. **Build and upload the frontend** from your own computer:
   ```bash
   cd frontend
   echo "VITE_API_URL=https://api.yourdomain.com/api" > .env
   npm install && npm run build
   ```
   Upload everything inside `frontend/dist/` to `public_html` for
   `yourdomain.com`. Its `.htaccess` (copied in automatically) makes page
   refreshes on routes like `/products` work.
9. **Enable SSL** for both domains (**Security → SSL** — Hostinger issues
   it automatically).
10. Log in, change the seeded admin password. Done.

#### Seeding without SSH

1. Locally, with `backend/` and `npm install` already run there:
   ```bash
   node -e "console.log(require('bcryptjs').hashSync('ChangeMe123!', 10))"
   ```
2. In phpMyAdmin's **SQL** tab:
   ```sql
   INSERT INTO roles (name, description, permissions) VALUES
   ('Admin', 'Full access to every feature, including user & role management.',
    '{"dashboard":"manage","inventory":"manage","products":"manage","profit":"manage","users":"manage"}'),
   ('Manager', 'Runs day-to-day operations: products, inventory and profit tracking.',
    '{"dashboard":"view","inventory":"manage","products":"manage","profit":"manage","users":"none"}'),
   ('Staff', 'Handles listing and shipping: can view everything and update products.',
    '{"dashboard":"view","inventory":"view","products":"manage","profit":"none","users":"none"}');

   INSERT INTO users (username, password_hash, full_name, email, role_id)
   VALUES ('admin', '<paste the hash here>', 'Administrator',
           'admin@example.com', (SELECT id FROM roles WHERE name = 'Admin'));
   ```
3. Log in with `admin` / the password you hashed, then change it.

### Deploying to cPanel hosts

Needs a cPanel host with **"Setup Node.js App"** (Phusion Passenger) and
**MySQL Databases**. Same layout and steps as the Hostinger walkthrough
above, with cPanel's own naming:

1. Check for **"Setup Node.js App"** in cPanel. Not there → use `php-app/`
   instead, or a VPS.
2. Create the `api.yourdomain.com` subdomain under **Domains**.
3. **MySQL Databases** → create a database + user (cPanel prefixes both,
   e.g. `cpaneluser_vinted...`) → add the user with **All Privileges**.
   Import `backend/db/schema.sql` via **phpMyAdmin**.
4. Upload `backend/` outside `public_html` (e.g.
   `/home/cpaneluser/vinted-api`).
5. **Setup Node.js App** → Create Application: Node 18+, application root
   = that folder, application URL = `api.yourdomain.com`, startup file =
   `server.js`. Add the environment variables from the list above, then
   **Run NPM Install**.
6. Seed once via **Terminal**/SSH (`source .../nodevenv/.../bin/activate`,
   `cd` into the app, `npm run seed`) — or by hand, per "Seeding without
   SSH" above.
7. **Restart**, test `https://api.yourdomain.com/api/health`.
8. Build the frontend locally (same commands as above) and upload
   `frontend/dist/`'s contents to `public_html`.
9. Issue SSL for both domains under **SSL/TLS Status** / **AutoSSL**.
10. Log in, change the admin password.
