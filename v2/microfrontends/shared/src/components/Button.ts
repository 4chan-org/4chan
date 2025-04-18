import { LitElement, html, css } from 'lit';
import { customElement, property } from 'lit/decorators.js';

@customElement('shared-button')
export class Button extends LitElement {
  @property({ type: String }) variant = 'default'; // default, primary, danger
  @property({ type: Boolean }) disabled = false;
  @property({ type: String }) size = 'medium'; // small, medium, large

  static styles = css`
    :host {
      display: inline-block;
    }
    button {
      font-family: inherit;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 500;
      border: none;
      transition: background-color 0.2s, box-shadow 0.2s;
    }
    button:focus {
      outline: none;
      box-shadow: 0 0 0 2px rgba(0, 120, 212, 0.5);
    }
    button:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    
    /* Variants */
    button.default {
      background-color: #f0f0f0;
      color: #333;
      border: 1px solid #ccc;
    }
    button.default:hover:not(:disabled) {
      background-color: #e0e0e0;
    }
    
    button.primary {
      background-color: #4a4a4a;
      color: white;
    }
    button.primary:hover:not(:disabled) {
      background-color: #333;
    }
    
    button.danger {
      background-color: #d32f2f;
      color: white;
    }
    button.danger:hover:not(:disabled) {
      background-color: #b71c1c;
    }
    
    /* Sizes */
    button.small {
      padding: 6px 12px;
      font-size: 0.85rem;
    }
    button.medium {
      padding: 8px 16px;
      font-size: 1rem;
    }
    button.large {
      padding: 10px 20px;
      font-size: 1.15rem;
    }
  `;

  render() {
    return html`
      <button 
        class="${this.variant} ${this.size}"
        ?disabled=${this.disabled}
        @click=${this._handleClick}
      >
        <slot></slot>
      </button>
    `;
  }

  private _handleClick(e: Event) {
    if (this.disabled) {
      e.stopPropagation();
      return;
    }
    
    this.dispatchEvent(new CustomEvent('button-click', {
      bubbles: true,
      composed: true
    }));
  }
}

declare global {
  interface HTMLElementTagNameMap {
    'shared-button': Button;
  }
}