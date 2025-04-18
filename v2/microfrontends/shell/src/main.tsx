import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import { eventBus } from './utils/eventBus';
import './index.css';

// Función para inicializar la aplicación
const initializeApp = () => {
  // Buscar el elemento raíz
  const rootElement = document.getElementById('root');
  if (!rootElement) {
    console.error('Elemento raíz no encontrado. Asegúrese de que existe un elemento con id="root".');
    return;
  }

  // Crear la raíz de React
  const root = ReactDOM.createRoot(rootElement);
  
  // Notificar en el bus de eventos que estamos iniciando
  eventBus.emit('app:initializing', {
    timestamp: new Date().toISOString(),
  });
  
  // Renderizar la aplicación
  root.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
  
  // Notificar que la aplicación está montada
  eventBus.emit('app:mounted', {
    timestamp: new Date().toISOString(),
  });
};

// Iniciar la aplicación
initializeApp();

// Exportar el bus de eventos a window para facilitar debugging
// Solo en desarrollo
if (import.meta.env.DEV) {
  (window as any).__eventBus = eventBus;
}