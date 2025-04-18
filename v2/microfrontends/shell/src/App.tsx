import { BrowserRouter as Router, Routes, Route } from 'react-router-dom'
import { Suspense, lazy } from 'react'
import './App.css'

// Placeholder components until the actual microfrontends are loaded
const BoardViewerPlaceholder = () => <div>Loading Board Viewer...</div>
const CatalogViewerPlaceholder = () => <div>Loading Catalog Viewer...</div>
const PostCreatorPlaceholder = () => <div>Loading Post Creator...</div>
const AuthPlaceholder = () => <div>Loading Auth...</div>
const MediaViewerPlaceholder = () => <div>Loading Media Viewer...</div>
const ModerationPlaceholder = () => <div>Loading Moderation Tools...</div>

// Dynamic imports for microfrontends
const BoardViewer = lazy(() => import('./loaders/board-viewer-loader'))
const CatalogViewer = lazy(() => import('./loaders/catalog-viewer-loader'))
const PostCreator = lazy(() => import('./loaders/post-creator-loader'))
const Auth = lazy(() => import('./loaders/auth-loader'))
const MediaViewer = lazy(() => import('./loaders/media-viewer-loader'))
const Moderation = lazy(() => import('./loaders/moderation-loader'))

function App() {
  return (
    <Router>
      <div className="app-container">
        <header className="app-header">
          <h1>Imageboard Application</h1>
          <nav>
            <ul>
              <li><a href="/">Home</a></li>
              <li><a href="/catalog">Catalog</a></li>
              <li><a href="/auth">Login/Register</a></li>
            </ul>
          </nav>
        </header>
        
        <main className="app-content">
          <Routes>
            <Route path="/" element={
              <Suspense fallback={<BoardViewerPlaceholder />}>
                <BoardViewer />
              </Suspense>
            } />
            <Route path="/catalog" element={
              <Suspense fallback={<CatalogViewerPlaceholder />}>
                <CatalogViewer />
              </Suspense>
            } />
            <Route path="/post" element={
              <Suspense fallback={<PostCreatorPlaceholder />}>
                <PostCreator />
              </Suspense>
            } />
            <Route path="/auth" element={
              <Suspense fallback={<AuthPlaceholder />}>
                <Auth />
              </Suspense>
            } />
            <Route path="/media/:id" element={
              <Suspense fallback={<MediaViewerPlaceholder />}>
                <MediaViewer />
              </Suspense>
            } />
            <Route path="/mod/*" element={
              <Suspense fallback={<ModerationPlaceholder />}>
                <Moderation />
              </Suspense>
            } />
          </Routes>
        </main>
        
        <footer className="app-footer">
          <p>© 2025 Imageboard Application</p>
        </footer>
      </div>
    </Router>
  )
}

export default App