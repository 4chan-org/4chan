import { LitElement, html, css } from 'lit';
import { customElement, property } from 'lit/decorators.js';

@customElement('catalog-viewer')
export class CatalogViewer extends LitElement {
  @property({ type: String }) boardId = '';

  static styles = css`
    :host {
      display: block;
      font-family: sans-serif;
      padding: 16px;
    }
    .catalog-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 16px;
    }
    .thread-preview {
      border: 1px solid #ccc;
      padding: 8px;
      height: 200px;
      overflow: hidden;
      cursor: pointer;
    }
    .thread-image {
      width: 100%;
      height: 120px;
      background-color: #eee;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .thread-title {
      font-weight: bold;
      margin-bottom: 4px;
      font-size: 0.9em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .thread-excerpt {
      font-size: 0.8em;
      color: #666;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
    }
  `;

  render() {
    return html`
      <div>
        <h2>Catalog: ${this.boardId || 'No board selected'}</h2>
        <div class="catalog-grid">
          ${Array(20).fill(0).map((_, i) => html`
            <div class="thread-preview" @click=${() => this._onThreadClick(i)}>
              <div class="thread-image">Image ${i + 1}</div>
              <div class="thread-title">Thread ${i + 1}</div>
              <div class="thread-excerpt">This is a sample thread excerpt. It shows a preview of the thread content.</div>
            </div>
          `)}
        </div>
      </div>
    `;
  }

  _onThreadClick(threadId: number) {
    this.dispatchEvent(new CustomEvent('thread-selected', {
      detail: { threadId, boardId: this.boardId },
      bubbles: true,
      composed: true
    }));
  }
}

// Make sure the element is defined in the custom elements registry
declare global {
  interface HTMLElementTagNameMap {
    'catalog-viewer': CatalogViewer;
  }
}