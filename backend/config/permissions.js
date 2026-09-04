// Central definition of the permission model used across the app.
//
// Each role has a `permissions` JSON object mapping a feature key to a level:
//   'none'   - the feature is hidden and every endpoint for it is blocked
//   'view'   - read-only access (GET endpoints)
//   'manage' - full access (create/update/delete/status changes)

const FEATURES = ['dashboard', 'inventory', 'products', 'profit', 'users'];

const LEVELS = ['none', 'view', 'manage'];

const LEVEL_RANK = { none: 0, view: 1, manage: 2 };

function normalizePermissions(raw) {
  const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw || {};
  const result = {};
  for (const feature of FEATURES) {
    const level = parsed[feature];
    result[feature] = LEVELS.includes(level) ? level : 'none';
  }
  return result;
}

// True when `permissions[feature]` grants at least `minLevel`.
function hasPermission(permissions, feature, minLevel = 'view') {
  if (!permissions) return false;
  const level = permissions[feature] || 'none';
  return LEVEL_RANK[level] >= LEVEL_RANK[minLevel];
}

// True when `permissions` grants at least `minLevel` on ANY of the given features.
function hasAnyPermission(permissions, features, minLevel = 'view') {
  return features.some((feature) => hasPermission(permissions, feature, minLevel));
}

const DEFAULT_ROLES = [
  {
    name: 'Admin',
    description: 'Full access to every feature, including user & role management.',
    permissions: {
      dashboard: 'manage',
      inventory: 'manage',
      products: 'manage',
      profit: 'manage',
      users: 'manage',
    },
  },
  {
    name: 'Manager',
    description: 'Runs day-to-day operations: products, inventory and profit tracking.',
    permissions: {
      dashboard: 'view',
      inventory: 'manage',
      products: 'manage',
      profit: 'manage',
      users: 'none',
    },
  },
  {
    name: 'Staff',
    description: 'Handles listing and shipping: can view everything and update products.',
    permissions: {
      dashboard: 'view',
      inventory: 'view',
      products: 'manage',
      profit: 'none',
      users: 'none',
    },
  },
];

module.exports = {
  FEATURES,
  LEVELS,
  normalizePermissions,
  hasPermission,
  hasAnyPermission,
  DEFAULT_ROLES,
};
