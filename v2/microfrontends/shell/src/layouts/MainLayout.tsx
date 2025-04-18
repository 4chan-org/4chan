import { Outlet } from 'react-router-dom';
import { useEffect } from 'react';
import { useAppSelector } from '../store';
import Header from '../components/Header';
import Footer from '../components/Footer';
import Sidebar from '../components/Sidebar';
import { eventBus } from '../utils/eventBus';
import '../styles/MainLayout.css';

// Temas disponibles
const themes = {
  light: '/styles/themes/light.css',
  dark: '/styles/themes/dark.css',
  yotsuba: '/styles/themes/yotsuba.css',
  tomorrow: '/styles/themes/tomorrow.css',
};

const MainLayout: React.FC = () => {
  const { mode: themeMode, highContrast, fontSize } = useAppSelector(state => state.theme);

  // Efecto para cargar y aplicar el tema actual
  useEffect(() => {
    // Eliminar temas existentes
    const existingThemeLink = document.getElementById('theme-stylesheet');
    if (existingThemeLink) {
      existingThemeLink.remove();
    }

    // Crear y agregar el nuevo tema
    const themeLink = document.createElement('link');
    themeLink.id = 'theme-stylesheet';
    themeLink.rel = 'stylesheet';
    themeLink.href = themes[themeMode] || themes.yotsuba;
    document.head.appendChild(themeLink);

    // Aplicar configuración de fuente y alto contraste
    document.documentElement.style.fontSize = `${fontSize}px`;
    document.body.classList.toggle('high-contrast', highContrast);

    // Notificar a los microfrontends sobre el cambio de tema
    eventBus.emit('theme:changed', { themeMode, highContrast, fontSize });

    return () => {
      if (themeLink.parentNode) {
        themeLink.parentNode.removeChild(themeLink);
      }
    };
  }, [themeMode, highContrast, fontSize]);

  return (
    <div className="main-layout">
      <Header />
      
      <div className="main-content">
        <Sidebar />
        <main className="content-area">
          <Outlet />
        </main>
      </div>
      
      <Footer />
    </div>
  );
};

export default MainLayout;