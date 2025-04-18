import { eventBus } from './eventBus';
import { safeExecute } from './safeExecute';

export type MicrofrontendManifest = {
  name: string;
  version: string;
  entry: string;
  type: 'module' | 'webcomponent';
  routes?: string[];
  dependencies?: string[];
  exposedComponents?: string[];
  integrityHash?: string;
};

type MicrofrontendRegistration = {
  manifest: MicrofrontendManifest;
  loaded: boolean;
  loading: boolean;
  mount?: (container: HTMLElement, props?: any) => void;
  unmount?: (container: HTMLElement) => void;
  exposedApi?: Record<string, any>;
};

/**
 * MicrofrontendManager - Administra la carga, registro y ciclo de vida de microfrontends
 * 
 * Esta clase proporciona métodos para cargar, registrar y administrar microfrontends dinámicamente.
 */
class MicrofrontendManager {
  private static instance: MicrofrontendManager;
  private microfrontends: Map<string, MicrofrontendRegistration> = new Map();
  private manifestCache: Map<string, MicrofrontendManifest> = new Map();
  private remoteEntryUrls: Record<string, string> = {};
  private webComponentRegistry: Map<string, CustomElementConstructor> = new Map();

  // URL base para cargar manifiestos (puede configurarse dinámicamente)
  private manifestBaseUrl = '/microfrontends';
  private isDevMode = process.env.NODE_ENV === 'development';

  private constructor() {
    // Inicializar URLs por defecto
    this.remoteEntryUrls = {
      'auth': '/microfrontends/auth/remoteEntry.js',
      'board-viewer': '/microfrontends/board-viewer/remoteEntry.js',
      'catalog-viewer': '/microfrontends/catalog-viewer/remoteEntry.js',
      'media-viewer': '/microfrontends/media-viewer/remoteEntry.js',
      'moderation': '/microfrontends/moderation/remoteEntry.js',
      'post-creator': '/microfrontends/post-creator/remoteEntry.js',
    };
    
    // Inicializar eventos
    this._setupEventListeners();
  }

  public static getInstance(): MicrofrontendManager {
    if (!MicrofrontendManager.instance) {
      MicrofrontendManager.instance = new MicrofrontendManager();
    }
    return MicrofrontendManager.instance;
  }

  /**
   * Configura los escuchadores de eventos para la comunicación entre microfrontends
   */
  private _setupEventListeners(): void {
    // Escuchar a eventos globales de registro de microfrontends
    window.addEventListener('microfrontend-register', ((event: CustomEvent) => {
      const { name, api } = event.detail;
      if (name && api) {
        this._registerMicrofrontendApi(name, api);
      }
    }) as EventListener);
    
    // Escuchar eventos de registro de Web Components
    window.addEventListener('webcomponent-register', ((event: CustomEvent) => {
      const { name, constructor } = event.detail;
      if (name && constructor) {
        this.registerWebComponent(name, constructor);
      }
    }) as EventListener);
  }

  /**
   * Registra internamente la API de un microfrontend
   */
  private _registerMicrofrontendApi(name: string, api: any): void {
    const registration = this.microfrontends.get(name);
    if (registration) {
      registration.exposedApi = api;
      eventBus.emit(`microfrontend:${name}:api-registered`, api);
    }
  }

  /**
   * Carga el manifiesto de un microfrontend
   */
  private async loadManifest(name: string): Promise<MicrofrontendManifest> {
    if (this.manifestCache.has(name)) {
      return this.manifestCache.get(name)!;
    }

    try {
      const response = await fetch(`${this.manifestBaseUrl}/${name}/manifest.json`);
      if (!response.ok) {
        throw new Error(`Error cargando manifiesto para ${name}: ${response.statusText}`);
      }
      
      const manifest = await response.json() as MicrofrontendManifest;
      
      // Verificar la integridad del manifiesto si está en modo producción
      if (!this.isDevMode && manifest.integrityHash) {
        const isValid = await this.verifyIntegrity(manifest);
        if (!isValid) {
          throw new Error(`Error de integridad en el manifiesto para ${name}`);
        }
      }
      
      this.manifestCache.set(name, manifest);
      return manifest;
    } catch (error) {
      console.error(`Error al cargar manifiesto de microfrontend ${name}:`, error);
      throw error;
    }
  }
  
  /**
   * Verifica la integridad criptográfica de un manifiesto
   */
  private async verifyIntegrity(manifest: MicrofrontendManifest): Promise<boolean> {
    // Implementación real usaría SubtleCrypto para verificar la firma o hash
    // Esta es una implementación simulada para demostración
    return true;
  }

  /**
   * Carga dinámicamente un script en el DOM
   */
  private loadScript(url: string): Promise<void> {
    // Verificar si el script ya está cargado
    const existingScript = document.querySelector(`script[src="${url}"]`);
    if (existingScript) {
      return Promise.resolve();
    }
    
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = url;
      script.type = 'text/javascript';
      script.crossOrigin = 'anonymous';
      
      // Configurar atributos de seguridad
      script.setAttribute('data-microfrontend', 'true');
      
      script.onload = () => resolve();
      script.onerror = (err) => reject(new Error(`Error cargando script ${url}: ${err}`));
      
      document.head.appendChild(script);
    });
  }

  /**
   * Registra un Web Component en el DOM
   */
  public registerWebComponent(name: string, component: CustomElementConstructor) {
    try {
      // Guardar en el registro local
      this.webComponentRegistry.set(name, component);
      
      // Registrar como elemento personalizado si no está registrado
      if (!customElements.get(name)) {
        customElements.define(name, component);
        this.log(`Web Component registrado: ${name}`);
      }
    } catch (error) {
      console.error(`Error al registrar Web Component ${name}:`, error);
    }
  }

  /**
   * Carga un microfrontend por nombre
   */
  public async loadMicrofrontend(name: string): Promise<any> {
    this.log(`Iniciando carga de microfrontend: ${name}`);
    
    if (this.microfrontends.has(name)) {
      const registration = this.microfrontends.get(name)!;
      
      // Si ya está cargado, devolvemos la API expuesta
      if (registration.loaded) {
        this.log(`Microfrontend ya cargado: ${name}`);
        return registration.exposedApi;
      }
      
      // Si está cargando, esperamos a que termine
      if (registration.loading) {
        this.log(`Microfrontend cargándose, esperando: ${name}`);
        return new Promise((resolve, reject) => {
          eventBus.once(`microfrontend:${name}:loaded`, (api) => resolve(api));
          eventBus.once(`microfrontend:${name}:error`, (error) => reject(error));
        });
      }
    }

    // Crear un nuevo registro si no existe
    if (!this.microfrontends.has(name)) {
      try {
        const manifest = await this.loadManifest(name);
        this.log(`Manifiesto cargado para: ${name}`, manifest);
        
        this.microfrontends.set(name, {
          manifest,
          loaded: false,
          loading: true
        });
      } catch (error) {
        eventBus.emit(`microfrontend:${name}:error`, error);
        throw error;
      }
    }

    const registration = this.microfrontends.get(name)!;
    registration.loading = true;

    try {
      // Cargar dependencias primero si existen
      if (registration.manifest.dependencies && registration.manifest.dependencies.length > 0) {
        this.log(`Cargando dependencias para: ${name}`, registration.manifest.dependencies);
        
        await Promise.all(
          registration.manifest.dependencies.map(dep => this.loadMicrofrontend(dep))
        );
      }

      // Cargar el script de entrada remota
      const remoteName = registration.manifest.name;
      const remoteUrl = this.remoteEntryUrls[remoteName] || registration.manifest.entry;
      this.log(`Cargando script remoto: ${remoteUrl}`);
      
      await this.loadScript(remoteUrl);

      // Obtener el módulo expuesto (según el tipo de federación)
      if (registration.manifest.type === 'module') {
        // Module Federation de Webpack
        await this._loadWebpackFederatedModule(remoteName);
      } else {
        // Web Components
        await this._loadWebComponent(remoteName);
      }
      
      registration.loaded = true;
      registration.loading = false;
      
      // Notificar que el microfrontend está cargado
      eventBus.emit(`microfrontend:${name}:loaded`, registration.exposedApi);
      this.log(`Microfrontend cargado con éxito: ${name}`);
      
      return registration.exposedApi;
    } catch (error) {
      registration.loading = false;
      console.error(`Error cargando microfrontend ${name}:`, error);
      eventBus.emit(`microfrontend:${name}:error`, error);
      throw error;
    }
  }
  
  /**
   * Carga un módulo federado de Webpack
   */
  private async _loadWebpackFederatedModule(remoteName: string): Promise<void> {
    // Obtenemos el contenedor
    const container = (window as any)[remoteName];
    if (!container) {
      throw new Error(`No se encontró el contenedor remoto para ${remoteName}`);
    }

    // Inicializar el contenedor
    await container.init(window.__webpack_share_scope || {});
    
    // Obtener el módulo principal
    const factory = await container.get('./MFComponent');
    const Module = factory();
    
    // Actualizar el registro
    const registration = this.microfrontends.get(remoteName)!;
    registration.exposedApi = Module.default || Module;
    registration.mount = Module.mount;
    registration.unmount = Module.unmount;
  }
  
  /**
   * Carga un Web Component
   */
  private async _loadWebComponent(remoteName: string): Promise<void> {
    // Para componentes web, el script ya debería haber registrado el elemento
    const elementName = `${remoteName}-component`;
    
    // Esperar a que el componente se registre (podría ocurrir asincrónicamente)
    const maxWaitTime = 5000; // 5 segundos máximo
    const startTime = Date.now();
    
    while (!customElements.get(elementName) && (Date.now() - startTime < maxWaitTime)) {
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    
    if (!customElements.get(elementName)) {
      throw new Error(`Web Component no registrado después de cargar: ${elementName}`);
    }
    
    // Guardar referencias a métodos de ciclo de vida
    const registration = this.microfrontends.get(remoteName)!;
    
    registration.mount = (container, props) => {
      const element = document.createElement(elementName);
      
      // Configurar propiedades
      if (props) {
        Object.entries(props).forEach(([key, value]) => {
          (element as any)[key] = value;
        });
      }
      
      // Montar en el contenedor
      container.appendChild(element);
    };
    
    registration.unmount = (container) => {
      const element = container.querySelector(elementName);
      if (element) {
        // Llamar al método de desmontaje si existe
        if ((element as any).unmount && typeof (element as any).unmount === 'function') {
          safeExecute(() => (element as any).unmount());
        }
        
        // Eliminar del DOM
        container.removeChild(element);
      }
    };
  }

  /**
   * Monta un microfrontend en un contenedor específico
   */
  public async mountMicrofrontend(name: string, container: HTMLElement, props: any = {}): Promise<void> {
    this.log(`Montando microfrontend: ${name}`, { container, props });
    
    try {
      await this.loadMicrofrontend(name);
      const registration = this.microfrontends.get(name);
      
      if (registration && registration.mount) {
        // Añadir props adicionales
        const enrichedProps = {
          ...props,
          mountTimestamp: new Date().toISOString(),
          isMounted: true
        };
        
        // Realizar el montaje
        registration.mount(container, enrichedProps);
        eventBus.emit(`microfrontend:${name}:mounted`, { container, props: enrichedProps });
      } else {
        throw new Error(`El microfrontend ${name} no proporciona un método mount`);
      }
    } catch (error) {
      console.error(`Error montando microfrontend ${name}:`, error);
      throw error;
    }
  }

  /**
   * Desmonta un microfrontend
   */
  public async unmountMicrofrontend(name: string, container: HTMLElement): Promise<void> {
    this.log(`Desmontando microfrontend: ${name}`);
    
    const registration = this.microfrontends.get(name);
    if (registration && registration.unmount) {
      try {
        registration.unmount(container);
        eventBus.emit(`microfrontend:${name}:unmounted`, { container });
      } catch (error) {
        console.error(`Error desmontando microfrontend ${name}:`, error);
        // No propagar el error para permitir limpieza
      }
    }
  }

  /**
   * Recupera la API expuesta de un microfrontend cargado
   */
  public getMicrofrontendApi(name: string): any {
    const registration = this.microfrontends.get(name);
    return registration?.exposedApi;
  }
  
  /**
   * Obtiene información sobre todos los microfrontends registrados
   */
  public getMicrofrontendsInfo(): Record<string, { 
    loaded: boolean, 
    loading: boolean,
    version?: string,
    type?: string
  }> {
    const info: Record<string, any> = {};
    
    this.microfrontends.forEach((registration, name) => {
      info[name] = {
        loaded: registration.loaded,
        loading: registration.loading,
        version: registration.manifest?.version,
        type: registration.manifest?.type
      };
    });
    
    return info;
  }

  /**
   * Verifica si un microfrontend está cargado
   */
  public isMicrofrontendLoaded(name: string): boolean {
    return this.microfrontends.get(name)?.loaded || false;
  }

  /**
   * Configura la URL base para cargar manifiestos
   */
  public setManifestBaseUrl(url: string): void {
    this.manifestBaseUrl = url;
  }
  
  /**
   * Configura la URL para un microfrontend específico
   */
  public setRemoteEntryUrl(name: string, url: string): void {
    this.remoteEntryUrls[name] = url;
  }
  
  /**
   * Método para registro de logs (filtrado según modo)
   */
  private log(message: string, data?: any): void {
    if (this.isDevMode) {
      if (data) {
        console.log(`[MicrofrontendManager] ${message}`, data);
      } else {
        console.log(`[MicrofrontendManager] ${message}`);
      }
    }
  }
}

// Exportamos una instancia singleton
export const microfrontendManager = MicrofrontendManager.getInstance();