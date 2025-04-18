/**
 * Ejecuta una función de forma segura con un timeout para evitar bloqueos
 * 
 * @param fn Función a ejecutar
 * @param timeoutMs Tiempo de espera en milisegundos antes de abortar
 * @returns Resultado de la función o null si ocurre un error
 */
export function safeExecute<T>(fn: () => T, timeoutMs = 2000): T | null {
  try {
    // Si estamos en un entorno que soporta AbortController
    if (typeof AbortController !== 'undefined') {
      const controller = new AbortController();
      const signal = controller.signal;
      
      // Configurar timeout
      const timeoutId = setTimeout(() => {
        controller.abort();
      }, timeoutMs);
      
      try {
        // Ejecutar la función con un listener para la señal de abort
        let executed = false;
        let result: T | null = null;
        
        // Necesitamos un listener para abort
        const abortListener = () => {
          throw new Error(`Operación abortada después de ${timeoutMs}ms`);
        };
        
        // Agregar listener de abort
        signal.addEventListener('abort', abortListener);
        
        try {
          // Ejecutar la función
          result = fn();
          executed = true;
          return result;
        } finally {
          // Limpiar listener y timeout
          signal.removeEventListener('abort', abortListener);
          clearTimeout(timeoutId);
          
          // Si no se ejecutó, es porque se abortó o hubo un error
          if (!executed) {
            console.warn(`La función no se ejecutó correctamente dentro del tiempo límite (${timeoutMs}ms)`);
            return null;
          }
        }
      } catch (error) {
        console.error('Error en safeExecute:', error);
        return null;
      }
    } else {
      // Fallback para entornos sin AbortController
      // Simplemente ejecutamos la función y esperamos que no bloquee
      return fn();
    }
  } catch (error) {
    console.error('Error en safeExecute:', error);
    return null;
  }
}