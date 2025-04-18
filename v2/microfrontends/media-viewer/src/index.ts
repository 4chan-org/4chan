import { LitElement, html, css } from 'lit';
import { customElement, property, state } from 'lit/decorators.js';

@customElement('media-viewer')
export class MediaViewer extends LitElement {
  @property({ type: String }) mediaId = '';
  @property({ type: String }) mediaType = 'image'; // image, video, etc.
  @property({ type: String }) mediaUrl = '';
  @state() private isFullscreen = false;
  @state() private isLoading = true;

  static styles = css`
    :host {
      display: block;
      font-family: sans-serif;
    }
    .media-container {
      position: relative;
      max-width: 100%;
      background-color: #1a1a1a;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .media-wrapper {
      position: relative;
      max-width: 100%;
      text-align: center;
      padding: 20px;
    }
    img, video {
      max-width: 100%;
      max-height: 80vh;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    .fullscreen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-color: rgba(0, 0, 0, 0.9);
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .fullscreen img, .fullscreen video {
      max-width: 90vw;
      max-height: 90vh;
    }
    .controls {
      display: flex;
      gap: 10px;
      margin-top: 10px;
    }
    button {
      background-color: #333;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 4px;
      cursor: pointer;
    }
    button:hover {
      background-color: #444;
    }
    .loading {
      padding: 20px;
      text-align: center;
      color: #888;
    }
    .media-info {
      margin-top: 10px;
      font-size: 0.8em;
      color: #888;
      text-align: center;
    }
  `;

  render() {
    if (this.isLoading) {
      return html`
        <div class="loading">
          Loading media...
        </div>
      `;
    }

    const containerClass = this.isFullscreen ? 'media-container fullscreen' : 'media-container';
    
    return html`
      <div class=${containerClass}>
        <div class="media-wrapper">
          ${this._renderMedia()}
          
          <div class="controls">
            <button @click=${this._toggleFullscreen}>
              ${this.isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'}
            </button>
            <button @click=${this._downloadMedia}>
              Download
            </button>
          </div>
          
          <div class="media-info">
            <p>File: ${this.mediaId} (${this.mediaType})</p>
          </div>
        </div>
      </div>
    `;
  }

  connectedCallback() {
    super.connectedCallback();
    // Simulate loading media
    setTimeout(() => {
      this.isLoading = false;
    }, 1000);
  }

  private _renderMedia() {
    if (this.mediaType === 'video') {
      return html`
        <video controls @click=${this._toggleFullscreen}>
          <source src=${this.mediaUrl || '#'} type="video/mp4">
          Your browser does not support the video tag.
        </video>
      `;
    }
    
    // Default to image
    return html`
      <img 
        src=${this.mediaUrl || 'https://via.placeholder.com/800x600?text=Sample+Image'} 
        alt="Media content"
        @click=${this._toggleFullscreen}
      />
    `;
  }

  private _toggleFullscreen() {
    this.isFullscreen = !this.isFullscreen;
    
    if (this.isFullscreen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    
    // Dispatch event for the shell to handle
    this.dispatchEvent(new CustomEvent('fullscreen-change', {
      detail: { isFullscreen: this.isFullscreen },
      bubbles: true,
      composed: true
    }));
  }
  
  private _downloadMedia() {
    // In a real implementation, this would trigger a download
    window.open(this.mediaUrl, '_blank');
  }
}

// Make sure the element is defined in the custom elements registry
declare global {
  interface HTMLElementTagNameMap {
    'media-viewer': MediaViewer;
  }
}