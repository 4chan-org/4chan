import { createSlice, PayloadAction } from '@reduxjs/toolkit';

// Tipos para el estado de microfrontends
interface MicrofrontendState {
  loading: Record<string, boolean>;
  loaded: Record<string, boolean>;
  errors: Record<string, string>;
  apis: Record<string, any>;
  metadata: Record<string, MicrofrontendMetadata>;
}

export interface MicrofrontendMetadata {
  name: string;
  version?: string;
  lastLoaded?: string;
  author?: string;
  dependencies?: string[];
  type?: 'module' | 'webcomponent';
}

// Estado inicial
const initialState: MicrofrontendState = {
  loading: {},
  loaded: {},
  errors: {},
  apis: {},
  metadata: {},
};

// Slice para gestionar el estado de microfrontends
const microfrontendSlice = createSlice({
  name: 'microfrontend',
  initialState,
  reducers: {
    // Establecer estado de carga para un microfrontend
    setLoading: (state, action: PayloadAction<{ name: string; loading: boolean }>) => {
      const { name, loading } = action.payload;
      state.loading[name] = loading;
    },
    
    // Establecer estado de cargado para un microfrontend
    setLoaded: (state, action: PayloadAction<{ name: string; loaded: boolean }>) => {
      const { name, loaded } = action.payload;
      state.loaded[name] = loaded;
      
      // Si se ha descargado, actualizar el timestamp
      if (loaded && state.metadata[name]) {
        state.metadata[name] = {
          ...state.metadata[name],
          lastLoaded: new Date().toISOString()
        };
      }
    },
    
    // Establecer un error para un microfrontend
    setError: (state, action: PayloadAction<{ name: string; error: string }>) => {
      const { name, error } = action.payload;
      state.errors[name] = error;
    },
    
    // Guardar la API expuesta por un microfrontend
    setApi: (state, action: PayloadAction<{ name: string; api: any }>) => {
      const { name, api } = action.payload;
      state.apis[name] = api;
    },
    
    // Añadir o actualizar metadatos para un microfrontend
    setMetadata: (state, action: PayloadAction<{ name: string; metadata: MicrofrontendMetadata }>) => {
      const { name, metadata } = action.payload;
      state.metadata[name] = {
        ...state.metadata[name] || { name },
        ...metadata
      };
    },
    
    // Limpiar datos de un microfrontend específico
    clearMicrofrontend: (state, action: PayloadAction<string>) => {
      const name = action.payload;
      delete state.loading[name];
      delete state.loaded[name];
      delete state.errors[name];
      delete state.apis[name];
      // No borramos los metadatos para mantener historial
    },
    
    // Limpiar todos los datos (útil para logout)
    clearAll: (state) => {
      state.loading = {};
      state.loaded = {};
      state.errors = {};
      state.apis = {};
      // Mantenemos metadata por si es útil para cache
    }
  }
});

// Exportar acciones y reducer
export const { 
  setLoading, 
  setLoaded, 
  setError, 
  setApi,
  setMetadata,
  clearMicrofrontend,
  clearAll
} = microfrontendSlice.actions;

export default microfrontendSlice.reducer;