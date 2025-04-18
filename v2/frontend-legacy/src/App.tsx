import React, { Suspense, lazy } from 'react';
import { Routes, Route } from 'react-router-dom';
import { ErrorBoundary } from './components/ErrorBoundary';
import Layout from './components/Layout';
import LoadingSpinner from './components/LoadingSpinner';

// Carga perezosa de componentes para optimizar el tamaño del bundle
const HomePage = lazy(() => import('./containers/HomePage'));
const BoardPage = lazy(() => import('./containers/BoardPage'));
const ThreadPage = lazy(() => import('./containers/ThreadPage'));
const CatalogPage = lazy(() => import('./containers/CatalogPage'));
const NotFoundPage = lazy(() => import('./containers/NotFoundPage'));

const App: React.FC = () => {
  return (
    <ErrorBoundary>
      <Layout>
        <Suspense fallback={<LoadingSpinner />}>
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/:boardId" element={<BoardPage />} />
            <Route path="/:boardId/thread/:threadId" element={<ThreadPage />} />
            <Route path="/:boardId/catalog" element={<CatalogPage />} />
            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </Suspense>
      </Layout>
    </ErrorBoundary>
  );
};

export default App;
