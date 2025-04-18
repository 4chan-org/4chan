import React, { useEffect, useRef, useState } from 'react';
import { useAppDispatch, useAppSelector } from '../hooks/reduxHooks';
import { microfrontendManager } from '../utils/microfrontendManager';
import { setLoading, setLoaded, setError } from '../store/slices/microfrontendSlice';
import { eventBus } from '../utils/eventBus';
import LoadingFallback from './LoadingFallback';
import ErrorDisplay from './ErrorDisplay';
import '../styles/MicrofrontendContainer.css';

interface MicrofrontendContainerProps {
  name: string;
  fallback?: React.ReactNode;
  errorFallback?: React.ReactNode;
  props?: Record<string, any>;
  events?: Record<string, (...args: any[]) => void>;
  loadImmediately?: boolean;
  onMounted?: () => void;
  onError?: (error: Error) => void;
  retryOnError?: boolean;
}

/**
 * Componente que sirve como contenedor para cargar un microfrontend
 * 
 * Se encarga de cargar, montar y gestionar el ciclo de vida del microfrontend
 */
const MicrofrontendContainer: React.FC<MicrofrontendContainerProps> = ({
  name,
  fallback,
  errorFallback,
  props = {},
  events = {},
  loadImmediately = true,
  onMounted,
  onError,
  retryOnError = false,
}) => {
  const containerRef = useRef<HTMLDivElement>(null);
  const [localError, setLocalError] = useState<string | null>(null);
  const [retryCount, setRetryCount] = useState(0);
  const dispatch = useAppDispatch();
  
  // Obtener estado del microfrontend desde Redux
  const isLoading = useAppSelector(state => state.microfrontend.loading[name]) || false;
  const isLoaded = useAppSelector(state => state.microfrontend.loaded[name]) || false;
  const stateError = useAppSelector(state => state.microfrontend.errors[name]);
  
  const loadMicrofrontend = async () => {
    if (!containerRef.current) return;
    
    dispatch(setLoading({ name, loading: true }));
    setLocalError(null);
    
    try {
      // Notificar que estamos cargando
      eventBus.emit('microfrontend:loading', { name });
      
      // Cargar el microfrontend
      await microfrontendManager.loadMicrofrontend(name);
      
      // Montar el microfrontend en el contenedor
      await microfrontendManager.mountMicrofrontend(
        name, 
        containerRef.current!, 
        {
          ...props,
          // Inyectar eventBus para comunicación
          eventBus,
          // Metadatos adicionales
          mountTimestamp: new Date().toISOString(),
          shellVersion: import.meta.env.VITE_APP_VERSION || '1.0.0',
        }
      );
      
      dispatch(setLoaded({ name, loaded: true }));
      dispatch(setLoading({ name, loading: false }));
      dispatch(setError({ name, error: '' }));
      
      // Notificar que el microfrontend está montado
      eventBus.emit('microfrontend:mounted', { name });
      
      // Ejecutar callback personalizado si existe
      if (onMounted) {
        onMounted();
      }
    } catch (err: any) {
      handleError(err);
    }
  };
  
  // Función para manejar errores
  const handleError = (err: any) => {
    const errorMessage = err?.message || 'Error desconocido al cargar el microfrontend';
    setLocalError(errorMessage);
    dispatch(setError({ name, error: errorMessage }));
    dispatch(setLoading({ name, loading: false }));
    console.error(`Error cargando microfrontend ${name}:`, err);
    eventBus.emit('microfrontend:error', { name, error: err });
    
    // Ejecutar callback personalizado si existe
    if (onError) {
      onError(err instanceof Error ? err : new Error(errorMessage));
    }
    
    // Intentar reintentar si está habilitado
    if (retryOnError && retryCount < 3) {
      const nextRetryDelay = Math.pow(2, retryCount) * 1000; // Exponential backoff
      console.log(`Reintentando cargar ${name} en ${nextRetryDelay}ms...`);
      
      setTimeout(() => {
        setRetryCount(prevCount => prevCount + 1);
        loadMicrofrontend();
      }, nextRetryDelay);
    }
  };
  
  // Efecto para cargar el microfrontend
  useEffect(() => {
    if (loadImmediately && !isLoaded && !isLoading) {
      loadMicrofrontend();
    }
    
    // Suscribirse a eventos del bus
    const subscriptions = Object.entries(events).map(([eventName, handler]) => {
      return eventBus.on(eventName, handler);
    });
    
    // Cleanup function
    return () => {
      // Desuscribirse de eventos
      subscriptions.forEach(sub => sub.unsubscribe());
      
      // Desmontar el microfrontend si está cargado
      if (isLoaded && containerRef.current) {
        microfrontendManager.unmountMicrofrontend(name, containerRef.current);
        eventBus.emit('microfrontend:unmounted', { name });
      }
    };
  }, [name, isLoaded, isLoading, loadImmediately, JSON.stringify(props), JSON.stringify(events)]);
  
  // Renderizar componente de error si hay un error
  if (localError || stateError) {
    if (errorFallback) {
      return <>{errorFallback}</>;
    }
    
    return (
      <ErrorDisplay 
        title={`Error al cargar ${name}`}
        message={localError || stateError || 'Error desconocido'}
        onRetry={() => {
          setLocalError(null);
          dispatch(setError({ name, error: '' }));
          dispatch(setLoaded({ name, loaded: false }));
          loadMicrofrontend();
        }}
      />
    );
  }
  
  // Mostrar el loader mientras carga
  if (isLoading && !isLoaded) {
    return fallback ? <>{fallback}</> : <LoadingFallback name={name} />;
  }
  
  // Contenedor que alojará el microfrontend
  return (
    <div 
      ref={containerRef} 
      className={`microfrontend-container ${name}-container`}
      data-microfrontend={name}
      aria-label={`Contenedor del microfrontend ${name}`}
    />
  );
};

export default MicrofrontendContainer;