type EventCallback = (...args: any[]) => void;

interface EventMap {
  [eventName: string]: EventCallback[];
}

/**
 * EventBus - Centralizado para comunicación entre microfrontends
 * 
 * Permite que los microfrontends se comuniquen entre sí sin acoplamiento directo
 * mediante un patrón publicador/suscriptor.
 */
class EventBus {
  private events: EventMap = {};
  private static instance: EventBus;
  private debugMode = false;

  private constructor() {
    // Inicializar con modo de depuración si está en desarrollo
    this.debugMode = process.env.NODE_ENV === 'development';
  }

  /**
   * Obtiene la instancia singleton del EventBus
   */
  public static getInstance(): EventBus {
    if (!EventBus.instance) {
      EventBus.instance = new EventBus();
    }
    return EventBus.instance;
  }

  /**
   * Suscribe una función callback a un evento específico
   * @param eventName Nombre del evento
   * @param callback Función a ejecutar cuando ocurra el evento
   * @returns Un objeto con un método para anular la suscripción
   */
  public on(eventName: string, callback: EventCallback) {
    if (!this.events[eventName]) {
      this.events[eventName] = [];
    }
    this.events[eventName].push(callback);

    if (this.debugMode) {
      console.log(`[EventBus] Suscripción a evento '${eventName}'`);
    }

    // Retorna función para facilitar la anulación de suscripción
    return {
      unsubscribe: () => this.off(eventName, callback)
    };
  }

  /**
   * Cancela la suscripción de un callback a un evento
   * @param eventName Nombre del evento
   * @param callback Función a remover
   */
  public off(eventName: string, callback: EventCallback) {
    if (!this.events[eventName]) return;
    
    this.events[eventName] = this.events[eventName].filter(
      cb => cb !== callback
    );
    
    if (this.debugMode) {
      console.log(`[EventBus] Cancelada suscripción a evento '${eventName}'`);
    }
  }

  /**
   * Emite un evento con sus datos correspondientes
   * @param eventName Nombre del evento a emitir
   * @param args Argumentos a pasar a los callbacks suscritos
   */
  public emit(eventName: string, ...args: any[]) {
    if (!this.events[eventName]) {
      if (this.debugMode) {
        console.log(`[EventBus] Evento '${eventName}' emitido sin suscriptores`);
      }
      return;
    }
    
    if (this.debugMode) {
      console.log(`[EventBus] Emitiendo evento '${eventName}' con ${this.events[eventName].length} suscriptores`, args);
    }
    
    this.events[eventName].forEach(callback => {
      try {
        callback(...args);
      } catch (error) {
        console.error(`[EventBus] Error en manejador de evento ${eventName}:`, error);
        // Emitir un evento especial para errores en manejadores
        if (eventName !== 'eventBus:error') {
          this.emit('eventBus:error', { 
            eventName, 
            error,
            timestamp: new Date().toISOString()
          });
        }
      }
    });
  }

  /**
   * Suscribe a un evento y se desuscribe automáticamente después de la primera ejecución
   * @param eventName Nombre del evento
   * @param callback Función a ejecutar una sola vez
   */
  public once(eventName: string, callback: EventCallback) {
    const onceCallback = (...args: any[]) => {
      this.off(eventName, onceCallback);
      callback(...args);
    };
    
    this.on(eventName, onceCallback);
  }
  
  /**
   * Activa o desactiva el modo de depuración
   * @param enabled Si debe activarse el modo de depuración
   */
  public setDebugMode(enabled: boolean): void {
    this.debugMode = enabled;
    console.log(`[EventBus] Modo de depuración ${enabled ? 'activado' : 'desactivado'}`);
  }
  
  /**
   * Obtiene la lista de nombres de eventos actualmente registrados
   */
  public getRegisteredEvents(): string[] {
    return Object.keys(this.events);
  }
  
  /**
   * Obtiene el número de suscriptores para un evento específico
   * @param eventName Nombre del evento
   */
  public getSubscriberCount(eventName: string): number {
    return this.events[eventName]?.length || 0;
  }
  
  /**
   * Elimina todos los suscriptores para un evento específico
   * @param eventName Nombre del evento
   */
  public clearEvent(eventName: string): void {
    delete this.events[eventName];
    
    if (this.debugMode) {
      console.log(`[EventBus] Eliminados todos los suscriptores para '${eventName}'`);
    }
  }
  
  /**
   * Elimina todos los eventos y suscriptores
   * Usar con precaución, normalmente solo para pruebas
   */
  public clear(): void {
    this.events = {};
    
    if (this.debugMode) {
      console.log(`[EventBus] Todos los eventos limpiados`);
    }
  }
}

// Exportamos una instancia singleton
export const eventBus = EventBus.getInstance();

// También exportamos tipos para facilitar el tipado
export type { EventCallback };