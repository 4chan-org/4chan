import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAppSelector } from '@/hooks/redux';
import { selectIsAuthenticated, selectUser } from '@/features/auth/authSlice';
import { hasPermission } from '@/utils/auth';

interface ProtectedRouteProps {
  allowedRoles?: string[];
  children?: React.ReactNode;
}

/**
 * Component for protecting routes that require authentication and/or specific roles
 */
const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ 
  allowedRoles = [], 
  children 
}) => {
  const location = useLocation();
  const isAuthenticated = useAppSelector(selectIsAuthenticated);
  const user = useAppSelector(selectUser);
  
  // If user is not authenticated, redirect to login
  if (!isAuthenticated) {
    return <Navigate to="/signin" state={{ from: location }} replace />;
  }
  
  // If specific roles are required, check if user has permission
  if (allowedRoles.length > 0) {
    const hasRole = hasPermission(user?.role, allowedRoles);
    
    if (!hasRole) {
      // Redirect to homepage if user doesn't have the required role
      return <Navigate to="/" replace />;
    }
  }
  
  // If there are children, render them, otherwise render the Outlet
  return <>{children ? children : <Outlet />}</>;
};

export default ProtectedRoute;