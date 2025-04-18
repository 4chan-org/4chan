import { LitElement, html, css } from 'lit';
import { customElement, property, state } from 'lit/decorators.js';

@customElement('auth-component')
export class AuthComponent extends LitElement {
  @property({ type: String }) mode: 'login' | 'register' = 'login';
  @state() private username = '';
  @state() private password = '';
  @state() private error = '';

  static styles = css`
    :host {
      display: block;
      font-family: sans-serif;
      padding: 16px;
      max-width: 400px;
      margin: 0 auto;
    }
    .form-container {
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
    input {
      width: 100%;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 4px;
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
    .mode-toggle {
      text-align: center;
      margin-top: 16px;
      font-size: 0.9em;
    }
    .mode-toggle a {
      color: #0066cc;
      cursor: pointer;
      text-decoration: underline;
    }
    .error {
      color: red;
      margin-bottom: 16px;
      font-size: 0.9em;
    }
  `;

  render() {
    return html`
      <div class="form-container">
        <h2>${this.mode === 'login' ? 'Login' : 'Register'}</h2>
        
        ${this.error ? html`<div class="error">${this.error}</div>` : ''}
        
        <div class="form-group">
          <label for="username">Username</label>
          <input 
            type="text" 
            id="username" 
            .value=${this.username}
            @input=${(e: InputEvent) => this.username = (e.target as HTMLInputElement).value}
          />
        </div>
        
        <div class="form-group">
          <label for="password">Password</label>
          <input 
            type="password" 
            id="password"
            .value=${this.password}
            @input=${(e: InputEvent) => this.password = (e.target as HTMLInputElement).value}
          />
        </div>
        
        <button @click=${this._handleSubmit}>
          ${this.mode === 'login' ? 'Login' : 'Register'}
        </button>
        
        <div class="mode-toggle">
          ${this.mode === 'login'
            ? html`Don't have an account? <a @click=${this._toggleMode}>Register</a>`
            : html`Already have an account? <a @click=${this._toggleMode}>Login</a>`
          }
        </div>
      </div>
    `;
  }

  private _handleSubmit() {
    // Simple validation
    if (!this.username || !this.password) {
      this.error = 'Username and password are required';
      return;
    }

    this.error = '';
    
    // Dispatch event to notify the shell application
    this.dispatchEvent(new CustomEvent('auth-submit', {
      detail: {
        mode: this.mode,
        username: this.username,
        password: this.password
      },
      bubbles: true,
      composed: true
    }));
  }

  private _toggleMode() {
    this.mode = this.mode === 'login' ? 'register' : 'login';
    this.error = '';
  }
}

// Make sure the element is defined in the custom elements registry
declare global {
  interface HTMLElementTagNameMap {
    'auth-component': AuthComponent;
  }
}