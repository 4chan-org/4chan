/**
 * Janitor Extension
 */

(function() {
/**
 * Admin tools
 */
var AdminTools = {
  cacheTTL: 60000,
  autoRefreshDelay: 120000,
  autoRefreshTimeout: null
};

// FIXME, put it as a helper in extension.js
AdminTools.initVisibilityAPI = function() {
  this.hidden = 'hidden';
  this.visibilitychange = 'visibilitychange';
  
  if (typeof document.hidden === 'undefined') {
    if ('mozHidden' in document) {
      this.hidden = 'mozHidden';
      this.visibilitychange = 'mozvisibilitychange';
    }
    else if ('webkitHidden' in document) {
      this.hidden = 'webkitHidden';
      this.visibilitychange = 'webkitvisibilitychange';
    }
    else if ('msHidden' in document) {
      this.hidden = 'msHidden';
      this.visibilitychange = 'msvisibilitychange';
    }
  }
  
  document.addEventListener(this.visibilitychange, this.onVisibilityChange, false);
};

AdminTools.init = function() {
  var cnt, html;
  
  AdminTools.initVisibilityAPI();
  
  cnt = document.createElement('div');
  cnt.className = 'extPanel reply';
  cnt.id = 'adminToolbox';
  cnt.setAttribute('data-trackpos', 'AT-position');

  if( Config['AT-position'] ) {
    cnt.style.cssText = Config['AT-position'];
  } else {
    cnt.style.right = '10px';
    cnt.style.top = '380px';
  }

  cnt.style.position = Config.fixedAdminToolbox ? 'fixed' : '';

  html = '<div class="drag" id="atHeader">Janitor Tools'
    + '<img alt="Refresh" title="Refresh" src="' + Main.icons.refresh
    + '" id="atRefresh" data-cmd="at-refresh" class="pointer right"></div>'
    + '<h4><a href="https://' + J.reportsSubDomain + '.4chan.org/" target="_blank">Reports</a>: '
    + '<span title="Total" id="at-total">?</span> ('
    + '<span title="Illegal" id="at-illegal">?</span>)</h4>'
    + '<h4 id="at-msg-cnt"><a data-cmd="at-msg" href="https://' + J.reportsSubDomain
      + '.4chan.org/?action=staffmessages" target="_blank">Messages</a>: <span id="at-msg">?</span></h4>';

  cnt.innerHTML = html;
  document.body.appendChild(cnt);
  AdminTools.refreshReportCount();

  Draggable.set($.id('atHeader'));
};

AdminTools.onVisibilityChange = function() {
  var self;
  
  self = AdminTools;
  
  if (document[AdminTools.hidden]) {
    clearInterval(self.autoRefreshTimeout);
    self.autoRefreshTimeout = null;
  }
  else {
    self.refreshReportCount();
    self.autoRefreshTimeout = setInterval(self.refreshReportCount, self.autoRefreshDelay);
  }
};

AdminTools.refreshReportCount = function(force) {
  var xhr, cache;
  
  if (force !== true && (cache = localStorage.getItem('4chan-cache-rc'))) {
    cache = JSON.parse(cache);
    
    if (cache.ts > Date.now() - AdminTools.cacheTTL) {
      $.id('at-total').textContent = cache.data[0];
      $.id('at-illegal').textContent = cache.data[1];
      
      $.id('at-msg-cnt').style.display = cache.data[2] ? 'block' : '';
      $.id('at-msg').textContent = cache.data[2] || 0;
      
      return;
    }
  }
  
  xhr = new XMLHttpRequest();
  
  xhr.open('GET', 'https://' + J.reportsSubDomain + '.4chan.org/H429f6uIsUqU.php', true);
  
  xhr.withCredentials = true;
  
  xhr.onload = function() {
    var cache, resp, data, msg_count;
    
    if (this.status == 200) {
      try {
        resp = JSON.parse(this.responseText);
      }
      catch (e) {
        console.log(e);
        return;
      }
      
      if (resp.status !== 'success') {
        console.log(resp.message); // FIXME, use global message
        return;
      }
      
      data = resp.data;
      
      msg_count = data.msg || 0;
      
      $.id('at-msg-cnt').style.display = msg_count ? 'block' : '';
      $.id('at-msg').textContent = msg_count;
      $.id('at-total').textContent = data.total;
      $.id('at-illegal').textContent = data.illegal;
      
      cache = {
        ts: Date.now(),
        data: [ data.total, data.illegal, msg_count ]
      };
      
      cache = JSON.stringify(cache);
      
      localStorage.setItem('4chan-cache-rc', cache);
      
      document.dispatchEvent(new CustomEvent('4chanATUpdated'));
    }
    else {
      this.onerror();
    }
  };
  
  xhr.onerror = function() {
    console.log('Error while refreshing the report count (Status: ' + this.status + ').');
  };
  
  xhr.onloadend = function() {
    $.id('atRefresh').src = Main.icons.refresh;
  };

  $.id('atRefresh').src = Main.icons.rotate;
  
  xhr.send(null);
};

AdminTools.resetMsgCount = function() {
  var cache;
  
  $.id('at-msg').textContent = 0;
  
  if (cache = localStorage.getItem('4chan-cache-rc')) {
    cache = JSON.parse(cache);
    cache.data[2] = 0;
    cache = JSON.stringify(cache);
    localStorage.setItem('4chan-cache-rc', cache);
  }
};

var J = {
  nextChunkIndex: 0,
  nextChunk: null,
  chunkSize: 100,
  
  reportsSubDomain: 'reports'
};

J.initIconsCatalog = function() {
  var key, paths, url;
  
  Main.icons = {
    up: 'arrow_up.png',
    down: 'arrow_down.png',
    right: 'arrow_right.png',
    download: 'arrow_down2.png',
    refresh: 'refresh.png',
    cross: 'cross.png',
    gis: 'gis.png',
    iqdb: 'iqdb.png',
    minus: 'post_expand_minus.png',
    plus: 'post_expand_plus.png',
    rotate: 'post_expand_rotate.gif',
    quote: 'quote.png',
    report: 'report.png',
    notwatched: 'watch_thread_off.png',
    watched: 'watch_thread_on.png',
    help: 'question.png'
  };
  
  paths = {
    yotsuba_new: 'futaba/',
    futaba_new: 'futaba/',
    yotsuba_b_new: 'burichan/',
    burichan_new: 'burichan/',
    tomorrow: 'tomorrow/',
    photon: 'photon/'
  };
  
  url = '//s.4cdn.org/image/';
  
  if (window.devicePixelRatio >= 2) {
    for (key in Main.icons) {
      Main.icons[key] = Main.icons[key].replace('.', '@2x.');
    }
  }
  
  url += 'buttons/' + paths[Main.stylesheet];
  for (key in Main.icons) {
    Main.icons[key] = url + Main.icons[key];
  }
};

J.apiUrlFilter = function(url) {
  return url + '?' + Math.round(Date.now() / 1000 / 3);
};

J.openDeletePrompt = function(id) {
  var html, cnt;

  id = id.getAttribute('data-id');
  html = '<div class="extPanel reply"><div class="panelHeader">Delete Post No.' + id
  + '<span class="panelCtrl"><img alt="Close" title="Close" class="pointer" data-cmd="close-delete-prompt" src="'
  + Main.icons.cross + '"></a>'
  + '</span></div><span id="delete-prompt-inner">'
    + '<input type="button" value="Delete Post" tabindex="-1" data-cmd="delete-post" data-id="' + id + '"> '
    + '<input type="button" value="Delete Image Only" data-cmd="delete-image" data-id="' + id + '">';

  cnt = document.createElement('div');
  cnt.className = 'UIPanel';
  cnt.id = 'delete-prompt';

  cnt.innerHTML = html;
  cnt.addEventListener('click', J.closeDeletePrompt, false);
  document.body.appendChild(cnt);
  
  $.id('delete-prompt-inner').firstElementChild.focus();
};

J.closeDeletePrompt = function(e) {
  var prompt;

  if (!e || e.target.id == 'delete-prompt') {
    if (prompt = $.id('delete-prompt')) {
      prompt.removeEventListener('click', J.closeDeletePrompt, false);
      document.body.removeChild(prompt);
    }
  }
};

J.deletePost = function(btn, imageOnly) {
  var id, xhr, form, msg, el, url, mode, del, isOp;

  id = btn.getAttribute('data-id');

  isOp = $.id('t' + id);

  form = new FormData();
  msg = 'Delete Post No.';
  url = 'https://sys.' + $L.d(Main.board) + '/' + Main.board + '/post';
  mode = window.thread_archived ? 'arcdel' : 'usrdel';

  if(imageOnly) {
    msg = 'Delete Image No.';
    form.append('onlyimgdel', 'on');
  }

  form.append(id, 'delete');
  form.append('mode', mode);
  form.append('pwd', 'janitorise');

  (del = $.id('delete-prompt-inner')).textContent = 'Deleting...';

  xhr = new XMLHttpRequest();
  xhr.open('POST', url);
  xhr.withCredentials = true;
  xhr.onload = function() {
    btn.src = Main.icons.cross;
    if (this.status == 200) {
      if (this.responseText.indexOf('Updating') != -1) {
        if (!imageOnly) {
          if (id == Main.tid) {
            location.href = '//boards.' + $L.d(Main.board) + '/' + Main.board + '/';
            return;
          }
          else {
            if (isOp) {
              el = isOp.parentNode;
              el.removeChild(isOp.nextElementSibling);
              el.removeChild(isOp);
            }
            else {
              el = $.id('pc' + id);
              el.parentNode.removeChild(el);
            }
          }
        }
        else {
          el = $.id('f' + id);
          el.innerHTML = '<span class="fileThumb"><img alt="File deleted."'
            + ' src="//s.4cdn.org/image/filedeleted' + (isOp ? '' : '-res') + '.gif"></span>';
        }

        J.closeDeletePrompt();
      }
      else {
        del.textContent = 'Error: Post might have already been deleted, or is a sticky.';
      }
    }
    else {
      del.textContent = 'Error: Wrong status while deleting No.' + id + ' (Status: ' + this.status + ').';
    }
  };
  xhr.onerror = function() {
    del.textContent = 'Error: Error while deleting No.' + id + ' (Status: ' + this.status + ').';
  };

  xhr.send(form);
};

J.openBanReqWindow = function(btn)
{
  var id;

  id = btn.getAttribute('data-id');
  window.open('https://sys.' + $L.d(Main.board) + '/' + Main.board + '/admin?mode=admin&admin=banreq&id=' + id, '_blank', 'scrollBars=yes,resizable=no,toolbar=no,menubar=no,location=no,directories=no,width=400,height=245');
};

J.openBanReqFrame = function(btn) {
  var id;
  
  if (this.banReqCnt) {
    this.close();
  }
  
  id = btn.getAttribute('data-id');
  
  this.banReqCnt = document.createElement('div');
  this.banReqCnt.id = 'banReq';
  this.banReqCnt.className = 'extPanel reply';
  this.banReqCnt.setAttribute('data-trackpos', 'banReq-position');
  
  if (Config['banReq-position']) {
    this.banReqCnt.style.cssText = Config['banReq-position'];
  }
  else {
    this.banReqCnt.style.right = '0px';
    this.banReqCnt.style.top = '50px';
  }
  
  this.banReqCnt.innerHTML =
    '<div id="banReqHeader" class="drag postblock">Ban Request No.' + id
    + '<img alt="X" src="' + Main.icons.cross + '" id="banReqClose" '
    + 'class="extButton" title="Close Window"></div>'
    + '<iframe src="https://sys.' + $L.d(Main.board) + '/'
    + Main.board + '/admin?mode=admin&admin=banreq&id=' + id
    + '&noheader=true" width="400" height="230" frameborder="0"></iframe>';
  
  document.body.appendChild(this.banReqCnt);
  
  window.addEventListener('message', J.onMessage, false);
  document.addEventListener('keydown', J.onKeyDown, false);
  
  $.id('banReqClose').addEventListener('click', J.closeBanReqFrame, false);
  Draggable.set($.id('banReqHeader'));
};

J.closeBanReqFrame = function() {
  window.removeEventListener('message', J.onMessage, false);
  document.removeEventListener('keydown', J.onKeyDown, false);
  Draggable.unset($.id('banReqHeader'));
  $.id('banReqClose').removeEventListener('click', J.closeBanReqFrame, false);
  document.body.removeChild(J.banReqCnt);
  J.banReqCnt = null;
};

J.processMessage = function(data) {
  if (!data) {
    return {};
  }
  
  data = data.split('-');
  
  return {
    cmd: data[0],
    type: data[1],
    id: data.slice(2).join('-')
  };
};

J.onKeyDown = function(e) {
  if (e.keyCode == 27 && !e.ctrlKey && !e.altKey && !e.shiftKey && !e.metaKey) {
    J.closeBanReqFrame();
  }
};

J.onMessage = function(e) {
  var msg;
  
  if (e.origin !== 'https://sys.' + $L.d(Main.board)) {
    return;
  }
  
  msg = J.processMessage(e.data);
  
  if (msg.type !== 'ban') {
    return;
  }
  
  if (msg.cmd === 'done' || msg.cmd === 'cancel') {
    J.closeBanReqFrame();
  }
};

/**
 * Click handler
 */
J.onClick = function(e) {
  var t, cmd;

  if ((t = e.target) == document) {
    return;
  }

  if (cmd = t.getAttribute('data-cmd')) {
    switch (cmd) {
      case 'at-refresh':
        AdminTools.refreshReportCount(true);
        break;
      case 'delete-post':
      case 'delete-image':
        J.deletePost(t, (cmd === 'delete-image'));
        break;
      case 'open-delete-prompt':
        J.openDeletePrompt(t);
        break;
      case 'close-delete-prompt':
        J.closeDeletePrompt();
        break;
      case 'open-banreq-prompt':
        if (Config.inlinePopups) {
          J.openBanReqFrame(t);
        }
        else {
          J.openBanReqWindow(t);
        }
        break;
      case 'at-msg':
        AdminTools.resetMsgCount();
        break;
      case 'toggle-file-spoiler':
        J.setFileSpoiler(t);
        break;
      
      case 'prompt-spoiler':
        if (confirm('Toggle spoiler?')) {
          J.setFileSpoiler(t);
        }
        break;
      
      case 'boardlist-open':
        J.openBoardList();
        break;
      case 'boardlist-close':
        J.closeBoardList();
        break;
      case 'boardlist-save':
        J.saveBoardList();
        J.closeBoardList();
        break;
    }
  }
};

J.onScroll = function() {
  var end;
  
  while (J.nextChunk.offsetTop < (document.documentElement.clientHeight + window.scrollY)) {
    end = J.nextChunkIndex + J.chunkSize;
    if (end >= J.postCount) {
      J.parseRange(J.nextChunkIndex, J.postCount);
      window.removeEventListener('scroll', J.onScroll, false);
      return false;
    }
    else {
      J.parseRange(J.nextChunkIndex, end);
    }
  }

  return true;
};

J.parseRange = function(start, end) {
  var i, j, posts;

  posts = document.getElementById('t' + Main.tid).getElementsByClassName('postInfo');
  
  for (i = start; i < end; ++i) {
    j = posts[i];
    
    if (!j) {
      break;
    }
    
    J.parsePost(j);
  }
  
  J.nextChunkIndex = i;
  J.nextChunk = posts[i];
};

J.onParsingDone = function(e) {
  var i, tid, offset, limit, posts;
  
  if (Config.useIconButtons) {
    if (e) {
      tid = e.detail.threadId;
      offset = e.detail.offset;
      limit = e.detail.limit;
      posts = document.getElementById('t' + tid).getElementsByClassName('postInfo');
    }
    else {
      offset = 0;
      posts = document.getElementsByClassName('postInfo');
      limit = posts.length;
    }
    
    for (i = offset; i < limit; ++i) {
      J.parsePost(posts[i]);
    }
  }
};

J.onPostMenuReady = function(e) {
  var el, pid, menu, flag;
  
  pid = e.detail.postId;
  menu = e.detail.node;
  
  el = document.createElement('li');
  el.className = 'dd-admin';
  el.setAttribute('data-cmd', 'open-delete-prompt');
  el.setAttribute('data-id', pid);
  el.textContent = 'Delete';
  menu.appendChild(el);
  
  if (window.spoilers && (el = $.id('fT' + pid))) {
    flag = $.cls('imgspoiler', el.parentNode)[0] ? 0 : 1;
    el = document.createElement('li');
    el.className = 'dd-admin';
    el.setAttribute('data-cmd', 'toggle-file-spoiler');
    el.setAttribute('data-id', pid);
    el.setAttribute('data-flag', flag);
    el.textContent = (flag ? 'Set' : 'Unset') + ' Spoiler';
    menu.appendChild(el);
  }
  
  if (window.thread_archived) {
    return;
  }
  
  el = document.createElement('li');
  el.className = 'dd-admin';
  el.setAttribute('data-cmd', 'open-banreq-prompt');
  el.setAttribute('data-id', pid);
  el.textContent = 'Ban request';
  menu.appendChild(el);
};

J.parsePost = function(postInfo) {
  var pid, html, cnt, tail;

  pid = postInfo.id.slice(2);
  
  html = '<img class="extButton" alt="X" data-cmd="open-delete-prompt" data-id="'
    + pid + '" src="' + Main.icons.cross
    + '" title="Delete">';
  
  if (window.spoilers && (el = $.id('fT' + pid))) {
    html += '<img class="extButton" alt="S" data-cmd="prompt-spoiler" data-id="'
    + pid + '" src="' + J.icons.spoiler
    + '" title="Toggle Spoiler">';
  }
  
  if (!window.thread_archived) {
    html += '<img class="extButton" alt="B" data-cmd="open-banreq-prompt" data-id="'
      + pid + '" src="' + J.icons.ban
      + '" title="Ban Request">';
  }

  cnt = document.createElement('div');
  cnt.className = 'extControls';
  cnt.innerHTML = html;

  tail = postInfo.getElementsByClassName('postMenuBtn')[0];

  postInfo.insertBefore(cnt, tail);
};

J.displayJCount = function(jLink, jLinkBot, no, delta) {
  var msg;
  
  $.addClass(jLink, 'j-newposts');
  $.addClass(jLinkBot, 'j-newposts');
  jLink.setAttribute('data-no', no);
  jLinkBot.setAttribute('data-no', no);
  jLink.textContent = jLinkBot.textContent = 'j +' + delta;
  
  msg = delta + ' new post' + (delta > 1 ? 's' : '');
  
  Main.addTooltip(jLink, msg, 'j-tooltip');
  Main.addTooltip(jLinkBot, msg, 'j-tooltip-bot');
};

J.refreshJCount = function() {
  var stored, jLink, jLinkBot, xhr;
  
  jLink = $.id('j-link');
  jLinkBot = $.id('j-link-bot');
  
  if (!jLink || !jLinkBot) {
    return;
  }
  
  jLink = jLink.firstElementChild;
  jLinkBot = jLinkBot.firstElementChild;
  
  if (stored = localStorage.getItem('4chan-j-count')) {
    stored = JSON.parse(stored);
  }
  
  if (!stored || (Date.now() - stored.time) >= 10000) {
    xhr = new XMLHttpRequest();
    xhr.open('GET', 'https://sys.4chan.org/j/1mcQTXbjW5WO.php?&' + Date.now());
    xhr.withCredentials = true;
    xhr.onloadend = function() {
      var data, obj, delta;
      if (this.status == 200 || this.status == 304) {
        data = JSON.parse(this.responseText);
        if (!stored || Main.board == 'j') {
          obj = { time: Date.now(), no: data.no };
        }
        else if (data.no > stored.no) {
          delta = data.no - stored.no;
          J.displayJCount(jLink, jLinkBot, data.no, delta);
          obj = { time: Date.now(), no: stored.no, delta: delta };
        }
        if (obj) {
          localStorage.setItem('4chan-j-count', JSON.stringify(obj));
        }
      }
      else {
        console.log('Error: Could not load /j/ post count (Status: ' + this.status + ').');
      }
    };
    xhr.send(null);
  }
  else if (stored.delta) {
    J.displayJCount(jLink, jLinkBot, stored.no, stored.delta);
  }
};

J.clearJCount = function() {
  var obj, no, tt, ttbot;
  
  tt = $.id('j-tooltip');
  ttbot = $.id('j-tooltip-bot');
  
  if (!tt) {
    return;
  }
  
  no = this.getAttribute('data-no');
  obj = { time: Date.now(), no: no };
  localStorage.setItem('4chan-j-count', JSON.stringify(obj));
  
  tt.parentNode.removeChild(tt);
  ttbot.parentNode.removeChild(ttbot);
  
  setTimeout(function() {
    var nodes = $.cls('j-newposts');
    if (nodes[0]) {
      nodes[0].textContent = 'j';
      $.removeClass(nodes[0], 'j-newposts');
      nodes[0].textContent = 'j';
      $.removeClass(nodes[0], 'j-newposts');
    }
  }, 10);
};

J.icons = {
  ban: 'ban.png',
  spoiler: 's.png'
};

J.initIcons = function() {
  var key, paths, url;

  paths = {
    yotsuba_new: 'futaba/',
    futaba_new: 'futaba/',
    yotsuba_b_new: 'burichan/',
    burichan_new: 'burichan/',
    tomorrow: 'tomorrow/',
    photon: 'photon/'
  };

  url = '//s.4cdn.org/image/buttons/' + paths[Main.stylesheet];

  if (window.devicePixelRatio >= 2) {
    for (key in J.icons) {
      J.icons[key] = J.icons[key].replace('.', '@2x.');
    }
  }

  for (key in J.icons) {
    J.icons[key] = url + J.icons[key];
  }
};

J.initNavLinks = function() {
  var el, nav, navbot;

  nav = $.id('navtopright');
  navbot = $.id('navbotright');

  // [j] link
  el = document.createElement('span');
  el.id = 'j-link';
  el.innerHTML = '[<a href="https://sys.4chan.org/j/" title="Janitor &amp; Moderator Discussion">j</a>]';
  el.firstElementChild.addEventListener('mouseup', J.clearJCount, false);
  nav.parentNode.insertBefore(el, nav);

  // [j] bottom link
  el = el.cloneNode(true);
  el.id = 'j-link-bot';
  el.firstElementChild.addEventListener('mouseup', J.clearJCount, false);
  navbot.parentNode.insertBefore(el, navbot);
  
  J.refreshJCount();
};

J.openBoardList = function() {
  var cnt;

  if ($.id('boardList')) {
    return;
  }

  cnt = document.createElement('div');
  cnt.id = 'boardList';
  cnt.className = 'UIPanel';
  cnt.setAttribute('data-cmd', 'boardlist-close');
  cnt.innerHTML = '\
<div class="extPanel reply"><div class="panelHeader">Boards\
<span class="panelCtrl"><img alt="Close" title="Close" class="pointer" data-cmd="boardlist-close" src="'
+ Main.icons.cross + '"></a></span></div>\
<input placeholder="Example: jp tg mu or all" id="boardListBox" type="text" value="'
+ (localStorage.getItem('4chan-boardlist') || '') + '">\
<div class="center"><button id="boardListSave" data-cmd="boardlist-save">Save</button></div>\
</td></tr></tfoot></table></div>';

  document.body.appendChild(cnt);
  cnt.addEventListener('click', this.onClick, false);
};

J.saveBoardList = function() {
  var input;

  if (input = $.id('boardListBox')) {
    localStorage.setItem('4chan-boardlist', input.value);
  }
};

J.closeBoardList = function() {
  var cnt;

  if (cnt = $.id('boardList')) {
    cnt.removeEventListener('click', this.onClick, false);
    document.body.removeChild(cnt);
  }
};

J.setFileSpoiler = function(t) {
  var xhr, pid, flag, el;
  
  pid = t.getAttribute('data-id');
  flag = t.getAttribute('data-flag');
  
  if (!pid) {
    return;
  }
  
  el = $.id('f' + pid);
  
  if (!flag) {
    flag = $.cls('imgspoiler', el.parentNode)[0] ? 0 : 1;
  }
  
  if (!el || el.hasAttribute('data-processing')) {
    return;
  }
  
  xhr = new XMLHttpRequest();
  xhr.open('GET', 'https://sys.' + $L.d(Main.board) + '/' + Main.board
    + '/admin.php?admin=spoiler&pid=' + pid + '&flag=' + flag, true);
  xhr.withCredentials = true;
  xhr.onload = J.onFileSpoilerLoad;
  xhr.onerror = J.onFileSpoilerError;
  xhr._pid = +pid;
  xhr._flag = +flag;
  
  Feedback.notify('Processing...', null);
  
  el.setAttribute('data-processing', '1');
  
  xhr.send(null);
};

J.onFileSpoilerLoad = function() {
  var el, el2;
  
  Feedback.hideMessage();
  
  if (this.responseText !== '1') {
    if (this.responseText === '-1') {
      Feedback.error('You are not logged in');
    }
    else {
      Feedback.error("Couldn't set spoiler flag for post No." + this._pid);
    }
    
    return;
  }
  
  if (!(el = $.id('f' + this._pid))) {
    return;
  }
  
  el.removeAttribute('data-processing');
  
  if (!(el = $.cls('fileThumb', el)[0])) {
    return;
  }
  
  if (this._flag) {
    $.addClass(el, 'imgspoiler');
    
    el2 = el.previousElementSibling;
    el2.setAttribute('title', el2.firstElementChild.textContent);
    
    if (!Config.revealSpoilers) {
      el = $.tag('img', el)[0];
      el.style.width = el.style.height = '100px';
      el.src = '//s.4cdn.org/image/spoiler-' + Main.board + '.png';
    }
  }
  else {
    if (!Config.revealSpoilers) {
      Parser.revealImageSpoiler(el);
    }
    $.removeClass(el, 'imgspoiler');
  }
};

J.onFileSpoilerError = function() {
  var el;
  
  if (!(el = $.id('f' + this._pid))) {
    return;
  }
  el.removeAttribute('data-processing');
  Feedback.error("Couldn't update the spoiler flag for post No." + this.pid);
};

J.initCatalog = function() {
  var storage;
  
  window.Main = {
    board: location.pathname.split(/\//)[1]
  };
  
  window.Main.addTooltip = function(link, message, id) {
    var el, pos;
    
    el = document.createElement('div');
    el.className = 'click-me';
    if (id) {
      el.id = id;
    }
    el.innerHTML = message || 'Change your settings';
    link.parentNode.appendChild(el);
    
    pos = (link.offsetWidth - el.offsetWidth + link.offsetLeft - el.offsetLeft) / 2;
    el.style.marginLeft = pos + 'px';
    
    return el;
  };
  
  if (J.stylesheet = J.getCookie(window.style_group)) {
    J.stylesheet = J.stylesheet.toLowerCase().replace(/ /g, '_');
  }
  else {
    J.stylesheet =
      style_group == 'nws_style' ? 'yotsuba_new' : 'yotsuba_b_new';
  }
  
  Main.stylesheet = J.stylesheet;
  
  J.initIconsCatalog();
  
  J.addCss(); // fixme
  
  document.addEventListener('click', J.onClick, false);
  
  J.runCatalog();
};

J.runCatalog = function () {
  var threads;
  //J.addCss(); // fixme
  //document.removeEventListener('4chanMainInit', J.runCatalog, false);
  
  J.initNavLinks();
  
  if (!FC.hasMobileLayout) {
    AdminTools.init();
  }
  
  threads = $.id('threads');
  
  $.on(threads, 'mouseover', J.onThreadMouseOver);
  //$.on(threads, 'mouseout', J.onThreadMouseOut);
};

J.init = function() {
  var ts;
  
  Config.boardList = true;

  SettingsMenu.options['Janitor'] = {
    boardList: [ 'Janitor Boards [<a href="javascript:;" data-cmd="boardlist-open">Select</a>]', 'Select boards to enable janitor buttons on', true ],
    useIconButtons: [ 'Use icon buttons', 'Display old-style buttons instead of using drop-down' ],
    changeUpdateDelay: [ 'Reduce auto-update delay', 'Reduce the thread updater delay', true ],
    fixedAdminToolbox: [ 'Pin Janitor Tools to the page', 'Janitor Tools will scroll with you' ],
    inlinePopups: [ 'Inline ban request panel', 'Open ban request panel in browser window, instead of a popup' ],
    disableMngExt: [ 'Disable janitor extension', 'Completely disable the janitor extension (overrides any checked boxes)', true ]
  };

  if (Config.disableMngExt) {
    return;
  }
  
  J.addCss();
  
  if (Config.useIconButtons) {
    J.initIcons();
  }
  
  QR.noCooldown = QR.noCaptcha = true;

  document.addEventListener('click', J.onClick, false);
  document.addEventListener('DOMContentLoaded', J.run, false);
};

J.run = function() {
  var boards, posts, nav, el;

  document.removeEventListener('DOMContentLoaded', J.run, false);

  J.initNavLinks();
  
  if (!Main.hasMobileLayout) {
    AdminTools.init();
  }
  
  if (Config.revealSpoilers) {
    $.addClass(document.body, 'reveal-img-spoilers');
  }
  
  if (Config.threadUpdater && Main.tid) {
    if (Config.changeUpdateDelay) {
      ThreadUpdater.delayIdHidden = 3;
      ThreadUpdater.delayRange = [ 5, 10, 15, 20, 30, 60 ];
      ThreadUpdater.apiUrlFilter = J.apiUrlFilter;
    }
  }
  
  boards = localStorage.getItem('4chan-boardlist') || '';
  
  if (Main.board != 'j' && (boards == 'all' || boards.split(/[, ]+/).indexOf(Main.board) != -1)) {
    if (Config.useIconButtons && !Main.hasMobileLayout) {
      if (Main.tid) {
        posts = document.getElementById('t' + Main.tid).getElementsByClassName('postInfo');
        J.postCount = posts.length;
        if (J.postCount > J.chunkSize) {
          J.nextChunk = posts[0];
          window.addEventListener('scroll', J.onScroll, false);
          J.onScroll();
        }
        else {
          J.onParsingDone();
        }
      }
      else {
        J.onParsingDone();
      }
      
      document.addEventListener('4chanParsingDone', J.onParsingDone, false);
    }
    
    document.addEventListener('4chanPostMenuReady', J.onPostMenuReady, false);
  }
  
  if (nav = $.id('boardSelectMobile')) {
    el = document.createElement('option');
    el.value = 'j';
    el.textContent = '/j/ - Janitors & Moderators';
    nav.insertBefore(el, nav.firstElementChild);
  }
};

J.getCookie = function(name) {
  var i, c, ca, key;
  
  key = name + "=";
  ca = document.cookie.split(';');
  
  for (i = 0; c = ca[i]; ++i) {
    while (c.charAt(0) == ' ') {
      c = c.substring(1, c.length);
    }
    if (c.indexOf(key) === 0) {
      return decodeURIComponent(c.substring(key.length, c.length));
    }
  }
  return null;
};

J.addCss = function() {
  var style, css;
  
  css = '\
#adminToolbox {\
  max-width: 256px;\
  display: block;\
  position: absolute;\
  padding: 3px;\
}\
#adminToolbox h4 {\
  font-size: 12px;\
  margin: 2px 0 0;\
  padding: 0;\
  font-weight: normal;\
}\
#adminToolbox li {\
  list-style: none;\
}\
#adminToolbox ul {\
  padding: 0;\
  margin: 2px 0 0 10px;\
}\
#atHeader {\
  height: 17px;\
  font-weight: bold;\
  padding-bottom: 2px;\
}\
#atRefresh {\
  margin: -1px 0 0 3px;\
}\
.post-ip {\
  margin-left: 5px;\
}\
#delete-prompt > div {\
  text-align: center;\
}\
#watchList li:first-child {\
  margin-top: 3px;\
  padding-top: 2px;\
  border-top: 1px solid rgba(0, 0, 0, 0.20);\
}\
.photon #atHeader {\
  border-bottom: 1px solid #ccc;\
}\
.yotsuba_new #atHeader {\
  border-bottom: 1px solid #d9bfb7;\
}\
.yotsuba_b_new #atHeader {\
  border-bottom: 1px solid #b7c5d9;\
}\
.tomorrow #atHeader {\
  border-bottom: 1px solid #111;\
}\
#at-illegal {\
  color: red;\
}\
#at-msg-cnt {\
  display: none;\
}\
.j-newposts {\
  font-weight: bold !important;\
}\
#j-link,\
#j-link-bot {\
  margin-right: 3px;\
  display: inline-block;\
  margin-left: 3px;\
}\
#boardList input {\
  width: 385px;\
  margin: auto;\
  display: block;\
}\
#boardListSave {\
  margin-top: 5px;\
}\
#banReqClose {\
  float: right;\
}\
#banReq iframe {\
  overflow: hidden;\
}\
#banReq {\
  display: block;\
  position: fixed;\
  padding: 2px;\
  font-size: 10pt;\
  height: 250px;\
}\
#banReqHeader {\
  text-align: center;\
  margin-bottom: 1px;\
  padding: 0;\
  height: 18px;\
  line-height: 18px;\
}\
#captchaFormPart {\
  display: none;\
}\
.mobileExtControls {\
  float: right;\
  font-size: 11px;\
  margin-bottom: 3px;\
}\
.ws .mobileExtControls {\
  color: #34345C;\
}\
.nws .mobileExtControls {\
  color: #0000EE;\
}\
.reply .mobileExtControls {\
  margin-right: 5px;\
}\
.mobileExtControls span {\
  margin-left: 10px;\
}\
.mobileExtControls span:after {\
  content: "]";\
}\
.mobileExtControls span:before {\
  content: "[";\
}\
.nws .mobileExtControls span:after {\
  color: #800000;\
}\
.nws .mobileExtControls span:before {\
  color: #800000;\
}\
.ws .mobileExtControls span:after {\
  color: #000;\
}\
.ws .mobileExtControls span:before {\
  color: #000;\
}\
.m-dark .mobileExtControls,\
.m-dark .mobileExtControls span:after,\
.m-dark .mobileExtControls span:before {\
  color: #707070 !important;\
}\
.dd-admin {\
  text-indent: 5px;\
}\
.dd-admin:before {\
  color: #FF0000;\
  content: "•";\
  left: -3px;\
  position: absolute;\
}\
.extPanel {\
  border: 1px solid rgba(0, 0, 0, 0.2);\
}\
.extPanel img.pointer { width: 18px; height: 18px }\
.drag {\
  -moz-user-select: none !important;\
  cursor: move !important;\
}\
.reveal-img-spoilers .imgspoiler::before {\
  content: " ";\
  width:0.75em;\
  height:0.75em;\
  border-radius: 0.5em;\
  position: absolute;\
  display: block;\
  background: red;\
  margin-top: 1px;\
  margin-left: 1px;\
  pointer-events: none;\
}\
.reveal-img-spoilers.is_catalog .imgspoiler::before { margin-top: 4px; margin-left: 12px;}\
.reveal-img-spoilers .imgspoiler:hover::before { background: #fff; }';

  style = document.createElement('style');
  style.setAttribute('type', 'text/css');
  style.textContent = css;
  document.head.appendChild(style);
};

if (/https?:\/\/boards\.(?:4chan|4channel)\.org\/[a-z0-9]+\/catalog($|#.*$)/.test(location.href)) {
  J.initCatalog();
}
else {
  J.init();
  //J.run();
}

})();
