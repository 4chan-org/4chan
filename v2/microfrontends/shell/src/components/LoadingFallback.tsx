import React from 'react';
import '../styles/LoadingFallback.css';

interface LoadingFallbackProps {
  type?: 'board' | 'catalog' | 'thread' | 'media' | 'post' | 'auth' | 'settings' | 'moderation';
  message?: string;
}

/**
 * Componente de carga que muestra una animación de carga 
 * adaptada al tipo de contenido que se está cargando
 */
const LoadingFallback: React.FC<LoadingFallbackProps> = ({ 
  type = 'board',
  message
}) => {
  // Mensajes predeterminados según el tipo
  const defaultMessages: Record<string, string> = {
    board: 'Cargando tablero...',
    catalog: 'Cargando catálogo...',
    thread: 'Cargando hilo...',
    media: 'Cargando contenido multimedia...',
    post: 'Preparando editor...',
    auth: 'Cargando autenticación...',
    settings: 'Cargando configuración...',
    moderation: 'Cargando herramientas de moderación...',
  };

  // Icono según el tipo de contenido
  const getIconForType = () => {
    switch (type) {
      case 'board':
      case 'catalog':
        return '📋';
      case 'thread':
        return '💬';
      case 'media':
        return '🖼️';
      case 'post':
        return '✏️';
      case 'auth':
        return '🔐';
      case 'settings':
        return '⚙️';
      case 'moderation':
        return '🛡️';
      default:
        return '⏳';
    }
  };

  return (
    <div className="loading-fallback">
      <div className="loading-spinner"></div>
      <div className="loading-icon">{getIconForType()}</div>
      <p className="loading-message">
        {message || defaultMessages[type] || 'Cargando...'}
      </p>
    </div>
  );
};

export default LoadingFallback;