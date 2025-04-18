import { LitElement, html, css } from 'lit';
import { customElement, property, state } from 'lit/decorators.js';

@customElement('post-creator')
export class PostCreator extends LitElement {
  @property({ type: String }) boardId = '';
  @property({ type: String }) threadId = ''; // Empty if creating a new thread
  @state() private comment = '';
  @state() private name = 'Anonymous';
  @state() private fileInput: HTMLInputElement | null = null;

  static styles = css`
    :host {
      display: block;
      font-family: sans-serif;
      padding: 16px;
    }
    .post-form {
      border: 1px solid #ccc;
      padding: 20px;
      border-radius: 4px;
    }
    .form-group {
      margin-bottom: 16px;
    }
    label {
      display: block;
      margin-bottom: 4px;
      font-weight: bold;
    }
    input[type="text"] {
      width: 100%;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    textarea {
      width: 100%;
      height: 120px;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 4px;
      resize: vertical;
    }
    button {
      background-color: #4a4a4a;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-weight: bold;
    }
    button:hover {
      background-color: #333;
    }
    .file-input {
      margin-bottom: 16px;
    }
  `;

  render() {
    return html`
      <div class="post-form">
        <h2>${this.threadId ? 'Reply to Thread' : 'Create New Thread'}</h2>
        <p>Posting on: /${this.boardId}/</p>
        
        <div class="form-group">
          <label for="name">Name (optional)</label>
          <input 
            type="text" 
            id="name" 
            .value=${this.name}
            @input=${(e: InputEvent) => this.name = (e.target as HTMLInputElement).value}
          />
        </div>
        
        <div class="form-group">
          <label for="comment">Comment</label>
          <textarea 
            id="comment"
            .value=${this.comment}
            @input=${(e: InputEvent) => this.comment = (e.target as HTMLTextAreaElement).value}
          ></textarea>
        </div>
        
        <div class="file-input">
          <label for="file">File (optional)</label>
          <input 
            type="file" 
            id="file"
            @change=${this._handleFileChange}
          />
        </div>
        
        <button @click=${this._handleSubmit}>
          ${this.threadId ? 'Post Reply' : 'Create Thread'}
        </button>
      </div>
    `;
  }

  firstUpdated() {
    this.fileInput = this.shadowRoot?.querySelector('#file') as HTMLInputElement;
  }

  private _handleFileChange(e: Event) {
    // File validation could be added here
  }

  private _handleSubmit() {
    // Simple validation
    if (!this.comment) {
      alert('Comment is required');
      return;
    }

    const file = this.fileInput?.files?.[0] || null;

    // Dispatch event to notify the shell application
    this.dispatchEvent(new CustomEvent('post-submit', {
      detail: {
        boardId: this.boardId,
        threadId: this.threadId,
        name: this.name,
        comment: this.comment,
        file: file
      },
      bubbles: true,
      composed: true
    }));

    // Reset form
    this.comment = '';
    this.name = 'Anonymous';
    if (this.fileInput) {
      this.fileInput.value = '';
    }
  }
}

// Make sure the element is defined in the custom elements registry
declare global {
  interface HTMLElementTagNameMap {
    'post-creator': PostCreator;
  }
}