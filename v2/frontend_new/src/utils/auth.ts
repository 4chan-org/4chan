import api from '@/api/axios';
import { jwtDecode } from 'jose';

/**
 * Set the authorization token for API requests
 * @param token JWT token
 */
export const setAuthToken = (token: string): void => {
  if (token) {
    api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  } else {
    delete api.defaults.headers.common['Authorization'];
  }
};

/**
 * Remove the authorization token from API requests
 */
export const removeAuthToken = (): void => {
  delete api.defaults.headers.common['Authorization'];
};

/**
 * Check if a JWT token is expired
 * @param token JWT token
 * @returns boolean indicating if token is expired
 */
export const isTokenExpired = (token: string): boolean => {
  try {
    const decoded = jwtDecode(token);
    const currentTime = Date.now() / 1000;
    
    if (typeof decoded.exp === 'number') {
      return decoded.exp < currentTime;
    }
    
    return true;
  } catch (error) {
    return true;
  }
};

/**
 * Get a user's role from the JWT token
 * @param token JWT token
 * @returns string role or null if token is invalid
 */
export const getRoleFromToken = (token: string): string | null => {
  try {
    const decoded = jwtDecode(token);
    return decoded.role as string || null;
  } catch (error) {
    return null;
  }
};

/**
 * Check if a user has permission based on allowed roles
 * @param userRole Current user's role
 * @param allowedRoles Array of allowed roles
 * @returns boolean indicating if user has permission
 */
export const hasPermission = (
  userRole: string | null | undefined,
  allowedRoles: string[]
): boolean => {
  if (!userRole) return false;
  
  // Role hierarchy: ADMIN > MODERATOR > JANITOR > USER
  const roleHierarchy = {
    'ADMIN': 4,
    'MODERATOR': 3,
    'JANITOR': 2,
    'USER': 1,
  };
  
  const userRoleValue = roleHierarchy[userRole as keyof typeof roleHierarchy] || 0;
  
  // Check if the user's role is in the allowed roles or has a higher role value
  return allowedRoles.some(role => {
    const allowedRoleValue = roleHierarchy[role as keyof typeof roleHierarchy] || 0;
    return userRole === role || userRoleValue > allowedRoleValue;
  });
};