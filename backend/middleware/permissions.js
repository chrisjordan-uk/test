const { hasPermission, hasAnyPermission } = require('../config/permissions');

// requirePermission('products', 'manage') -> 403 unless req.user's role grants it.
// Must run after requireAuth (needs req.user.permissions).
function requirePermission(feature, minLevel = 'view') {
  return (req, res, next) => {
    if (!req.user || !hasPermission(req.user.permissions, feature, minLevel)) {
      return res.status(403).json({ error: 'You do not have permission to do that.' });
    }
    next();
  };
}

// requireAnyPermission(['inventory', 'products'], 'view') -> passes if the role
// has at least `minLevel` on ANY of the listed features. Useful for data that
// is shared between two pages with distinct permission keys.
function requireAnyPermission(features, minLevel = 'view') {
  return (req, res, next) => {
    if (!req.user || !hasAnyPermission(req.user.permissions, features, minLevel)) {
      return res.status(403).json({ error: 'You do not have permission to do that.' });
    }
    next();
  };
}

module.exports = { requirePermission, requireAnyPermission };
