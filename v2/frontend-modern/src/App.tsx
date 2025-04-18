import React, { Suspense, lazy, useEffect } from 'react';
import { Routes, Route, useLocation } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';

import MainLayout from '@/layouts/MainLayout';
import LoadingFallback from '@/components/LoadingFallback';
import ProtectedRoute from '@/components/ProtectedRoute';
import { useAppDispatch } from '@/hooks/redux';
import { checkAuth } from '@/features/auth/authSlice';

// Lazy-loaded components for better performance
const HomePage = lazy(() => import('@/pages/HomePage'));
const BoardsPage = lazy(() => import('@/pages/BoardsPage'));
const BoardPage = lazy(() => import('@/pages/BoardPage'));
const ThreadPage = lazy(() => import('@/pages/ThreadPage'));
const CatalogPage = lazy(() => import('@/pages/CatalogPage'));
const ModPage = lazy(() => import('@/pages/ModPage'));
const AdminPage = lazy(() => import('@/pages/AdminPage'));
const SignInPage = lazy(() => import('@/pages/SignInPage'));
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage'));

const App: React.FC = () => {
  const location = useLocation();
  const dispatch = useAppDispatch();
  
  // Check authentication status on app start
  useEffect(() => {
    dispatch(checkAuth());
  }, [dispatch]);

  return (
    <AnimatePresence mode="wait">
      <Suspense fallback={<LoadingFallback />}>
        <Routes location={location} key={location.pathname}>
          <Route path="/" element={<MainLayout />}>
            <Route index element={<HomePage />} />
            <Route path="boards" element={<BoardsPage />} />
            <Route path="board/:boardId" element={<BoardPage />} />
            <Route path="board/:boardId/thread/:threadId" element={<ThreadPage />} />
            <Route path="board/:boardId/catalog" element={<CatalogPage />} />
            
            {/* Protected routes */}
            <Route element={<ProtectedRoute allowedRoles={['JANITOR', 'MODERATOR', 'ADMIN']} />}>
              <Route path="mod/*" element={<ModPage />} />
            </Route>
            
            <Route element={<ProtectedRoute allowedRoles={['ADMIN']} />}>
              <Route path="admin/*" element={<AdminPage />} />
            </Route>
            
            {/* Authentication */}
            <Route path="signin" element={<SignInPage />} />
            
            {/* 404 */}
            <Route path="*" element={<NotFoundPage />} />
          </Route>
        </Routes>
      </Suspense>
    </AnimatePresence>
  );
};

export default App;