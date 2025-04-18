import { LitElement, html, css } from 'lit';
import { customElement, property } from 'lit/decorators.js';

@customElement('board-viewer')
export class BoardViewer extends LitElement {
  @property({ type: String }) boardId = '';

  static styles = css`
    :host {
      display: block;
      font-family: sans-serif;
      padding: 16px;
    }
    .thread {
      margin-bottom: 20px;
      padding: 10px;
      border: 1px solid #ccc;
    }
    .post {
      margin-bottom: 10px;
    }
    .post-header {
      font-size: 0.8em;
      color: #666;
      margin-bottom: 5px;
    }
    .post-content {
      margin-bottom: 10px;
    }
  `;

  render() {
    return html`
      <div>
        <h2>Board: ${this.boardId || 'No board selected'}</h2>
        <div class="threads">
          <div class="thread">
            <div class="post">
              <div class="post-header">Anonymous 04/17/25(Wed)12:34:56 No.123456789</div>
              <div class="post-content">This is a sample post content</div>
            </div>
            <div class="post">
              <div class="post-header">Anonymous 04/17/25(Wed)12:35:22 No.123456790</div>
              <div class="post-content">This is a reply to the sample post</div>
            </div>
          </div>
        </div>
      </div>
    `;
  }
}

// Make sure the element is defined in the custom elements registry
declare global {
  interface HTMLElementTagNameMap {
    'board-viewer': BoardViewer;
  }
}