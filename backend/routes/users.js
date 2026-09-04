const express = require('express');
const bcrypt = require('bcryptjs');
const pool = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { requirePermission } = require('../middleware/permissions');
const { normalizePermissions, FEATURES, LEVELS } = require('../config/permissions');

const router = express.Router();

router.use(requireAuth);

function toPublicUser(row) {
  return {
    id: row.id,
    username: row.username,
    fullName: row.full_name,
    email: row.email,
    isActive: !!row.is_active,
    role: { id: row.role_id, name: row.role_name },
    createdAt: row.created_at,
  };
}

// ------------------------------------------------------------------ Users --

router.get('/users', requirePermission('users', 'view'), async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT u.id, u.username, u.full_name, u.email, u.is_active, u.created_at,
              r.id AS role_id, r.name AS role_name
       FROM users u JOIN roles r ON r.id = u.role_id
       ORDER BY u.username`
    );
    res.json({ users: rows.map(toPublicUser) });
  } catch (err) {
    console.error('Listing users failed:', err);
    res.status(500).json({ error: 'Could not load users.' });
  }
});

router.post('/users', requirePermission('users', 'manage'), async (req, res) => {
  const { username, password, fullName, email, roleId, isActive = true } = req.body || {};
  if (!username || !password || !roleId) {
    return res.status(400).json({ error: 'Username, password and role are required.' });
  }
  if (password.length < 6) {
    return res.status(400).json({ error: 'Password must be at least 6 characters.' });
  }

  try {
    const hash = await bcrypt.hash(password, 10);
    const [result] = await pool.query(
      `INSERT INTO users (username, password_hash, full_name, email, role_id, is_active)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [username, hash, fullName || null, email || null, roleId, isActive ? 1 : 0]
    );
    const [[row]] = await pool.query(
      `SELECT u.id, u.username, u.full_name, u.email, u.is_active, u.created_at,
              r.id AS role_id, r.name AS role_name
       FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?`,
      [result.insertId]
    );
    res.status(201).json({ user: toPublicUser(row) });
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(409).json({ error: 'That username is already taken.' });
    }
    console.error('Creating user failed:', err);
    res.status(500).json({ error: 'Could not create user.' });
  }
});

router.put('/users/:id', requirePermission('users', 'manage'), async (req, res) => {
  const { fullName, email, roleId, isActive, password } = req.body || {};
  const fields = {};
  if (fullName !== undefined) fields.full_name = fullName || null;
  if (email !== undefined) fields.email = email || null;
  if (roleId !== undefined) fields.role_id = roleId;
  if (isActive !== undefined) fields.is_active = isActive ? 1 : 0;

  try {
    if (password) {
      if (password.length < 6) {
        return res.status(400).json({ error: 'Password must be at least 6 characters.' });
      }
      fields.password_hash = await bcrypt.hash(password, 10);
    }

    if (Object.keys(fields).length) {
      const setClause = Object.keys(fields).map((f) => `${f} = ?`).join(', ');
      const [result] = await pool.query(`UPDATE users SET ${setClause} WHERE id = ?`, [
        ...Object.values(fields),
        req.params.id,
      ]);
      if (!result.affectedRows) return res.status(404).json({ error: 'User not found.' });
    }

    const [[row]] = await pool.query(
      `SELECT u.id, u.username, u.full_name, u.email, u.is_active, u.created_at,
              r.id AS role_id, r.name AS role_name
       FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?`,
      [req.params.id]
    );
    if (!row) return res.status(404).json({ error: 'User not found.' });
    res.json({ user: toPublicUser(row) });
  } catch (err) {
    console.error('Updating user failed:', err);
    res.status(500).json({ error: 'Could not update user.' });
  }
});

router.delete('/users/:id', requirePermission('users', 'manage'), async (req, res) => {
  if (Number(req.params.id) === req.user.sub) {
    return res.status(400).json({ error: 'You cannot delete your own account.' });
  }
  try {
    const [result] = await pool.query('DELETE FROM users WHERE id = ?', [req.params.id]);
    if (!result.affectedRows) return res.status(404).json({ error: 'User not found.' });
    res.status(204).end();
  } catch (err) {
    console.error('Deleting user failed:', err);
    res.status(500).json({ error: 'Could not delete user.' });
  }
});

// ------------------------------------------------------------------ Roles --

router.get('/roles', requirePermission('users', 'view'), async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM roles ORDER BY id');
    res.json({
      features: FEATURES,
      levels: LEVELS,
      roles: rows.map((r) => ({
        id: r.id,
        name: r.name,
        description: r.description,
        permissions: normalizePermissions(r.permissions),
      })),
    });
  } catch (err) {
    console.error('Listing roles failed:', err);
    res.status(500).json({ error: 'Could not load roles.' });
  }
});

router.post('/roles', requirePermission('users', 'manage'), async (req, res) => {
  const { name, description, permissions } = req.body || {};
  if (!name) return res.status(400).json({ error: 'Role name is required.' });
  try {
    const [result] = await pool.query(
      'INSERT INTO roles (name, description, permissions) VALUES (?, ?, ?)',
      [name, description || null, JSON.stringify(normalizePermissions(permissions))]
    );
    const [[row]] = await pool.query('SELECT * FROM roles WHERE id = ?', [result.insertId]);
    res.status(201).json({
      role: { id: row.id, name: row.name, description: row.description, permissions: normalizePermissions(row.permissions) },
    });
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(409).json({ error: 'A role with that name already exists.' });
    }
    console.error('Creating role failed:', err);
    res.status(500).json({ error: 'Could not create role.' });
  }
});

router.put('/roles/:id', requirePermission('users', 'manage'), async (req, res) => {
  const { name, description, permissions } = req.body || {};
  const fields = {};
  if (name !== undefined) fields.name = name;
  if (description !== undefined) fields.description = description || null;
  if (permissions !== undefined) fields.permissions = JSON.stringify(normalizePermissions(permissions));

  if (!Object.keys(fields).length) {
    return res.status(400).json({ error: 'No editable fields provided.' });
  }

  try {
    const setClause = Object.keys(fields).map((f) => `${f} = ?`).join(', ');
    const [result] = await pool.query(`UPDATE roles SET ${setClause} WHERE id = ?`, [
      ...Object.values(fields),
      req.params.id,
    ]);
    if (!result.affectedRows) return res.status(404).json({ error: 'Role not found.' });
    const [[row]] = await pool.query('SELECT * FROM roles WHERE id = ?', [req.params.id]);
    res.json({
      role: { id: row.id, name: row.name, description: row.description, permissions: normalizePermissions(row.permissions) },
    });
  } catch (err) {
    console.error('Updating role failed:', err);
    res.status(500).json({ error: 'Could not update role.' });
  }
});

router.delete('/roles/:id', requirePermission('users', 'manage'), async (req, res) => {
  try {
    const [[inUse]] = await pool.query('SELECT COUNT(*) AS count FROM users WHERE role_id = ?', [
      req.params.id,
    ]);
    if (inUse.count > 0) {
      return res.status(409).json({ error: 'Cannot delete a role that still has users assigned.' });
    }
    const [result] = await pool.query('DELETE FROM roles WHERE id = ?', [req.params.id]);
    if (!result.affectedRows) return res.status(404).json({ error: 'Role not found.' });
    res.status(204).end();
  } catch (err) {
    console.error('Deleting role failed:', err);
    res.status(500).json({ error: 'Could not delete role.' });
  }
});

module.exports = router;
