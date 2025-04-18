import { createSlice, PayloadAction } from '@reduxjs/toolkit';

// Tipos para el estado del tema
export type ThemeName = 'light' | 'dark' | 'yotsuba' | 'tomorrow' | 'futaba' | 'auto' | string;

export interface ThemeMetadata {
  name: string;
  author?: string;
  version?: string;
  description?: string;
  darkMode?: boolean;
  highContrast?: boolean;
  reducedMotion?: boolean;
  cssVariables?: Record<string, string>;
  signatureHash?: string; // Para verificación de integridad
}

interface ThemeState {
  current: ThemeName;
  available: ThemeName[];
  defaultTheme: ThemeName;
  customThemes: Record<string, ThemeMetadata>;
  preferSystemSettings: boolean;
}

// Estado inicial
const initialState: ThemeState = {
  current: 'light',
  available: ['light', 'dark', 'yotsuba', 'tomorrow', 'futaba', 'auto'],
  defaultTheme: 'light',
  customThemes: {},
  preferSystemSettings: true
};

// Slice de temas
const themeSlice = createSlice({
  name: 'theme',
  initialState,
  reducers: {
    // Cambiar tema actual
    setTheme: (state, action: PayloadAction<ThemeName>) => {
      // Verificar que el tema exista
      const themeExists = [...state.available, ...Object.keys(state.customThemes)].includes(action.payload);
      
      if (themeExists || action.payload === 'auto') {
        state.current = action.payload;
      } else {
        // Si el tema no existe, usar el predeterminado
        state.current = state.defaultTheme;
      }
    },
    
    // Añadir un tema personalizado
    addCustomTheme: (state, action: PayloadAction<{ 
      themeName: string, 
      themeData: ThemeMetadata
    }>) => {
      const { themeName, themeData } = action.payload;
      
      // Asegurarse de que el tema no existe o se está actualizando
      if (!state.available.includes(themeName)) {
        state.customThemes[themeName] = themeData;
        
        // Añadir a la lista de temas disponibles si no existe
        if (!state.available.includes(themeName)) {
          state.available.push(themeName);
        }
      }
    },
    
    // Eliminar un tema personalizado
    removeCustomTheme: (state, action: PayloadAction<string>) => {
      const themeName = action.payload;
      
      // Verificar que el tema existe y es personalizado
      if (Object.keys(state.customThemes).includes(themeName)) {
        delete state.customThemes[themeName];
        
        // Eliminar de la lista de temas disponibles
        state.available = state.available.filter(theme => theme !== themeName);
        
        // Si el tema actual era el que se ha eliminado, cambiar al tema predeterminado
        if (state.current === themeName) {
          state.current = state.defaultTheme;
        }
      }
    },
    
    // Cambiar preferencia de usar configuraciones del sistema
    setPreferSystemSettings: (state, action: PayloadAction<boolean>) => {
      state.preferSystemSettings = action.payload;
    },
    
    // Cambiar tema predeterminado
    setDefaultTheme: (state, action: PayloadAction<ThemeName>) => {
      // Verificar que el tema existe
      const themeExists = [...state.available, ...Object.keys(state.customThemes)].includes(action.payload);
      
      if (themeExists) {
        state.defaultTheme = action.payload;
      }
    },
    
    // Actualizar metadatos de un tema personalizado
    updateCustomTheme: (state, action: PayloadAction<{
      themeName: string,
      updates: Partial<ThemeMetadata>
    }>) => {
      const { themeName, updates } = action.payload;
      
      // Verificar que el tema existe
      if (Object.keys(state.customThemes).includes(themeName)) {
        state.customThemes[themeName] = {
          ...state.customThemes[themeName],
          ...updates
        };
      }
    }
  }
});

// Exportar acciones y reducer
export const { 
  setTheme, 
  addCustomTheme, 
  removeCustomTheme, 
  setPreferSystemSettings,
  setDefaultTheme,
  updateCustomTheme
} = themeSlice.actions;

export default themeSlice.reducer;