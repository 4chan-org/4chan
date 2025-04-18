import { css } from 'lit';

// Common styles that can be imported by all microfrontends
export const themeStyles = css`
  /* 
   * Base CSS Variables 
   * Used by all microfrontends for consistent styling
   */
  :host {
    /* Colors - Default (Dark) Theme */
    --color-background: #1a1a1a;
    --color-surface: #2b2b2b;
    --color-surface-variant: #333333;
    --color-on-background: rgba(255, 255, 255, 0.87);
    --color-on-surface: rgba(255, 255, 255, 0.87);
    --color-on-surface-variant: rgba(255, 255, 255, 0.6);
    --color-primary: #bb86fc;
    --color-primary-variant: #3700B3;
    --color-on-primary: #000000;
    --color-secondary: #03dac6;
    --color-secondary-variant: #018786;
    --color-on-secondary: #000000;
    --color-error: #cf6679;
    --color-on-error: #000000;
    --color-border: rgba(255, 255, 255, 0.12);
    
    /* Typography */
    --font-family-sans: Inter, system-ui, sans-serif;
    --font-family-mono: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    
    --font-size-xs: 0.75rem;
    --font-size-sm: 0.875rem;
    --font-size-md: 1rem;
    --font-size-lg: 1.125rem;
    --font-size-xl: 1.25rem;
    --font-size-2xl: 1.5rem;
    
    --line-height-tight: 1.25;
    --line-height-normal: 1.5;
    --line-height-relaxed: 1.75;
    
    /* Spacing */
    --space-1: 0.25rem;
    --space-2: 0.5rem;
    --space-3: 0.75rem;
    --space-4: 1rem;
    --space-6: 1.5rem;
    --space-8: 2rem;
    --space-12: 3rem;
    --space-16: 4rem;
    --space-20: 5rem;
    
    /* Shadows */
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    
    /* Transitions */
    --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-normal: 300ms cubic-bezier(0.4, 0, 0.2, 1);
    
    /* Border Radius */
    --radius-sm: 0.125rem;
    --radius-md: 0.25rem;
    --radius-lg: 0.5rem;
    --radius-full: 9999px;
  }
  
  /* Light Theme Variables (can be applied via a class on a parent element) */
  :host(.light-theme) {
    --color-background: #f5f5f5;
    --color-surface: #ffffff;
    --color-surface-variant: #e0e0e0;
    --color-on-background: rgba(0, 0, 0, 0.87);
    --color-on-surface: rgba(0, 0, 0, 0.87);
    --color-on-surface-variant: rgba(0, 0, 0, 0.6);
    --color-primary: #6200ee;
    --color-primary-variant: #3700B3;
    --color-on-primary: #ffffff;
    --color-secondary: #03dac6;
    --color-secondary-variant: #018786;
    --color-on-secondary: #000000;
    --color-error: #b00020;
    --color-on-error: #ffffff;
    --color-border: rgba(0, 0, 0, 0.12);
  }
`;

// Example themes for different boards
export const themeYotsuba = css`
  :host {
    --color-background: #ffe;
    --color-surface: #f0e0d6;
    --color-surface-variant: #d9c9bf;
    --color-on-background: rgba(0, 0, 0, 0.87);
    --color-on-surface: rgba(0, 0, 0, 0.87);
    --color-on-surface-variant: rgba(0, 0, 0, 0.6);
    --color-primary: #800000;
    --color-primary-variant: #600000;
    --color-on-primary: #ffffff;
    --color-border: #d9bfb7;
  }
`;

export const themeTomorrow = css`
  :host {
    --color-background: #1d1f21;
    --color-surface: #282a2e;
    --color-surface-variant: #373b41;
    --color-on-background: #c5c8c6;
    --color-on-surface: #c5c8c6;
    --color-on-surface-variant: #969896;
    --color-primary: #81a2be;
    --color-primary-variant: #5f819d;
    --color-on-primary: #ffffff;
    --color-secondary: #b5bd68;
    --color-secondary-variant: #8c9440;
    --color-on-secondary: #000000;
    --color-error: #cc6666;
    --color-on-error: #ffffff;
    --color-border: #373b41;
  }
`;

export const themeFotaba = css`
  :host {
    --color-background: #ffffee;
    --color-surface: #f0e0d6;
    --color-surface-variant: #e0d0c6;
    --color-on-background: #800000;
    --color-on-surface: #800000;
    --color-on-surface-variant: #600000;
    --color-primary: #117743;
    --color-primary-variant: #0d5730;
    --color-on-primary: #ffffff;
    --color-border: #d9bfb7;
  }
`;