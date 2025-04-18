/**
 * Mod Extension
 */

(function () {
var J = {
  isCatalog: false,
  colours: {},
  posterids: {},
  nextChunkIndex: 0,
  nextChunk: null,
  chunkSize: 100,
  sameIDActive: false,
  
  parserEventBound: false,
  
  autoReloadCatInterval: null,
  autoReloadCatDelay: 30000,
  
  samePostersMap: {},
  
  xhrs: {},
  
  reportsSubDomain: 'reports',
  teamSubDomain: 'team',
  
  flags: []
};

J.bin2hex = function(data) {
  var i, l, hex, c;
  
  hex = '';
  l = data.length;
  
  for (i = 0; i < l; ++i) {
    c = data.charCodeAt(i);
    hex += (c >> 4).toString(16);
    hex += (c & 0xF).toString(16);
  }
  
  return hex;
};

J.getFileMD5FromPid = function(pid) {
  var el, data;
  
  el = $.id('f' + pid);
  
  if (!el) {
    return false;
  }
  
  el = $.qs('img[data-md5]', el);
  
  if (!el) {
    return false;
  }
  
  data = window.atob(el.getAttribute('data-md5'));
  
  return J.bin2hex(data);
};

J.onGetMD5Click = function(el) {
  var md5, pid = el.getAttribute('data-id');
  
  md5 = J.getFileMD5FromPid(pid);
  
  if (md5 === false) {
    alert('Post or file not found');
  }
  else {
    prompt('', md5);
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
  
  if ($.id((J.isCatalog ? 'thread-' : 't') + id) && !window.thread_archived) {
    html += ' <input type="button" value="Archive Thread" data-cmd="force-archive" data-id="' + id + '">';
  }
  
  if (!window.thread_archived && !J.isCatalog) {
    html += '<br>[<input type="checkbox" id="delete-all-by-ip"><label for="delete-all-by-ip">Delete all by IP?</label>]';
  }
  
  html += '</span></div>';

  cnt = document.createElement('div');
  cnt.className = 'UIPanel';
  cnt.id = 'delete-prompt';

  cnt.innerHTML = html;
  
  document.addEventListener('keydown', J.onKeyDown, false);
  cnt.addEventListener('click', J.closeDeletePrompt, false);
  document.body.appendChild(cnt);
  
  $.id('delete-prompt-inner').firstElementChild.focus();
};

J.addPosterIds = function(pid, hash, isMobile) {
  var post, cnt, el, name, hand, p;
  
  post = !isMobile ? $.id('pi' + pid) : $.id('pim' + pid);
  
  if (!window.user_ids || !(el = $.cls('posteruid', post)[0])) {
    el = $.el('span');
    
    cnt = $.cls('nameBlock', post)[0];
    name = $.cls('name', cnt)[0];
    
    if (name.classList.contains('capcode')) {
      return;
    }
    
    cnt.insertBefore(el, name.nextSibling);
    
    if (!isMobile) {
      cnt.insertBefore(document.createTextNode(' '), name.nextSibling);
    }
  }
  
  el.innerHTML = '(ID: <span class="hand" title="Highlight posts by this ID">' + hash + '</span>)';
  el.className = 'posteruid id_' + hash;
  
  hand = el.firstElementChild;
  
  IDColor.apply(hand);
  
  el.addEventListener('click', window.idClick, false);
  
  if (window.currentHighlighted && el.className.indexOf('id_' + window.currentHighlighted) != -1) {
    p = el.parentNode.parentNode.parentNode;
    p.className = 'highlight ' + p.className;
  }
}

J.onSamePostersLoaded = function() {
  var posts, hash, pid, tmp, isMobile;
  
  if (this.status != 200 && this.status != 304) {
    return;
  }
  
  posts = JSON.parse(this.responseText);
  
  if (!posts) {
    return;
  }
  
  isMobile = Main.hasMobileLayout;
  
  if (!IDColor.enabled) {
    tmp = window.user_ids;
    window.user_ids = true;
    IDColor.init();
    window.user_ids = tmp;
  }
  
  if (!J.sameIDActive) {
    J.sameIDActive = true;
  }
  
  for (pid in posts) {
    if (J.samePostersMap[pid]) {
      continue;
    }
    
    hash = posts[pid];
    
    J.samePostersMap[pid] = true;
    
    J.addPosterIds(pid, hash, isMobile);
  }
}

J.loadSamePosters = function(from) {
  var url, theNode, xhr;
  
  if (!J.parserEventBound) {
    document.addEventListener('4chanParsingDone', J.onParsingDone, false);
  }
  
  url = 'https://sys.' + $L.d(Main.board) + '/' + Main.board + '/admin?admin=adminext&thread=' + Main.tid;
  
  if (from) {
    url += '&from=' + from;
  }

  xhr = new XMLHttpRequest();
  xhr.open('GET', url);
  xhr.withCredentials = true;
  xhr.onload = J.onSamePostersLoaded;

  xhr.send(null);
};

J.closeDeletePrompt = function (e) {
  var prompt;

  if (!e || e.target.id == 'delete-prompt') {
    if (prompt = $.id('delete-prompt')) {
      document.removeEventListener('keydown', J.onKeyDown, false);
      prompt.removeEventListener('click', J.closeDeletePrompt, false);
      document.body.removeChild(prompt);
    }
  }
};

J.checkDeletedPosts = function () {
  var url, xhr;

  if (!Main.tid) {
    return;
  }

  url = '//a.4cdn.org/' + Main.board + '/res/' + Main.tid + '.json';

  xhr = new XMLHttpRequest();
  xhr.open('GET', url);
  xhr.onload = function () {
    if (this.status == 200 || this.status == 304) {
      ThreadUpdater.markDeletedReplies(Parser.parseThreadJSON(this.responseText));
    }
  };

  xhr.send(null);
};

J.get_random_light_color = function () {
  var letters = 'ABCDE'.split('');
  var color = '#';
  for (var i = 0; i < 3; i++) {
    color += letters[Math.floor(Math.random() * letters.length)];
  }
  return color;
};

J.deletePost = function (btn, imageOnly) {
  var id, xhr, form, msg, el, url, mode, delall, del, isOp, resp;

  id = btn.getAttribute('data-id');

  isOp = !J.isCatalog && $.id('t' + id);

  form = new FormData();
  msg = 'Delete Post No.';
  url = 'https://sys.' + $L.d(Main.board) + '/' + Main.board;
  
  if (window.thread_archived) {
    mode = 'arcdel';
  }
  else {
    mode = 'usrdel';
    delall = !J.isCatalog && $.id('delete-all-by-ip').checked;
  }

  if (delall) {
    mode = 'admin.php';
    form.append('admin', 'delall');
    form.append('id', id);
  }

  if (delall) {
    url += '/admin';
  }
  else {
    url += '/post';
  }

  if (imageOnly) {
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
  xhr.onload = function () {
    var builtMsg;
    btn.src = Main.icons.cross;
    if (this.status == 200) {
      if ((!delall && this.responseText.indexOf('Updating') != -1) || (delall && this.responseText.indexOf('deleted') != -1)) {
        if (J.isCatalog) {
          if (el = $.id('thread-' + id)) {
            $.addClass(el, 'disabled');
          }
        }
        else if (!imageOnly) {
          if (id == Main.tid) {
            location.href = '//boards.' + $L.d(Main.board) + '/' + Main.board + '/';
            return;
          }
          else {
            if (delall) {
              builtMsg = document.createElement('span');
              builtMsg.innerHTML = '<br><br><strong style="font-color: red;">(YOU HAVE DELETED ALL POSTS BY THIS IP)</strong>';
              el = $.id('m' + id);
              el.appendChild(builtMsg);
              J.checkDeletedPosts();
            }
            else {
              if (isOp) {
                el = isOp.parentNode;
                el.removeChild(isOp.nextSibling);
                el.removeChild(isOp);
              }
              else {
                el = $.id('pc' + id);
                el.parentNode.removeChild(el);
              }
            }
          }
        }
        else {
          el = $.id('f' + id);
          el.innerHTML = '<span class="fileThumb"><img alt="File deleted."'
            + ' src="//s.4cdn.org/image/filedeleted' + (isOp ? '' : '-res') + '.gif"></span>';

          if (delall) {
            builtMsg = document.createElement('span');
            builtMsg.innerHTML = '<br><br><strong style="font-color: red;">(YOU HAVE DELETED ALL IMAGES BY THIS IP)</strong>';
            el = $.id('m' + id);
            el.appendChild(builtMsg);
          }
        }

        J.closeDeletePrompt();
      }
      else {
        if (resp = this.responseText.match(/"errmsg"[^>]*>(.*?)<\/span/)) {
          del.textContent = resp[1];
        }
        else {
          del.textContent = 'Error: Something went wrong.';
        }
      }
    }
    else {
      del.textContent = 'Error: Wrong status while deleting No.' + id + ' (Status: ' + this.status + ').';
    }
  };
  xhr.onerror = function () {
    del.textContent = 'Error: Error while deleting No.' + id + ' (Status: ' + this.status + ').';
  };

  xhr.send(form);
};

J.forceArchive = function(btn) {
  var id, xhr, form, msg, url, del, resp;

  id = btn.getAttribute('data-id');

  form = new FormData();
  msg = 'Archive Thread No.';
  
  url = 'https://sys.' + $L.d(Main.board) + '/' + Main.board + '/post';
  
  form.append('id', id);
  form.append('mode', 'forcearchive');

  (del = $.id('delete-prompt-inner')).textContent = 'Archiving...';

  xhr = new XMLHttpRequest();
  xhr.open('POST', url);
  xhr.withCredentials = true;
  xhr.onload = function () {
    var el;
    if (btn.src) {
      btn.src = Main.icons.cross;
    }
    if (this.status == 200) {
      if (this.responseText.indexOf('Updating') != -1) {
        if (J.isCatalog) {
          if (el = $.id('thread-' + id)) {
            $.addClass(el, 'disabled');
          }
        }
        J.closeDeletePrompt();
      }
      else {
        if (resp = this.responseText.match(/"errmsg"[^>]*>(.*?)<\/span/)) {
          del.textContent = resp[1];
        }
        else {
          del.textContent = 'Error: Something went wrong.';
        }
      }
    }
    else {
      del.textContent = 'Error: Wrong status while archiving No.' + id + ' (Status: ' + this.status + ').';
    }
  };
  xhr.onerror = function () {
    del.textContent = 'Error: Error while archiving No.' + id + ' (Status: ' + this.status + ').';
  };

  xhr.send(form);
};

J.openBanWindow = function (btn) {
  var id;

  id = btn.getAttribute('data-id');
  window.open('https://sys.' + $L.d(Main.board) + '/' + Main.board + '/admin?mode=admin&admin=ban&id=' + id, '_blank', 'scrollBars=yes,resizable=no,toolbar=no,menubar=no,location=no,directories=no,width=400,height=470');
};

J.openBanFrame = function(btn) {
  var id;
  
  if (this.banReqCnt) {
    this.closeBanFrame();
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
    '<div id="banReqHeader" class="drag postblock">Ban No.' + id
    + '<img alt="X" src="' + Main.icons.cross + '" id="banReqClose" '
    + 'class="extButton" title="Close Window"></div>'
    + '<iframe src="https://sys.' + $L.d(Main.board) + '/'
    + Main.board + '/admin?mode=admin&admin=ban&id=' + id
    + '&noheader=true" width="400" height="470" frameborder="0"></iframe>';
  
  document.body.appendChild(this.banReqCnt);
  
  window.addEventListener('message', J.onMessage, false);
  document.addEventListener('keydown', J.onKeyDown, false);
  
  $.id('banReqClose').addEventListener('click', J.closeBanFrame, false);
  Draggable.set($.id('banReqHeader'));
};

J.closeBanFrame = function() {
  window.removeEventListener('message', J.onMessage, false);
  document.removeEventListener('keydown', J.onKeyDown, false);
  Draggable.unset($.id('banReqHeader'));
  $.id('banReqClose').removeEventListener('click', J.closeBanFrame, false);
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
    if (J.banReqCnt) {
      J.closeBanFrame();
    }
    if (J.threadOptsCnt) {
      J.closeThreadOptionsFrame();
    }
    if ($.id('delete-prompt')) {
      J.closeDeletePrompt();
    }
  }
};

J.onCatalogKeyDown = function(e) {
  if (e.keyCode == 82 && e.shiftKey) {
    J.initCatAutoReload();
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
    J.closeBanFrame();
  }
};

J.initCatAutoReload = function(init) {
  var flag;
  
  flag = sessionStorage.getItem('4chan-c-ar');
  
  if (flag) {
    if (init) {
      window.scrollTo(0, +flag);
      J.toggleCatAutoReload(true);
    }
    else {
      J.toggleCatAutoReload(false);
    }
  }
  else {
    if (init) {
      return;
    }
    J.toggleCatAutoReload(true);
  }
};

J.toggleCatAutoReload = function(flag) {
  if (flag) {
    sessionStorage.setItem('4chan-c-ar', document.documentElement.scrollTop);
    J.autoReloadCatInterval = setInterval(J.autoRefreshWindow, J.autoReloadCatDelay);
    $.addClass($.id('refresh-btn'), 'active-btn');
  }
  else {
    sessionStorage.removeItem('4chan-c-ar');
    clearInterval(J.autoReloadCatInterval);
    $.removeClass($.id('refresh-btn'), 'active-btn');
  }
};

J.autoRefreshWindow = function() {
  var el = $.id('ctrl');
  
  if (document.documentElement.scrollTop <= el.offsetTop + el.offsetHeight) {
    sessionStorage.setItem('4chan-c-ar', document.documentElement.scrollTop);
    location.href = location.href;
  }
}

J.openThreadOptions = function(btn) {
  var id = btn.getAttribute('data-id');
  window.open('https://sys.' + $L.d(Main.board) + '/' + Main.board + '/admin?mode=admin&admin=opt&id=' + id, '_blank', 'scrollBars=yes,resizable=no,toolbar=no,menubar=no,location=no,directories=no,width=400,height=290');
};

J.openThreadOptionsFrame = function(btn) {
  var id;
  
  if (this.threadOptsCnt) {
    this.closeThreadOptionsFrame();
  }
  
  id = btn.getAttribute('data-id');
  
  this.threadOptsCnt = document.createElement('div');
  this.threadOptsCnt.id = 'threadOpts';
  this.threadOptsCnt.className = 'extPanel reply';
  this.threadOptsCnt.setAttribute('data-trackpos', 'threadOpts-position');
  
  if (Config['threadOpts-position']) {
    this.threadOptsCnt.style.cssText = Config['threadOpts-position'];
  }
  else {
    this.threadOptsCnt.style.right = '0px';
    this.threadOptsCnt.style.top = '50px';
  }
  
  this.threadOptsCnt.innerHTML =
    '<div id="threadOptsHeader" class="drag postblock">Thread Options No.' + id
    + '<img alt="X" src="' + Main.icons.cross + '" id="threadOptsClose" '
    + 'class="extButton" title="Close Window"></div>'
    + '<iframe src="https://sys.' + $L.d(Main.board) + '/'
    + Main.board + '/admin?mode=admin&admin=opt&id=' + id
    + '&noheader=true" width="400" height="175" frameborder="0"></iframe>';
  
  document.body.appendChild(this.threadOptsCnt);
  
  window.addEventListener('message', J.onThreadOptsDone, false);
  document.addEventListener('keydown', J.onKeyDown, false);
  
  $.id('threadOptsClose').addEventListener('click', J.closeThreadOptionsFrame, false);
  Draggable.set($.id('threadOptsHeader'));
};

J.closeThreadOptionsFrame = function() {
  window.removeEventListener('message', J.onThreadOptsDone, false);
  document.removeEventListener('keydown', J.onKeyDown, false);
  Draggable.unset($.id('threadOptsHeader'));
  $.id('threadOptsClose').removeEventListener('click', J.closeThreadOptionsFrame, false);
  document.body.removeChild(J.threadOptsCnt);
  J.threadOptsCnt = null;
};

J.onThreadOptsDone = function(e) {
  if (J.threadOptsCnt && e.origin === 'https://sys.' + $L.d(Main.board) && e.data === 'done-threadopt') {
    J.closeThreadOptionsFrame();
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

/**
 * Multi
 */
var Multi = {};

Multi.exec = function(btn) {
  var pid, sel;
  
  if (UA.isOpera && typeof (sel = document.getSelection()) == 'string') {}
  else {
    sel = window.getSelection().toString();
  }
  
  if (sel) {
    window.open('https://' + J.teamSubDomain
      + '.4chan.org/search#{"comment":"' + sel.replace(/[\r\n]+/g, ' ') + '"}');
  }
  else {
    pid = btn.getAttribute('data-id');
    
    window.open('https://team.4chan.org/search?action=from_pid&board=' + Main.board
    + '&pid=' + pid);
  }
};

Multi.prompt = function (ip, pid) {
  var cnt, btn, link;
  
  cnt = $.id('pi' + pid);
  btn = $.cls('postMenuBtn', cnt)[0];
  
  link = document.createElement('a');
  link.href = 'https://' + J.teamSubDomain + '.4chan.org/search#{"ip":"' + ip + '"}';
  link.setAttribute('target', '_blank');
  link.className = 'post-ip';
  link.textContent = ip;

  cnt.insertBefore(link, btn);
};

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

AdminTools.init = function () {
  var cnt, html;
  
  AdminTools.initVisibilityAPI();
  
  cnt = document.createElement('div');
  cnt.className = 'extPanel reply';
  cnt.id = 'adminToolbox';
  cnt.setAttribute('data-trackpos', 'AT-position');

  if (Config['AT-position']) {
    cnt.style.cssText = Config['AT-position'];
  } else {
    cnt.style.right = '10px';
    cnt.style.top = '380px';
  }

  cnt.style.position = Config.fixedAdminToolbox ? 'fixed' : '';

  html = '<div class="drag" id="atHeader">Moderator Tools'
    + '<img alt="Refresh" title="Refresh" src="' + Main.icons.refresh
    + '" id="atRefresh" data-cmd="at-refresh" class="pointer right"></div>'
    + '<h4><a href="https://' + J.reportsSubDomain + '.4chan.org/" target="_blank">Reports</a>: '
    + '<span title="Total" id="at-total">?</span> ('
    + '<span title="Illegal" id="at-illegal">?</span>)</h4>'
    + '<h4><a href="https://' + J.reportsSubDomain + '.4chan.org/?action=ban_requests" target="_blank">Ban Requests</a>: '
    + '<span id="at-banreqs">?</span> (<span title="Illegal" id="at-illegal-br">?</span>)</h4>'
    + '<h4><a href="https://' + J.teamSubDomain + '.4chan.org/appeals" target="_blank">Appeals</a>: '
    + '<span id="at-appeals">?</span> (<span title="4chan Pass Users" id="at-prio-appeals">?</span>)</h4>'
    + '<h4 id="at-msg-cnt"><a data-cmd="at-msg" href="https://' + J.reportsSubDomain
      + '.4chan.org/?action=staffmessages" target="_blank">Messages</a>: <span id="at-msg">?</span></h4>';
    
  if (Main.tid) {
    html += '<hr><h4><a href="javascript:void(0);" data-cmd="poster-id">Same Poster ID</a></h4>';
  }

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
  var xhr, cache, msg_count;
  
  if (force !== true && (cache = localStorage.getItem('4chan-cache-rc'))) {
    cache = JSON.parse(cache);
    
    if (cache.ts > Date.now() - AdminTools.cacheTTL) {
      $.id('at-total').textContent = cache.data[0];
      $.id('at-illegal').textContent = cache.data[1];
      $.id('at-banreqs').textContent = cache.data[2];
      $.id('at-appeals').textContent = cache.data[3];
      $.id('at-illegal-br').textContent = cache.data[4] || 0;
      $.id('at-prio-appeals').textContent = cache.data[5] || 0;
      
      $.id('at-msg-cnt').style.display = cache.data[6] ? 'block' : '';
      $.id('at-msg').textContent = cache.data[6] || 0;
      
      return;
    }
  }
  
  xhr = new XMLHttpRequest();
  
  xhr.open('GET', 'https://' + J.reportsSubDomain + '.4chan.org/H429f6uIsUqU.php', true);
  
  xhr.withCredentials = true;
  
  xhr.onload = function () {
    var cache, resp, data;
    
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
      $.id('at-banreqs').textContent = data.banreqs;
      $.id('at-illegal-br').textContent = data.illegal_banreqs;
      $.id('at-appeals').textContent = data.appeals;
      $.id('at-prio-appeals').textContent = data.prio_appeals;
      
      cache = {
        ts: Date.now(),
        data: [
          data.total,
          data.illegal,
          data.banreqs,
          data.appeals,
          data.illegal_banreqs,
          data.prio_appeals,
          data.msg
        ]
      };
      
      cache = JSON.stringify(cache);
      
      localStorage.setItem('4chan-cache-rc', cache);
      
      document.dispatchEvent(new CustomEvent('4chanATUpdated'));
    }
    else {
      this.onerror();
    }
  };
  
  xhr.onerror = function () {
    console.log('Error while refreshing the report count (Status: ' + this.status + ').');
  };
  
  xhr.onloadend = function () {
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
    cache.data[6] = 0;
    cache = JSON.stringify(cache);
    localStorage.setItem('4chan-cache-rc', cache);
  }
};

/**
 * Click handler
 */
J.onClick = function (e) {
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
      case 'force-archive':
        J.forceArchive(t);
        break;
      
      case 'open-delete-prompt':
        J.openDeletePrompt(t);
        break;
      
      case 'close-delete-prompt':
        J.closeDeletePrompt();
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
      
      case 'thread-options':
        if (Config.inlinePopups) {
          J.openThreadOptionsFrame(t);
        }
        else {
          J.openThreadOptions(t);
        }
        break;

      case 'multi':
        e.preventDefault();
        Multi.exec(t);
        break;
      
      case 'get-md5':
        J.onGetMD5Click(t);
        break;
      
      case 'html-toggle':
        J.onHTMLToggle(t);
        break;
      
      case 'preview-html':
        e.preventDefault();
        J.onPreviewHTMLClick(t);
        break;
      
      case 'close-html-preview':
        J.closeHTMLPreview();
        break;
      
      case 'poster-id':
        J.loadSamePosters();
        break;

      case 'ban':
        if (Config.inlinePopups) {
          J.openBanFrame(t);
        }
        else {
          J.openBanWindow(t);
        }
        break;
    }
  }
};

J.onScroll = function () {
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

J.parseRange = function (start, end) {
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
  
  if (e) {
    tid = e.detail.threadId;
    offset = e.detail.offset;
    limit = e.detail.limit;
    posts = document.getElementById('t' + tid).getElementsByClassName('postInfo');
    
    if (J.sameIDActive) {
      J.loadSamePosters(posts[offset].id.slice(2));
    }
  }
  else {
    offset = 0;
    posts = document.getElementsByClassName('postInfo');
    limit = posts.length;
  }
  
  if (Config.useIconButtons) {
    for (i = offset; i < limit; ++i) {
      J.parsePost(posts[i]);
    }
  }
};

J.onPostMenuReady = function(e) {
  var elw, el, pid, menu, flag;
  
  pid = e.detail.postId;
  menu = e.detail.node;
  
  if (window.thread_archived && $.id('f' + pid)) {
    elw = document.createElement('li');
    elw.className = 'dd-admin';
    el = document.createElement('a');
    el.href = '#';
    el.setAttribute('data-cmd', 'get-md5');
    el.setAttribute('data-id', pid);
    el.textContent = 'File MD5';
    elw.appendChild(el);
    menu.appendChild(elw);
  }
  
  if (window.spoilers && (el = $.id('fT' + pid))) {
    flag = $.cls('imgspoiler', el.parentNode)[0] ? 0 : 1;
    elw = document.createElement('li');
    elw.className = 'dd-admin';
    el = document.createElement('a');
    el.setAttribute('data-cmd', 'toggle-file-spoiler');
    el.setAttribute('data-id', pid);
    el.setAttribute('data-flag', flag);
    el.textContent = (flag ? 'Set' : 'Unset') + ' Spoiler';
    elw.appendChild(el);
    menu.appendChild(elw);
  }
  
  if (Config.useIconButtons && !Main.hasMobileLayout) {
    return;
  }
  
  elw = document.createElement('li');
  elw.className = 'dd-admin';
  el = document.createElement('a');
  el.setAttribute('data-cmd', 'open-delete-prompt');
  el.setAttribute('data-id', pid);
  el.textContent = 'Delete';
  elw.appendChild(el);
  menu.appendChild(elw);
  
  if (window.thread_archived) {
    return;
  }
  
  elw = document.createElement('li');
  elw.className = 'dd-admin';
  el = document.createElement('a');
  el.setAttribute('data-cmd', 'ban');
  el.setAttribute('data-id', pid);
  el.textContent = 'Ban';
  elw.appendChild(el);
  menu.appendChild(elw);
  
  elw = document.createElement('li');
  elw.className = 'dd-admin';
  el = document.createElement('a');
  el.setAttribute('data-cmd', 'multi');
  el.setAttribute('data-id', pid);
  el.textContent = 'Search';
  elw.appendChild(el);
  menu.appendChild(elw);
  
  if (e.detail.isOP) {
    elw = document.createElement('li');
    elw.className = 'dd-admin';
    el = document.createElement('a');
    el.setAttribute('data-cmd', 'thread-options');
    el.setAttribute('data-id', pid);
    el.textContent = 'Thread options';
    elw.appendChild(el);
    menu.appendChild(elw);
  }
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
    html += '<img class="extButton" alt="M" data-cmd="multi" data-id="'
    + pid + '" src="' + J.icons.multi
    + '" title="Display posts by this IP">'
    + '<img class="extButton" alt="B" data-cmd="ban" data-id="'
    + pid + '" src="' + J.icons.ban
    + '" title="Ban">';
  
    if ($.id('t' + pid)) {
      html += '<img class="extButton" alt="&gt;" data-cmd="thread-options" data-id="'
        + pid + '" src="' + J.icons.arrow_right + '" title="Thread Options">';
    }    
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

J.hasFlag = function(flag) {
  return this.flags.indexOf(flag) != -1;
};

J.icons = {
  multi: 'multi.png',
  ban: 'ban.png',
  arrow_right: 'arrow_right.png',
  spoiler: 's.png'
};

J.initIcons = function () {
  var key, paths, url;

  paths = {
    yotsuba_new:'futaba/',
    futaba_new:'futaba/',
    yotsuba_b_new:'burichan/',
    burichan_new:'burichan/',
    tomorrow:'tomorrow/',
    photon:'photon/'
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

J.initNavLinks = function () {
  var el, txt, frag, fragBot, nav, navbot;

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
  
  // Team link
  txt = nav.lastElementChild.previousSibling;
  frag = document.createDocumentFragment();
  frag.appendChild(document.createTextNode('] ['));
  el = document.createElement('a');
  el.textContent = 'Team';
  el.href = 'https://' + J.teamSubDomain + '.4chan.org/';
  el.setAttribute('target', '_blank');
  frag.appendChild(el);
  frag.appendChild(document.createTextNode('] ['));
  fragBot = frag.cloneNode(true);
  nav.replaceChild(frag, txt);

  // Team bottom link
  txt = navbot.lastElementChild.previousSibling;
  navbot.replaceChild(fragBot, txt);
  
  // Poster IDs
  if (Main.tid && Main.hasMobileLayout) {
    el = document.createElement('span');
    el.className = 'mobileib button redButton';
    el.innerHTML = '<label data-cmd="poster-id">Same Poster ID</label>';
    if (nav = $.cls('navLinks')[0]) {
      nav.appendChild(el);
    }
  }
};

J.initPostForm = function () {
  var form, el, field, cnt, cookie;
  
  form = $.id('postForm');
  
  if (!form && Main.tid && (cnt = $.cls('closed')[0])) {
    el = document.createElement('form');
    el.name = 'post';
    el.method = 'POST';
    el.enctype = 'multipart/form-data';
    el.action = 'https://sys.' + $L.d(Main.board) + '/' + Main.board + '/post';
    el.innerHTML = J.postFormHTML
      + '<input type="hidden" name="mode" value="regist">'
      + '<input type="hidden" name="resto" value="' + Main.tid + '">';
    
    cnt.parentNode.insertBefore(el, cnt);
    
    form = el.firstElementChild;
    
    QR.enabled = true;
  }
  
  if (form) {
    el = document.forms.post.name;

    if (el.type == 'hidden' && J.hasFlag('forcedanonname')) {
      field = document.createElement('tr');
      field.setAttribute('data-type', 'Name');
      field.innerHTML = '<td>Name</td><td><input name="name" type="text"></td>';
      if (cookie = Main.getCookie('4chan_name')) {
        field.lastChild.firstChild.value = cookie;
      }
      cnt = $.id('postForm').firstElementChild;
      cnt.insertBefore(field, cnt.firstElementChild);
      el.parentNode.removeChild(el);
    }
    
    if (J.hasFlag('html')) {
      el = document.createElement('tr');
      el.innerHTML = '<tr><td style="height: 20px;">Extra</td><td>[<label><input type="checkbox" data-cmd="html-toggle" name="html" value="1">HTML</label>] <span class="html-otp">[<label data-tip="2FA One-Time Password">OTP</label> <input type="text" autocomplete="off" name="otp" maxlength="6">] <a href="#" class="preview-html-btn" data-cmd="preview-html">Preview</a></span></td></tr>';
      form = form.firstElementChild;
      form.insertBefore(el, form.lastElementChild);
    }
  }
};

J.onHTMLToggle = function(t) {
  var el = $.cls('html-otp', t.parentNode.parentNode)[0];
  
  if (!el) {
    return;
  }
  
  el.style.display = t.checked ? 'inline' : '';
};

J.onPreviewHTMLClick = function(t) {
  var data, form, xhr;
  
  if (J.xhrs['html']) {
    return;
  }
  
  if (document.forms.qrPost) {
    form = document.forms.qrPost;
  }
  else {
    form = document.forms.post;
  }
  
  if (form.com.value === '') {
    return;
  }
  
  data = new FormData();
  data.append('com', form.com.value);
  
  xhr = new XMLHttpRequest();
  xhr.open('post', form.action + '?mode=preview_html', true);
  xhr.withCredentials = true;
  xhr.onload = J.onHTMLPReviewLoaded;
  xhr.onerror = J.onHTMLPReviewError;
  
  J.xhrs['html'] = xhr;
  
  $.addClass(t, 'disabled');
  
  xhr.send(data);
  
  Feedback.notify('Processing...', null);
};

J.resetHTMLPreviewBtn = function() {
  var nodes = $.cls('preview-html-btn');
  $.removeClass(nodes[0], 'disabled');
  nodes[1] && $.removeClass(nodes[1], 'disabled');
};

J.onHTMLPReviewLoaded = function() {
  var resp;
  
  J.xhrs['html'] = null;
  
  Feedback.hideMessage();
  
  J.resetHTMLPreviewBtn();
  
  try {
    resp = JSON.parse(this.responseText);
    
    if (resp.status == 'error') {
      Feedback.error(resp.message);
    }
  }
  catch (err) {
    Feedback.error('Something went wrong.');
  }
  
  J.buildHTMLPreview(resp.data);
};

J.onHTMLPReviewError = function() {
  J.xhrs['html'] = null;
  
  Feedback.hideMessage();
  
  J.resetHTMLPreviewBtn();
  
  console.log(this);
};

J.closeHTMLPreview = function() {
  var el;
  
  if (el = $.id('html-preview-cnt')) {
    el.parentNode.removeChild(el);
  }
  
  J.resetHTMLPreviewBtn();
};

J.buildHTMLPreview = function(html) {
  var el;
  
  J.closeHTMLPreview();
  
  el = document.createElement('div');
  el.id = 'html-preview-cnt';
  el.setAttribute('data-cmd', 'close-html-preview');
  el.innerHTML = '\
<div class="extPanel reply"><div class="panelHeader">Preview HTML Post\
<span class="panelCtrl"><img alt="Close" title="Close" class="pointer" data-cmd="close-html-preview" src="'
+ Main.icons.cross + '"></span></div>' + html + '</div>';
  
  document.body.appendChild(el);
};

J.onThreadMouseOver = function(e) {
  var t = e.target;
  
  if ($.hasClass(t, 'thumb')) {
    if (J.hasCatalogControls) {
      J.hideCatalogControls();
    }
    if (!$.hasClass(t.parentNode.parentNode, 'disabled')) {
      J.showCatalogControls(t);
    }
  }
};
/*
J.onThreadMouseOut = function(e) {
  var t = e.target;
  
  if (J.hasCatalogControls && $.hasClass(t, 'thumb')) {
    J.hideCatalogControls();
  }
};
*/
J.showCatalogControls = function(t) {
  var el, id, cnt;
  
  id = t.getAttribute('data-id');
  
  el = document.createElement('div');
  el.id = 'cat-ctrl';
  el.className = J.stylesheet;
  el.innerHTML = '<span class="threadIcon deleteIcon" data-cmd="open-delete-prompt" data-id="'
    + id + '"></span><span class="threadIcon multiIcon" data-cmd="multi" data-id="'
    + id + '"></span><span class="threadIcon banIcon" data-cmd="ban" data-id="'
    + id + '"></span>';
  
  if (cnt = $.cls('threadIcons', t.parentNode.parentNode)[0]) {
    cnt.insertBefore(el, cnt.firstElementChild);
  }
  else {
    cnt = document.createElement('div');
    cnt.className = 'threadIcons';
    cnt.appendChild(el);
    t.parentNode.parentNode.insertBefore(cnt, t.parentNode.nextElementSibling);
  }
  
  J.hasCatalogControls = true;
};

J.hideCatalogControls = function() {
  var el = $.id('cat-ctrl');
  
  if (el) {
    el.parentNode.removeChild(el);
  }
  
  J.hasCatalogControls = false;
};

J.initCatalog = function() {
  var storage;
  
  window.Main = {
    board: location.pathname.split(/\//)[1]
  };
  
  Main.addTooltip = function(link, message, id) {
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
  
  J.initCatAutoReload(true);
  
  document.addEventListener('keydown', J.onCatalogKeyDown, false);
  
  $.on(threads, 'mouseover', J.onThreadMouseOver);
  
  if (window.text_only) {
    document.addEventListener('4chanPostMenuReady', J.onPostMenuReady, false);
  }
  //$.on(threads, 'mouseout', J.onThreadMouseOut);
};

J.init = function () {
  var flags, ts;
  
  SettingsMenu.options['Moderator'] = {
    useIconButtons: [ 'Use icon buttons', 'Display old-style buttons instead of using drop-down' ],
    changeUpdateDelay:[ 'Reduce auto-update delay', 'Reduce the thread updater delay', true ],
    fixedAdminToolbox:[ 'Pin Moderator Tools to the page', 'Moderator Tools will scroll with you' ],
    inlinePopups: [ 'Inline admin panels', 'Open admin panels in browser window, instead of a popup' ],
    disableMngExt:[ 'Disable moderator extension', 'Completely disable the moderator extension (overrides any checked boxes)', true ]
  };

  if (Config.disableMngExt) {
    return;
  }
  
  if (flags = Main.getCookie('4chan_aflags')) {
    J.flags = flags.split(',');
  }
  
  J.addCss();
  
  if (Config.useIconButtons) {
    J.initIcons();
  }
  
  QR.noCooldown = QR.noCaptcha = true;
  //QR.comLength = window.comlen = 10000;

  document.addEventListener('click', J.onClick, false);
  document.addEventListener('DOMContentLoaded', J.run, false);
};

J.run = function() {
  var posts, el, nav;

  document.removeEventListener('DOMContentLoaded', J.run, false);

  J.initNavLinks();
  J.initPostForm();
  
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
      
      nav = $.cls('navLinksBot')[0];
      el = document.createElement('span');
      el.id = 'threadOptsButtom';
      el.innerHTML = '[<a href="javascript:;" data-id="' + Main.tid + '" data-cmd="thread-options">Thread Options</a>]';
      nav.appendChild(el);
    }
    else {
      J.onParsingDone();
    }
    
    document.addEventListener('4chanParsingDone', J.onParsingDone, false);
    
    J.parserEventBound = true;
  }
  
  document.addEventListener('4chanPostMenuReady', J.onPostMenuReady, false);
  
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

J.postFormHTML = '<table class="postForm" id="postForm"><tbody>\
<tr data-type="Name"><td>Name</td><td>\
<input name="name" type="text" tabindex="1" \
placeholder="Anonymous"></td></tr><tr data-type="Options"><td>Options</td>\
<td><input name="email" type="text" tabindex="2"><input type="submit" \
value="Post" tabindex="6"></td></tr><tr data-type="Comment"><td>Comment</td>\
<td><textarea name="com" cols="48" rows="4" tabindex="4" wrap="soft">\
</textarea></td></tr><tr data-type="File"><td>File</td><td>\
<input id="postFile" name="upfile" type="file" tabindex="7">\
<span class="desktop">[<label><input type="checkbox" name="spoiler" value="on" \
tabindex="8">Spoiler?</label>]</span></td></tr><tr><td></td></tr></tbody></table>';

J.addCss = function () {
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
#captchaFormPart {\
  display: none;\
}\
#at-prio-appeals {\
  color: blue;\
}\
#at-illegal-br,\
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
#adminToolbox hr {\
  margin: 2px 0;\
}\
#threadOptsClose,\
#banReqClose {\
  float: right;\
}\
#threadOpts iframe,\
#banReq iframe {\
  overflow: hidden;\
}\
#threadOpts,\
#banReq {\
  display: block;\
  position: fixed;\
  padding: 2px;\
  font-size: 10pt;\
}\
#banReq {\
  height: 490px;\
}\
#threadOpts {\
  height: 194px;\
}\
#threadOptsHeader,\
#banReqHeader {\
  text-align: center;\
  margin-bottom: 1px;\
  padding: 0;\
  height: 18px;\
  line-height: 18px;\
}\
#threadOptsButtom {\
  float: right;\
  margin-right: 4px;\
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
.m-dark .mobileExtControls {\
  color: #81a2be !important;\
}\
.m-dark .mobileExtControls span:after,\
.m-dark .mobileExtControls span:before {\
  color: #1d1f21 !important;\
}\
#cat-ctrl {\
  display: inline-block;\
  margin-right: 2px;\
  margin-top: -1px;\
}\
#cat-ctrl .threadIcon {\
  cursor: pointer;\
}\
.disabled {\
  opacity: 0.5;\
}\
.burichan_new .deleteIcon,\
.yotsuba_b_new .deleteIcon {\
  background-image: url("//s.4cdn.org/image/buttons/burichan/cross.png");\
}\
.burichan_new .banIcon,\
.yotsuba_b_new .banIcon {\
  background-image: url("//s.4cdn.org/image/buttons/burichan/ban.png");\
}\
.burichan_new .fileIcon,\
.yotsuba_b_new .fileIcon {\
  background-image: url("//s.4cdn.org/image/buttons/burichan/f.png");\
}\
.burichan_new .multiIcon,\
.yotsuba_b_new .multiIcon {\
  background-image: url("//s.4cdn.org/image/buttons/burichan/multi.png");\
}\
.futaba_new .deleteIcon,\
.yotsuba_new .deleteIcon {\
  background-image: url("//s.4cdn.org/image/buttons/futaba/cross.png");\
}\
.futaba_new .banIcon,\
.yotsuba_new .banIcon {\
  background-image: url("//s.4cdn.org/image/buttons/futaba/ban.png");\
}\
.futaba_new .fileIcon,\
.yotsuba_new .fileIcon {\
  background-image: url("//s.4cdn.org/image/buttons/futaba/f.png");\
}\
.futaba_new .multiIcon,\
.yotsuba_new .multiIcon {\
  background-image: url("//s.4cdn.org/image/buttons/futaba/multi.png");\
}\
.photon .deleteIcon {\
  background-image: url("//s.4cdn.org/image/buttons/photon/cross.png");\
}\
.photon .banIcon {\
  background-image: url("//s.4cdn.org/image/buttons/photon/ban.png");\
}\
.photon .fileIcon {\
  background-image: url("//s.4cdn.org/image/buttons/photon/f.png");\
}\
.photon .multiIcon {\
  background-image: url("//s.4cdn.org/image/buttons/photon/multi.png");\
}\
.tomorrow .deleteIcon {\
  background-image: url("//s.4cdn.org/image/buttons/tomorrow/cross.png");\
}\
.tomorrow .banIcon {\
  background-image: url("//s.4cdn.org/image/buttons/tomorrow/ban.png");\
}\
.tomorrow .fileIcon {\
  background-image: url("//s.4cdn.org/image/buttons/tomorrow/f.png");\
}\
.tomorrow .multiIcon {\
  background-image: url("//s.4cdn.org/image/buttons/tomorrow/multi.png");\
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
.preview-html-btn { font-size: 11px; }\
#html-preview-cnt .extPanel { width: 800px; margin-left: -400px; }\
#html-preview-cnt {\
  position: fixed;\
  width: 100%;\
  height: 100%;\
  z-index: 9002;\
  top: 0;\
  left: 0;\
}\
#html-preview-cnt {\
  line-height: 14px;\
  font-size: 14px;\
  background-color: rgba(0, 0, 0, 0.25);\
}\
#html-preview-cnt:after {\
  display: inline-block;\
  height: 100%;\
  vertical-align: middle;\
  content: "";\
}\
#html-preview-cnt > div {\
  -moz-box-sizing: border-box;\
  box-sizing: border-box;\
  display: inline-block;\
  height: auto;\
  max-height: 100%;\
  position: relative;\
  width: 400px;\
  left: 50%;\
  margin-left: -200px;\
  overflow: auto;\
  box-shadow: 0 0 5px rgba(0, 0, 0, 0.25);\
  vertical-align: middle;\
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
.reveal-img-spoilers .imgspoiler:hover::before { background: #fff; }\
.html-otp { display: none; width: 50px; }\
.html-otp input { width: 50px !important; height: 12px; }\
.drag {\
  -moz-user-select: none !important;\
  cursor: move !important;\
}' + (J.isCatalog ? '\
.panelHeader .panelCtrl {\
  position: absolute;\
  right: 5px;\
  top: 5px;\
}\
.active-btn { border-bottom: 3px double; }\
.UIPanel {\
  position: fixed;\
  width: 100%;\
  height: 100%;\
  top: 0;\
  left: 0;\
  z-index: 9000 !important;\
}\
.UIPanel {\
  line-height: 14px;\
  font-size: 14px;\
  background-color: rgba(0, 0, 0, 0.25);\
}\
.UIPanel:after {\
  display: inline-block;\
  height: 100%;\
  vertical-align: middle;\
  content: "";\
}\
.UIPanel > div {\
  -moz-box-sizing: border-box;\
  box-sizing: border-box;\
  display: inline-block;\
  height: auto;\
  max-height: 100%;\
  position: relative;\
  width: 400px;\
  left: 50%;\
  margin-left: -200px;\
  overflow: auto;\
  box-shadow: 0 0 5px rgba(0, 0, 0, 0.25);\
  vertical-align: middle;\
}\
.UIPanel .extPanel {\
  padding: 2px;\
}' : '');

  style = document.createElement('style');
  style.setAttribute('type', 'text/css');
  style.textContent = css;
  document.head.appendChild(style);
};

if (/https?:\/\/boards\.(?:4chan|4channel)\.org\/[a-z0-9]+\/catalog($|#.*$)/.test(location.href)) {
  J.isCatalog = true;
  J.initCatalog();
}
else {
  J.init();
  //J.run();
}

})();
