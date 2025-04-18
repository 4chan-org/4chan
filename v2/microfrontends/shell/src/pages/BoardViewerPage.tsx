import React, { useEffect } from 'react';
import { useParams, useLocation } from 'react-router-dom';
import { useAppDispatch, useAppSelector } from '../store';
import { setCurrentBoard, addBreadcrumb } from '../store/slices/navigationSlice';
import MicrofrontendContainer from '../components/MicrofrontendContainer';
import { eventBus } from '../utils/eventBus';
import '../styles/BoardViewerPage.css';

const BoardViewerPage: React.FC = () => {
  const { boardId = 'b' } = useParams<{ boardId: string }>();
  const location = useLocation();
  const dispatch = useAppDispatch();
  
  // Obtener el estado actual
  const isAuthenticated = useAppSelector(state => state.auth.isAuthenticated);
  const userRole = useAppSelector(state => state.auth.user?.role);
  const currentBoard = useAppSelector(state => state.navigation.currentBoard);
  
  // Actualizar el tablero actual en el estado global
  useEffect(() => {
    if (boardId !== currentBoard) {
      dispatch(setCurrentBoard(boardId));
      
      // Actualizar migas de pan
      dispatch(addBreadcrumb({
        label: `/${boardId}/`,
        path: `/${boardId}`
      }));
    }
  }, [boardId, currentBoard, dispatch]);
  
  // Eventos que el shell escuchará del microfrontend
  const boardEvents = {
    'board:threadSelected': (data: { threadId: string; title: string }) => {
      console.log(`Thread selected: ${data.threadId}`);
      // El shell puede decidir cómo manejar este evento
      // Por ejemplo, podría navegar al hilo o actualizar algún estado
    },
    'board:needsModeration': (data: { threadId: string; reason: string }) => {
      // Solo los moderadores deberían recibir estos eventos
      if (isAuthenticated && (userRole === 'moderator' || userRole === 'admin')) {
        console.log(`Thread ${data.threadId} needs moderation: ${data.reason}`);
        // El shell podría mostrar una notificación o actualizar alguna UI
      }
    }
  };
  
  // Props que se pasarán al microfrontend
  const boardProps = {
    boardId,
    mode: new URLSearchParams(location.search).get('mode') || 'list',
    isAuthenticated,
    userRole,
    // Callback que el microfrontend puede usar
    onThreadCreate: (threadId: string) => {
      console.log(`Thread created: ${threadId}`);
      eventBus.emit('notification:show', {
        type: 'success',
        message: 'Hilo creado correctamente',
        duration: 3000
      });
    }
  };
  
  return (
    <div className="board-viewer-page">
      <h1 className="board-title">/{boardId}/</h1>
      
      <MicrofrontendContainer
        name="board-viewer"
        props={boardProps}
        events={boardEvents}
        fallback={
          <div className="board-loading">
            <h2>Cargando tablero /{boardId}/...</h2>
            <div className="loading-spinner"></div>
          </div>
        }
        errorFallback={
          <div className="board-error">
            <h2>Error al cargar el tablero /{boardId}/</h2>
            <p>Por favor, intenta recargar la página.</p>
            <button 
              onClick={() => window.location.reload()}
              className="reload-button"
            >
              Recargar página
            </button>
          </div>
        }
      />
    </div>
  );
};

export default BoardViewerPage;