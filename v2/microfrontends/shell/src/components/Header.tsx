import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAppSelector, useAppDispatch } from '../store';
import { logout } from '../store/slices/authSlice';
import { setTheme } from '../store/slices/themeSlice';
import { eventBus } from '../utils/eventBus';
import '../styles/Header.css';

const Header: React.FC = () => {
  const navigate = useNavigate();
  const dispatch = useAppDispatch();
  const [searchQuery, setSearchQuery] = useState('');
  const [showMobileMenu, setShowMobileMenu] = useState(false);
  
  // Obtener estado de Redux
  const isAuthenticated = useAppSelector(state => state.auth.isAuthenticated);
  const user = useAppSelector(state => state.auth.user);
  const currentBoard = useAppSelector(state => state.navigation.currentBoard);
  const recentBoards = useAppSelector(state => state.navigation.recentBoards);
  const themeMode = useAppSelector(state => state.theme.mode);
  
  // Cambiar tema
  const handleThemeChange = (theme: 'light' | 'dark' | 'yotsuba' | 'tomorrow') => {
    dispatch(setTheme(theme));
    // Notificar a microfrontends del cambio de tema
    eventBus.emit('theme:changed', { themeMode: theme });
  };
  
  // Realizar búsqueda
  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      // Notificar a microfrontends sobre la búsqueda
      eventBus.emit('search:query', { 
        query: searchQuery, 
        board: currentBoard || 'all' 
      });
      
      // Navegar a la página de resultados
      navigate(`/search?q=${encodeURIComponent(searchQuery)}&board=${currentBoard || 'all'}`);
      
      // Limpiar el campo de búsqueda
      setSearchQuery('');
    }
  };
  
  // Cerrar sesión
  const handleLogout = () => {
    dispatch(logout());
    eventBus.emit('auth:logout');
    navigate('/');
  };
  
  return (
    <header className="app-header">
      <div className="header-container">
        {/* Logo y título */}
        <div className="logo-container">
          <Link to="/" className="logo">
            <img src="/assets/logo.png" alt="Imageboard" />
            <span className="site-title">Imageboard</span>
          </Link>
        </div>
        
        {/* Botón de menú móvil */}
        <button 
          className="mobile-menu-button"
          onClick={() => setShowMobileMenu(!showMobileMenu)}
          aria-label="Toggle menu"
        >
          <span className="hamburger-icon">☰</span>
        </button>
        
        {/* Navegación principal */}
        <nav className={`main-nav ${showMobileMenu ? 'mobile-open' : ''}`}>
          <ul className="nav-links">
            <li>
              <Link to="/">Inicio</Link>
            </li>
            <li className="dropdown">
              <button className="dropdown-toggle">
                Tableros <span className="dropdown-arrow">▼</span>
              </button>
              <div className="dropdown-menu">
                <div className="dropdown-section">
                  <h4>Recientes</h4>
                  <ul>
                    {recentBoards.map(board => (
                      <li key={board}>
                        <Link to={`/${board}`}>/{board}/</Link>
                      </li>
                    ))}
                  </ul>
                </div>
                <div className="dropdown-section">
                  <h4>Populares</h4>
                  <ul>
                    <li><Link to="/b">Random</Link></li>
                    <li><Link to="/a">Anime</Link></li>
                    <li><Link to="/v">Videojuegos</Link></li>
                    <li><Link to="/g">Tecnología</Link></li>
                  </ul>
                </div>
                <div className="dropdown-footer">
                  <Link to="/boards" className="view-all">Ver todos los tableros</Link>
                </div>
              </div>
            </li>
            <li>
              <Link to="/catalog">Catálogo</Link>
            </li>
            {currentBoard && (
              <li>
                <Link to={`/${currentBoard}/post`} className="post-button">Publicar</Link>
              </li>
            )}
          </ul>
        </nav>
        
        {/* Búsqueda */}
        <div className="search-container">
          <form onSubmit={handleSearch}>
            <input
              type="search"
              placeholder="Buscar..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="search-input"
            />
            <button type="submit" className="search-button">
              🔍
            </button>
          </form>
        </div>
        
        {/* Usuario y tema */}
        <div className="user-controls">
          {/* Selector de tema */}
          <div className="theme-selector dropdown">
            <button className="dropdown-toggle theme-button" title="Cambiar tema">
              {themeMode === 'light' ? '☀️' : themeMode === 'dark' ? '🌙' : '🎨'}
            </button>
            <div className="dropdown-menu">
              <button 
                onClick={() => handleThemeChange('light')}
                className={`theme-option ${themeMode === 'light' ? 'active' : ''}`}
              >
                ☀️ Claro
              </button>
              <button 
                onClick={() => handleThemeChange('dark')}
                className={`theme-option ${themeMode === 'dark' ? 'active' : ''}`}
              >
                🌙 Oscuro
              </button>
              <button 
                onClick={() => handleThemeChange('yotsuba')}
                className={`theme-option ${themeMode === 'yotsuba' ? 'active' : ''}`}
              >
                🍃 Yotsuba
              </button>
              <button 
                onClick={() => handleThemeChange('tomorrow')}
                className={`theme-option ${themeMode === 'tomorrow' ? 'active' : ''}`}
              >
                🌈 Tomorrow
              </button>
            </div>
          </div>
          
          {/* Área de usuario */}
          <div className="user-area">
            {isAuthenticated ? (
              <div className="user-dropdown dropdown">
                <button className="dropdown-toggle user-button">
                  <span className="user-name">{user?.username}</span>
                  <span className="dropdown-arrow">▼</span>
                </button>
                <div className="dropdown-menu">
                  <Link to="/settings" className="dropdown-item">
                    ⚙️ Configuración
                  </Link>
                  {(user?.role === 'moderator' || user?.role === 'admin') && (
                    <Link to="/mod" className="dropdown-item">
                      🛡️ Moderación
                    </Link>
                  )}
                  <button onClick={handleLogout} className="dropdown-item logout-button">
                    🚪 Cerrar sesión
                  </button>
                </div>
              </div>
            ) : (
              <div className="auth-buttons">
                <Link to="/auth/login" className="login-button">
                  Iniciar sesión
                </Link>
                <Link to="/auth/register" className="register-button">
                  Registrarse
                </Link>
              </div>
            )}
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;