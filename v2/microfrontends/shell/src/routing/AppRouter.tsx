import React, { Suspense, lazy, useEffect } from 'react';
import { 
  BrowserRouter, 
  Routes, 
  Route, 
  Navigate,
  useLocation,
} from 'react-router-dom';
import { useAppSelector, useAppDispatch } from '../hooks/reduxHooks';
import { setPreviousRoute } from '../store/slices/navigationSlice';
import { eventBus } from '../utils/eventBus';

// Componentes de Layout y Error
import MainLayout from '../layouts/MainLayout';
import LoadingFallback from '../components/LoadingFallback';
import ErrorBoundary from '../components/ErrorBoundary';
import NotFoundPage from '../pages/NotFoundPage';

// HOC para proteger rutas
import ProtectedRoute from '../components/ProtectedRoute';

// Componentes cargados de forma lazy (code splitting)
const BoardViewerPage = lazy(() => import('../pages/BoardViewerPage'));
const CatalogViewerPage = lazy(() => import('../pages/CatalogViewerPage'));
const ThreadViewerPage = lazy(() => import('../pages/ThreadViewerPage'));
const PostCreatorPage = lazy(() => import('../pages/PostCreatorPage'));
const AuthPage = lazy(() => import('../pages/AuthPage'));
const MediaViewerPage = lazy(() => import('../pages/MediaViewerPage'));
const ModeratorDashboard = lazy(() => import('../pages/ModeratorDashboard'));
const UserSettingsPage = lazy(() => import('../pages/UserSettingsPage'));
const BoardSettingsPage = lazy(() => import('../pages/BoardSettingsPage'));
const HomePage = lazy(() => import('../pages/HomePage'));
const AccessibilitySettingsPage = lazy(() => import('../pages/AccessibilitySettingsPage'));

// Componente para rastrear cambios de ruta
const RouteTracker = () => {
  const location = useLocation();
  const dispatch = useAppDispatch();
  
  useEffect(() => {
    // Guardar ruta anterior en Redux
    dispatch(setPreviousRoute(location.pathname));
    
    // Emitir evento de cambio de ruta para que los microfrontends puedan reaccionar
    eventBus.emit('router:locationChange', {
      pathname: location.pathname,
      search: location.search,
      hash: location.hash,
      timestamp: Date.now()
    });
    
    // Acción de scroll al inicio cuando cambia la ruta, a menos que haya un hash
    if (!location.hash) {
      window.scrollTo(0, 0);
    }
  }, [location, dispatch]);
  
  return null;
};

// Componente principal de enrutamiento
const AppRouter: React.FC = () => {
  const isAuthenticated = useAppSelector(state => state.auth.isAuthenticated);
  const userRole = useAppSelector(state => state.auth.user?.role || 'guest');
  
  return (
    <BrowserRouter>
      <RouteTracker />
      
      <ErrorBoundary>
        <Routes>
          {/* Rutas públicas dentro del layout principal */}
          <Route path="/" element={<MainLayout />}>
            {/* Página principal */}
            <Route index element={
              <Suspense fallback={<LoadingFallback name="home" />}>
                <HomePage />
              </Suspense>
            } />
            
            {/* Rutas de tableros */}
            <Route path="/:boardId" element={
              <Suspense fallback={<LoadingFallback name="board" />}>
                <BoardViewerPage />
              </Suspense>
            } />
            
            <Route path="/:boardId/catalog" element={
              <Suspense fallback={<LoadingFallback name="catalog" />}>
                <CatalogViewerPage />
              </Suspense>
            } />
            
            <Route path="/:boardId/thread/:threadId" element={
              <Suspense fallback={<LoadingFallback name="thread" />}>
                <ThreadViewerPage />
              </Suspense>
            } />
            
            {/* Visor de medios */}
            <Route path="/media/:mediaId" element={
              <Suspense fallback={<LoadingFallback name="media" />}>
                <MediaViewerPage />
              </Suspense>
            } />
            
            {/* Creador de publicaciones */}
            <Route path="/:boardId/post" element={
              <Suspense fallback={<LoadingFallback name="post" />}>
                <PostCreatorPage mode="new" />
              </Suspense>
            } />
            
            <Route path="/:boardId/thread/:threadId/reply" element={
              <Suspense fallback={<LoadingFallback name="post" />}>
                <PostCreatorPage mode="reply" />
              </Suspense>
            } />
            
            {/* Autenticación */}
            <Route path="/auth/*" element={
              <Suspense fallback={<LoadingFallback name="auth" />}>
                <AuthPage />
              </Suspense>
            } />
            
            {/* Configuración del usuario (protegida) */}
            <Route path="/settings" element={
              <ProtectedRoute 
                isAllowed={isAuthenticated}
                redirectTo="/auth/login?returnTo=/settings"
              >
                <Suspense fallback={<LoadingFallback name="settings" />}>
                  <UserSettingsPage />
                </Suspense>
              </ProtectedRoute>
            } />
            
            {/* Configuración de accesibilidad (pública) */}
            <Route path="/accessibility" element={
              <Suspense fallback={<LoadingFallback name="accessibility" />}>
                <AccessibilitySettingsPage />
              </Suspense>
            } />
            
            {/* Rutas de moderación (solo para admin y moderadores) */}
            <Route path="/mod/*" element={
              <ProtectedRoute 
                isAllowed={isAuthenticated && ['admin', 'moderator', 'janitor'].includes(userRole)}
                redirectTo="/auth/login?returnTo=/mod"
              >
                <Suspense fallback={<LoadingFallback name="moderation" />}>
                  <ModeratorDashboard />
                </Suspense>
              </ProtectedRoute>
            } />
            
            {/* Configuración de tableros (solo para admin) */}
            <Route path="/admin/boards/:boardId" element={
              <ProtectedRoute 
                isAllowed={isAuthenticated && userRole === 'admin'}
                redirectTo="/auth/login?returnTo=/admin"
              >
                <Suspense fallback={<LoadingFallback name="settings" />}>
                  <BoardSettingsPage />
                </Suspense>
              </ProtectedRoute>
            } />
            
            {/* Redireccionamientos para URLs legacy */}
            <Route path="/b/*" element={<Navigate to="/b" replace />} />
            <Route path="/pol/*" element={<Navigate to="/pol" replace />} />
            
            {/* Página 404 para rutas no encontradas */}
            <Route path="*" element={<NotFoundPage />} />
          </Route>
        </Routes>
      </ErrorBoundary>
    </BrowserRouter>
  );
};

export default AppRouter;