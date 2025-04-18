var TCaptcha = {
  node: null,
  
  frameNode: null,
  imgCntNode: null,
  bgNode: null,
  fgNode: null,
  msgNode: null,
  sliderNode: null,
  respNode: null,
  reloadNode: null,
  helpNode: null,
  challengeNode: null,
  
  ticketCaptchaNode: null,
  
  challenge: null,
  
  reloadTs: null,
  reloadTimeout: null,
  expireTimeout: null,
  frameTimeout: null,
  
  pcdBypassable: false,
  
  errorCb: null,
  
  path: '/captcha',
  
  ticketKey: '4chan-tc-ticket',
  
  domain: '4chan.org',
  
  failCd: 60,
  
  tabindex: null,
  
  hCaptchaSiteKey: '49d294fa-f15c-41fc-80ba-c2544c52ec2a',
  
  init: function(el, board, thread_id, tabindex) {
    if (this.node) {
      this.destroy();
    }
    
    if (tabindex) {
      this.tabindex = tabindex;
    }
    
    this.node = el;
    
    el.style.position = 'relative';
    el.style.width = '300px';
    
    this.frameNode = null;
    this.imgCntNode = this.buildImgCntNode();
    this.bgNode = this.buildImgNode('bg');
    this.fgNode = this.buildImgNode('fg');
    this.sliderNode = this.buildSliderNode();
    
    this.respNode = this.buildRespField();
    this.reloadNode = this.buildReloadNode(board, thread_id);
    this.helpNode = this.buildHelpNode();
    this.msgNode = this.buildMsgNode();
    this.challengeNode = this.buildChallengeNode();
    
    el.appendChild(this.reloadNode);
    el.appendChild(this.respNode);
    el.appendChild(this.helpNode);
    
    this.imgCntNode.appendChild(this.bgNode);
    this.imgCntNode.appendChild(this.fgNode);
    el.appendChild(this.imgCntNode);
    
    el.appendChild(this.sliderNode);
    el.appendChild(this.msgNode);
    el.appendChild(this.challengeNode);
    
    window.addEventListener('message', this.onFrameMessage);
  },
  
  destroy: function() {
    let self = TCaptcha;
    
    if (!self.node) {
      return;
    }
    
    window.removeEventListener('message', self.onFrameMessage);
    
    clearTimeout(self.frameTimeout);
    clearTimeout(self.reloadTimeout);
    clearTimeout(self.expireTimeout);
    
    self.node.textContent = '';
    
    self.node = null;
    self.frameNode = null;
    self.imgCntNode = null;
    self.bgNode = null;
    self.fgNode = null;
    self.msgNode = null;
    self.sliderNode = null;
    self.respNode = null;
    self.reloadNode = null;
    self.helpNode = null;
    self.challengeNode = null;
    
    self.ticketCaptchaNode = null;
    
    self.challenge = null;
    
    self.pcdBypassable = false;
    
    self.errorCb = null;
    
    self.reloadTs = null;
    
    self.onReloadCdDone = null;
  },
  
  setErrorCb: function(func) {
    TCaptcha.errorCb = func;
  },
  
  toggleImgCntNode: function(flag) {
    TCaptcha.imgCntNode.style.display = flag ? 'block' : 'none';
  },
  
  getTicket: function() {
    return localStorage.getItem(TCaptcha.ticketKey);
  },
  
  setTicket: function(val) {
    if (val) {
      localStorage.setItem(TCaptcha.ticketKey, val);
    }
    else if (val === false) {
      localStorage.removeItem(TCaptcha.ticketKey);
    }
  },
  
  buildFrameNode: function() {
    let el = document.createElement('iframe');
    el.id = 't-frame';
    el.style.border = '0';
    el.style.width = '100%';
    el.style.height = '80px';
    el.style.marginTop = '2px';
    el.style.position = 'relative';
    el.style.display = 'block';
    el.onerror = TCaptcha.onFrameError;
    return el;
  },
  
  buildImgCntNode: function() {
    let el = document.createElement('div');
    el.id = 't-cnt';
    el.style.height = '80px';
    el.style.marginTop = '2px';
    el.style.position = 'relative';
    return el;
  },
  
  buildImgNode: function(id) {
    let el = document.createElement('div');
    el.id = 't-' + id;
    el.style.width = '100%';
    el.style.height = '100%';
    el.style.position = 'absolute';
    el.style.backgroundRepeat = 'no-repeat';
    el.style.backgroundPosition = 'top left';
    el.style.pointerEvents = 'none';
    return el;
  },
  
  buildMsgNode: function() {
    let el = document.createElement('div');
    el.id = 't-msg';
    el.style.width = '100%';
    el.style.height = 'calc(100% - 20px)';
    el.style.position = 'absolute';
    el.style.top = '20px';
    el.style.textAlign = 'center';
    el.style.fontSize = '12px';
    el.style.filter = 'inherit';
    el.style.display = 'none';
    el.style.alignContent = 'center';
    return el;
  },
  
  buildRespField: function() {
    let el = document.createElement('input');
    el.id = 't-resp';
    el.name = 't-response';
    el.placeholder = 'Type the CAPTCHA here';
    el.setAttribute('autocomplete', 'off');
    el.type = 'text';
    el.style.width = '160px';
    el.style.boxSizing = 'border-box';
    el.style.textTransform = 'uppercase';
    el.style.fontSize = '11px';
    el.style.height = '18px';
    el.style.margin = '0';
    el.style.padding = '0 2px';
    el.style.fontFamily = 'monospace';
    el.style.verticalAlign = 'middle';
    if (this.tabindex) {
      el.setAttribute('tabindex', this.tabindex + 2);
    }
    return el;
  },
  
  buildSliderNode: function() {
    let el = document.createElement('input');
    el.id = 't-slider';
    el.setAttribute('autocomplete', 'off');
    el.type = 'range';
    el.style.width = '100%';
    el.style.boxSizing = 'border-box';
    el.style.visibility = 'hidden';
    el.style.margin = '0';
    el.style.transition = 'box-shadow 15s linear';
    el.style.boxShadow = '0 0 6px 4px #1d8dc4';
    el.style.position = 'relative';
    el.value = 0;
    el.min = 0;
    el.max = 100;
    el.addEventListener('input', this.onSliderInput, false);
    if (this.tabindex) {
      el.setAttribute('tabindex', this.tabindex + 1);
    }
    return el;
  },
  
  buildChallengeNode: function() {
    let el = document.createElement('input');
    el.name = 't-challenge';
    el.type = 'hidden';
    return el;
  },
  
  buildReloadNode: function(board, thread_id) {
    let el = document.createElement('button');
    el.id = 't-load';
    el.type = 'button';
    el.style.fontSize = '11px';
    el.style.padding = '0';
    el.style.width = '90px';
    el.style.boxSizing = 'border-box';
    el.style.margin = '0 6px 0 0';
    el.style.verticalAlign = 'middle';
    el.style.height = '18px';
    el.textContent = 'Get Captcha';
    el.setAttribute('data-board', board);
    el.setAttribute('data-tid', thread_id);
    el.addEventListener('click', this.onReloadClick, false);
    if (this.tabindex) {
      el.setAttribute('tabindex', this.tabindex);
    }
    return el;
  },
  
  buildHelpNode: function() {
    let el = document.createElement('button');
    el.id = 't-help';
    el.type = 'button';
    el.style.fontSize = '11px';
    el.style.padding = '0';
    el.style.width = '20px';
    el.style.boxSizing = 'border-box';
    el.style.margin = '0 0 0 6px';
    el.style.verticalAlign = 'middle';
    el.style.height = '18px';
    el.textContent = '?';
    el.setAttribute('data-tip', 'Help');
    el.tabIndex = -1;
    el.addEventListener('click', this.onHelpClick, false);
    return el;
  },
  
  onHelpClick: function() {
    let str = `- Only type letters and numbers displayed in the image.
- If needed, use the slider to align the image to make it readable.
- Make sure to not block any cookies set by 4chan.`;
    alert(str);
  },
  
  onTicketCaptchaError: function() {
    TCaptcha.toggleMsgOverlay(true, "Couldn't load the captcha.<br><br>Please check your browser's content blocker.");
  },
  
  onTicketCaptchaDone: function(resp) {
    TCaptcha.reloadNode.setAttribute('data-ticket-resp', resp);
    TCaptcha.destroyTicketCaptcha();
    TCaptcha.onReloadClick();
  },
  
  loadTicketCaptcha: function() {
    window.pcd_c_loaded = TCaptcha.buildTicketCaptcha;
    window.pcd_c_done = TCaptcha.onTicketCaptchaDone;
    TCaptcha.toggleMsgOverlay(true, 'Loading…');
    let s = document.createElement('script');
    s.src = 'https://js.hcaptcha.com/1/api.js?onload=pcd_c_loaded&render=explicit&recaptchacompat=off';
    s.onerror = TCaptcha.onTicketCaptchaError;
    document.head.appendChild(s);
  },
  
  buildTicketCaptcha: function() {
    let self = TCaptcha;
    
    self.toggleMsgOverlay(false);
    
    if (!window.hcaptcha) {
      self.loadTicketCaptcha();
      return;
    }
    
    let el = document.createElement('div');
    el.id = 't-tc-cnt';
    self.imgCntNode.appendChild(el);
    
    let wid = window.hcaptcha.render('t-tc-cnt', {
      sitekey: self.hCaptchaSiteKey,
      callback: 'pcd_c_done'
    });
    
    el.setAttribute('data-wid', wid);
    
    self.ticketCaptchaNode = el;
  },
  
  destroyTicketCaptcha: function() {
    let self = TCaptcha;
    
    if (!window.hcaptcha || !self.ticketCaptchaNode) {
      return;
    }
    
    let wid = self.ticketCaptchaNode.getAttribute('data-wid');
    window.hcaptcha.reset(wid);
    self.imgCntNode.removeChild(self.ticketCaptchaNode);
    self.ticketCaptchaNode = null;
  },
  
  onReloadClick: function() {
    let btn = TCaptcha.reloadNode;
    let board = btn.getAttribute('data-board');
    let thread_id = btn.getAttribute('data-tid');
    let ticket_resp = btn.getAttribute('data-ticket-resp');
    btn.removeAttribute('data-ticket-resp');
    TCaptcha.toggleReloadBtn(false, 'Loading');
    TCaptcha.load(board, thread_id, ticket_resp);
  },
  
  onFrameMessage: function(e) {
    if (e.origin !== `https://sys.${TCaptcha.domain}`) {
      return;
    }
    
    if (e.data && e.data.twister) {
      TCaptcha.destroyFrame();
      TCaptcha.buildFromJson(e.data.twister);
    }
  },
  
  onFrameError: function(e) {
    TCaptcha.unlockReloadBtn();
    
    console.log(e);
    
    if (TCaptcha.errorCb) {
      TCaptcha.errorCb.call(null,
        "Couldn't load the captcha frame. Check your content blocker settings."
      );
    }
  },
  
  load: function(board, thread_id, ticket_resp) {
    let self = TCaptcha;
    
    clearTimeout(self.frameTimeout);
    clearTimeout(self.reloadTimeout);
    clearTimeout(self.expireTimeout);
    
    let params = ['framed=1'];
    
    if (board) {
      params.push('board=' + board);
    }
    
    if (thread_id > 0) {
      params.push('thread_id=' + thread_id);
    }
    
    if (ticket_resp) {
      params.push('ticket_resp=' + encodeURIComponent(ticket_resp));
    }
    
    let ticket = self.getTicket();
    
    if (ticket) {
      params.push('ticket=' + ticket);
    }
    
    if (params.length > 0) {
      params = '?' + params.join('&');
    }
    
    let src = 'https://sys.' + self.domain + self.path + params;
    
    self.frameNode = self.buildFrameNode();
    self.toggleImgCntNode(false);
    self.node.insertBefore(self.frameNode, self.imgCntNode);
    self.frameTimeout = setTimeout(self.onFrameTimeout, 60000, src);
    self.frameNode.src = src;
  },
  
  onFrameTimeout: function(src) {
    let self = TCaptcha;
    
    self.destroyFrame();
    
    console.log('Captcha frame timeout');
    
    if (self.errorCb) {
      self.errorCb.call(null, `Couldn't get the captcha.
Make sure your browser doesn't block content on 4chan then click
<a href="${src.replace('framed=1', 'opened=1')}" target="_blank">here</a>.`);
    }
  },
  
  destroyFrame: function() {
    let self = TCaptcha;
    
    clearTimeout(self.frameTimeout);
    self.frameTimeout = null;
    if (self.frameNode) {
      self.frameNode.remove();
      self.frameNode = null;
    }
    self.toggleImgCntNode(true);
    self.unlockReloadBtn();
  },
  
  unlockReloadBtn: function() {
    TCaptcha.reloadTs = null;
    TCaptcha.toggleReloadBtn(true, 'Get Captcha');
  },
  
  toggleReloadBtn: function(flag, label) {
    let self = TCaptcha;
    
    if (self.reloadNode) {
      self.reloadNode.disabled = !flag;
      
      if (label !== undefined) {
        self.reloadNode.textContent = label;
      }
    }
  },
  
  onCaptchaFailed: function() {
    let self = TCaptcha;
    
    let cd = self.failCd * 1000;
    
    if (self.reloadTs && self.reloadTs < cd) {
      self.setReloadCd(cd, true);
    }
  },
  
  setReloadCd: function(cd, visible, onDone) {
    let self = TCaptcha;
    
    if (!self.node) {
      return;
    }
    
    clearTimeout(self.reloadTimeout);
    
    self.onReloadCdDone = onDone;
    
    self.pcdBypassable = visible === -1;
    
    if (cd) {
      self.toggleReloadBtn(false);
      if (visible) {
        self.reloadTs = Date.now() + cd;
        self.onReloadCdTick();
      }
      else {
        self.reloadTimeout = setTimeout(self.stopReloadCd, cd);
      }
    }
    else {
      self.stopReloadCd();
    }
  },
  
  stopReloadCd: function() {
    let self = TCaptcha;
    self.unlockReloadBtn();
    if (self.onReloadCdDone) {
      self.onReloadCdDone.call(self);
    }
  },
  
  onReloadCdTick: function() {
    let self = TCaptcha;
    
    if (!self.reloadNode || !self.reloadTs) {
      return;
    }
    
    let cd = self.reloadTs - Date.now();
    
    if (self.pcdBypassable) {
      if (document.cookie.indexOf('_ev1=') !== -1) {
        cd = 0;
      }
    }
    
    if (cd > 0) {
      self.reloadNode.textContent = Math.ceil(cd / 1000);
      self.reloadTimeout = setTimeout(self.onReloadCdTick, Math.min(cd, 1000));
    }
    else {
      self.stopReloadCd();
    }
  },
  
  clearChallenge: function() {
    let self = TCaptcha;
    
    if (self.node) {
      self.challengeNode.value = '';
      self.respNode.value = '';
      self.fgNode.style.backgroundImage = '';
      self.bgNode.style.backgroundImage = '';
      self.toggleSlider(false);
      self.toggleMsgOverlay(false);
    }
  },
  
  toggleSlider: function(flag) {
    TCaptcha.sliderNode.style.visibility = flag ? '' : 'hidden';
    TCaptcha.sliderNode.style.boxShadow = flag ? '' : '0 0 4px 2px #1d8dc4';
  },
  
  toggleMsgOverlay: function(flag, txt) {
    if (txt !== undefined) {
      TCaptcha.msgNode.innerHTML = `<div>${txt}</div>`;
    }
    TCaptcha.msgNode.style.display = flag ? 'grid' : 'none';
  },
  
  onSliderInput: function() {
    var m = -Math.floor((+this.value) / 100 * this.twisterDelta);
    TCaptcha.bgNode.style.backgroundPositionX = m + 'px';
  },
  
  onTicketPcdTick: function() {
    let self = TCaptcha;
    
    let el = document.getElementById('t-pcd');
    
    if (!el) {
      return;
    }
    
    let pcd = +el.getAttribute('data-pcd');
    
    pcd = pcd - (0 | (Date.now() / 1000));
    
    if (pcd <= 0) {
      self.onTicketPcdEnd();
      return;
    }
    
    el.textContent = pcd;
    
    setTimeout(self.updateTicketPcd, 1000);
  },
  
  clearTicketOverlay: function() {
    TCaptcha.toggleMsgOverlay(false);
  },
  
  buildFromJson: function(data) {
    let self = TCaptcha;
    
    if (!self.node) {
      return;
    }
    
    self.unlockReloadBtn();
    self.toggleSlider(false);
    self.toggleMsgOverlay(false);
    
    self.setTicket(data.ticket);
    
    if (TCaptcha.errorCb) {
      TCaptcha.errorCb.call(null, '');
    }
    
    if (data.cd) {
      self.setReloadCd(data.cd * 1000, !data.challenge);
    }
    
    if (data.mpcd) {
      self.clearChallenge();
      self.destroyTicketCaptcha();
      self.buildTicketCaptcha();
      return;
    }
    
    if (data.pcd) {
      self.buildTicket(data);
      return;
    }
    
    if (data.error) {
      console.log(data.error);
      
      if (TCaptcha.errorCb) {
        TCaptcha.errorCb.call(null, data.error);
      }
      
      return;
    }
    
    self.imgCntNode.style.width = data.img_width + 'px';
    self.imgCntNode.style.height = data.img_height + 'px';
    
    self.challengeNode.value = data.challenge;
    
    self.expireTimeout = setTimeout(self.clearChallenge, data.ttl * 1000 - 3000);
    
    if (data.bg_width) {
      self.buildTwister(data);
    }
    else if (data.img) {
      self.buildStatic(data);
    }
    else {
      self.buildNoop(data);
    }
  },
  
  buildTwister: function(data) {
    let self = TCaptcha;
    
    self.fgNode.style.backgroundImage = 'url(data:image/png;base64,' + data.img + ')';
    self.bgNode.style.backgroundImage = 'url(data:image/png;base64,' + data.bg + ')';
    
    self.bgNode.style.backgroundPositionX = '0px';
    
    self.toggleSlider(true);
    self.sliderNode.value = 0;
    self.sliderNode.twisterDelta = data.bg_width - data.img_width;
    self.sliderNode.focus();
  },
  
  buildStatic: function(data) {
    let self = TCaptcha;
    self.fgNode.style.backgroundImage = 'url(data:image/png;base64,' + data.img + ')';
    self.bgNode.style.backgroundImage = '';
  },
  
  buildTicket: function(data) {
    let self = TCaptcha;
    self.toggleMsgOverlay(true, data.pcd_msg || 'Please wait a while.');
    self.fgNode.style.backgroundImage = '';
    self.bgNode.style.backgroundImage = '';
    self.setReloadCd(data.pcd * 1000, data.bpcd ? -1 : true, self.clearTicketOverlay);
  },
  
  buildNoop: function(data) {
    let self = TCaptcha;
    self.toggleMsgOverlay(true, 'Verification not required.');
    self.fgNode.style.backgroundImage = '';
    self.bgNode.style.backgroundImage = '';
  }
};
