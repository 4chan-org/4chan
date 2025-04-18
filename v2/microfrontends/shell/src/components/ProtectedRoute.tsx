import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';

interface ProtectedRouteProps {
  children: React.ReactNode;
  isAllowed: boolean;
  redirectTo?: string;
}

/**
 * Componente para proteger rutas que requieren autenticación
 * o permisos específicos.
 */
const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  isAllowed,
  redirectTo,
}) => {
  const location = useLocation();
  
  // Si no tiene permiso, redirigir
  if (!isAllowed) {
    // Si se especifica una URL de redirección, usarla
    if (redirectTo) {
      return <Navigate to={redirectTo} state={{ from: location }} replace />;
    }
    
    // Redirección predeterminada a login, con parámetro returnTo
    const returnPath = encodeURIComponent(location.pathname + location.search);
    return <Navigate to={`/auth/login?returnTo=${returnPath}`} replace />;
  }
  
  return <>{children}</>;
};

export default ProtectedRoute;