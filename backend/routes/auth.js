const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const pool = require('../config/db');
const { normalizePermissions } = require('../config/permissions');
const { requireAuth } = require('../middleware/auth');

const router = express.Router();

async function findUserWithRole(username) {
  const [rows] = await pool.query(
    `SELECT u.id, u.username, u.password_hash, u.full_name, u.email, u.is_active,
            r.id AS role_id, r.name AS role_name, r.permissions
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.username = ?`,
    [username]
  );
  return rows[0];
}

function toPublicUser(row) {
  return {
    id: row.id,
    username: row.username,
    fullName: row.full_name,
    email: row.email,
    role: { id: row.role_id, name: row.role_name },
    permissions: normalizePermissions(row.permissions),
  };
}

router.post('/login', async (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) {
    return res.status(400).json({ error: 'Username and password are required.' });
  }

  try {
    const row = await findUserWithRole(username);
    if (!row || !row.is_active) {
      return res.status(401).json({ error: 'Invalid username or password.' });
    }

    const ok = await bcrypt.compare(password, row.password_hash);
    if (!ok) {
      return res.status(401).json({ error: 'Invalid username or password.' });
    }

    const user = toPublicUser(row);
    const token = jwt.sign(
      {
        sub: user.id,
        username: user.username,
        role: user.role,
        permissions: user.permissions,
      },
      process.env.JWT_SECRET,
      { expiresIn: process.env.JWT_EXPIRES_IN || '8h' }
    );

    res.json({ token, user });
  } catch (err) {
    console.error('Login failed:', err);
    res.status(500).json({ error: 'Could not log in right now.' });
  }
});

router.get('/me', requireAuth, async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT u.id, u.username, u.full_name, u.email, u.is_active,
              r.id AS role_id, r.name AS role_name, r.permissions
       FROM users u JOIN roles r ON r.id = u.role_id
       WHERE u.id = ?`,
      [req.user.sub]
    );
    if (!rows[0] || !rows[0].is_active) {
      return res.status(401).json({ error: 'Account no longer active.' });
    }
    res.json({ user: toPublicUser(rows[0]) });
  } catch (err) {
    console.error('Fetching current user failed:', err);
    res.status(500).json({ error: 'Could not load your profile.' });
  }
});

router.post('/change-password', requireAuth, async (req, res) => {
  const { currentPassword, newPassword } = req.body || {};
  if (!currentPassword || !newPassword) {
    return res.status(400).json({ error: 'Current and new password are required.' });
  }
  if (newPassword.length < 6) {
    return res.status(400).json({ error: 'New password must be at least 6 characters.' });
  }

  try {
    const [rows] = await pool.query('SELECT password_hash FROM users WHERE id = ?', [req.user.sub]);
    if (!rows[0]) return res.status(404).json({ error: 'User not found.' });

    const ok = await bcrypt.compare(currentPassword, rows[0].password_hash);
    if (!ok) return res.status(401).json({ error: 'Current password is incorrect.' });

    const hash = await bcrypt.hash(newPassword, 10);
    await pool.query('UPDATE users SET password_hash = ? WHERE id = ?', [hash, req.user.sub]);
    res.json({ ok: true });
  } catch (err) {
    console.error('Changing password failed:', err);
    res.status(500).json({ error: 'Could not change password.' });
  }
});

module.exports = router;
