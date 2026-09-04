import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { NAV_ITEMS } from '../constants';
import Icon from './Icon';

export default function Layout() {
  const { user, logout, can } = useAuth();
  const visibleItems = NAV_ITEMS.filter((item) => can(item.feature, 'view'));

  return (
    <div className="flex min-h-screen bg-slate-50">
      <aside className="flex w-64 flex-col border-r border-slate-200 bg-white">
        <div className="flex items-center gap-2 px-6 py-5">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white">
            <Icon name="tag" className="h-5 w-5" />
          </div>
          <div>
            <p className="text-sm font-semibold leading-tight text-slate-900">Vinted Resell</p>
            <p className="text-xs leading-tight text-slate-500">Business Manager</p>
          </div>
        </div>

        <nav className="flex-1 space-y-1 px-3">
          {visibleItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === '/'}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-brand-50 text-brand-700'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                }`
              }
            >
              <Icon name={item.icon} className="h-5 w-5" />
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-slate-200 p-4">
          <div className="mb-3 flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700">
              {(user?.fullName || user?.username || '?').slice(0, 1).toUpperCase()}
            </div>
            <div className="min-w-0">
              <p className="truncate text-sm font-medium text-slate-900">
                {user?.fullName || user?.username}
              </p>
              <p className="truncate text-xs text-slate-500">{user?.role?.name}</p>
            </div>
          </div>
          <button onClick={logout} className="btn-secondary w-full">
            <Icon name="logout" className="h-4 w-4" />
            Log out
          </button>
        </div>
      </aside>

      <main className="flex-1 overflow-y-auto">
        <div className="mx-auto max-w-7xl px-6 py-8 lg:px-10">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
