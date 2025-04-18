import { createSlice, PayloadAction } from '@reduxjs/toolkit';

// Tipos para el estado de navegación
interface NavigationState {
  currentBoard: string | null;
  currentThread: string | null;
  previousRoute: string | null;
  recentBoards: string[];
  recentThreads: RecentThread[];
  sidebarOpen: boolean;
  breadcrumbs: Breadcrumb[];
}

interface RecentThread {
  id: string;
  boardId: string;
  title?: string;
  lastVisited: string;
}

interface Breadcrumb {
  label: string;
  path: string;
  icon?: string;
}

// Estado inicial
const initialState: NavigationState = {
  currentBoard: null,
  currentThread: null,
  previousRoute: null,
  recentBoards: [],
  recentThreads: [],
  sidebarOpen: false,
  breadcrumbs: []
};

// Slice para gestionar el estado de navegación
const navigationSlice = createSlice({
  name: 'navigation',
  initialState,
  reducers: {
    // Establecer tablero actual
    setCurrentBoard: (state, action: PayloadAction<string | null>) => {
      state.currentBoard = action.payload;
      
      // Añadir a recientes si no es null
      if (action.payload) {
        // Eliminar si ya existe
        state.recentBoards = state.recentBoards.filter(b => b !== action.payload);
        // Añadir al principio (más reciente)
        state.recentBoards.unshift(action.payload);
        // Limitar a 5 tableros recientes
        if (state.recentBoards.length > 5) {
          state.recentBoards = state.recentBoards.slice(0, 5);
        }
      }
    },
    
    // Establecer hilo actual
    setCurrentThread: (state, action: PayloadAction<{ 
      threadId: string | null; 
      boardId: string | null;
      title?: string; 
    }>) => {
      const { threadId, boardId, title } = action.payload;
      state.currentThread = threadId;
      
      // Actualizar tablero actual si se proporciona
      if (boardId) {
        state.currentBoard = boardId;
      }
      
      // Añadir a recientes si no es null
      if (threadId && boardId) {
        // Crear objeto de hilo reciente
        const recentThread: RecentThread = {
          id: threadId,
          boardId,
          title,
          lastVisited: new Date().toISOString()
        };
        
        // Eliminar si ya existe (usando id y board como clave)
        state.recentThreads = state.recentThreads.filter(
          t => !(t.id === threadId && t.boardId === boardId)
        );
        
        // Añadir al principio (más reciente)
        state.recentThreads.unshift(recentThread);
        
        // Limitar a 10 hilos recientes
        if (state.recentThreads.length > 10) {
          state.recentThreads = state.recentThreads.slice(0, 10);
        }
      }
    },
    
    // Establecer ruta anterior
    setPreviousRoute: (state, action: PayloadAction<string>) => {
      state.previousRoute = action.payload;
    },
    
    // Alternar sidebar
    toggleSidebar: (state) => {
      state.sidebarOpen = !state.sidebarOpen;
    },
    
    // Establecer estado de sidebar
    setSidebarOpen: (state, action: PayloadAction<boolean>) => {
      state.sidebarOpen = action.payload;
    },
    
    // Establecer migas de pan
    setBreadcrumbs: (state, action: PayloadAction<Breadcrumb[]>) => {
      state.breadcrumbs = action.payload;
    },
    
    // Añadir una miga de pan
    addBreadcrumb: (state, action: PayloadAction<Breadcrumb>) => {
      // Evitar duplicados
      if (!state.breadcrumbs.some(b => b.path === action.payload.path)) {
        state.breadcrumbs.push(action.payload);
      }
    },
    
    // Limpiar migas de pan
    clearBreadcrumbs: (state) => {
      state.breadcrumbs = [];
    },
    
    // Limpiar historial reciente
    clearRecents: (state) => {
      state.recentBoards = [];
      state.recentThreads = [];
    }
  }
});

// Exportar acciones y reducer
export const { 
  setCurrentBoard, 
  setCurrentThread, 
  setPreviousRoute,
  toggleSidebar,
  setSidebarOpen,
  setBreadcrumbs,
  addBreadcrumb,
  clearBreadcrumbs,
  clearRecents
} = navigationSlice.actions;

export default navigationSlice.reducer;