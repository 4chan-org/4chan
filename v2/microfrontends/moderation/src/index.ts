import { LitElement, html, css } from 'lit';
import { customElement, property, state } from 'lit/decorators.js';

@customElement('moderation-tools')
export class ModerationTools extends LitElement {
  @property({ type: String }) activeTab = 'reports';
  @state() private reports = [
    { id: 1, boardId: 'b', threadId: 12345, postId: 67890, reason: 'Spam', status: 'pending' },
    { id: 2, boardId: 'a', threadId: 54321, postId: 98765, reason: 'Offensive content', status: 'pending' },
    { id: 3, boardId: 'g', threadId: 13579, postId: 24680, reason: 'Off-topic', status: 'resolved' },
  ];
  @state() private bans = [
    { id: 1, ip: '192.168.1.x', boardId: 'all', reason: 'Spam', expires: '2025-05-17', status: 'active' },
    { id: 2, ip: '10.0.0.x', boardId: 'b', reason: 'Trolling', expires: '2025-04-25', status: 'active' },
    { id: 3, ip: '172.16.0.x', boardId: 'a', reason: 'Advertising', expires: '2025-04-19', status: 'expired' },
  ];

  static styles = css`
    :host {
      display: block;
      font-family: sans-serif;
      padding: 16px;
    }
    .tabs {
      display: flex;
      border-bottom: 1px solid #ccc;
      margin-bottom: 20px;
    }
    .tab {
      padding: 10px 16px;
      cursor: pointer;
      border: 1px solid transparent;
      border-bottom: none;
      margin-right: 4px;
    }
    .tab.active {
      background-color: #f5f5f5;
      border-color: #ccc;
      border-radius: 4px 4px 0 0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      text-align: left;
      padding: 8px 12px;
      border-bottom: 1px solid #ddd;
    }
    th {
      background-color: #f5f5f5;
      font-weight: bold;
    }
    tr:hover {
      background-color: #f9f9f9;
    }
    .actions {
      display: flex;
      gap: 8px;
    }
    button {
      background-color: #4a4a4a;
      color: white;
      border: none;
      padding: 6px 10px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.8em;
    }
    button:hover {
      background-color: #333;
    }
    button.resolve {
      background-color: #4caf50;
    }
    button.delete {
      background-color: #f44336;
    }
    .status {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 0.8em;
    }
    .status.pending {
      background-color: #ffeb3b;
      color: #333;
    }
    .status.resolved {
      background-color: #4caf50;
      color: white;
    }
    .status.active {
      background-color: #f44336;
      color: white;
    }
    .status.expired {
      background-color: #9e9e9e;
      color: white;
    }
  `;

  render() {
    return html`
      <div>
        <h2>Moderation Tools</h2>
        
        <div class="tabs">
          <div 
            class="tab ${this.activeTab === 'reports' ? 'active' : ''}" 
            @click=${() => this.activeTab = 'reports'}
          >
            Reports
          </div>
          <div 
            class="tab ${this.activeTab === 'bans' ? 'active' : ''}" 
            @click=${() => this.activeTab = 'bans'}
          >
            Bans
          </div>
          <div 
            class="tab ${this.activeTab === 'settings' ? 'active' : ''}" 
            @click=${() => this.activeTab = 'settings'}
          >
            Settings
          </div>
        </div>
        
        ${this._renderTabContent()}
      </div>
    `;
  }

  private _renderTabContent() {
    switch (this.activeTab) {
      case 'reports':
        return this._renderReports();
      case 'bans':
        return this._renderBans();
      case 'settings':
        return this._renderSettings();
      default:
        return html`<p>Unknown tab</p>`;
    }
  }

  private _renderReports() {
    return html`
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Board</th>
            <th>Thread</th>
            <th>Post</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ${this.reports.map(report => html`
            <tr>
              <td>${report.id}</td>
              <td>/${report.boardId}/</td>
              <td><a href="#/thread/${report.threadId}">${report.threadId}</a></td>
              <td><a href="#/post/${report.postId}">${report.postId}</a></td>
              <td>${report.reason}</td>
              <td>
                <span class="status ${report.status}">
                  ${report.status}
                </span>
              </td>
              <td class="actions">
                ${report.status === 'pending' ? html`
                  <button class="resolve" @click=${() => this._resolveReport(report.id)}>Resolve</button>
                  <button @click=${() => this._banUser(report.postId)}>Ban User</button>
                  <button class="delete" @click=${() => this._deletePost(report.postId)}>Delete Post</button>
                ` : html`
                  <button @click=${() => this._reopenReport(report.id)}>Reopen</button>
                `}
              </td>
            </tr>
          `)}
        </tbody>
      </table>
    `;
  }

  private _renderBans() {
    return html`
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>IP (masked)</th>
            <th>Board</th>
            <th>Reason</th>
            <th>Expires</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ${this.bans.map(ban => html`
            <tr>
              <td>${ban.id}</td>
              <td>${ban.ip}</td>
              <td>${ban.boardId === 'all' ? 'All boards' : `/${ban.boardId}/`}</td>
              <td>${ban.reason}</td>
              <td>${ban.expires}</td>
              <td>
                <span class="status ${ban.status}">
                  ${ban.status}
                </span>
              </td>
              <td class="actions">
                ${ban.status === 'active' ? html`
                  <button class="delete" @click=${() => this._liftBan(ban.id)}>Lift Ban</button>
                ` : html`
                  <button @click=${() => this._renewBan(ban.id)}>Renew</button>
                `}
              </td>
            </tr>
          `)}
        </tbody>
      </table>
      
      <div style="margin-top: 20px">
        <button @click=${this._showNewBanForm}>New Ban</button>
      </div>
    `;
  }

  private _renderSettings() {
    return html`
      <p>Settings panel would go here.</p>
    `;
  }

  // Action handlers for reports
  private _resolveReport(reportId: number) {
    this.reports = this.reports.map(report => 
      report.id === reportId ? {...report, status: 'resolved'} : report
    );
  }

  private _reopenReport(reportId: number) {
    this.reports = this.reports.map(report => 
      report.id === reportId ? {...report, status: 'pending'} : report
    );
  }

  private _banUser(postId: number) {
    // In a real implementation, this would open a ban form
    alert(`Ban user who posted ${postId}`);
  }

  private _deletePost(postId: number) {
    // In a real implementation, this would delete the post after confirmation
    if (confirm(`Are you sure you want to delete post ${postId}?`)) {
      alert(`Post ${postId} deleted`);
    }
  }

  // Action handlers for bans
  private _liftBan(banId: number) {
    this.bans = this.bans.map(ban => 
      ban.id === banId ? {...ban, status: 'expired'} : ban
    );
  }

  private _renewBan(banId: number) {
    this.bans = this.bans.map(ban => 
      ban.id === banId ? {...ban, status: 'active', expires: '2025-05-17'} : ban
    );
  }

  private _showNewBanForm() {
    // In a real implementation, this would open a form to create a new ban
    alert('New ban form would appear here');
  }
}

// Make sure the element is defined in the custom elements registry
declare global {
  interface HTMLElementTagNameMap {
    'moderation-tools': ModerationTools;
  }
}