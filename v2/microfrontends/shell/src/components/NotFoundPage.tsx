import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import '../styles/NotFoundPage.css';

const NotFoundPage: React.FC = () => {
  const navigate = useNavigate();
  
  // Volver a la página anterior
  const handleGoBack = () => {
    navigate(-1);
  };
  
  return (
    <div className="not-found-page">
      <div className="not-found-container">
        <h1 className="not-found-code">404</h1>
        <h2 className="not-found-title">Página no encontrada</h2>
        
        <p className="not-found-message">
          Lo sentimos, la página que estás buscando no existe o ha sido eliminada.
        </p>
        
        <div className="not-found-actions">
          <button 
            onClick={handleGoBack}
            className="back-button"
          >
            ← Volver atrás
          </button>
          
          <Link to="/" className="home-button">
            Ir al inicio
          </Link>
        </div>
        
        <div className="not-found-suggestions">
          <h3>¿Estás buscando uno de estos tableros populares?</h3>
          <div className="suggestions-links">
            <Link to="/b">Random</Link>
            <Link to="/a">Anime</Link>
            <Link to="/v">Videojuegos</Link>
            <Link to="/pol">Política</Link>
            <Link to="/g">Tecnología</Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default NotFoundPage;