import React from 'react';
import { Link } from 'react-router-dom';
import '../styles/Footer.css';

const Footer: React.FC = () => {
  const currentYear = new Date().getFullYear();
  
  return (
    <footer className="app-footer">
      <div className="footer-container">
        <div className="footer-section">
          <h4>Enlaces rápidos</h4>
          <ul className="footer-links">
            <li><Link to="/">Inicio</Link></li>
            <li><Link to="/catalog">Catálogo</Link></li>
            <li><Link to="/boards">Tableros</Link></li>
            <li><Link to="/rules">Reglas</Link></li>
            <li><Link to="/faq">FAQ</Link></li>
          </ul>
        </div>
        
        <div className="footer-section">
          <h4>Legal</h4>
          <ul className="footer-links">
            <li><Link to="/terms">Términos de uso</Link></li>
            <li><Link to="/privacy">Política de privacidad</Link></li>
            <li><Link to="/dmca">DMCA</Link></li>
            <li><Link to="/contact">Contacto</Link></li>
          </ul>
        </div>
        
        <div className="footer-section">
          <h4>Información</h4>
          <ul className="footer-links">
            <li><Link to="/about">Acerca de</Link></li>
            <li><Link to="/advertise">Publicidad</Link></li>
            <li><Link to="/support">Soporte</Link></li>
            <li><Link to="/donate">Donar</Link></li>
          </ul>
        </div>
      </div>
      
      <div className="footer-bottom">
        <p className="copyright">
          &copy; {currentYear} Imageboard. Todos los derechos reservados.
        </p>
        <p className="disclaimer">
          La administración no es responsable de los contenidos publicados por los usuarios.
        </p>
      </div>
    </footer>
  );
};

export default Footer;