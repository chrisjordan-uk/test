import { createContext, useContext, useEffect, useMemo, useState, useCallback } from 'react';
import { api, setAuthToken, setUnauthorizedHandler } from '../api/client';

const AuthContext = createContext(null);
const STORAGE_KEY = 'vinted-resell.token';

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const logout = useCallback(() => {
    localStorage.removeItem(STORAGE_KEY);
    setAuthToken(null);
    setUser(null);
  }, []);

  useEffect(() => {
    setUnauthorizedHandler(logout);
  }, [logout]);

  useEffect(() => {
    const token = localStorage.getItem(STORAGE_KEY);
    if (!token) {
      setLoading(false);
      return;
    }
    setAuthToken(token);
    api
      .get('/auth/me')
      .then((data) => setUser(data.user))
      .catch(() => logout())
      .finally(() => setLoading(false));
  }, [logout]);

  const login = useCallback(async (username, password) => {
    const data = await api.post('/auth/login', { username, password });
    localStorage.setItem(STORAGE_KEY, data.token);
    setAuthToken(data.token);
    setUser(data.user);
    return data.user;
  }, []);

  const can = useCallback(
    (feature, level = 'view') => {
      if (!user) return false;
      const rank = { none: 0, view: 1, manage: 2 };
      return rank[user.permissions?.[feature] || 'none'] >= rank[level];
    },
    [user]
  );

  const value = useMemo(
    () => ({ user, setUser, loading, login, logout, can }),
    [user, loading, login, logout, can]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
