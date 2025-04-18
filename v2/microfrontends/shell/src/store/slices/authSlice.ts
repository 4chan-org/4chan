import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';

export interface UserProfile {
  id: string;
  username: string;
  role: 'user' | 'moderator' | 'admin';
  email?: string;
  avatarUrl?: string;
  preferences?: Record<string, any>;
}

interface AuthState {
  isAuthenticated: boolean;
  user: UserProfile | null;
  token: string | null;
  loading: boolean;
  error: string | null;
}

const initialState: AuthState = {
  isAuthenticated: false,
  user: null,
  token: localStorage.getItem('auth_token'),
  loading: false,
  error: null,
};

// Thunks para operaciones asíncronas
export const login = createAsyncThunk(
  'auth/login',
  async ({ username, password }: { username: string; password: string }, { rejectWithValue }) => {
    try {
      // En una implementación real, aquí se haría la petición al API
      // Simulamos la respuesta para este ejemplo
      const response = await new Promise<{ token: string; user: UserProfile }>((resolve) => {
        setTimeout(() => {
          resolve({
            token: 'fake_token_12345',
            user: {
              id: 'user1',
              username,
              role: 'user',
            },
          });
        }, 500);
      });
      
      // Guardar token en localStorage
      localStorage.setItem('auth_token', response.token);
      
      return response;
    } catch (error: any) {
      return rejectWithValue(error.message || 'Error al iniciar sesión');
    }
  }
);

export const logout = createAsyncThunk(
  'auth/logout',
  async (_, { rejectWithValue }) => {
    try {
      // Eliminar token del localStorage
      localStorage.removeItem('auth_token');
      return true;
    } catch (error: any) {
      return rejectWithValue(error.message || 'Error al cerrar sesión');
    }
  }
);

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setUser: (state, action: PayloadAction<UserProfile>) => {
      state.user = action.payload;
      state.isAuthenticated = true;
    },
    clearAuth: (state) => {
      state.user = null;
      state.token = null;
      state.isAuthenticated = false;
      localStorage.removeItem('auth_token');
    },
    updateUserPreferences: (state, action: PayloadAction<Record<string, any>>) => {
      if (state.user) {
        state.user.preferences = {
          ...state.user.preferences,
          ...action.payload,
        };
      }
    },
  },
  extraReducers: (builder) => {
    // Login
    builder.addCase(login.pending, (state) => {
      state.loading = true;
      state.error = null;
    });
    builder.addCase(login.fulfilled, (state, action) => {
      state.loading = false;
      state.isAuthenticated = true;
      state.token = action.payload.token;
      state.user = action.payload.user;
    });
    builder.addCase(login.rejected, (state, action) => {
      state.loading = false;
      state.error = action.payload as string;
    });
    
    // Logout
    builder.addCase(logout.fulfilled, (state) => {
      state.isAuthenticated = false;
      state.user = null;
      state.token = null;
    });
  },
});

export const { setUser, clearAuth, updateUserPreferences } = authSlice.actions;

export default authSlice.reducer;