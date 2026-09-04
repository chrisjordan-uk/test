import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

// Wrap a page element: redirects to /login when unauthenticated, and to /
// when the user lacks the required permission level on `feature`.
export default function ProtectedRoute({ feature, level = 'view', children }) {
  const { user, loading, can } = useAuth();

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center text-slate-400">
        Loading…
      </div>
    );
  }

  if (!user) return <Navigate to="/login" replace />;
  if (feature && !can(feature, level)) return <Navigate to="/" replace />;

  return children;
}
