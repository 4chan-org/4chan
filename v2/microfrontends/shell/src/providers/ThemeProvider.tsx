import React, { createContext, useContext, useEffect, useState } from 'react';
import { useAppSelector, useAppDispatch } from '../hooks/reduxHooks';
import { setTheme, addCustomTheme } from '../store/slices/themeSlice';
import { eventBus } from '../utils/eventBus';

// Definir temas disponibles
export type ThemeName = 'light' | 'dark' | 'yotsuba' | 'tomorrow' | 'futaba' | string;

interface ThemeContextType {
  currentTheme: ThemeName;
  changeTheme: (theme: ThemeName) => void;
  toggleDarkMode: () => void;
  isCustomTheme: (theme: ThemeName) => boolean;
  getThemeMetadata: (theme: ThemeName) => ThemeMetadata | undefined;
  verifyThemeIntegrity: (theme: ThemeName) => boolean;
}

interface ThemeMetadata {
  name: string;
  author?: string;
  version?: string;
  description?: string;
  darkMode?: boolean;
  highContrast?: boolean;
  reducedMotion?: boolean;
  cssVariables?: Record<string, string>;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme debe usarse dentro de un ThemeProvider');
  }
  return context;
};

interface ThemeProviderProps {
  children: React.ReactNode;
}

export const ThemeProvider: React.FC<ThemeProviderProps> = ({ children }) => {
  const dispatch = useAppDispatch();
  const currentTheme = useAppSelector(state => state.theme.current);
  const availableThemes = useAppSelector(state => state.theme.available);
  const customThemes = useAppSelector(state => state.theme.customThemes);
  
  const [systemPrefersDark, setSystemPrefersDark] = useState(false);
  
  // Detectar la preferencia del sistema al inicio
  useEffect(() => {
    const darkModePreference = window.matchMedia('(prefers-color-scheme: dark)');
    setSystemPrefersDark(darkModePreference.matches);
    
    // Escuchar cambios en la preferencia del sistema
    const listener = (e: MediaQueryListEvent) => {
      setSystemPrefersDark(e.matches);
      
      // Cambiar automáticamente el tema si está en modo automático
      if (currentTheme === 'auto') {
        applyTheme(e.matches ? 'dark' : 'light');
      }
    };
    
    // Agregar y limpiar el listener
    darkModePreference.addEventListener('change', listener);
    return () => darkModePreference.removeEventListener('change', listener);
  }, [currentTheme]);
  
  // Efecto para inicializar el tema
  useEffect(() => {
    // Intentar obtener el tema almacenado
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme) {
      // Verificar que el tema existe, si no, usar el predeterminado
      const themeExists = [...availableThemes, ...Object.keys(customThemes)].includes(savedTheme);
      
      if (themeExists) {
        dispatch(setTheme(savedTheme));
      } else {
        dispatch(setTheme('light'));
      }
    } else {
      // Si no hay tema guardado, usar la preferencia del sistema
      dispatch(setTheme(systemPrefersDark ? 'dark' : 'light'));
    }
  }, [dispatch, availableThemes, customThemes, systemPrefersDark]);
  
  // Aplicar el tema actual al DOM
  useEffect(() => {
    applyTheme(currentTheme === 'auto' ? (systemPrefersDark ? 'dark' : 'light') : currentTheme);
    
    // Guardar el tema en localStorage
    localStorage.setItem('theme', currentTheme);
    
    // Notificar a los microfrontends del cambio de tema
    eventBus.emit('theme:changed', {
      theme: currentTheme,
      isDark: currentTheme === 'dark' || (currentTheme === 'auto' && systemPrefersDark),
      timestamp: new Date().toISOString()
    });
  }, [currentTheme, systemPrefersDark]);
  
  // Función para aplicar el tema al DOM
  const applyTheme = (theme: ThemeName) => {
    // Eliminar todas las clases de tema anteriores
    document.documentElement.classList.remove(
      'theme-light', 'theme-dark', 'theme-yotsuba', 'theme-tomorrow', 'theme-futaba'
    );
    
    // Aplicar la clase del tema seleccionado
    document.documentElement.classList.add(`theme-${theme}`);
    
    // Actualizar la metaetiqueta de color del tema
    const metaThemeColor = document.querySelector('meta[name="theme-color"]');
    if (metaThemeColor) {
      metaThemeColor.setAttribute(
        'content', 
        theme === 'dark' ? '#1a1a1a' : 
        theme === 'yotsuba' ? '#f0e0d6' :
        theme === 'tomorrow' ? '#1d1f21' :
        theme === 'futaba' ? '#ffe' : '#ffffff'
      );
    }
    
    // Si es un tema personalizado, aplicar las variables CSS
    if (customThemes[theme]) {
      const cssVars = customThemes[theme].cssVariables || {};
      Object.entries(cssVars).forEach(([key, value]) => {
        document.documentElement.style.setProperty(key, value);
      });
    }
  };
  
  // Cambiar a un tema específico
  const changeTheme = (theme: ThemeName) => {
    dispatch(setTheme(theme));
  };
  
  // Alternar entre modo claro y oscuro
  const toggleDarkMode = () => {
    if (currentTheme === 'dark') {
      changeTheme('light');
    } else {
      changeTheme('dark');
    }
  };
  
  // Verificar si un tema es personalizado
  const isCustomTheme = (theme: ThemeName) => {
    return Object.keys(customThemes).includes(theme);
  };
  
  // Obtener metadatos de un tema
  const getThemeMetadata = (theme: ThemeName): ThemeMetadata | undefined => {
    if (isCustomTheme(theme)) {
      return customThemes[theme];
    }
    
    // Metadatos para temas integrados
    const builtInThemes: Record<string, ThemeMetadata> = {
      light: { 
        name: 'Light', 
        darkMode: false,
        description: 'Tema claro predeterminado'
      },
      dark: { 
        name: 'Dark', 
        darkMode: true,
        description: 'Tema oscuro predeterminado'
      },
      yotsuba: { 
        name: 'Yotsuba', 
        darkMode: false,
        description: 'Tema clásico inspirado en el diseño original'
      },
      tomorrow: { 
        name: 'Tomorrow', 
        darkMode: true,
        description: 'Tema oscuro con acentos de color'
      },
      futaba: { 
        name: 'Futaba', 
        darkMode: false,
        description: 'Tema retro con estilo minimalista'
      }
    };
    
    return builtInThemes[theme];
  };
  
  // Verificar la integridad del tema (para temas personalizados)
  const verifyThemeIntegrity = (theme: ThemeName): boolean => {
    if (!isCustomTheme(theme)) return true;
    
    // Aquí se implementaría la verificación criptográfica
    // por ejemplo, comprobar una firma digital del tema
    return true;
  };
  
  // Suscribirse a eventos de temas personalizados
  useEffect(() => {
    // Escuchar evento para agregar un tema personalizado
    const subscription = eventBus.on('theme:addCustom', (themeData) => {
      if (themeData.name && themeData.cssVariables) {
        dispatch(addCustomTheme({
          themeName: themeData.name,
          themeData
        }));
      }
    });
    
    return () => subscription.unsubscribe();
  }, [dispatch]);
  
  const contextValue: ThemeContextType = {
    currentTheme,
    changeTheme,
    toggleDarkMode,
    isCustomTheme,
    getThemeMetadata,
    verifyThemeIntegrity
  };
  
  return (
    <ThemeContext.Provider value={contextValue}>
      {children}
    </ThemeContext.Provider>
  );
};