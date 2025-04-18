import axios from 'axios';
import { store } from '@/store/store';
import { refreshToken, logout } from '@/features/auth/authSlice';

// Create an Axios instance with custom configuration
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor for adding auth token
api.interceptors.request.use(
  (config) => {
    const state = store.getState();
    const { token } = state.auth;
    
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor for handling token expiration
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    
    // If the error is 401 (Unauthorized) and we haven't tried to refresh the token yet
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      
      try {
        // Try to refresh the token
        const state = store.getState();
        const { refreshToken: currentRefreshToken } = state.auth;
        
        if (currentRefreshToken) {
          // Dispatch the refresh token action
          const result = await store.dispatch(refreshToken(currentRefreshToken)).unwrap();
          
          // If we successfully got a new token, retry the original request
          originalRequest.headers.Authorization = `Bearer ${result.accessToken}`;
          return api(originalRequest);
        }
      } catch (refreshError) {
        // If refreshing fails, log out the user
        store.dispatch(logout());
        return Promise.reject(refreshError);
      }
    }
    
    // For other errors, just pass them through
    return Promise.reject(error);
  }
);

export default api;