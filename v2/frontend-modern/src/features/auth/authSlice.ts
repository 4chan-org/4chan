import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { RootState } from '@/store/store';
import api from '@/api/axios';
import { setAuthToken, removeAuthToken } from '@/utils/auth';

// Types
export interface User {
  id: string;
  username: string;
  role: 'USER' | 'JANITOR' | 'MODERATOR' | 'ADMIN';
  isActive: boolean;
  createdAt: string;
  lastLoginAt?: string;
}

interface AuthState {
  user: User | null;
  token: string | null;
  refreshToken: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
}

interface LoginCredentials {
  username: string;
  password: string;
}

interface AuthResponse {
  accessToken: string;
  refreshToken: string;
  user: User;
}

// Initial state
const initialState: AuthState = {
  user: null,
  token: localStorage.getItem('token'),
  refreshToken: localStorage.getItem('refreshToken'),
  isAuthenticated: !!localStorage.getItem('token'),
  isLoading: false,
  error: null,
};

// Async thunks
export const login = createAsyncThunk<
  AuthResponse,
  LoginCredentials,
  { rejectValue: string }
>('auth/login', async (credentials, { rejectWithValue }) => {
  try {
    const response = await api.post<AuthResponse>('/auth/login', credentials);
    return response.data;
  } catch (error: any) {
    return rejectWithValue(
      error.response?.data?.message || 'Failed to login'
    );
  }
});

export const refreshToken = createAsyncThunk<
  AuthResponse,
  string,
  { rejectValue: string }
>('auth/refreshToken', async (refreshToken, { rejectWithValue }) => {
  try {
    const response = await api.post<AuthResponse>('/auth/refresh', {
      refreshToken,
    });
    return response.data;
  } catch (error: any) {
    return rejectWithValue(
      error.response?.data?.message || 'Failed to refresh token'
    );
  }
});

export const logout = createAsyncThunk('auth/logout', async (_, { getState, rejectWithValue }) => {
  const { auth } = getState() as { auth: AuthState };
  if (!auth.token) return;
  
  try {
    await api.post('/auth/logout');
    return;
  } catch (error: any) {
    return rejectWithValue(
      error.response?.data?.message || 'Failed to logout'
    );
  }
});

export const checkAuth = createAsyncThunk<
  void,
  void,
  { state: RootState }
>('auth/checkAuth', async (_, { getState, dispatch }) => {
  const { auth } = getState();
  
  // If there's no refresh token, we can't refresh
  if (!auth.refreshToken) {
    dispatch(logout());
    return;
  }
  
  // If there's a token, check if it's valid
  if (auth.token) {
    try {
      // Make a request to a protected endpoint
      await api.get('/auth/me');
      return;
    } catch (error: any) {
      // If the token is invalid, try to refresh it
      if (error.response?.status === 401) {
        try {
          await dispatch(refreshToken(auth.refreshToken)).unwrap();
          return;
        } catch {
          // If refresh fails, logout
          dispatch(logout());
        }
      }
    }
  } else if (auth.refreshToken) {
    // If there's only a refresh token, try to get a new token
    try {
      await dispatch(refreshToken(auth.refreshToken)).unwrap();
    } catch {
      dispatch(logout());
    }
  }
});

// Auth slice
const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    clearError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Login
      .addCase(login.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(login.fulfilled, (state, action) => {
        state.isLoading = false;
        state.isAuthenticated = true;
        state.user = action.payload.user;
        state.token = action.payload.accessToken;
        state.refreshToken = action.payload.refreshToken;
        
        // Store tokens in localStorage
        localStorage.setItem('token', action.payload.accessToken);
        localStorage.setItem('refreshToken', action.payload.refreshToken);
        
        // Set the auth token for API requests
        setAuthToken(action.payload.accessToken);
      })
      .addCase(login.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload || 'Failed to login';
      })
      
      // Refresh token
      .addCase(refreshToken.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(refreshToken.fulfilled, (state, action) => {
        state.isLoading = false;
        state.isAuthenticated = true;
        state.user = action.payload.user;
        state.token = action.payload.accessToken;
        state.refreshToken = action.payload.refreshToken;
        
        // Update tokens in localStorage
        localStorage.setItem('token', action.payload.accessToken);
        localStorage.setItem('refreshToken', action.payload.refreshToken);
        
        // Set the auth token for API requests
        setAuthToken(action.payload.accessToken);
      })
      .addCase(refreshToken.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload || 'Failed to refresh token';
        state.isAuthenticated = false;
        state.user = null;
        state.token = null;
        state.refreshToken = null;
        
        // Remove tokens from localStorage
        localStorage.removeItem('token');
        localStorage.removeItem('refreshToken');
        
        // Remove the auth token from API requests
        removeAuthToken();
      })
      
      // Logout
      .addCase(logout.pending, (state) => {
        state.isLoading = true;
      })
      .addCase(logout.fulfilled, (state) => {
        state.isLoading = false;
        state.isAuthenticated = false;
        state.user = null;
        state.token = null;
        state.refreshToken = null;
        state.error = null;
        
        // Remove tokens from localStorage
        localStorage.removeItem('token');
        localStorage.removeItem('refreshToken');
        
        // Remove the auth token from API requests
        removeAuthToken();
      })
      .addCase(logout.rejected, (state) => {
        state.isLoading = false;
        state.isAuthenticated = false;
        state.user = null;
        state.token = null;
        state.refreshToken = null;
        
        // Remove tokens from localStorage
        localStorage.removeItem('token');
        localStorage.removeItem('refreshToken');
        
        // Remove the auth token from API requests
        removeAuthToken();
      });
  },
});

// Actions
export const { clearError } = authSlice.actions;

// Selectors
export const selectAuth = (state: RootState) => state.auth;
export const selectUser = (state: RootState) => state.auth.user;
export const selectIsAuthenticated = (state: RootState) => state.auth.isAuthenticated;
export const selectAuthError = (state: RootState) => state.auth.error;
export const selectIsLoading = (state: RootState) => state.auth.isLoading;

// Reducer
export default authSlice.reducer;