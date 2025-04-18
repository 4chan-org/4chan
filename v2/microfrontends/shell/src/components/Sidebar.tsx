import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAppSelector } from '../store';
import { eventBus } from '../utils/eventBus';
import '../styles/Sidebar.css';

const Sidebar: React.FC = () => {
  const navigate = useNavigate();
  const [isExpanded, setIsExpanded] = useState(false);
  
  // Obtener datos del estado global
  const recentBoards = useAppSelector(state => state.navigation.recentBoards);
  const recentThreads = useAppSelector(state => state.navigation.recentThreads);
  const currentBoard = useAppSelector(state => state.navigation.currentBoard);
  const isAuthenticated = useAppSelector(state => state.auth.isAuthenticated);
  
  // Manejador para navegar a un hilo
  const handleThreadClick = (boardId: string, threadId: string) => {
    navigate(`/${boardId}/thread/${threadId}`);
    
    // Notificar a través del bus de eventos
    eventBus.emit('navigation:thread', { boardId, threadId });
  };
  
  // Manejador para navegar a un tablero
  const handleBoardClick = (boardId: string) => {
    navigate(`/${boardId}`);
    
    // Notificar a través del bus de eventos
    eventBus.emit('navigation:board', { boardId });
  };
  
  return (
    <aside className={`app-sidebar ${isExpanded ? 'expanded' : 'collapsed'}`}>
      {/* Toggle para móviles */}
      <button 
        className="sidebar-toggle"
        onClick={() => setIsExpanded(!isExpanded)}
        aria-label={isExpanded ? 'Colapsar barra lateral' : 'Expandir barra lateral'}
      >
        {isExpanded ? '◀' : '▶'}
      </button>
      
      <div className="sidebar-content">
        {/* Sección de tableros recientes */}
        <section className="sidebar-section">
          <h3 className="sidebar-heading">Tableros recientes</h3>
          <ul className="board-list">
            {recentBoards.length > 0 ? (
              recentBoards.map(board => (
                <li key={board} className={board === currentBoard ? 'active' : ''}>
                  <button 
                    onClick={() => handleBoardClick(board)}
                    className="board-link"
                  >
                    /{board}/
                  </button>
                </li>
              ))
            ) : (
              <li className="empty-list">
                <span>No hay tableros recientes</span>
              </li>
            )}
          </ul>
          <Link to="/boards" className="view-all-link">Ver todos</Link>
        </section>
        
        {/* Sección de hilos recientes */}
        <section className="sidebar-section">
          <h3 className="sidebar-heading">Hilos recientes</h3>
          <ul className="thread-list">
            {recentThreads.length > 0 ? (
              recentThreads.map(thread => (
                <li key={`${thread.boardId}-${thread.threadId}`}>
                  <button
                    onClick={() => handleThreadClick(thread.boardId, thread.threadId)}
                    className="thread-link"
                    title={thread.title}
                  >
                    <span className="thread-board">/{thread.boardId}/</span>
                    <span className="thread-title">{
                      thread.title.length > 25 
                        ? thread.title.substring(0, 22) + '...' 
                        : thread.title
                    }</span>
                  </button>
                </li>
              ))
            ) : (
              <li className="empty-list">
                <span>No hay hilos recientes</span>
              </li>
            )}
          </ul>
        </section>
        
        {/* Accesos rápidos */}
        <section className="sidebar-section">
          <h3 className="sidebar-heading">Accesos rápidos</h3>
          <div className="quick-links">
            <Link to="/catalog" className="quick-link">
              <span className="icon">📚</span>
              <span className="label">Catálogo</span>
            </Link>
            
            {currentBoard && (
              <Link to={`/${currentBoard}/post`} className="quick-link">
                <span className="icon">✏️</span>
                <span className="label">Publicar</span>
              </Link>
            )}
            
            <Link to="/rules" className="quick-link">
              <span className="icon">📋</span>
              <span className="label">Reglas</span>
            </Link>
            
            {isAuthenticated ? (
              <Link to="/settings" className="quick-link">
                <span className="icon">⚙️</span>
                <span className="label">Configuración</span>
              </Link>
            ) : (
              <Link to="/auth/login" className="quick-link">
                <span className="icon">🔑</span>
                <span className="label">Iniciar sesión</span>
              </Link>
            )}
          </div>
        </section>
        
        {/* Información */}
        <section className="sidebar-section sidebar-info">
          <div className="site-stats">
            <div className="stat">
              <span className="stat-value">1,234</span>
              <span className="stat-label">En línea</span>
            </div>
            <div className="stat">
              <span className="stat-value">42,567</span>
              <span className="stat-label">Hilos</span>
            </div>
          </div>
        </section>
      </div>
    </aside>
  );
};

export default Sidebar;