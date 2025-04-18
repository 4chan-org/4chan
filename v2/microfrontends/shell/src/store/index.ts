import { configureStore } from '@reduxjs/toolkit';
import { combineReducers } from 'redux';

// Importar reducers
import authReducer from './slices/authSlice';
import navigationReducer from './slices/navigationSlice';
import themeReducer from './slices/themeSlice';
import microfrontendReducer from './slices/microfrontendSlice';
import notificationsReducer from './slices/notificationsSlice';
import accessibilityReducer from './slices/accessibilitySlice';

// Combinar todos los reducers
const rootReducer = combineReducers({
  auth: authReducer,
  navigation: navigationReducer,
  theme: themeReducer,
  microfrontend: microfrontendReducer,
  notifications: notificationsReducer,
  accessibility: accessibilityReducer
});

// Crear la store
export const store = configureStore({
  reducer: rootReducer,
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware({
      serializableCheck: {
        // Ignorar acciones no serializables en entornos específicos
        ignoredActions: ['microfrontend/setApi'],
        // Ignorar rutas de estado no serializables
        ignoredPaths: ['microfrontend.apis']
      }
    })
});

// Exportar tipos para uso en otros archivos
export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;