import React, { Component, ErrorInfo, ReactNode } from 'react';
import { eventBus } from '../utils/eventBus';
import '../styles/ErrorBoundary.css';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

/**
 * Componente que captura errores en el árbol de componentes
 * y muestra una interfaz de recuperación en lugar de fallar
 */
class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null
    };
  }

  static getDerivedStateFromError(error: Error): State {
    // Actualiza el estado para mostrar la UI de fallback
    return {
      hasError: true,
      error,
      errorInfo: null
    };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
    // También puedes registrar el error en un servicio de reportes
    console.error('Error capturado en ErrorBoundary:', error, errorInfo);
    
    // Actualizar estado con información del error
    this.setState({
      errorInfo
    });
    
    // Emitir evento para que otros componentes puedan reaccionar
    eventBus.emit('error:boundary', { error, errorInfo });
  }

  handleReset = (): void => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null
    });
  }

  render(): ReactNode {
    if (this.state.hasError) {
      // Si se proporciona un fallback personalizado, usarlo
      if (this.props.fallback) {
        return this.props.fallback;
      }
      
      // Interfaz de error predeterminada
      return (
        <div className="error-boundary">
          <div className="error-container">
            <h2>Algo salió mal</h2>
            <p>
              Ha ocurrido un error inesperado. Puedes intentar recargar la página
              o regresar a la página principal.
            </p>
            
            <div className="error-actions">
              <button 
                onClick={this.handleReset}
                className="btn btn-primary"
              >
                Intentar de nuevo
              </button>
              <button 
                onClick={() => window.location.href = '/'}
                className="btn btn-secondary"
              >
                Ir a página principal
              </button>
            </div>
            
            <details className="error-details">
              <summary>Detalles técnicos</summary>
              <pre>{this.state.error?.toString()}</pre>
              <pre>{this.state.errorInfo?.componentStack}</pre>
            </details>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;