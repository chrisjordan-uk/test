import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import Icon from '../components/Icon';
import Modal from '../components/Modal';

const LEVEL_LABELS = { none: 'No access', view: 'View only', manage: 'Full access' };

export default function Users() {
  const { user: currentUser, can } = useAuth();
  const canManage = can('users', 'manage');

  const [tab, setTab] = useState('users');
  const [users, setUsers] = useState([]);
  const [roles, setRoles] = useState([]);
  const [features, setFeatures] = useState([]);
  const [levels, setLevels] = useState([]);
  const [error, setError] = useState('');
  const [editingUser, setEditingUser] = useState(undefined);
  const [editingRole, setEditingRole] = useState(undefined);

  function loadUsers() {
    api.get('/users').then((d) => setUsers(d.users)).catch((err) => setError(err.message));
  }
  function loadRoles() {
    api
      .get('/roles')
      .then((d) => {
        setRoles(d.roles);
        setFeatures(d.features);
        setLevels(d.levels);
      })
      .catch((err) => setError(err.message));
  }

  useEffect(() => {
    loadUsers();
    loadRoles();
  }, []);

  async function handleDeleteUser(id) {
    if (!confirm('Delete this user?')) return;
    try {
      await api.delete(`/users/${id}`);
      setUsers((prev) => prev.filter((u) => u.id !== id));
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Users & Roles</h1>
          <p className="mt-1 text-sm text-slate-500">Control who can access each part of the system.</p>
        </div>
        <div className="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm">
          <button
            onClick={() => setTab('users')}
            className={`rounded-md px-4 py-1.5 font-medium transition-colors ${tab === 'users' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'}`}
          >
            Users
          </button>
          <button
            onClick={() => setTab('roles')}
            className={`rounded-md px-4 py-1.5 font-medium transition-colors ${tab === 'roles' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'}`}
          >
            Roles & permissions
          </button>
        </div>
      </div>

      {error && <p className="rounded-lg bg-red-50 p-4 text-sm text-red-600">{error}</p>}

      {tab === 'users' && (
        <div className="space-y-4">
          {canManage && (
            <div className="flex justify-end">
              <button className="btn-primary" onClick={() => setEditingUser(null)}>
                <Icon name="plus" className="h-4 w-4" />
                Add user
              </button>
            </div>
          )}
          <div className="card overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50">
                  <tr className="text-xs uppercase tracking-wide text-slate-500">
                    <th className="px-4 py-3">Username</th>
                    <th className="px-4 py-3">Full name</th>
                    <th className="px-4 py-3">Role</th>
                    <th className="px-4 py-3">Status</th>
                    {canManage && <th className="px-4 py-3 text-right">Actions</th>}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {users.map((u) => (
                    <tr key={u.id} className="hover:bg-slate-50">
                      <td className="px-4 py-3 font-medium text-slate-800">{u.username}</td>
                      <td className="px-4 py-3 text-slate-600">{u.fullName || '—'}</td>
                      <td className="px-4 py-3">
                        <span className="badge bg-brand-50 text-brand-700">{u.role.name}</span>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`badge ${u.isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                          {u.isActive ? 'Active' : 'Disabled'}
                        </span>
                      </td>
                      {canManage && (
                        <td className="px-4 py-3">
                          <div className="flex justify-end gap-1">
                            <button
                              className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                              onClick={() => setEditingUser(u)}
                              aria-label="Edit"
                            >
                              <Icon name="edit" className="h-4 w-4" />
                            </button>
                            {u.id !== currentUser.id && (
                              <button
                                className="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                onClick={() => handleDeleteUser(u.id)}
                                aria-label="Delete"
                              >
                                <Icon name="trash" className="h-4 w-4" />
                              </button>
                            )}
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {tab === 'roles' && (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
          {roles.map((role) => (
            <div key={role.id} className="card p-5">
              <div className="mb-1 flex items-center justify-between">
                <h3 className="font-semibold text-slate-900">{role.name}</h3>
                {canManage && (
                  <button
                    className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    onClick={() => setEditingRole(role)}
                    aria-label="Edit role"
                  >
                    <Icon name="edit" className="h-4 w-4" />
                  </button>
                )}
              </div>
              <p className="mb-4 text-xs text-slate-500">{role.description}</p>
              <ul className="space-y-2">
                {features.map((f) => (
                  <li key={f} className="flex items-center justify-between text-sm">
                    <span className="capitalize text-slate-600">{f}</span>
                    <span
                      className={`badge ${
                        role.permissions[f] === 'manage'
                          ? 'bg-emerald-100 text-emerald-700'
                          : role.permissions[f] === 'view'
                            ? 'bg-sky-100 text-sky-700'
                            : 'bg-slate-100 text-slate-500'
                      }`}
                    >
                      {LEVEL_LABELS[role.permissions[f]]}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      )}

      {editingUser !== undefined && (
        <UserFormModal
          targetUser={editingUser}
          roles={roles}
          onClose={() => setEditingUser(undefined)}
          onSaved={(u) => {
            setUsers((prev) => (prev.some((x) => x.id === u.id) ? prev.map((x) => (x.id === u.id ? u : x)) : [...prev, u]));
            setEditingUser(undefined);
          }}
        />
      )}

      {editingRole !== undefined && (
        <RoleFormModal
          role={editingRole}
          features={features}
          levels={levels}
          onClose={() => setEditingRole(undefined)}
          onSaved={(r) => {
            setRoles((prev) => prev.map((x) => (x.id === r.id ? r : x)));
            setEditingRole(undefined);
          }}
        />
      )}
    </div>
  );
}

function UserFormModal({ targetUser, roles, onClose, onSaved }) {
  const isEdit = !!targetUser;
  const [username, setUsername] = useState(targetUser?.username || '');
  const [fullName, setFullName] = useState(targetUser?.fullName || '');
  const [email, setEmail] = useState(targetUser?.email || '');
  const [roleId, setRoleId] = useState(targetUser?.role?.id || roles[0]?.id || '');
  const [isActive, setIsActive] = useState(targetUser?.isActive ?? true);
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      const data = isEdit
        ? await api.put(`/users/${targetUser.id}`, {
            fullName,
            email,
            roleId: Number(roleId),
            isActive,
            ...(password ? { password } : {}),
          })
        : await api.post('/users', { username, password, fullName, email, roleId: Number(roleId), isActive });
      onSaved(data.user);
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal title={isEdit ? 'Edit user' : 'Add user'} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="label" htmlFor="username">Username</label>
          <input
            id="username"
            className="input"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            disabled={isEdit}
            required
          />
        </div>
        <div>
          <label className="label" htmlFor="fullName">Full name</label>
          <input id="fullName" className="input" value={fullName} onChange={(e) => setFullName(e.target.value)} />
        </div>
        <div>
          <label className="label" htmlFor="email">Email</label>
          <input id="email" type="email" className="input" value={email} onChange={(e) => setEmail(e.target.value)} />
        </div>
        <div>
          <label className="label" htmlFor="role">Role</label>
          <select id="role" className="input" value={roleId} onChange={(e) => setRoleId(e.target.value)}>
            {roles.map((r) => (
              <option key={r.id} value={r.id}>{r.name}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="label" htmlFor="password">{isEdit ? 'New password (optional)' : 'Password'}</label>
          <input
            id="password"
            type="password"
            className="input"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required={!isEdit}
            minLength={6}
          />
        </div>
        {isEdit && (
          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
            Account active
          </label>
        )}

        {error && <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{error}</p>}

        <div className="flex justify-end gap-2">
          <button type="button" className="btn-secondary" onClick={onClose}>Cancel</button>
          <button type="submit" disabled={saving} className="btn-primary">{saving ? 'Saving…' : 'Save'}</button>
        </div>
      </form>
    </Modal>
  );
}

function RoleFormModal({ role, features, levels, onClose, onSaved }) {
  const [permissions, setPermissions] = useState(role.permissions);
  const [description, setDescription] = useState(role.description || '');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      const data = await api.put(`/roles/${role.id}`, { description, permissions });
      onSaved(data.role);
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal title={`Edit "${role.name}" permissions`} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="label" htmlFor="description">Description</label>
          <input id="description" className="input" value={description} onChange={(e) => setDescription(e.target.value)} />
        </div>

        <div className="space-y-3">
          {features.map((f) => (
            <div key={f} className="flex items-center justify-between">
              <span className="text-sm capitalize text-slate-700">{f}</span>
              <div className="flex gap-1 rounded-lg bg-slate-100 p-1 text-xs">
                {levels.map((lvl) => (
                  <button
                    key={lvl}
                    type="button"
                    onClick={() => setPermissions((p) => ({ ...p, [f]: lvl }))}
                    className={`rounded-md px-2.5 py-1 font-medium capitalize transition-colors ${
                      permissions[f] === lvl ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'
                    }`}
                  >
                    {lvl}
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>

        {error && <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{error}</p>}

        <div className="flex justify-end gap-2">
          <button type="button" className="btn-secondary" onClick={onClose}>Cancel</button>
          <button type="submit" disabled={saving} className="btn-primary">{saving ? 'Saving…' : 'Save'}</button>
        </div>
      </form>
    </Modal>
  );
}
