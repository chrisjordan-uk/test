# Vinted Resell Manager — PHP version

Plain PHP + MySQL, no build step, no Node.js, no Composer, no dependencies
at all. Upload the folder, point it at a MySQL database, and it works —
this is the version to use on ordinary shared hosting (Hostinger, cPanel
hosts, etc.).

## What's in here

```
config.php          The only file you need to edit (database credentials)
db/database.sql      Import this once into your MySQL database
db/seed.php           Visit this once in your browser to create the admin account
index.php             Home / dashboard
inventory.php         Read-only product table with filters
products.php + product_form.php    Add/edit products, change status
profit.php + transaction_form.php  Purchases/sales/expenses ledger
users.php + user_form.php + role_form.php   User & role management
includes/            Shared layout + helper functions
```

## Setup (5 steps)

1. **Create a MySQL database** through your hosting's control panel
   (cPanel's "MySQL Databases", Hostinger's hPanel "Databases", etc.) and
   note the database name, username and password it gives you.
2. **Import the schema.** Open phpMyAdmin → select your database →
   **Import** tab → choose `db/database.sql` → Go. (Already set this up
   before? Run the file in `db/migrations/` instead — it just adds what's
   new without touching your data.)
3. **Upload this whole `php-app` folder** to your hosting (as the contents
   of `public_html`, or a subfolder if you want the app at
   `yoursite.com/shop/`) via File Manager or FTP.
4. **Edit `config.php`** (directly in the hosting's File Manager editor, or
   locally before uploading) and fill in the three `CHANGE_ME` values with
   the database name/username/password from step 1. Optionally change the
   `SEED_ADMIN_*` values too — that's the login the next step creates.
5. **Visit `db/seed.php` once in your browser**
   (e.g. `https://yoursite.com/db/seed.php`) to create the default roles
   and the admin account. It tells you what it did; you can delete that
   file afterwards.

Then log in at `login.php` with the admin account and change its password
from the Users page.

## Business features

- **Currency:** everything is priced in British pounds (£).
- **Aging stock alert** on Home: any item sitting in stock for 30+ days
  without selling is called out, with an "awaiting shipment" banner too
  when items are marked *To Ship*.
- **Margin & days-in-stock columns** on Inventory: at-a-glance profit % per
  item and how long it's been held (or took to sell).
- **CSV export** on Inventory and Profit, for bookkeeping/backup — respects
  whatever filters are active on Inventory.
- **Expense categories** (Shipping, Packaging, Platform fees, Supplies,
  Other, or your own) on manual Profit entries, with a spend-by-category
  breakdown on the Profit page.
- **Sales performance stats** on Profit: items sold, average sale price,
  average margin, average days to sell — computed straight from your sold
  products.

## Using it as an iPhone app

There's no separate app to build or install from the App Store — the site
itself is set up to install like one:

1. Open your live site in **Safari** on the iPhone (must be Safari, not
   Chrome — only Safari can do this on iOS).
2. Tap the **Share** button (square with an arrow) → **Add to Home Screen**.
3. Give it a name (defaults to "Vinted Resell") → **Add**.

You'll get an app icon on the home screen that opens full-screen, with no
Safari address bar — it looks and feels like a normal app. It's still the
same live website underneath (so it always shows current data and needs
an internet connection), it's just launched without the browser chrome.
This works because of the icons in `assets/icons/` and the tags in
`includes/app_meta.php` — nothing further to configure.

## Notes

- Auth uses PHP sessions (a login cookie) and `password_hash()` /
  `password_verify()` — nothing to install, it's all built into PHP.
- Styling is Tailwind CSS loaded from its CDN (`cdn.tailwindcss.com`) in
  `includes/header.php` / `login.php` — that's a script tag, not a build
  step, so it just works as long as the visitor's browser can reach it
  (which is virtually always the case).
- The profit ledger stays in sync automatically: creating a product adds a
  purchase entry, marking one *Sold* (with a sold price) adds a sale entry.
  Manual entries (bulk stock, shipping/packaging expenses) can be added
  from the Profit page and aren't tied to a specific product.
- Roles and their permissions (`none` / `view` / `manage` per feature) are
  edited from **Users & Roles** by anyone whose role has `manage` on
  `users`.
