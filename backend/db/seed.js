// Idempotent seed script: creates the default roles and a first admin user
// if they don't exist yet. Safe to run multiple times.
//
// Usage: npm run seed   (reads DB + admin credentials from .env)

require('dotenv').config();
const bcrypt = require('bcryptjs');
const pool = require('../config/db');
const { DEFAULT_ROLES } = require('../config/permissions');

async function seed() {
  const conn = await pool.getConnection();
  try {
    for (const role of DEFAULT_ROLES) {
      const [existing] = await conn.query('SELECT id FROM roles WHERE name = ?', [role.name]);
      if (existing.length) {
        await conn.query('UPDATE roles SET description = ?, permissions = ? WHERE id = ?', [
          role.description,
          JSON.stringify(role.permissions),
          existing[0].id,
        ]);
        console.log(`Role "${role.name}" already existed, permissions refreshed.`);
      } else {
        await conn.query(
          'INSERT INTO roles (name, description, permissions) VALUES (?, ?, ?)',
          [role.name, role.description, JSON.stringify(role.permissions)]
        );
        console.log(`Created role "${role.name}".`);
      }
    }

    const adminUsername = process.env.SEED_ADMIN_USERNAME || 'admin';
    const [existingAdmin] = await conn.query('SELECT id FROM users WHERE username = ?', [
      adminUsername,
    ]);

    if (existingAdmin.length) {
      console.log(`User "${adminUsername}" already exists, leaving it untouched.`);
    } else {
      const [[adminRole]] = await conn.query('SELECT id FROM roles WHERE name = ?', ['Admin']);
      const password = process.env.SEED_ADMIN_PASSWORD || 'ChangeMe123!';
      const hash = await bcrypt.hash(password, 10);
      await conn.query(
        'INSERT INTO users (username, password_hash, full_name, email, role_id) VALUES (?, ?, ?, ?, ?)',
        [adminUsername, hash, 'Administrator', process.env.SEED_ADMIN_EMAIL || null, adminRole.id]
      );
      console.log(`Created admin user "${adminUsername}" with the password from SEED_ADMIN_PASSWORD.`);
      console.log('IMPORTANT: log in and change this password immediately.');
    }
  } finally {
    conn.release();
    await pool.end();
  }
}

seed().catch((err) => {
  console.error('Seed failed:', err);
  process.exit(1);
});
