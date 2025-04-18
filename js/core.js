var $L = {
  nws: {"aco":1,"b":1,"bant":1,"d":1,"e":1,"f":1,"gif":1,"h":1,"hc":1,"hm":1,"hr":1,"i":1,"ic":1,"pol":1,"r":1,"r9k":1,"s":1,"s4s":1,"soc":1,"t":1,"trash":1,"u":1,"wg":1,"y":1},
  blue: '4chan.org', red: '4chan.org',
  d: function(b) {
    return $L.nws[b] ? $L.red : $L.blue;
  }
};

/**
 * Captcha
*/
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

/**
 * Tooltips
 */
var Tip = {
  node: null,
  timeout: null,
  delay: 300,
  
  init: function() {
    document.addEventListener('mouseover', this.onMouseOver, false);
    document.addEventListener('mouseout', this.onMouseOut, false);
  },
  
  onMouseOver: function(e) {
    var cb, data, t;
    
    t = e.target;
    
    if (Tip.timeout) {
      clearTimeout(Tip.timeout);
      Tip.timeout = null;
    }
    
    if (t.hasAttribute('data-tip')) {
      data = null;
      
      if (t.hasAttribute('data-tip-cb')) {
        cb = t.getAttribute('data-tip-cb');
        if (window[cb]) {
          data = window[cb](t);
        }
      }
      Tip.timeout = setTimeout(Tip.show, Tip.delay, e.target, data);
    }
  },
  
  onMouseOut: function(e) {
    if (Tip.timeout) {
      clearTimeout(Tip.timeout);
      Tip.timeout = null;
    }
    
    Tip.hide();
  },
  
  show: function(t, data, pos) {
    var el, rect, style, left, top;
    
    rect = t.getBoundingClientRect();
    
    el = document.createElement('div');
    el.id = 'tooltip';
    
    if (data) {
      el.innerHTML = data;
    }
    else {
      el.textContent = t.getAttribute('data-tip');
    }
    
    if (!pos) {
      pos = 'top';
    }
    
    el.className = 'tip-' + pos;
    
    document.body.appendChild(el);
    
    left = rect.left - (el.offsetWidth - t.offsetWidth) / 2;
    
    if (left < 0) {
      left = rect.left + 2;
      el.className += '-right';
    }
    else if (left + el.offsetWidth > document.documentElement.clientWidth) {
      left = rect.left - el.offsetWidth + t.offsetWidth + 2;
      el.className += '-left';
    }
    
    top = rect.top - el.offsetHeight - 5;
    
    style = el.style;
    style.top = (top + window.pageYOffset) + 'px';
    style.left = left + window.pageXOffset + 'px';
    
    Tip.node = el;
  },
  
  hide: function() {
    if (Tip.node) {
      document.body.removeChild(Tip.node);
      Tip.node = null;
    }
  }
};

/**
 * Settings Syncher
 */
/*
var StorageSync = {
  queue: [],
  
  init: function() {
    var el, self = StorageSync;
    
    if (self.inited || !document.body) {
      return;
    }
    
    self.remoteFrame = null;
    
    self.remoteOrigin = location.protocol + '//boards.'
      + (location.host === 'boards.4channel.org' ? '4chan' : '4channel')
      + '.org';
    
    window.addEventListener('message', self.onFrameMessage, false);
    
    el = document.createElement('iframe');
    el.width = 0;
    el.height = 0;
    el.style.display = 'none';
    el.style.visibility = 'hidden';
    
    el.src = self.remoteOrigin + '/syncframe.html';
    
    document.body.appendChild(el);
    
    self.inited = true;
  },
  
  onFrameMessage: function(e) {
    var self = StorageSync;
    
    if (e.origin !== self.remoteOrigin) {
      return;
    }
    
    if (e.data === 'ready') {
      self.remoteFrame = e.source;
      
      if (self.queue.length) {
        self.send();
      }
      
      return;
    }
  },
  
  sync: function(key) {
    var self = StorageSync;
    
    self.queue.push(key);
    self.send();
  },
  
  send: function() {
    var i, key, data, self = StorageSync;
    
    if (!self.inited) {
      return self.init();
    }
    
    if (!self.remoteFrame) {
      return;
    }
    
    data = {};
    
    for (i = 0; key = self.queue[i]; ++i) {
      data[key] = localStorage.getItem(key);
    }
    
    self.queue = [];
    
    self.remoteFrame.postMessage({ storage: data }, self.remoteOrigin);
  }
};
*/
function mShowFull(t) {
  var el, data;
  
  if (t.className === 'name') {
    if (el = t.parentNode.parentNode.parentNode
        .getElementsByClassName('name')[1]) {
      data = el.innerHTML;
    }
  }
  else if (t.parentNode.className === 'subject') {
    if (el = t.parentNode.parentNode.parentNode.parentNode
        .getElementsByClassName('subject')[1]) {
      data = el.innerHTML;
    }
  }
  else if (/fileThumb/.test(t.parentNode.className)) {
    if (el = t.parentNode.parentNode.getElementsByClassName('fileText')[0]) {
      el = el.firstElementChild;
      data = el.getAttribute('title') || el.innerHTML;
    }
  }
  
  return data;
}

function loadBannerImage() {
  var cnt = document.getElementById('bannerCnt');
  
  if (!cnt || cnt.offsetWidth <= 0) {
    return;
  }
  
  cnt.innerHTML = '<img alt="4chan" src="//s.4cdn.org/image/title/'
    + cnt.getAttribute('data-src') + '">';
}

function onMobileSelectChange() {
  var board, page;
  
  board = this.options[this.selectedIndex].value;
  page = (board !== 'f' && /\/catalog$/.test(location.pathname)) ? 'catalog' : '';
  
  window.location = '//boards.' + $L.d(board) + '/' + board + '/' + page;
}

function buildMobileNav() {
  var el, boards, i, b, html, order;
  
  if (el = document.getElementById('boardSelectMobile')) {
    html = '';
    order = [];
    
    boards = document.querySelectorAll('#boardNavDesktop .boardList a');
    
    for (i = 0; b = boards[i]; ++i) {
      order.push(b);
    }
    
    order.sort(function(a, b) {
      if (a.textContent < b.textContent) {
        return -1;
      }
      if (a.textContent > b.textContent) {
        return 1;
      }
      return 0;
    });
    
    for (i = 0; b = order[i]; ++i) {
      html += '<option class="'
        + (b.parentNode.classList.contains('nwsb') ? 'nwsb' : '') + '" value="'
        + b.textContent + '">/'
        + b.textContent + '/ - '
        + b.title + '</option>';
    }
    
    el.innerHTML = html;
  }
}

function cloneTopNav() {
  var navT, navB, ref, el;
  
  navT = document.getElementById('boardNavDesktop');
  
  if (!navT) {
    return;
  }
  
  ref = document.getElementById('absbot');
  
  navB = navT.cloneNode(true);
  navB.id = navB.id + 'Foot';
  
  if (el = navB.querySelector('#navtopright')) {
    el.id = 'navbotright';
  }
  
  if (el = navB.querySelector('#settingsWindowLink')) {
    el.id = el.id + 'Bot';
  }
  
  document.body.insertBefore(navB, ref);
}

function initPass() {
  if (get_cookie("pass_enabled") == '1' || get_cookie('extra_path')) {
    window.passEnabled = true;
  }
  else {
    window.passEnabled = false;
  }
}

function initBlotter() {
  var mTime, seenTime, el;
  
  el = document.getElementById('toggleBlotter');
  
  if (!el) {
    return;
  }
  
  el.addEventListener('click', toggleBlotter, false);
  
  seenTime = localStorage.getItem('4chan-blotter');
  
  if (!seenTime) {
    return;
  }
  
  mTime = +el.getAttribute('data-utc');
  
  if (mTime <= +seenTime) {
    toggleBlotter();
  }
}

function toggleBlotter(e) {
  var el, btn;
  
  e && e.preventDefault();
  
  el = document.getElementById('blotter-msgs');
  
  if (!el) {
    return;
  }
  
  btn = document.getElementById('toggleBlotter');
  
  if (el.style.display == 'none') {
    el.style.display = '';
    localStorage.removeItem('4chan-blotter');
    btn.textContent = 'Hide';
    
    el = btn.nextElementSibling;
    
    if (el.style.display) {
      el.style.display = '';
    }
  }
  else {
    el.style.display = 'none';
    localStorage.setItem('4chan-blotter', btn.getAttribute('data-utc'));
    btn.textContent = 'Show Blotter';
    btn.nextElementSibling.style.display = 'none';
  }
}

function onRecaptchaLoaded() {
  if (document.getElementById('postForm').style.display == 'table') {
    initRecaptcha();
  }
}

function initRecaptcha() {
  var el;
  
  el = document.getElementById('g-recaptcha');
  
  if (!el || el.firstElementChild) {
    return;
  }
  
  if (!window.passEnabled && window.grecaptcha) {
    grecaptcha.render(el, {
      sitekey: window.recaptchaKey,
      theme: (activeStyleSheet === 'Tomorrow' || window.dark_captcha) ? 'dark' : 'light'
    });
  }
}

function initTCaptcha() {
  let el = document.getElementById('t-root');
  
  if (el) {
    let board = location.pathname.split(/\//)[1];
    
    let thread_id;
    
    if (document.forms.post && document.forms.post.resto) {
      thread_id = +document.forms.post.resto.value;
    }
    else {
      thread_id = 0;
    }
    
    TCaptcha.init(el, board, thread_id, 5);
    TCaptcha.setErrorCb(window.showPostFormError);
  }
}

function initAnalytics() {
  var tid = location.host.indexOf('.4channel.org') !== -1 ? 'UA-166538-5' : 'UA-166538-1';
  
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
  
  ga('create', tid, {'sampleRate': 1});
  ga('set', 'anonymizeIp', true);
  ga('send','pageview');
}

function initAdsPF(cnt, slot_id) {
  let sid, nid;
  
  if (slot_id == 1) {
    sid = '657b2d8958f9186175770b1f';
    nid = 'pf-6892-1';
  }
  else if (slot_id == 2) {
    sid = '657b2d9d58f9186175770b37';
    nid = 'pf-6893-1';
  }
  else if (slot_id == 3) {
    sid = '657b2d56256794003cd16fe4';
    nid = 'pf-6890-1';
  }
  else if (slot_id == 4) {
    sid = '657b2d74256794003cd17019';
    nid = 'pf-6891-1';
  }
  else {
    return;
  }
  
  cnt.innerHTML = '';
  
  let d = document.createElement('div');
  d.id = nid;
  cnt.appendChild(d);
  
  window.pubfuturetag = window.pubfuturetag || [];
  window.pubfuturetag.push({unit: sid, id: nid});
}

function initAdsADT(scope) {
  var el, nodes, i, cls, s;
  
  if (window.matchMedia && window.matchMedia('(max-width: 480px)').matches && localStorage.getItem('4chan_never_show_mobile') != 'true') {
    cls = 'adg-m';
  }
  else {
    cls = 'adg';
  }
  
  nodes = (scope || document).getElementsByClassName(cls);
  
  for (i = 0; el = nodes[i]; ++i) {
    if (el.hasAttribute('data-abc')) {
      s = document.createElement('iframe');
      s.setAttribute('scrolling', 'no');
      s.setAttribute('frameborder', '0');
      s.setAttribute('allowtransparency', 'true');
      s.setAttribute('marginheight', '0');
      s.setAttribute('marginwidth', '0');
      
      if (cls === 'adg') {
        s.setAttribute('width', '728');
        s.setAttribute('height', '90');
      }
      else {
        s.setAttribute('width', '300');
        s.setAttribute('height', '250');
      }
      
      s.setAttribute('name', 'spot_id_' + el.getAttribute('data-abc'));
      s.src = 'https://a.adtng.com/get/' + el.getAttribute('data-abc') + '?time=' + Date.now();
      el.appendChild(s);
    }
  }
}

function danboAddSlot(n, b, m, s) {
  let pubid = 27;
  
  let el = document.createElement('div');
  el.className = 'danbo_dta';
  
  if (m) {
    if (s) {
      s = '3';
    }
    else {
      s = '4';
      el.id = 'js-danbo-rld';
    }
    el.setAttribute('data-danbo', `${pubid}-${b}-${s}-300-250`);
    el.classList.add('danbo-m');
  }
  else {
    if (s) {
      s = '1';
    }
    else {
      s = '2';
      el.id = 'js-danbo-rld';
    }
    el.setAttribute('data-danbo', `${pubid}-${b}-${s}-728-90`);
    el.classList.add('danbo-d');
  }
  
  n.appendChild(el);
  
  return el;
}

function initAdsDanbo() {
  if (!window.Danbo) {
    return;
  }
  
  let b = location.pathname.split(/\//)[1] || '_';
  
  let m = window.matchMedia && window.matchMedia('(max-width: 480px)').matches;
  
  let nodes = document.getElementsByClassName('danbo-slot');
  
  for (let cnt of nodes) {
    let s = cnt.id === 'danbo-s-t';
    danboAddSlot(cnt, b, m, s);
  }
  
  window.addEventListener('message', function(e) {
    if (e.origin === 'https://hakurei.cdnbo.org' && e.data && e.data.origin === 'danbo') {
      window.initAdsFallback(e.data.unit_id);
    }
  });
  
  window.Danbo.initialize();
}

function reloadAdsDanbo() {
  let cnt = document.getElementById('danbo-s-b');
  
  if (!cnt) {
    return;
  }
  
  cnt.innerHTML = '';
  
  let b = 'a';//location.pathname.split(/\//)[1] || '_';
  
  let m = window.matchMedia && window.matchMedia('(max-width: 480px)').matches;
  
  danboAddSlot(cnt, b, m, false);
  
  window.Danbo.reload('js-danbo-rld');
}

function initAdsFallback(slot_id) {
  let fb = window.danbo_fb;
  
  let cnt_id;
  
  if (slot_id == 1 || slot_id == 3) {
    cnt_id = 'danbo-s-t';
  }
  else {
    cnt_id = 'danbo-s-b';
  }
  
  let cnt = document.getElementById(cnt_id);
  
  if (!cnt) {
    return;
  }
  
  let is_burichan = document.body.classList.contains('ws');
  
  let hr = is_burichan ? 0.1 : 0.01;
  
  if (Math.random() < hr) {
    return initAdsHome(cnt);
  }
  
  if (cnt_id === 'danbo-s-t') {
    if (is_burichan) {
      initAdsPF(cnt, slot_id);
    }
    else if (fb) {
      if (slot_id == 1 && fb.t_abc_d) {
        cnt.innerHTML = `<div class="adg-rects desktop"><div class="adg adp-90" data-abc="${fb.t_abc_d}"></div></div>`;
        initAdsADT(cnt);
      }
      else if (slot_id == 3 && fb.t_abc_m) {
        cnt.innerHTML = `<div class="adg-rects mobile"><div class="adg-m adp-250" data-abc="${fb.t_abc_m}"></div></div>`;
        initAdsADT(cnt);
      }
      else {
        initAdsHome(cnt);
      }
    }
    else {
      initAdsHome(cnt);
    }
  }
  else if (cnt_id === 'danbo-s-b') {
    if (is_burichan) {
      initAdsPF(cnt, slot_id);
    }
    else if (fb) {
      if (slot_id == 4 && fb.b_abc_m) {
        cnt.innerHTML = `<div class="adg-rects mobile"><div class="adg-m adp-250" data-abc="${fb.b_abc_m}"></div></div>`;
        initAdsADT(cnt);
      }
      else {
        initAdsHome(cnt);
      }
    }
    else {
      initAdsHome(cnt);
    }
  }
  else {
    console.log('Fallback', slot_id);
  }
}

function initAdsHome(cnt) {
  let banners = [
    ['advertise', '1.png', '2.png', '3.png'],
    ['pass', '4.png'],
  ];
  
  let banners_m = [
    ['advertise', '1m.png'],
  ];
  
  let d;
  
  if (location.host.indexOf('4channel')) {
    d = '4channel';
  }
  else {
    d = '4chan';
  }
  
  let b;
  
  if (window.matchMedia && window.matchMedia('(max-width: 480px)').matches) {
    b = banners_m;
  }
  else {
    b = banners;
  }
  
  b = b[Math.floor(Math.random() * b.length)];
  
  let href = b[0];
  let src = b[1 + Math.floor(Math.random() * (b.length - 1))];
  
  let a = document.createElement('a');
  a.href = `https://www.${d}.org/${href}`;
  a.target = '_blank';
  
  let img = document.createElement('img');
  img.src = '//s.4cdn.org/image/banners/' + src;
  
  a.appendChild(img);
  
  if (cnt.children.length) {
    cnt.innerHTML = '';
  }
  
  cnt.appendChild(a);
}

function applySearch(e) {
  var str;
  
  e && e.preventDefault();
  
  str = document.getElementById('search-box').value;
  
  if (str !== '') {
    window.location.href = 'catalog#s=' + str;
  }
}

function onKeyDownSearch(e) {
  if (e.keyCode == 13) {
    applySearch();
  }
}

function onReportClick(e) {
  var i, input, nodes, board;
  
  nodes = document.getElementsByTagName('input');
  
  board = location.pathname.split(/\//)[1];
  
  for (i = 0; input = nodes[i]; ++i) {
    if (input.type == 'checkbox' && input.checked && input.value == 'delete') {
      return reppop('https://sys.' + $L.d(board) + '/' + board + '/imgboard.php?mode=report&no='
        + input.name.replace(/[a-z]+/, '')
      );
    }
  }
}

function onStyleSheetChange(e) {
  setActiveStyleSheet(this.value);
}

function onPageSwitch(e) {
  e.preventDefault();
  window.location = this.action;
}

function onMobileFormClick(e) {
  var index = location.pathname.split(/\//).length < 4;
  
  e.preventDefault();
  
  if (window.QR && Main.tid && QR.enabled) {
    QR.show(Main.tid);
  }
  else if (this.parentNode.id == 'mpostform') {
    toggleMobilePostForm(index);
  }
  else {
    toggleMobilePostForm(index, 1);
  }
}

function onMobileRefreshClick(e) {
  locationHashChanged(this);
}

function toggle(name) {
  var a = document.getElementById(name);
  a.style.display = ((a.style.display != 'block') ? 'block' : 'none');
}

function quote(text) {
  if (document.selection) {
    document.post.com.focus();
    var sel = document.selection.createRange();
    sel.text = ">>" + text + "\n";
  } else if (document.post.com.selectionStart || document.post.com.selectionStart == "0") {
    var startPos = document.post.com.selectionStart;
    var endPos = document.post.com.selectionEnd;
    document.post.com.value = document.post.com.value.substring(0, startPos) + ">>" + text + "\n" + document.post.com.value.substring(endPos, document.post.com.value.length);
  } else {
    document.post.com.value += ">>" + text + "\n";
  }
}

function repquote(rep) {
  if (document.post.com.value == "") {
    quote(rep);
  }
}

function reppop(url) {
  var height;
  
  if (window.passEnabled || !window.grecaptcha) {
    height = 205;
  }
  else {
    height = 510;
  }
  
  window.open(url, Date.now(), 
    'toolbar=0,scrollbars=1,location=0,status=1,menubar=0,resizable=1,width=380,height=' + height
  );
  
  return false;
}

function recaptcha_load() {
  var d = document.getElementById("recaptcha_div");
  if (!d) return;

  Recaptcha.create("6Ldp2bsSAAAAAAJ5uyx_lx34lJeEpTLVkP5k04qc", "recaptcha_div",{theme: "clean"});
}

function onParsingDone(e) {
  var i, nodes, n, p, tid, offset, limit;

  tid = e.detail.threadId;
  offset = e.detail.offset;
  
  if (!offset) {
    return;
  }

  nodes = document.getElementById('t' + tid).getElementsByClassName('nameBlock');
  limit = e.detail.limit ? (e.detail.limit * 2) : nodes.length;
  for (i = offset * 2 + 1; i < limit; i+=2) {
    if (n = nodes[i].children[1]) {
      if (currentHighlighted
        && n.className.indexOf('id_' + currentHighlighted) != -1) {
        p = n.parentNode.parentNode.parentNode;
        p.className = 'highlight ' + p.className;
      }
      n.addEventListener('click', idClick, false);
    }
  }
}

function loadExtraScripts() {
  var el, path;
  
  path = readCookie('extra_path');
  
  if (!path || !/^[a-z0-9]+$/.test(path)) {
    return false;
  }
  
  if (window.FC) {
    el = document.createElement('script');
    el.type = 'text/javascript';
    el.src = 'https://s.4cdn.org/js/' + path + '.' + jsVersion + '.js';
    document.head.appendChild(el);
  }
  else {
    document.write('<script type="text/javascript" src="https://s.4cdn.org/js/' + path + '.' + jsVersion + '.js"></script>');
  }
  
  return true;
}


function toggleMobilePostForm(index, scrolltotop) {
  var elem = document.getElementById('mpostform').firstElementChild;
  var postForm = document.getElementById('postForm');
  
  if (elem.className.match('hidden')) {
    elem.className = elem.className.replace('hidden', 'shown');
    postForm.className = postForm.className.replace(' hideMobile', '');
    elem.innerHTML = 'Close Post Form';
    initRecaptcha();
    initTCaptcha();
    checkIncognito();
  }
  else {
    elem.className = elem.className.replace('shown', 'hidden');
    postForm.className += ' hideMobile';
    elem.innerHTML = (index) ? 'Start New Thread' : 'Post Reply';
  }
  
  if (scrolltotop) {
    elem.scrollIntoView();
  }
}

function toggleGlobalMessage(e) {
  var elem, postForm;
  
  if (e) {
    e.preventDefault();
  }
  
  elem = document.getElementById('globalToggle');
  postForm = document.getElementById('globalMessage');

  if( elem.className.match('hidden') ) {
    elem.className = elem.className.replace('hidden', 'shown');
    postForm.className = postForm.className.replace(' hideMobile', '');

    elem.innerHTML = 'Close Announcement';
  } else {
    elem.className = elem.className.replace('shown', 'hidden');
    postForm.className += ' hideMobile';

    elem.innerHTML = 'View Announcement';
  }
}

function checkRecaptcha()
{
  if( typeof RecaptchaState.timeout != 'undefined' ) {
    if( RecaptchaState.timeout == 1800 ) {
      RecaptchaState.timeout = 570;
      Recaptcha._reset_timer();
      clearInterval(captchainterval);
    }
  }
}

function setPassMsg() {
  var el, msg;
  
  el = document.getElementById('captchaFormPart');
  
  if (!el) {
    return;
  }
  
  msg = 'You are using a 4chan Pass. [<a href="https://sys.' + $L.d(location.pathname.split(/\//)[1]) + '/auth?act=logout" onclick="confirmPassLogout(event);" tabindex="-1">Logout</a>]';
  el.children[1].innerHTML = '<div style="padding: 5px;">' + msg + '</div>';
}

function confirmPassLogout(event)
{
  var conf = confirm('Are you sure you want to logout?');
  if( !conf ) {
    event.preventDefault();
    return false;
  }
}

var activeStyleSheet;

function initStyleSheet() {
  var i, rem, link, len;
  
  // fix me
  if (window.FC) {
    return;
  }
  
  if (window.style_group) {
    var cookie = readCookie(style_group);
    activeStyleSheet = cookie ? cookie : getPreferredStyleSheet();
  }
  
  if (window.css_event && localStorage.getItem('4chan_stop_css_event') !== `${window.css_event}-${window.css_event_v}`) {
    activeStyleSheet = '_special';
  }
  
  switch(activeStyleSheet) {
    case "Yotsuba B":
      setActiveStyleSheet("Yotsuba B New", true);
      break;

    case "Yotsuba":
      setActiveStyleSheet("Yotsuba New", true);
      break;

    case "Burichan":
      setActiveStyleSheet("Burichan New", true);
      break;

    case "Futaba":
      setActiveStyleSheet("Futaba New", true);
      break;

    default:
      setActiveStyleSheet(activeStyleSheet, true);
    break;
  }
  
  if (localStorage.getItem('4chan_never_show_mobile') == 'true') {
    link = document.querySelectorAll('link');
    len = link.length;
    for (i = 0; i < len; i++) {
      if (link[i].getAttribute('href').match('mobile')) {
        (rem = link[i]).parentNode.removeChild(rem);
      }
    }
  }
}

function pageHasMath() {
  var i, el, nodes;
  
  nodes = document.getElementsByClassName('postMessage');
  
  for (i = 0; el = nodes[i]; ++i) {
    if (/\[(?:eqn|math)\]|"math">/.test(el.innerHTML)) {
      return true;
    }
  }
  
  return false;
}

function cleanWbr(el) {
  var i, nodes, n;
  
  nodes = el.getElementsByTagName('wbr');
  
  for (i = nodes.length - 1; n = nodes[i]; i--) {
    n.parentNode.removeChild(n);
  }
}

function parseMath() {
  var i, el, nodes;
  
  nodes = document.getElementsByClassName('postMessage');
  
  for (i = 0; el = nodes[i]; ++i) {
    if (/\[(?:eqn|math)\]/.test(el.innerHTML)) {
      cleanWbr(el);
    }
  }
  
  MathJax.Hub.Queue(['Typeset', MathJax.Hub, nodes]);
}

function loadMathJax() {
  var head, script;
  
  head = document.getElementsByTagName('head')[0];
  
  script = document.createElement('script');
  script.type = 'text/x-mathjax-config';
  script.text = "MathJax.Hub.Config({\
extensions: ['Safe.js'],\
tex2jax: { processRefs: false, processEnvironments: false, preview: 'none', inlineMath: [['[math]','[/math]']], displayMath: [['[eqn]','[/eqn]']] },\
Safe: { allow: { URLs: 'none', classes: 'none', cssIDs: 'none', styles: 'none', fontsize: 'none', require: 'none' } },\
displayAlign: 'left', messageStyle: 'none', skipStartupTypeset: true,\
'CHTML-preview': { disabled: true }, MathMenu: { showRenderer: false, showLocale: false },\
TeX: { Macros: { color: '{}', newcommand: '{}', renewcommand: '{}', newenvironment: '{}', renewenvironment: '{}', def: '{}', let: '{}'}}});";
  head.appendChild(script);  
  
  script = document.createElement('script');
  script.src = '//cdn.mathjax.org/mathjax/2.6-latest/MathJax.js?config=TeX-AMS_HTML-full';
  script.onload = parseMath;
  head.appendChild(script);
}

captchainterval = null;
function init() {
  var el, i;
  var error = typeof is_error != "undefined";
  var board = location.href.match(/(?:4chan|4channel)\.org\/(\w+)/)[1];
  var arr = location.href.split(/#/);
  if( arr[1] && arr[1].match(/q[0-9]+$/) ) {
    repquote( arr[1].match(/q([0-9]+)$/)[1] );
  }


  if (window.math_tags && pageHasMath()) {
    loadMathJax();
  }

  if(navigator.userAgent) {
    if( navigator.userAgent.match( /iP(hone|ad|od)/i ) ) {
      links = document.querySelectorAll('s');
      len = links.length;

      for(i = 0; i < len; i++ ) {
        links[i].onclick = function() {
          if (this.hasAttribute('style')) {
            this.removeAttribute('style');
          }
          else {
            this.setAttribute('style', 'color: #fff!important;');
          }
        };
      }
    }
  }

  if( document.getElementById('styleSelector') ) {
        styleSelect = document.getElementById('styleSelector');
        len = styleSelect.options.length;
        for (i = 0; i < len; i++) {
            if (styleSelect.options[i].value == activeStyleSheet) {
                styleSelect.selectedIndex = i;
                continue;
            }
        }
    }

  if (!error && document.forms.post) {
    if (board != 'i' && board != 'ic' && board != 'f') {
      if (window.File && window.FileReader && window.FileList && window.Blob) {
        el = document.getElementById('postFile');
        el && el.addEventListener('change', handleFileSelect, false);
      }
    }
  }

  //window.addEventListener('onhashchange', locationHashChanged, false);

  if( typeof extra != "undefined" && extra && !error ) extra.init();
}

var coreLenCheckTimeout = null;
function onComKeyDown() {
  clearTimeout(coreLenCheckTimeout);
  coreLenCheckTimeout = setTimeout(coreCheckComLength, 500);
}

function coreCheckComLength() {
  var byteLength, comField, error;
  
  if (comlen) {
    comField = document.getElementsByName('com')[0];
    byteLength = encodeURIComponent(comField.value).split(/%..|./).length - 1;
    
    if (byteLength > comlen) {
      if (!(error = document.getElementById('comlenError'))) {
        error = document.createElement('div');
        error.id = 'comlenError';
        error.style.cssText = 'font-weight:bold;padding:5px;color:red;';
        comField.parentNode.appendChild(error);
      }
      error.textContent = 'Error: Comment too long (' + byteLength + '/' + comlen + ').';
    }
    else if (error = document.getElementById('comlenError')) {
      error.parentNode.removeChild(error);
    }
  }
}

function disableMobile() {
  localStorage.setItem('4chan_never_show_mobile', 'true');
  location.reload(true);
}

function enableMobile() {
  localStorage.removeItem('4chan_never_show_mobile');
  location.reload(true);
}

var currentHighlighted = null;
function enableClickableIds()
{
  var i = 0, len = 0;
  var elems = document.getElementsByClassName('posteruid');
  var capcode = document.getElementsByClassName('capcode');

  if( capcode != null ) {
    for( i = 0, len = capcode.length; i < len; i++ ) {
      capcode[i].addEventListener("click", idClick, false);
    }
  }

  if( elems == null ) return;
  for( i = 0, len = elems.length; i < len; i++ ) {
    elems[i].addEventListener("click", idClick, false);
  }
}

function idClick(evt)
{
  var i = 0, len = 0, node;
  var uid = evt.target.className == 'hand' ? evt.target.parentNode.className.match(/id_([^ $]+)/)[1] : evt.target.className.match(/id_([^ $]+)/)[1];

  // remove all .highlight classes
  var hl = document.getElementsByClassName('highlight');
  len = hl.length;
  for( i = 0; i < len; i++ ) {
    var cn = hl[0].className.toString();
    hl[0].className = cn.replace(/highlight /g, '');
  }

  if( currentHighlighted == uid ) {
    currentHighlighted = null;
    return;
  }
  currentHighlighted = uid;

  var nhl = document.getElementsByClassName('id_' + uid);
  len = nhl.length;
  for( i = 0; i < len; i++ ) {
    node = nhl[i].parentNode.parentNode.parentNode;
    if( !node.className.match(/highlight /) ) node.className = "highlight " + node.className;
  }
}

function showPostFormError(msg) {
  var el = document.getElementById('postFormError');
  
  if (msg) {
    el.innerHTML = msg;
    el.style.display = 'block';
  }
  else {
    el.textContent = '';
    el.style.display = '';
  }
}

function handleFileSelect() {
  var fsize, ftype, maxFilesize;
  
  if (this.files) {
    maxFilesize = window.maxFilesize;
    
    fsize = this.files[0].size;
    ftype = this.files[0].type;
    
    if (ftype.indexOf('video/') !== -1 && window.maxWebmFilesize) {
      maxFilesize = window.maxWebmFilesize;
    }
    
    if (fsize > maxFilesize) {
      showPostFormError('Error: Maximum file size allowed is '
        + Math.floor(maxFilesize / 1048576) + ' MB');
    }
    else {
      showPostFormError();
    }
  }
}

function locationHashChanged(e)
{
  var css = document.getElementById('id_css');

  switch( e.id )
  {
    case 'refresh_top':
      url = window.location.href.replace(/#.+/, '#top');
      if( !/top$/.test(url) ) url += '#top';
      css.innerHTML = '<meta http-equiv="refresh" content="0;URL=' + url + '">';
      document.location.reload(true);
      break;

    case 'refresh_bottom':
      url = window.location.href.replace(/#.+/, '#bottom');
      if( !/bottom$/.test(url) ) url += '#bottom';
      css.innerHTML = '<meta http-equiv="refresh" content="0;URL=' + url + '">';
      document.location.reload(true);
      break;

    default:break;
  }

  return true;

}

function setActiveStyleSheet(title, init) {
  var a, link, href, i, nodes, fn;
  
  if( document.querySelectorAll('link[title]').length == 1 ) {
    return;
  }
  
  href = '';
  
  nodes = document.getElementsByTagName('link');
  
  for (i = 0; a = nodes[i]; i++) {
    if (a.getAttribute("title") == "switch") {
      link = a;
    }
    
    if (a.getAttribute("rel").indexOf("style") != -1 && a.getAttribute("title")) {
      if (a.getAttribute("title") == title) {
        href = a.href;
      }
    }
  }

  link && link.setAttribute("href", href);

  if (!init) {
    if (title !== '_special') {
      createCookie(style_group, title, 365, location.host.indexOf('4channel.org') === -1 ? '4chan.org' : '4channel.org');
      
      if (window.css_event) {
        fn = window['fc_' + window.css_event + '_cleanup'];
        localStorage.setItem('4chan_stop_css_event', `${window.css_event}-${window.css_event_v}`);
      }
    }
    else if (window.css_event) {
      fn = window['fc_' + window.css_event + '_init'];
      localStorage.removeItem('4chan_stop_css_event');
    }
    
    //StorageSync.sync('4chan_stop_css_event');
    
    activeStyleSheet = title;
    
    fn && fn();
  }
}

function getActiveStyleSheet() {
  var i, a;
  var link;

    if( document.querySelectorAll('link[title]').length == 1 ) {
        return 'Yotsuba P';
    }

  for (i = 0; (a = document.getElementsByTagName("link")[i]); i++) {
    if (a.getAttribute("title") == "switch")
               link = a;
    else if (a.getAttribute("rel").indexOf("style") != -1 && a.getAttribute("title") && a.href==link.href) return a.getAttribute("title");
  }
  return null;
}

function getPreferredStyleSheet() {
  return (style_group == "ws_style") ? "Yotsuba B New" : "Yotsuba New";
}

function createCookie(name, value, days, domain) {
  let expires;
  
  if (days) {
    var date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    expires = "; expires=" + date.toGMTString();
  }
  else {
    expires = '';
  }
  
  if (domain) {
    domain = "; domain=" + domain;
  }
  else {
    domain = '';
  }
  
  document.cookie = name + "=" + value + expires + "; path=/" + domain;
}

function readCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(';');
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == ' ') c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) == 0) {
      return decodeURIComponent(c.substring(nameEQ.length, c.length));
    }
  }
  return '';
}

// legacy
var get_cookie = readCookie;

function setRetinaIcons() {
  var i, j, nodes;
  
  nodes = document.getElementsByClassName('retina');
  
  for (i = 0; j = nodes[i]; ++i) {
    j.src = j.src.replace(/\.(gif|png)$/, "@2x.$1");
  }
}

function onCoreClick(e) {
  if (/flag flag-/.test(e.target.className) && e.which == 1) {
    window.open('//s.4cdn.org/image/country/'
      + e.target.className.match(/flag-([a-z]+)/)[1]
      + '.gif', '');
  }
}

function showPostForm(e) {
  var el;
  
  e && e.preventDefault();
  
  if (el = document.getElementById('postForm')) {
    $.id('togglePostFormLink').style.display = 'none';
    el.style.display = 'table';
    initRecaptcha();
    initTCaptcha();
  }
}

function oeCanvasPreview(e) {
  var t, el, sel;
  
  if (el = document.getElementById('oe-canvas-preview')) {
    el.parentNode.removeChild(el);
  }
  
  if (e.target.nodeName == 'OPTION' && e.target.value != '0') {
    t = document.getElementById('f' + e.target.value);
    
    if (!t) {
      return;
    }
    
    t = t.getElementsByTagName('img')[0];
    
    if (!t || !t.hasAttribute('data-md5')) {
      return;
    }
    
    el = t.cloneNode();
    el.id = 'oe-canvas-preview';
    sel = e.target.parentNode;
    sel.parentNode.insertBefore(el, sel.nextSibling);
  }
}

function oeClearPreview(e) {
  var el;
  
  if (el = document.getElementById('oe-canvas-preview')) {
    el.parentNode.removeChild(el);
  }
}

var PainterCore = {
  init: function() {
    var cnt, btns;
    
    if (!document.forms.post) {
      return;
    }
    
    cnt = document.forms.post.getElementsByClassName('painter-ctrl')[0];
    
    if (!cnt) {
      return;
    }
    
    btns = cnt.getElementsByTagName('button');
    
    if (!btns[1]) {
      return;
    }
    
    this.data = null;
    this.replayBlob = null;
    
    this.time = 0;
    
    this.btnDraw = btns[0];
    this.btnClear = btns[1];
    this.btnFile = document.getElementById('postFile');
    this.btnSubmit = document.forms.post.querySelector('input[type="submit"]');
    this.inputNodes = cnt.getElementsByTagName('input');
    this.replayCb = cnt.getElementsByClassName('oe-r-cb')[0];
    
    btns[0].addEventListener('click', this.onDrawClick, false);
    btns[1].addEventListener('click', this.onCancel, false);
  },
  
  onDrawClick: function() {
    var w, h, dims = this.parentNode.getElementsByTagName('input');
    
    w = +dims[0].value;
    h = +dims[1].value;
    
    if (w < 1 || h < 1) {
      return;
    }
    
    window.Keybinds && (Keybinds.enabled = false);
    
    Tegaki.open({
      onDone: PainterCore.onDone,
      onCancel: PainterCore.onCancel,
      saveReplay: PainterCore.replayCb && PainterCore.replayCb.checked,
      width: w,
      height: h
    });
  },
  
  replay: function(id) {
    id = +id;
    
    Tegaki.open({
      replayMode: true,
      replayURL: '//i.4cdn.org/' + location.pathname.split(/\//)[1] + '/' + id + '.tgkr'
    });
  },
  
  // move this to tegaki.js
  b64toBlob: function(data) {
    var i, bytes, ary, bary, len;
    
    bytes = atob(data);
    len = bytes.length;
    
    ary = new Array(len);
    
    for (i = 0; i < len; ++i) {
      ary[i] = bytes.charCodeAt(i);
    }
    
    bary = new Uint8Array(ary);
    
    return new Blob([bary]);
  },
  
  onDone: function() {
    var self, el;
    
    self = PainterCore;
    
    window.Keybinds && (Keybinds.enabled = true);
    
    self.btnFile.disabled = true;
    self.btnClear.disabled = false;
    
    self.data = Tegaki.flatten().toDataURL('image/png');
    
    if (Tegaki.saveReplay) {
      self.replayBlob = Tegaki.replayRecorder.toBlob();
    }
    
    if (!Tegaki.hasCustomCanvas && Tegaki.startTimeStamp) {
      self.time = Math.round((Date.now() - Tegaki.startTimeStamp) / 1000);
    }
    else {
      self.time = 0;
    }
    
    self.btnFile.style.visibility = 'hidden';
    
    self.btnDraw.textContent = 'Edit';
    
    for (el of self.inputNodes) {
      el.disabled = true;
    }
    
    document.forms.post.addEventListener('submit', self.onSubmit, false);
  },
  
  onCancel: function() {
    var self = PainterCore;
    
    window.Keybinds && (Keybinds.enabled = true);
    
    self.data = null;
    self.replayBlob = null;
    self.time = 0;
    
    self.btnFile.disabled = false;
    self.btnClear.disabled = true;
    
    self.btnFile.style.visibility = '';
    
    self.btnDraw.textContent = 'Draw';
    
    for (var el of self.inputNodes) {
      el.disabled = false;
    }
    
    document.forms.post.removeEventListener('submit', self.onSubmit, false);
  },
  
  onSubmit: function(e) {
    var formdata, blob, xhr;
    
    e.preventDefault();
    
    formdata = new FormData(this);
    
    blob = PainterCore.b64toBlob(PainterCore.data.slice(PainterCore.data.indexOf(',') + 1));
    
    if (blob) {
      formdata.append('upfile', blob, 'tegaki.png');
      
      if (PainterCore.replayBlob) {
        formdata.append('oe_replay', PainterCore.replayBlob, 'tegaki.tgkr');
      }
    }
    
    formdata.append('oe_time', PainterCore.time);
    
    xhr = new XMLHttpRequest();
    xhr.open('POST', this.action, true);
    xhr.withCredentials = true;
    xhr.onerror = PainterCore.onSubmitError;
    xhr.onload = PainterCore.onSubmitDone;
    
    xhr.send(formdata);
    
    PainterCore.btnSubmit.disabled = true;
  },
  
  onSubmitError: function() {
    PainterCore.btnSubmit.disabled = false;
    showPostFormError('Connection Error.');
  },
  
  onSubmitDone: function() {
    var resp, ids, tid, pid, board;
    
    PainterCore.btnSubmit.disabled = false;
    
    if (ids = this.responseText.match(/<!-- thread:([0-9]+),no:([0-9]+) -->/)) {
      tid = +ids[1];
      pid = +ids[2];
      
      if (!tid) {
        tid = pid;
      }
      
      board = location.pathname.split(/\//)[1];
      
      window.location.href = '/' + board + '/thread/' + tid + '#p' + pid;
      
      PainterCore.onCancel();
      
      if (tid != pid) {
        PainterCore.btnClear.disabled = true;
        window.location.reload();
      }
      
      return;
    }
    
    if (resp = this.responseText.match(/"errmsg"[^>]*>(.*?)<\/span/)) {
      showPostFormError(resp[1]);
    }
  }
};

function oeReplay(id) {
  PainterCore.replay(id);
}

/*! https://github.com/Joe12387/detectIncognito */
function checkIncognito() {
  if (window.isIncognito !== undefined) {
    return;
  }
  
  if (!navigator.maxTouchPoints || navigator.vendor === undefined) {
    window.isIncognito = false;
    return;
  }
  
  (new Promise(function(resolve, reject) {
    let eh = eval.toString().length;
    
    if (navigator.vendor.indexOf('Apple') === 0 && eh === 37) {
      if (navigator.maxTouchPoints === undefined) {
        resolve(false);
      }
      
      let db_name = Math.random().toString();
      
      try {
        let db = window.indexedDB.open(db_name, 1);
        db.onupgradeneeded = function (e) {
          let res = e.target.result;
          try {
            res.createObjectStore('test', { autoIncrement: true }).put(new Blob);
            resolve(false);
          }
          catch(err) {
            let msg;
            if (err instanceof Error) {
              msg = err.message;
            }
            if (typeof msg !== 'string') {
              resolve(false);
            }
            resolve(/BlobURLs are not yet supported/.test(msg));
          }
          finally {
            res.close();
            window.indexedDB.deleteDatabase(db_name);
          }
        };
      }
      catch(err) {
        resolve(false);
      }
    }
    else if (navigator.vendor.indexOf('Google') === 0 && eh === 33) {
      let hsl;
      
      try {
        hsl = performance.memory.jsHeapSizeLimit;
      }
      catch(err) {
        hsl = 1073741824;
      }
      
      navigator.webkitTemporaryStorage.queryUsageAndQuota(function (_, quota) {
        let q = Math.round(quota / (1024 * 1024));
        let q_lim = Math.round(hsl / (1024 * 1024)) * 2;
        resolve(q < q_lim);
      }, function (err) {
        resolve(false);
      });
    }
    else if (document.body.style.MozAppearance !== undefined && eh === 37) {
      resolve(navigator.serviceWorker === undefined);
    }
    else {
      resolve(false);
    }
  })).then((v) => window.isIncognito = v);
}

function onPostFormSubmit(e) {
  let el = $.id('postFile');
  if (el && el.value && window.isIncognito) {
    e.stopPropagation()
    e.preventDefault();
    el.value = '';
    showPostFormError('Uploading files in incognito mode is not allowed.'
      + '<br>The File field has been cleared.');
    return false;
  }
}

function contentLoaded() {
  var i, el, el2, nodes, len, mobileSelect, params, board, val, fn;
  
  document.removeEventListener('DOMContentLoaded', contentLoaded, true);
  
  initAdsADT();
  
  initAdsDanbo();
  
  if (document.post) {
    document.post.name.value = get_cookie("4chan_name");
    document.post.email.value = get_cookie("options");
    document.post.addEventListener('submit', onPostFormSubmit, false);
  }
  
  cloneTopNav();
  
  initAnalytics();
  
  params = location.pathname.split(/\//);
  
  board = params[1];
  
  if (window.passEnabled) {
    setPassMsg();
  }
  
  if (window.Tegaki) {
    PainterCore.init();
  }
  
  if (el = document.getElementById('bottomReportBtn')) {
    el.addEventListener('click', onReportClick, false);
  }
  
  if (el = document.getElementById('styleSelector')) {
    el.addEventListener('change', onStyleSheetChange, false);
  }
  
  // Post form toggle
  if (el = document.getElementById('togglePostFormLink')) {
    if (el = el.firstElementChild) {
      el.addEventListener('click', showPostForm, false);
    }
    if (location.hash === '#reply') {
      showPostForm();
    }
  }
  
  // Selectable flags
  if ((el = document.forms.post) && el.flag) {
    el.flag.addEventListener('change', onBoardFlagChanged, false);
    
    if ((val = localStorage.getItem('4chan_flag_' + board)) && (el2 = el.querySelector('option[value="' + val + '"]'))) {
      el2.setAttribute('selected', 'selected');
    }
  }
  
  // Mobile nav menu
  buildMobileNav();
  
  // Mobile global message toggle
  if (el = document.getElementById('globalToggle')) {
    el.addEventListener('click', toggleGlobalMessage, false);
  }
  
  if (localStorage.getItem('4chan_never_show_mobile') == 'true') {
    if (el = document.getElementById('disable-mobile')) {
      el.style.display = 'none';
      el = document.getElementById('enable-mobile');
      el.parentNode.style.cssText = 'display: inline !important;';
    }
  }
  
  if (mobileSelect = document.getElementById('boardSelectMobile')) {
    len = mobileSelect.options.length;
    for ( i = 0; i < len; i++) {
      if (mobileSelect.options[i].value == board) {
        mobileSelect.selectedIndex = i;
        continue;
      }
    }
    
    mobileSelect.addEventListener('change', onMobileSelectChange, false);
  }
  
  if (document.forms.oeform && (el = document.forms.oeform.oe_src)) {
    el.addEventListener('mouseover', oeCanvasPreview, false);
    el.addEventListener('mouseout', oeClearPreview, false);
  }
  
  if (params[2] != 'catalog') {
    // Mobile post form toggle
    nodes = document.getElementsByClassName('mobilePostFormToggle');
    
    for (i = 0; el = nodes[i]; ++i) {
      el.addEventListener('click', onMobileFormClick, false);
    }
    
    if (el = document.getElementsByName('com')[0]) {
      el.addEventListener('keydown', onComKeyDown, false);
      el.addEventListener('paste', onComKeyDown, false);
      el.addEventListener('cut', onComKeyDown, false);
    }
    
    // Mobile refresh buttons
    if (el = document.getElementById('refresh_top')) {
      el.addEventListener('mouseup', onMobileRefreshClick, false);
    }
    
    if (el = document.getElementById('refresh_bottom')) {
      el.addEventListener('mouseup', onMobileRefreshClick, false);
    }
    
    // Clickable flags
    if (board == 'int' || board == 'sp' || board == 'pol') {
      el = document.getElementById('delform');
      el.addEventListener('click', onCoreClick, false);
    }
    
    // Page switcher + Search field
    if (!params[3]) {
      nodes = document.getElementsByClassName('pageSwitcherForm');
      
      for (i = 0; el = nodes[i]; ++i) {
        el.addEventListener('submit', onPageSwitch, false);
      }
      
      if (el = document.getElementById('search-box')) {
        el.addEventListener('keydown', onKeyDownSearch, false);
      }
    }
    
    if (window.clickable_ids) {
      enableClickableIds();
    }
    
    Tip.init();
  }
  
  if (window.devicePixelRatio >= 2) {
    setRetinaIcons();
  }
  
  initBlotter();
  
  loadBannerImage();
  
  if (window.css_event && activeStyleSheet === '_special') {
    fn = window['fc_' + window.css_event + '_init'];
    fn && fn();
  }
}

function onBoardFlagChanged() {
  var key = '4chan_flag_' + location.pathname.split(/\//)[1];
  
  if (this.value === '0') {
    localStorage.removeItem(key);
  }
  else {
    localStorage.setItem(key, this.value);
  }
}

initPass();

window.onload = init;

if (window.clickable_ids) {
  document.addEventListener('4chanParsingDone', onParsingDone, false);
}

document.addEventListener('4chanMainInit', loadExtraScripts, false);
document.addEventListener('DOMContentLoaded', contentLoaded, true);

initStyleSheet();
