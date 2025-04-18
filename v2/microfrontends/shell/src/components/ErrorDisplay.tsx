import React from 'react';
import '../styles/ErrorDisplay.css';

interface ErrorDisplayProps {
  title: string;
  message: string;
  onRetry?: () => void;
  details?: string;
  code?: number;
}

/**
 * Componente para mostrar errores de forma consistente
 * Proporciona una interfaz amigable para mostrar errores y permitir al usuario reintentar
 */
const ErrorDisplay: React.FC<ErrorDisplayProps> = ({
  title,
  message,
  onRetry,
  details,
  code
}) => {
  const hasDetails = Boolean(details);
  const [showDetails, setShowDetails] = React.useState(false);

  return (
    <div className="error-display" role="alert" aria-live="assertive">
      <div className="error-icon">
        <svg 
          xmlns="http://www.w3.org/2000/svg" 
          viewBox="0 0 24 24" 
          fill="none" 
          stroke="currentColor" 
          strokeWidth="2" 
          strokeLinecap="round" 
          strokeLinejoin="round"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
      </div>
      
      <div className="error-content">
        <h3 className="error-title">
          {title}
          {code && <span className="error-code">Código: {code}</span>}
        </h3>
        
        <p className="error-message">{message}</p>
        
        {hasDetails && (
          <div className="error-details-section">
            <button 
              type="button" 
              className="details-toggle" 
              onClick={() => setShowDetails(!showDetails)}
              aria-expanded={showDetails}
              aria-controls="error-details"
            >
              {showDetails ? 'Ocultar detalles' : 'Mostrar detalles'}
            </button>
            
            {showDetails && (
              <pre id="error-details" className="error-details">
                {details}
              </pre>
            )}
          </div>
        )}
        
        <div className="error-actions">
          {onRetry && (
            <button 
              type="button" 
              className="retry-button" 
              onClick={onRetry}
              aria-label="Intentar nuevamente"
            >
              Intentar nuevamente
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default ErrorDisplay;