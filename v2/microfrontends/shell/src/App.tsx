import React, { useEffect } from 'react';
import { Provider } from 'react-redux';
import { store } from './store';
import AppRouter from './routing/AppRouter';
import { eventBus } from './utils/eventBus';
import { microfrontendManager } from './utils/microfrontendManager';
import { ThemeProvider } from './providers/ThemeProvider';
import './App.css';

/**
 * Componente principal de la aplicación shell
 * 
 * Se encarga de:
 * 1. Inicializar el estado global (Redux)
 * 2. Configurar el bus de eventos
 * 3. Preparar el gestor de microfrontends
 * 4. Cargar configuraciones iniciales
 * 5. Proporcionar el sistema de temas
 */
const App: React.FC = () => {
  // Efecto para inicializar componentes globales
  useEffect(() => {
    // Configurar el gestor de microfrontends
    microfrontendManager.setManifestBaseUrl(import.meta.env.VITE_MICROFRONTEND_BASE_URL || '/microfrontends');
    
    // Registrar manejadores globales para eventos del sistema
    const handleGlobalError = (event: ErrorEvent) => {
      console.error('[Shell] Error global capturado:', event.error);
      eventBus.emit('error:global', {
        message: event.message,
        source: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        error: event.error,
      });
    };
    
    const handleUnhandledRejection = (event: PromiseRejectionEvent) => {
      console.error('[Shell] Promesa rechazada no manejada:', event.reason);
      eventBus.emit('error:unhandledRejection', {
        reason: event.reason,
      });
    };
    
    // Agregar escuchas de eventos globales
    window.addEventListener('error', handleGlobalError);
    window.addEventListener('unhandledrejection', handleUnhandledRejection);
    
    // Registrar la shell como iniciada en el bus de eventos
    eventBus.emit('shell:initialized', {
      timestamp: new Date().toISOString(),
      version: import.meta.env.VITE_APP_VERSION || '1.0.0',
    });
    
    // Función de limpieza
    return () => {
      window.removeEventListener('error', handleGlobalError);
      window.removeEventListener('unhandledrejection', handleUnhandledRejection);
    };
  }, []);
  
  return (
    <Provider store={store}>
      <ThemeProvider>
        <AppRouter />
      </ThemeProvider>
    </Provider>
  );
};

export default App;