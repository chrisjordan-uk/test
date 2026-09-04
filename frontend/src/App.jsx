import { Routes, Route } from 'react-router-dom';
import Layout from './components/Layout';
import ProtectedRoute from './components/ProtectedRoute';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Inventory from './pages/Inventory';
import Products from './pages/Products';
import Profit from './pages/Profit';
import Users from './pages/Users';

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />

      <Route
        element={
          <ProtectedRoute>
            <Layout />
          </ProtectedRoute>
        }
      >
        <Route
          path="/"
          element={
            <ProtectedRoute feature="dashboard">
              <Dashboard />
            </ProtectedRoute>
          }
        />
        <Route
          path="/inventory"
          element={
            <ProtectedRoute feature="inventory">
              <Inventory />
            </ProtectedRoute>
          }
        />
        <Route
          path="/products"
          element={
            <ProtectedRoute feature="products">
              <Products />
            </ProtectedRoute>
          }
        />
        <Route
          path="/profit"
          element={
            <ProtectedRoute feature="profit">
              <Profit />
            </ProtectedRoute>
          }
        />
        <Route
          path="/users"
          element={
            <ProtectedRoute feature="users">
              <Users />
            </ProtectedRoute>
          }
        />
      </Route>
    </Routes>
  );
}
