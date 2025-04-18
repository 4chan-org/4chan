/********************************
 *                              *
 *        4chan Extension       *
 *                              *
 ********************************/

/**
 * Helpers
 */
$ = {};

$.id = function(id) {
  return document.getElementById(id);
};

$.cls = function(klass, root) {
  return (root || document).getElementsByClassName(klass);
};

$.byName = function(name) {
  return document.getElementsByName(name);
};

$.tag = function(tag, root) {
  return (root || document).getElementsByTagName(tag);
};

$.qs = function(sel, root) {
  return (root || document).querySelector(sel);
};

$.extend = function(destination, source) {
  for (var key in source) {
    destination[key] = source[key];
  }
};

if (!document.documentElement.classList) {
  $.hasClass = function(el, klass) {
    return (' ' + el.className + ' ').indexOf(' ' + klass + ' ') != -1;
  };
  
  $.addClass = function(el, klass) {
    el.className = (el.className == '') ? klass : el.className + ' ' + klass;
  };
  
  $.removeClass = function(el, klass) {
    el.className = (' ' + el.className + ' ').replace(' ' + klass + ' ', '');
  };
}
else {
  $.hasClass = function(el, klass) {
    return el.classList.contains(klass);
  };
  
  $.addClass = function(el, klass) {
    el.classList.add(klass);
  };
  
  $.removeClass = function(el, klass) {
    el.classList.remove(klass);
  };
}

$.get = function(url, callbacks, headers) {
  var key, xhr;
  
  xhr = new XMLHttpRequest();
  xhr.open('GET', url, true);
  if (callbacks) {
    for (key in callbacks) {
      xhr[key] = callbacks[key];
    }
  }
  if (headers) {
    for (key in headers) {
      xhr.setRequestHeader(key, headers[key]);
    }
  }
  xhr.send(null);
  return xhr;
};

$.hash = function(str) {
  var i, j, msg = 0;
  for (i = 0, j = str.length; i < j; ++i) {
    msg = ((msg << 5) - msg) + str.charCodeAt(i);
  }
  return msg;
};

$.prettySeconds = function(fs) {
  var m, s;
  
  m = Math.floor(fs / 60);
  s = Math.round(fs - m * 60);
  
  return [ m, s ];
};

$.docEl = document.documentElement;

$.cache = {};

/**
 * Parser
 */
var Parser = {};

Parser.init = function() {
  var o, a, h, m, tail, staticPath, tracked, el;
  
  if (Config.filter || Config.embedSoundCloud || Config.embedYouTube || Config.embedVocaroo) {
    this.needMsg = true;
  }
  
  staticPath = '//s.4cdn.org/image/';
  
  tail = window.devicePixelRatio >= 2 ? '@2x.gif' : '.gif';
  
  this.icons = {
    admin: staticPath + 'adminicon' + tail,
    mod: staticPath + 'modicon' + tail,
    dev: staticPath + 'developericon' + tail,
    manager: staticPath + 'managericon' + tail,
    del: staticPath + 'filedeleted-res' + tail
  };
  
  this.prettify = typeof prettyPrint == 'function';
  
  this.customSpoiler = {};
  
  if (Config.localTime) {
    if (o = (new Date).getTimezoneOffset()) {
      a = Math.abs(o);
      h = (0 | (a / 60));
      
      this.utcOffset = 'Timezone: UTC' + (o < 0 ? '+' : '-')
        + h + ((m = a - h * 60) ? (':' + m) : '');
    }
    else {
      this.utcOffset = 'Timezone: UTC';
    }
    
    this.weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  }
  
  if (Main.tid) {
    this.trackedReplies = this.getTrackedReplies(Main.tid) || {};
  }
};

Parser.getTrackedReplies = function(tid) {
  var tracked = null;
  
  if (tracked = sessionStorage.getItem('4chan-track-' + Main.board + '-' + tid)) {
    tracked = JSON.parse(tracked);
  }
  
  return tracked;
};

Parser.saveTrackedReplies = function(tid, replies) {
  sessionStorage.setItem(
    '4chan-track-' + Main.board + '-' + tid,
    JSON.stringify(replies)
  );
};

Parser.parseThreadJSON = function(data) {
  var thread;
  
  try {
    thread = JSON.parse(data).posts;
  }
  catch (e) {
    console.log(e);
    thread = [];
  }
  
  return thread;
};

Parser.parseCatalogJSON = function(data) {
  var catalog;
  
  try {
    catalog = JSON.parse(data);
  }
  catch (e) {
    console.log(e);
    catalog = [];
  }
  
  return catalog;
};

Parser.setCustomSpoiler = function(board, val) {
  var s;
  if (!this.customSpoiler[board] && (val = parseInt(val))) {
    if (board == Main.board && (s = $.cls('imgspoiler')[0])) {
      this.customSpoiler[board] =
        s.firstChild.src.match(/spoiler(-[a-z0-9]+)\.png$/)[1];
    }
    else {
      this.customSpoiler[board] = '-' + board
        + (Math.floor(Math.random() * val) + 1);
    }
  }
};

Parser.buildPost = function(thread, board, pid) {
  var i, j, el = null;
  
  for (i = 0; j = thread[i]; ++i) {
    if (j.no != pid) {
      continue;
    }
    
    if (!Config.revealSpoilers && thread[0].custom_spoiler) {
      Parser.setCustomSpoiler(board, thread[0].custom_spoiler);
    }
    
    el = Parser.buildHTMLFromJSON(j, board, false, true).lastElementChild;
    
    if (Config.IDColor && (uid = $.cls('posteruid', el)[Main.hasMobileLayout ? 0 : 1])) {
      IDColor.applyRemote(uid.firstElementChild);
    }
  }
  
  return el;
};

Parser.decodeSpecialChars = function(str) {
  return str.replace(/&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>');
};

Parser.encodeSpecialChars = function(str) {
  return str.replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
};

Parser.buildHTMLFromJSON = function(data, board, standalone, fromQuote) {
  var
    container = document.createElement('div'),
    isOP = false,
    
    userId,
    fileDims = '',
    imgSrc = '',
    fileInfo = '',
    fileHtml = '',
    fileThumb,
    filePath,
    fileName,
    fileSpoilerTip = '"',
    size = '',
    fileClass = '',
    shortFile = '',
    longFile = '',
    tripcode = '',
    capcodeStart = '',
    capcodeClass = '',
    capcode = '',
    flag,
    highlight = '',
    emailStart = '',
    emailEnd = '',
    name,
    subject,
    noLink,
    quoteLink,
    replySpan = '',
    noFilename,
    decodedFilename,
    mobileLink = '',
    postType = 'reply',
    summary = '',
    postCountStr,
    resto,
    capcode_replies = '',
    threadIcons = '',
    needFileTip = false,
    
    i, q, href, quotes,
    
    imgDir = '//i.4cdn.org/' + board;
  
  if (data.resto == 0) {
    isOP = true;
    
    if (standalone) {
      mobileLink = '<div class="postLink mobile"><span class="info"></span><a href="'
        + 'thread/' + data.no + '" class="button">View Thread</a></div>';
      postType = 'op';
      replySpan = '&nbsp; <span>[<a href="'
        + 'thread/' + data.no + (data.semantic_url ? ('/' + data.semantic_url) : '')
        + '" class="replylink" rel="canonical">Reply</a>]</span>'
    }
    
    resto = data.no;
  }
  else {
    resto = data.resto;
  }
  
  
  if (!Main.tid || board != Main.board) {
    noLink = 'thread/' + resto + '#p' + data.no;
    quoteLink = 'thread/' + resto + '#q' + data.no;
  }
  else {
    noLink = '#p' + data.no;
    quoteLink = 'javascript:quote(\'' + data.no + '\')';
  }
  
  if (!data.capcode && data.id) {
    userId = ' <span class="posteruid id_'
      + data.id + '">(ID: <span class="hand" title="Highlight posts by this ID">'
      + data.id + '</span>)</span> ';
  }
  else {
    userId = '';
  }
  
  switch (data.capcode) {
    case 'admin_highlight':
      highlight = ' highlightPost';
    case 'admin':
      capcodeStart = ' <strong class="capcode hand id_admin"'
        + 'title="Highlight posts by the Administrator">## Admin</strong>';
      capcodeClass = ' capcodeAdmin';
      
      capcode = ' <img src="' + Parser.icons.admin + '" '
        + 'alt="This user is the 4chan Administrator." '
        + 'title="This user is the 4chan Administrator." class="identityIcon">';
      break;
    case 'mod':
      capcodeStart = ' <strong class="capcode hand id_mod" '
        + 'title="Highlight posts by Moderators">## Mod</strong>';
      capcodeClass = ' capcodeMod';
      
      capcode = ' <img src="' + Parser.icons.mod + '" '
        + 'alt="This user is a 4chan Moderator." '
        + 'title="This user is a 4chan Moderator." class="identityIcon">';
      break;
    case 'developer':
      capcodeStart = ' <strong class="capcode hand id_developer" '
        + 'title="Highlight posts by Developers">## Developer</strong>';
      capcodeClass = ' capcodeDeveloper';
      
      capcode = ' <img src="' + Parser.icons.dev + '" '
        + 'alt="This user is a 4chan Developer." '
        + 'title="This user is a 4chan Developer." class="identityIcon">';
      break;
    case 'manager':
      capcodeStart = ' <strong class="capcode hand id_manager" '
        + 'title="Highlight posts by Managers">## Manager</strong>';
      capcodeClass = ' capcodeManager';
      
      capcode = ' <img src="' + Parser.icons.manager + '" '
        + 'alt="This user is a 4chan Manager." '
        + 'title="This user is a 4chan Manager." class="identityIcon">';
      break;
  }
  
  if (data.email) {
    emailStart = '<a href="mailto:' + data.email.replace(/ /g, '%20') + '" class="useremail">';
    emailEnd = '</a>';
  }
  
  if (data.country) {
    if (board == 'pol') {
      flag = ' <img src="//s.4cdn.org/image/country/troll/'
        + data.country.toLowerCase() + '.gif" alt="'
        + data.country + '" title="' + data.country_name + '" class="countryFlag">';
    }
    else {
      flag = ' <span title="' + data.country_name + '" class="flag flag-'
        + data.country.toLowerCase() + '"></span>';
    }
  }
  else {
    flag = '';
  }
  
  if (data.filedeleted) {
    fileHtml = '<div id="f' + data.no + '" class="file"><span class="fileThumb"><img src="'
      + Parser.icons.del + '" class="fileDeletedRes" alt="File deleted."></span></div>';
  }
  else if (data.ext) {
    decodedFilename = Parser.decodeSpecialChars(data.filename);
    
    shortFile = longFile = data.filename + data.ext;
    
    if (decodedFilename.length > (isOP ? 40 : 30)) {
      shortFile = Parser.encodeSpecialChars(
        decodedFilename.slice(0, isOP ? 35 : 25)
      ) + '(...)' + data.ext;
      
      needFileTip = true;
    }
    
    if (!data.tn_w && !data.tn_h && data.ext == '.gif') {
      data.tn_w = data.w;
      data.tn_h = data.h;
    }
    if (data.fsize >= 1048576) {
      size = ((0 | (data.fsize / 1048576 * 100 + 0.5)) / 100) + ' M';
    }
    else if (data.fsize > 1024) {
      size = (0 | (data.fsize / 1024 + 0.5)) + ' K';
    }
    else {
      size = data.fsize + ' ';
    }
    
    if (data.spoiler) {
      if (!Config.revealSpoilers) {
        fileName = 'Spoiler Image';
        fileSpoilerTip = '" title="' + longFile + '"';
        fileClass = ' imgspoiler';
        
        fileThumb = '//s.4cdn.org/image/spoiler'
          + (Parser.customSpoiler[board] || '') + '.png';
        data.tn_w = 100;
        data.tn_h = 100;
        
        noFilename = true;
      }
      else {
        fileName = shortFile;
      }
    }
    else {
      fileName = shortFile;
    }
    
    if (!fileThumb) {
      fileThumb = '//0.t.4cdn.org/' + board + '/' + data.tim + 's.jpg';
    }
    
    fileDims = data.ext == '.pdf' ? 'PDF' : data.w + 'x' + data.h;
    
    if (board != 'f') {
      filePath = imgDir + '/' + data.tim + data.ext;
      
      imgSrc = '<a class="fileThumb' + fileClass + '" href="' + filePath
        + '" target="_blank"><img src="' + fileThumb
        + '" alt="' + size + 'B" data-md5="' + data.md5
        + '" style="height: ' + data.tn_h + 'px; width: '
        + data.tn_w + 'px;">'
        + '<div class="mFileInfo mobile">' + size + 'B '
        + data.ext.slice(1).toUpperCase()
        + '</div></a>';
      
      fileInfo = '<div class="fileText" id="fT' + data.no + fileSpoilerTip
        + '>File: <a' + (needFileTip ? (' title="' + longFile + '"') : '')
        + ' href="' + filePath + '" target="_blank">'
        + fileName + '</a> (' + size + 'B, ' + fileDims + ')</div>';
    }
    else {
      filePath = imgDir + '/' + data.filename + data.ext;
      
      fileDims += ', ' + data.tag;
      
      fileInfo = '<div class="fileText" id="fT' + data.no + '"'
        + '>File: <a href="' + filePath + '" target="_blank">'
        + data.filename + '.swf</a> (' + size + 'B, ' + fileDims + ')</div>';
    }
    
    fileHtml = '<div id="f' + data.no + '" class="file">'
      + fileInfo + imgSrc + '</div>';
  }
  
  if (data.trip) {
    tripcode = ' <span class="postertrip">' + data.trip + '</span>';
  }
  
  name = data.name || '';
  
  
  if (isOP) {
    if (data.capcode_replies) {
      capcode_replies = Parser.buildCapcodeReplies(data.capcode_replies, board, data.no);
    }
    
    if (fromQuote && data.replies) {
      postCountStr = data.replies + ' post' + (data.replies > 1 ? 's' : '');
      
      if (data.images) {
        postCountStr += ' and ' + data.images + ' image repl' +
          (data.images > 1 ? 'ies' : 'y');
      }
      
      summary = '<span class="summary preview-summary">' + postCountStr + '.</span>';
    }
    
    if (data.sticky) {
      threadIcons += '<img class="stickyIcon retina" title="Sticky" alt="Sticky" src="'
        + Main.icons2.sticky + '"> ';
    }
    
    if (data.closed) {
      if (data.archived) {
        threadIcons += '<img class="archivedIcon retina" title="Archived" alt="Archived" src="'
          + Main.icons2.archived + '"> ';
      }
      else {
        threadIcons += '<img class="closedIcon retina" title="Closed" alt="Closed" src="'
          + Main.icons2.closed + '"> ';
      }
    }
    
    subject = '<span class="subject">' + (data.sub || '') + '</span> ';
  }
  else {
    subject = '';
  }
  
  container.className = 'postContainer ' + postType + 'Container';
  container.id = 'pc' + data.no;
  
  container.innerHTML =
    (isOP ? '' : '<div class="sideArrows" id="sa' + data.no + '">&gt;&gt;</div>') +
    '<div id="p' + data.no + '" class="post ' + postType + highlight + '">' +
      '<div class="postInfoM mobile" id="pim' + data.no + '">' +
        '<span class="nameBlock' + capcodeClass + '">' +
        '<span class="name">' + name + '</span>' + tripcode +
        capcodeStart + capcode + userId + flag +
        '<br>' + subject +
        '</span><span class="dateTime postNum" data-utc="' + data.time + '">' +
        data.now + ' <a href="' + data.no + '#p' + data.no + '" title="Link to this post">No.</a>' +
        '<a href="javascript:quote(\'' + data.no + '\');" title="Reply to this post">' +
        data.no + '</a></span>' +
      '</div>' +
      (isOP ? fileHtml : '') +
      '<div class="postInfo desktop" id="pi' + data.no + '"' +
        (board != Main.board ? (' data-board="' + board + '"') : '') + '>' +
        '<input type="checkbox" name="' + data.no + '" value="delete"> ' +
        subject +
        '<span class="nameBlock' + capcodeClass + '">' + emailStart +
          '<span class="name">' + name + '</span>' +
          tripcode + capcodeStart + emailEnd + capcode + userId + flag +
        ' </span> ' +
        '<span class="dateTime" data-utc="' + data.time + '">' + data.now + '</span> ' +
        '<span class="postNum desktop">' +
          '<a href="' + noLink + '" title="Link to this post">No.</a><a href="' +
          quoteLink + '" title="Reply to this post">' + data.no + '</a> '
            + threadIcons + replySpan +
        '</span>' +
      '</div>' +
      (isOP ? '' : fileHtml) +
      '<blockquote class="postMessage" id="m' + data.no + '">'
      + (data.com || '') + capcode_replies + summary + '</blockquote> ' +
    '</div>' + mobileLink;
  
  if (!Main.tid || board != Main.board) {
    quotes = container.getElementsByClassName('quotelink');
    for (i = 0; q = quotes[i]; ++i) {
      href = q.getAttribute('href');
      if (href.charAt(0) != '/') {
        q.href = '/' + board + '/thread/' + resto + href;
      }
    }
  }
  
  return container;
};

Parser.buildCapcodeReplies = function(replies, board, tid) {
  var i, capcode, id, html, map, post_ids, prelink, pretext;
  
  map = {
    admin: 'Administrator',
    mod: 'Moderator',
    developer: 'Developer',
    manager: 'Manager'
  };
  
  if (board != Main.board) {
    prelink = '/' + board + '/thread/';
    pretext = '&gt;&gt;&gt;/' + board + '/';
  }
  else {
    prelink = '';
    pretext = '&gt;&gt;';
  }
  
  html = '<br><br><span class="capcodeReplies"><span class="smaller">';
  
  for (capcode in replies) {
    html += '<span class="bold">' + map[capcode] + ' Replies:</span> ';
    
    post_ids = replies[capcode];
    
    for (i = 0; id = post_ids[i]; ++i) {
      html += '<a class="quotelink" href="'
        + prelink + tid + '#p' + id + '">' + pretext + id + '</a> ';
    }
  }
  
  return html + '</span></span>';
};

Parser.parseBoard = function() {
  var i, threads = document.getElementsByClassName('thread');
  
  for (i = 0; threads[i]; ++i) {
    Parser.parseThread(threads[i].id.slice(1));
  }
};

Parser.parseThread = function(tid, offset, limit) {
  var i, j, thread, posts, pi, el, frag, summary, omitted, key, filtered, cnt,
    frag;
  
  thread = $.id('t' + tid);
  posts = thread.getElementsByClassName('post');
  
  if (!offset) {
    pi = document.getElementById('pi' + tid);
    
    if (!Main.tid) {
      if (Config.filter) {
        filtered = Filter.exec(
          thread,
          pi, 
          document.getElementById('m' + tid),
          tid
        );
      }
      
      if (Config.threadHiding && !filtered) {
        if (Main.hasMobileLayout) {
          el = document.createElement('a');
          el.href = 'javascript:;';
          el.setAttribute('data-cmd', 'hide');
          el.setAttribute('data-id', tid);
          el.className = 'mobileHideButton button';
          el.textContent = 'Hide';
          posts[0].nextElementSibling.appendChild(el);
        }
        else {
          el = document.createElement('span');
          el.innerHTML = '<img alt="H" class="extButton threadHideButton"'
            + 'data-cmd="hide" data-id="' + tid + '" src="'
            + Main.icons.minus + '" title="Hide thread">';
          posts[0].insertBefore(el, posts[0].firstChild);
        }
        el.id = 'sa' + tid;
        if (ThreadHiding.hidden[tid]) {
          ThreadHiding.hidden[tid] = Main.now;
          ThreadHiding.hide(tid);
        }
      }
      
      if (ThreadExpansion.enabled
          && (summary = $.cls('summary', thread)[0])) {
        frag = document.createDocumentFragment();
        
        omitted = summary.cloneNode(true);
        omitted.className = '';
        summary.textContent = '';
        
        el = document.createElement('img');
        el.className = 'extButton expbtn';
        el.title = 'Expand thread';
        el.alt = '+';
        el.setAttribute('data-cmd', 'expand');
        el.setAttribute('data-id', tid);
        el.src = Main.icons.plus;
        frag.appendChild(el);
        
        frag.appendChild(omitted);
        
        el = document.createElement('span');
        el.style.display = 'none';
        el.textContent = 'Showing all replies.'
        frag.appendChild(el);
        
        summary.appendChild(frag);
      }
    }
    
    if (Main.tid && Config.threadWatcher && (cnt = $.cls('navLinksBot')[0])) {
      el = document.createElement('img');
      
      if (ThreadWatcher.watched[key = tid + '-' + Main.board]) {
        el.src = Main.icons.watched;
        el.setAttribute('data-active', '1');
      }
      else {
        el.src = Main.icons.notwatched;
      }
      
      el.className = 'extButton wbtn wbtn-' + key;
      el.setAttribute('data-cmd', 'watch');
      el.setAttribute('data-id', tid);
      el.alt = 'W';
      el.title = 'Add to watch list';
      
      frag = document.createDocumentFragment();
      frag.appendChild(document.createTextNode('['));
      frag.appendChild(el.cloneNode(true));
      frag.appendChild(document.createTextNode('] '));
      cnt.insertBefore(frag, cnt.firstChild);
    }
  }
  
  j = offset ? offset < 0 ? posts.length + offset : offset : 0;
  limit = limit ? j + limit : posts.length;
  
  if (Main.isMobileDevice && Config.quotePreview) {
    for (i = j; i < limit; ++i) {
      Parser.parseMobileQuotelinks(posts[i]);
    }
  }
  
  if (Parser.trackedReplies) {
    for (i = j; i < limit; ++i) {
      Parser.parseTrackedReplies(posts[i]);
    }
  }
  
  for (i = j; i < limit; ++i) {
    Parser.parsePost(posts[i].id.slice(1), tid);
  }
  
  if (offset) {
    if (Parser.prettify) {
      for (i = j; i < limit; ++i) {
        Parser.parseMarkup(posts[i]);
      }
    }
    if (window.jsMath) {
      if (window.jsMath.loaded) {
        for (i = j; i < limit; ++i) {
          window.jsMath.ProcessBeforeShowing(posts[i]);
        }
      }
      else {
        Parser.loadJSMath();
      }
    }
  }
  
  UA.dispatchEvent('4chanParsingDone', { threadId: tid, offset: j, limit: limit });
};

Parser.loadJSMath = function(root) {
  if ($.cls('math', root)[0]) {
    window.jsMath.Autoload.Script.Push('ProcessBeforeShowing', [ null ]);
    window.jsMath.Autoload.LoadJsMath();
  }
};

Parser.parseMathOne = function(node) {
  if (window.jsMath.loaded) {
    window.jsMath.ProcessBeforeShowing(node);
  }
  else {
    Parser.loadJSMath(node);
  }
};

Parser.parseTrackedReplies = function(post) {
  var i, link, quotelinks;
  
  quotelinks = $.cls('quotelink', post);
  
  for (i = 0; link = quotelinks[i]; ++i) {
    if (Parser.trackedReplies[link.textContent]) {
      link.textContent += ' (You)';
      Parser.hasYouMarkers = true;
    }
  }
};

Parser.parseMobileQuotelinks = function(post) {
  var i, link, quotelinks, t, el;
  
  quotelinks = $.cls('quotelink', post);
  
  for (i = 0; link = quotelinks[i]; ++i) {
    t = link.getAttribute('href').match(/^(?:\/([^\/]+)\/)?(?:thread\/)?([0-9]+)?#p([0-9]+)$/);
    
    if (!t) {
      continue;
    }
    
    el = document.createElement('a');
    el.href = link.href;
    el.textContent = ' #';
    el.className = 'quoteLink';
    
    link.parentNode.insertBefore(el, link.nextSibling);
  }
};

Parser.parseMarkup = function(post) {
  var i, pre, el;
  
  if ((pre = post.getElementsByClassName('prettyprint'))[0]) {
    for (i = 0; el = pre[i]; ++i) {
      el.innerHTML = prettyPrintOne(el.innerHTML);
    }
  }
};

Parser.parsePost = function(pid, tid) {
  var hasMobileLayout, cnt, el, pi, href, img, file, msg, filtered, html, filename, txt, finfo, isOP, uid;
  
  hasMobileLayout = Main.hasMobileLayout;
  
  if (!tid) {
    pi = pid.getElementsByClassName('postInfo')[0];
    pid = pi.id.slice(2);
  }
  else {
    pi = document.getElementById('pi' + pid);
  }
  
  if (Parser.needMsg) {
    msg = document.getElementById('m' + pid);
  }
  
  if (hasMobileLayout) {
    if (Config.reportButton) {
      el = document.createElement('span');
      el.className = 'mobile mobile-report';
      el.setAttribute('data-cmd', 'report');
      el.setAttribute('data-id', pid);
      el.textContent = 'Report';
      pi.parentNode.appendChild(el);
    }
  }
  else {
    el = document.createElement('a');
    el.href = '#';
    el.className = 'postMenuBtn';
    el.title = 'Post menu';
    el.setAttribute('data-cmd', 'post-menu');
    el.textContent = '▶';
    pi.appendChild(el);
  }
  
  if (tid && pid != tid) {
    if (Config.filter) {
      filtered = Filter.exec(pi.parentNode, pi, msg);
    }
    
    if (!filtered && ReplyHiding.hidden[pid]) {
      ReplyHiding.hidden[pid] = Main.now;
      ReplyHiding.hide(pid);
    }
    
    if (Config.backlinks) {
      Parser.parseBacklinks(pid, tid);
    }
  }
  
  if (IDColor.enabled && (uid = $.cls('posteruid', pi.parentNode)[hasMobileLayout ? 0 : 1])) {
    IDColor.apply(uid.firstElementChild);
  }
  
  if (Config.embedSoundCloud) {
    Media.parseSoundCloud(msg);
  }
  
  if (Config.embedYouTube) {
    Media.parseYouTube(msg);
  }
  
  if (Config.embedVocaroo) {
    Media.parseVocaroo(msg);
  }
  
  if (Config.revealSpoilers
      && (file = document.getElementById('f' + pid))
      && (file = file.children[1])
    ) {
    if ($.hasClass(file, 'imgspoiler')) {
      img = file.firstChild;
      file.removeChild(img);
      img.removeAttribute('style');
      isOP = $.hasClass(pi.parentNode, 'op');
      img.style.maxWidth = img.style.maxHeight = isOP ? '250px' : '125px';
      img.src = '//0.t.4cdn.org'
        + (file.pathname.replace(/([0-9]+).+$/, '/$1s.jpg'));
      
      filename = file.previousElementSibling;
      finfo = filename.title.split('.');
      
      if (finfo[0].length > (isOP ? 40 : 30)) {
        txt = finfo[0].slice(0, isOP ? 35 : 25) + '(...)' + finfo[1];
      }
      else {
        txt = filename.title;
        filename.removeAttribute('title');
      }
      
      filename.firstElementChild.innerHTML = txt;
      file.insertBefore(img, file.firstElementChild);
    }
  }
  
  if (Config.localTime) {
    if (hasMobileLayout) {
      el = pi.parentNode.getElementsByClassName('dateTime')[0];
      el.firstChild.nodeValue
        = Parser.getLocaleDate(new Date(el.getAttribute('data-utc') * 1000)) + ' ';
    }
    else {
      el = pi.getElementsByClassName('dateTime')[0];
      el.title = this.utcOffset;
      el.textContent
        = Parser.getLocaleDate(new Date(el.getAttribute('data-utc') * 1000));
    }
  }
  
};

Parser.getLocaleDate = function(date) {
  return ('0' + (1 + date.getMonth())).slice(-2) + '/'
    + ('0' + date.getDate()).slice(-2) + '/'
    + ('0' + date.getFullYear()).slice(-2) + '('
    + this.weekdays[date.getDay()] + ')'
    + ('0' + date.getHours()).slice(-2) + ':'
    + ('0' + date.getMinutes()).slice(-2) + ':'
    + ('0' + date.getSeconds()).slice(-2);
};

Parser.parseBacklinks = function(pid, tid) {
  var i, j, msg, backlinks, linklist, ids, target, bid, html, bl, el, href;
  
  msg = document.getElementById('m' + pid);
  
  if (!(backlinks = msg.getElementsByClassName('quotelink'))) {
    return;
  }
  
  linklist = {};
  
  for (i = 0; j = backlinks[i]; ++i) {
    // [tid, pid]
    ids = j.getAttribute('href').split('#p');
    
    if (!ids[1]) {
      continue;
    }
    
    if (ids[1] == tid) {
      j.textContent += ' (OP)';
    }
    
    if (!(target = document.getElementById('pi' + ids[1]))) {
      if (Main.tid && j.textContent.charAt(2) != '>' ) {
        j.textContent += ' →';
      }
      continue;
    }
    
    // Already processed?
    if (linklist[ids[1]]) {
      continue;
    }
    
    linklist[ids[1]] = true;
    
    // Backlink node
    bl = document.createElement('span');
    
    if (!Main.tid) {
      href = 'thread/' + tid + '#p' + pid;
    }
    else {
      href = '#p' + pid;
    }
    
    if (!Main.hasMobileLayout) {
      bl.innerHTML = '<a href="' + href + '" class="quotelink">&gt;&gt;' + pid + '</a> ';
    }
    else {
      bl.innerHTML = '<a href="' + href + '" class="quotelink">&gt;&gt;' + pid
        + '</a><a href="' + href + '" class="quoteLink"> #</a> ';
    }
    
    // Backlinks container
    if (!(el = document.getElementById('bl_' + ids[1]))) {
      el = document.createElement('div');
      el.id = 'bl_' + ids[1];
      el.className = 'backlink';
      
      if (Main.hasMobileLayout) {
        el.className = 'backlink mobile';
        target = document.getElementById('p' + ids[1]);
      }
      
      target.appendChild(el);
    }
    
    el.appendChild(bl);
  }
};

Parser.buildSummary = function(tid, oRep, oImg) {
  if (oRep) {
    oRep = oRep + ' post' + (oRep > 1 ? 's' : '');
  }
  else {
    return null;
  }
  
  if (oImg) {
    oImg = ' and ' + oImg + ' image repl' + (oImg > 1 ? 'ies' : 'y');
  }
  else {
    oImg = '';
  }
  
  el = document.createElement('span');
  el.className = 'summary desktop';
  el.innerHTML = oRep + oImg
    + ' omitted. <a href="thread/'
    + tid + '" class="replylink">Click here</a> to view.';
  
  return el;
};

/**
 * Sync
 */
var UserSync = {
  url: 'https://sys.4chan.org/sync',
  timeout: null,
  processing: false,
  maxDelay: 3600000,
  queue: {}
};

UserSync.onEnable = function() {
  var tkn = Math.random().toString(16).substring(2)
    + Math.random().toString(16).substring(2);
  
  Main.setCookie('sync', tkn, '4chan.org');
};

UserSync.onDisable = function() {
  Main.removeCookie('sync', '4chan.org');
  localStorage.removeItem('4chan-sync-ts');
};

UserSync.onSyncNowClick = function() {
  UserSync.syncStatus(true);
};

UserSync.purgeSync = function() {
  var xhr, tkn;
  
  tkn = Main.getCookie('sync');
  
  if (!tkn) {
    alert("Syncing doesn't seem to be enabled on this machine");
    return;
  }
  
  if (!confirm('All data associated with this sync key will be deleted from the server.')) {
    return;
  }
  
  xhr = new XMLHttpRequest();
  xhr.open('POST', UserSync.url + '?action=purge');
  xhr.onload = UserSync.onPurgeSyncLoaded;
  xhr.onerror = UserSync.onSyncError;
  xhr.withCredentials = true;
  xhr.withFeedback = true;
  
  //Feedback.notify('Processing…', false);
  
  xhr.send(JSON.stringify({tkn: tkn}));
};

UserSync.onPurgeSyncLoaded = function() {
  var resp = JSON.parse(this.responseText);
  
  if (resp.error) {
    return Feedback.error(resp.error);
  }
  
  //Feedback.notify('Done');
  
  UserSync.onDisable();
};

UserSync.syncStatus = function(withFeedback) {
  var xhr;
  
  if (UserSync.processing) {
    console.log('Sync: Already syncing');
    return;
  }
  
  UserSync.processing = true;
  
  if (withFeedback) {
    //Feedback.notify('Syncing…', false);
  }
  
  xhr = new XMLHttpRequest();
  xhr.open('GET', UserSync.url + '?action=status');
  xhr.withCredentials = true;
  xhr.withFeedback = withFeedback;
  xhr.onerror = UserSync.onSyncError;
  xhr.onload = UserSync.onSyncStatusLoaded;
  xhr.send(null);
};

UserSync.onSyncStatusLoaded = function() {
  var i, key, item, items, get, set, req, xhr, local_ts, remote_ts, data, syncTs, tkn;
  
  UserSync.processing = false;
  
  items = JSON.parse(this.responseText);
  
  if (items.error) {
    console.log('Sync: ' + items.error);
    return;
  }
  
  syncTs = UserSync.getSyncTs();
  syncTs.ts = Date.now();
  
  get = [];
  set = {};
  
  for (key in items) {
    local_ts = syncTs[key] || 0;
    remote_ts = items[key] || 0;
    
    if (remote_ts > local_ts) {
      get.push(key);
    }
    else if (local_ts > remote_ts) {
      data = localStorage.getItem(key);
      
      if (data) {
        set[key] = {
          ts: local_ts,
          data: JSON.parse(data)
        };
      }
      else {
        delete syncTs[key];
      }
    }
  }
  
  UserSync.setSyncTs(syncTs);
  
  req = {};
  
  if (get.length) {
    req['get'] = get;
  }
  
  for (i in set) {
    req['set'] = set;
    break;
  }
  
  if (!req['get'] && !req['set']) {
    if (this.withFeedback) {
      //Feedback.notify('Done');
    }
    if (Config.threadWatcher) {
      ThreadWatcher.onUserSyncLoaded();
    }
    return;
  }
  
  tkn = Main.getCookie('sync');
  
  if (!tkn) {
    return;
  }
  
  req.tkn = tkn;
  
  xhr = new XMLHttpRequest();
  xhr.open('POST', UserSync.url + '?action=sync');
  xhr.withCredentials = true;
  xhr.withFeedback = this.withFeedback;
  xhr.onload = UserSync.onSyncLoaded;
  xhr.onerror = UserSync.onSyncError;
  xhr.send(JSON.stringify(req));
};

UserSync.onSyncError = function() {
  var msg = 'Sync: Connection Error';
  
  UserSync.processing = false;
  
  UserSync.resetSyncTs();
  
  console.log(msg);
};

UserSync.getSyncTs = function() {
  var data = localStorage.getItem('4chan-sync-ts');
  
  return data ? JSON.parse(data) : {};
};

UserSync.setSyncTs = function(data) {
  return localStorage.setItem('4chan-sync-ts', JSON.stringify(data));
};

UserSync.resetSyncTs = function() {
  var tsData = UserSync.getSyncTs();
  delete tsData.ts;
  UserSync.setSyncTs(tsData);
};

UserSync.onSyncLoaded = function() {
  var items, key, value, local_ts, syncTs;
  
  items = JSON.parse(this.responseText);
  
  if (items.error) {
    console.log('Sync: ' + items.error);
    return;
  }
  
  if (this.withFeedback) {
    //Feedback.notify('Done');
  }
  
  syncTs = UserSync.getSyncTs();
  syncTs.ts = Date.now();
  
  for (key in items) {
    value = items[key];
    
    local_ts = syncTs[key] || 0;
    
    if (+local_ts > +value['ts']) {
      continue;
    }
    
    localStorage.setItem(key, JSON.stringify(value['data']));
    
    syncTs[key] = value['ts'];
  }
  
  UserSync.setSyncTs(syncTs);
  
  if (Config.threadWatcher) {
    ThreadWatcher.onUserSyncLoaded();
  }
};
  
UserSync.onQueueProcessed = function() {
  var items;
  
  items = JSON.parse(this.responseText);
  
  if (items.error) {
    console.log('Sync: ' + items.error);
  }
}
  
UserSync.syncPush = function(key) {
  var ts, tsData;
  
  ts = Math.round(Date.now() / 1000);
  
  tsData = UserSync.getSyncTs();
  tsData[key] = ts;
  UserSync.setSyncTs(tsData);
  
  UserSync.queue[key] = ts;
  
  if (UserSync.timeout) {
    clearTimeout(UserSync.timeout);
  }
  
  UserSync.timeout = setTimeout(UserSync.syncProcessQueue, 1000);
};
  
UserSync.syncProcessQueue = function() {
  var set, xhr, key, tkn;
  
  tkn = Main.getCookie('sync');
  
  if (!tkn) {
    UserSync.queue = {};
    return;
  }
  
  set = {};
  
  for (key in UserSync.queue) {
    set[key] = {
      ts: UserSync.queue[key],
      data: JSON.parse(localStorage.getItem(key))
    }
  }
  
  UserSync.queue = {};
  
  xhr = new XMLHttpRequest();
  xhr.open('POST', UserSync.url + '?action=sync');
  xhr.withCredentials = true;
  xhr.onload = UserSync.onQueueProcessed;
  xhr.onerror = UserSync.onSyncError;
  xhr.send(JSON.stringify({
    tkn: tkn,
    set: set
  }));
};


/**
 * Post Menu
 */
var PostMenu = {
  activeBtn: null
};

PostMenu.open = function(btn) {
  var div, html, pid, board, btnPos, txt, el, href, left, limit, isOP;
  
  PostMenu.close();
  
  pid = btn.parentNode.id.split('pi')[1];
  
  board = btn.parentNode.getAttribute('data-board');
  
  isOP = !board && !!$.id('t' + pid);
  
  html = '<ul><li data-cmd="report" data-id="' + pid
    + (board ? ('" data-board="' + board + '"') : '"')
    + '">Report post</li>';
  
  if (isOP) {
    if (!Main.tid) {
      html += '<li data-cmd="hide" data-id="' + pid + '">'
        + ($.hasClass($.id('t' + pid), 'post-hidden') ? 'Unhide' : 'Hide')
        + ' thread</li>';
    }
    if (Config.threadWatcher) {
      html += '<li data-cmd="watch" data-id="' + pid + '">'
        + (ThreadWatcher.watched[pid + '-' + Main.board] ? 'Remove from' : 'Add to')
        + ' watch list</li>';
    }
  }
  else if (el = $.id('pc' + pid)) {
    html += '<li data-cmd="hide-r" data-id="' + pid + '">'
      + ($.hasClass(el, 'post-hidden') ? 'Unhide' : 'Hide')
      + ' post</li>';
  }
  
  if (file = $.id('fT' + pid)) {
    el = $.cls('fileThumb', file.parentNode)[0];
    
    if (el) {
      if (/\.(png|jpg)$/.test(el.href)) {
        href = el.href;
      }
      else {
        href = 'http://0.t.4cdn.org/' + Main.board + '/'
          + el.href.match(/\/([0-9]+)\..+$/)[1] + 's.jpg';
      }
      
      html += '<li><ul>'
        + '<li><a href="//www.google.com/searchbyimage?image_url=' + href
        + '" target="_blank">Google</a></li>'
        + '<li><a href="http://iqdb.org/?url='
        + href + '" target="_blank">iqdb</a></li></ul>Image search &raquo</li>';
    }
  }
  
  if (Config.filter) {
    html += '<li><a href="#" data-cmd="filter-sel">Filter selected text</a></li>';
  }
  
  div = document.createElement('div');
  div.id = 'post-menu';
  div.className = 'dd-menu';
  div.innerHTML = html + '</ul>';
  
  btnPos = btn.getBoundingClientRect();
  
  div.style.top = btnPos.bottom + 3 + window.pageYOffset + 'px';
  
  document.addEventListener('click', PostMenu.close, false);
  
  $.addClass(btn, 'menuOpen');
  PostMenu.activeBtn = btn;
  
  UA.dispatchEvent('4chanPostMenuReady', { postId: pid, isOP: isOP, node: div.firstElementChild });
  
  document.body.appendChild(div);
  
  left = btnPos.left + window.pageXOffset;
  limit = $.docEl.clientWidth - div.offsetWidth;
  
  if (left > (limit - 75)) {
    div.className += ' dd-menu-left';
  }
  
  if (left > limit) {
    left = limit;
  }
  
  div.style.left = left + 'px';
};

PostMenu.close = function() {
  var el;
  
  if (el = $.id('post-menu')) {
    el.parentNode.removeChild(el);
    document.removeEventListener('click', PostMenu.close, false);
    $.removeClass(PostMenu.activeBtn, 'menuOpen');
    PostMenu.activeBtn = null;
  }
};

/**
 * Depager
 */
var Depager = {};

Depager.init = function() {
  var el, el2, cnt;
  
  this.isLoading = false;
  this.isEnabled = false;
  this.isComplete = false;
  this.threadsLoaded = false;
  this.threadQueue = [];
  this.debounce = 100;
  this.threshold = 350;
  
  this.adId = 'azk53379';
  this.adZones = [ 16258, 16260 ];
  
  this.boardHasAds = !!$.id(this.adId);
  
  if (this.boardHasAds) {
    el = $.cls('ad-plea');
    this.adPlea = el[el.length - 1];
  }
  
  if (el = $.cls('prev')[0]) {
    el.innerHTML = '[<a title="Toggle infinite scroll" '
      + 'class="depagelink" href="" data-cmd="depage">All</a>]';
    el = el.firstElementChild;
  }
  else {
    return;
  }
  
  if (Config.alwaysDepage) {
    this.isEnabled = true;
    el.parentNode.parentNode.className += ' depagerEnabled';
    Depager.bindHandlers();
    
    if (cnt = $.cls('board')[0]) {
      el2 = document.createElement('span');
      el2.className = 'depageNumber';
      el2.textContent = 'Page 1';
      cnt.insertBefore(el2, cnt.firstElementChild);
    }
  }
  else {
    el.setAttribute('data-cmd', 'depage');
  }
};

Depager.onScroll = function() {
  if (document.documentElement.scrollHeight
      <= (window.innerHeight + window.pageYOffset + Depager.threshold)) {
    if (Depager.threadsLoaded) {
      Depager.renderNext();
    }
    else {
      Depager.depage();
    }
  }
};

Depager.trackPageview = function(pageId) {
  var url;
  
  try {
    if (window._gat) {
      url = '/' + Main.board + '/' + pageId;
      window._gat._getTrackerByName()._trackPageview(url);
    }
    
    if (window.__qc) {
      window.__qc.qpixelsent = [];
      window._qevents.push({ qacct: window.__qc.qopts.qacct });
      window.__qc.firepixels();
    }
  }
  catch(e) {
    console.log(e);
  }
};

Depager.insertAd = function(pageId, frag, zone, isLastPage) {
  var wrap, cnt, nodes;
  
  if (!Depager.boardHasAds || !window.ados_add_placement) {
    return;
  }
  
  if (isLastPage) {
    nodes = $.cls('bottomad');
    wrap = nodes[nodes.length - 1];
    cnt = document.createElement('div');
    cnt.id = 'azkDepage' + pageId;
    wrap.appendChild(cnt);
    window.ados_add_placement(3536, 18130, cnt.id, 4).setZone(zone);
  }
  else {
    wrap = document.createElement('div');
    wrap.className = 'bottomad center';
    
    if (pageId == 2) {
      cnt = $.id(Depager.adId);
    }
    else {
      cnt = document.createElement('div');
      cnt.id = 'azkDepage' + pageId;
    }
    
    wrap.appendChild(cnt);
    frag.appendChild(wrap);
    
    if (Depager.adPlea) {
      frag.appendChild(Depager.adPlea.cloneNode(true));
    }
    
    frag.appendChild(document.createElement('hr'));
    
    if (pageId != 2) {
      window.ados_add_placement(3536, 18130, cnt.id, 4).setZone(zone);
    }
  }
};

Depager.loadAds = function() {
  if (!Depager.boardHasAds || !window.ados_load) {
    return;
  }
  
  window.ados_load();
};

Depager.renderNext = function() {
  var el, frag, i, j, k, threads, op, summary, cnt, reply, parseList, scroll,
    lastReplies, pageId, data, isLastPage, html;
  
  parseList = [];
    
  scroll = window.pageYOffset;
  
  frag = document.createDocumentFragment();
  
  data = Depager.threadQueue.shift();
  
  if (!data) {
    return;
  }
  
  threads = data.threads;
  pageId = data.page;
  
  isLastPage = !Depager.threadQueue.length;
  
  Depager.insertAd(pageId, frag, data.adZone, isLastPage);
  
  el = document.createElement('span');
  el.className = 'depageNumber';
  el.textContent = 'Page ' + pageId;
  frag.appendChild(el);
  
  for (j = 0; op = threads[j]; ++j) {
    if ($.id('t' + op.no)) {
      continue;
    }
    
    cnt = document.createElement('div');
    cnt.id = 't' + op.no;
    cnt.className = 'thread';
    
    cnt.appendChild(Parser.buildHTMLFromJSON(op, Main.board, true));
    
    if (summary = Parser.buildSummary(op.no, op.omitted_posts, op.omitted_images)) {
      cnt.appendChild(summary);
    }
    
    if (op.replies) {
      last_replies = op.last_replies;
      
      for (k = 0; reply = last_replies[k]; ++k) {
        cnt.appendChild(Parser.buildHTMLFromJSON(reply, Main.board));
      }
    }
    
    frag.appendChild(cnt);
    
    frag.appendChild(document.createElement('hr'));
    
    parseList.push(op.no);
  }
  
  if (isLastPage) {
    Depager.unbindHandlers();
    Depager.isComplete = true;
    Depager.setStatus('disabled');
  }
  
  boardDiv = $.cls('board')[0];
  boardDiv.insertBefore(frag, boardDiv.lastElementChild);
  
  Depager.trackPageview(pageId);
  
  Depager.loadAds();
  
  for (i = 0; op = parseList[i]; ++i) {
    Parser.parseThread(op);
  }
  
  window.scrollTo(0, scroll);
};

Depager.bindHandlers = function() {
  window.addEventListener('scroll', Depager.onScroll, false);
  window.addEventListener('resize', Depager.onScroll, false);
};

Depager.unbindHandlers = function() {
  window.removeEventListener('scroll', Depager.onScroll, false);
  window.removeEventListener('resize', Depager.onScroll, false);
};

Depager.setStatus = function(type) {
  var i, el, links, p;
  
  links = $.cls('depagelink');
  
  if (!links.length) {
    return;
  }
  
  if (type == 'enabled') {
    for (i = 0; el = links[i]; ++i) {
      el.textContent = 'All';
      p = el.parentNode.parentNode;
      if (!$.hasClass(p, 'depagerEnabled')) {
        $.addClass(p,'depagerEnabled');
      }
    }
  }
  else if (type == 'loading') {
    for (i = 0; el = links[i]; ++i) {
      el.textContent = 'Loading…';
    }
  }
  else if (type == 'disabled') {
    for (i = 0; el = links[i]; ++i) {
      el.textContent = 'All';
      $.removeClass(el.parentNode.parentNode,'depagerEnabled');
    }
  }
  else if (type == 'error') {
    for (i = 0; el = links[i]; ++i) {
      el.textContent = 'Error';
      el.removeAttribute('title');
      el.removeAttribute('data-cmd');
      $.removeClass(el.parentNode.parentNode, 'depagerEnabled');
    }
  }
};

Depager.toggle = function() {
  if (Depager.isLoading || Depager.isComplete) {
    return;
  }
  
  if (Depager.isEnabled) {
    Depager.disable();
  }
  else {
    Depager.enable();
  }
  
  Depager.isEnabled = !Depager.isEnabled;
};

Depager.enable = function() {
  Depager.bindHandlers();
  Depager.setStatus('enabled');
  Depager.onScroll();
};

Depager.disable = function() {
  Depager.unbindHandlers();
  Depager.setStatus('disabled');
};

Depager.depage = function() {
  if (Depager.isLoading) {
    return;
  }
  
  Depager.isLoading = true;
  
  $.get('//a.4cdn.org/' + Main.board + '/catalog.json', {
    onload: Depager.onLoad,
    onerror: Depager.onError
  });
  
  Depager.setStatus('loading');
};

Depager.onLoad = function() {
  var catalog, i, page, queue, adZone;
  
  Depager.isLoading = false;
  Depager.threadsLoaded = true;
  
  if (this.status == 200) {
    Depager.setStatus('enabled');
    
    if (!Config.alwaysDepage) {
      Depager.bindHandlers();
    }
    
    catalog = Parser.parseCatalogJSON(this.responseText);
    
    queue = Depager.threadQueue;
    
    adZone = 0;
    for (i = 1; page = catalog[i]; ++i) {
      page.adZone = adZone;
      queue.push(page);
      adZone = adZone ? 0 : 1;
    }
    
    Depager.renderNext();
  }
  else if (this.status == 404) {
    Depager.unbindHandlers();
    Depager.setStatus('error');
  }
  else {
    Depager.unbindHandlers();
    console.log('Error: ' + this.status);
    Depager.setStatus('error');
  }
};

Depager.onError = function() {
  Depager.isLoading = false;
  Depager.unbindHandlers();
  console.log('Error: ' + this.status);
  Depager.setStatus('error');
};

/**
 * Quote inlining
 */
var QuoteInline = {};

QuoteInline.isSelfQuote = function(node, pid, board) {
  var cnt;
  
  if (board && board != Main.board) {
    return false;
  }
  
  node = node.parentNode;
  
  if ((node.nodeName == 'BLOCKQUOTE' && node.id.split('m')[1] == pid)
      || node.parentNode.id.split('_')[1] == pid) {
    return true;
  }
  
  return false;
};

QuoteInline.toggle = function(link, e) {
  var t, pfx, src, el, count;
  
  t = link.getAttribute('href').match(/^(?:\/([^\/]+)\/)?(?:thread\/)?([0-9]+)?#p([0-9]+)$/);
  
  if (!t || t[1] == 'rs' || QuoteInline.isSelfQuote(link, t[3], t[1])) {
    return;
  }
  
  e && e.preventDefault();
  
  if (pfx = link.getAttribute('data-pfx')) {
    link.removeAttribute('data-pfx');
    $.removeClass(link, 'linkfade');
    
    el = $.id(pfx + 'p' + t[3]);
    el.parentNode.removeChild(el);
    
    if (link.parentNode.parentNode.className == 'backlink') {
      el = $.id('pc' + t[3]);
      count = +el.getAttribute('data-inline-count') - 1;
      if (count == 0) {
        el.style.display = '';
        el.removeAttribute('data-inline-count');
      }
      else {
        el.setAttribute('data-inline-count', count);
      }
    }
    
    return;
  }
  
  if (src = $.id('p' + t[3])) {
    QuoteInline.inline(link, src, t[3]);
  }
  else {
    QuoteInline.inlineRemote(link, t[1] || Main.board, t[2], t[3]);
  }
};

QuoteInline.inlineRemote = function(link, board, tid, pid) {
  var xhr, onload, onerror, cached, key, el, dummy;
  
  if (link.hasAttribute('data-loading')) {
    return;
  }
  
  key = board + '-' + tid;
  
  if ((cached = $.cache[key]) && (el = Parser.buildPost(cached, board, pid))) {
    Parser.parsePost(el);
    QuoteInline.inline(link, el);
    return;
  }
  
  if ((dummy = link.nextElementSibling) && $.hasClass(dummy, 'spinner')) {
    dummy.parentNode.removeChild(dummy);
    return;
  }
  else {
    dummy = document.createElement('div');
  }
  
  dummy.className = 'preview spinner inlined';
  dummy.textContent = 'Loading...';
  link.parentNode.insertBefore(dummy, link.nextSibling);
  
  onload = function() {
    var el, thread;
    
    link.removeAttribute('data-loading');
    
    if (this.status == 200 || this.status == 304 || this.status == 0) {
      thread = Parser.parseThreadJSON(this.responseText);
      
      $.cache[key] = thread;
      
      if (el = Parser.buildPost(thread, board, pid)) {
        dummy.parentNode && dummy.parentNode.removeChild(dummy);
        Parser.parsePost(el);
        QuoteInline.inline(link, el);
      }
      else {
        $.addClass(link, 'deadlink');
        dummy.textContent = 'This post doesn\'t exist anymore';
      }
    }
    else if (this.status == 404) {
      $.addClass(link, 'deadlink');
      dummy.textContent = 'This thread doesn\'t exist anymore';
    }
    else {
      this.onerror();
    }
  };
  
  onerror = function() {
    dummy.textContent = 'Error: ' + this.statusText + ' (' + this.status + ')';
    link.removeAttribute('data-loading');
  };
  
  link.setAttribute('data-loading', '1');
  
  $.get('//a.4cdn.org/' + board + '/thread/' + tid + '.json',
    {
      onload: onload,
      onerror: onerror
    }
  );
};

QuoteInline.inline = function(link, src, id) {
  var i, j, now, el, blcnt, isBl, inner, tblcnt, pfx, dest, count, cnt;
  
  now = Date.now();
  
  if (id) {
    if ((blcnt = link.parentNode.parentNode).className == 'backlink') {
      el = blcnt.parentNode.parentNode.parentNode;
      isBl = true;
    }
    else {
      el = blcnt.parentNode;
    }
    
    while (el.parentNode !== document) {
      if (el.id.split('m')[1] == id) {
        return;
      }
      el = el.parentNode;
    }
  }
  
  link.className += ' linkfade';
  link.setAttribute('data-pfx', now);
  
  el = src.cloneNode(true);
  el.id = now + el.id;
  el.setAttribute('data-pfx', now);
  el.className += ' preview inlined';
  $.removeClass(el, 'highlight');
  $.removeClass(el, 'highlight-anti');
  
  if ((inner = $.cls('inlined', el))[0]) {
    while (j = inner[0]) {
      j.parentNode.removeChild(j);
    }
    inner = $.cls('quotelink', el);
    for (i = 0; j = inner[i]; ++i) {
      j.removeAttribute('data-pfx');
      $.removeClass(j, 'linkfade');
    }
  }
  
  for (i = 0; j = el.children[i]; ++i) {
    j.id = now + j.id;
  }
  
  if (tblcnt = $.cls('backlink', el)[0]) {
    tblcnt.id = now + tblcnt.id;
  }
  
  if (isBl) {
    pfx = blcnt.parentNode.parentNode.getAttribute('data-pfx') || '';
    dest = $.id(pfx + 'm' + blcnt.id.split('_')[1]);
    dest.insertBefore(el, dest.firstChild);
    if (count = src.parentNode.getAttribute('data-inline-count')) {
      count = +count + 1;
    }
    else {
      count = 1;
      src.parentNode.style.display = 'none';
    }
    src.parentNode.setAttribute('data-inline-count', count);
  }
  else {
    if ($.hasClass(link.parentNode, 'quote')) {
      link = link.parentNode;
      cnt = link.parentNode;
    }
    else {
      cnt = link.parentNode;
    }
    cnt.insertBefore(el, link.nextSibling);
  }
};

/**
 * Quote preview
 */
var QuotePreview = {};

QuotePreview.init = function() {
  var thread;
  
  this.regex = /^(?:\/([^\/]+)\/)?(?:thread\/)?([0-9]+)?#p([0-9]+)$/;
  this.highlight = null;
  this.highlightAnti = null;
  this.out = true;
};

QuotePreview.resolve = function(link) {
  var self, t, post, ids, offset, pfx;
  
  self = QuotePreview;
  self.out = false;
  
  t = link.getAttribute('href').match(self.regex);
  
  if (!t) {
    return;
  }
  
  // Quoted post in scope
  pfx = link.getAttribute('data-pfx') || '';
  
  if (post = document.getElementById(pfx + 'p' + t[3])) {
    // Visible and not filtered out?
    offset = post.getBoundingClientRect();
    if (offset.top > 0
        && offset.bottom < document.documentElement.clientHeight
        && !$.hasClass(post.parentNode, 'post-hidden')) {
      if (!$.hasClass(post, 'highlight') && location.hash.slice(1) != post.id) {
        self.highlight = post;
        $.addClass(post, 'highlight');
      }
      else if (!$.hasClass(post, 'op')) {
        self.highlightAnti = post;
        $.addClass(post, 'highlight-anti');
      }
      return;
    }
    // Nope
    self.show(link, post);
  }
  // Quoted post out of scope
  else {
    if (!UA.hasCORS) {
      return;
    }
    self.showRemote(link, t[1] || Main.board, t[2], t[3]);
  }
};

QuotePreview.showRemote = function(link, board, tid, pid) {
  var xhr, onload, onerror, el, cached, key;
  
  key = board + '-' + tid;
  
  if ((cached = $.cache[key]) && (el = Parser.buildPost(cached, board, pid))) {
    QuotePreview.show(link, el);
    return;
  }
  
  link.style.cursor = 'wait';
  
  onload = function() {
    var el, thread;
    
    link.style.cursor = '';
    
    if (this.status == 200 || this.status == 304 || this.status == 0) {
      thread = Parser.parseThreadJSON(this.responseText);
      
      $.cache[key] = thread;
      
      if ($.id('quote-preview') || QuotePreview.out) {
        return;
      }
      
      if (el = Parser.buildPost(thread, board, pid)) {
        el.className = 'post preview';
        el.style.display = 'none';
        el.id = 'quote-preview';
        document.body.appendChild(el);
        QuotePreview.show(link, el, true);
      }
      else {
        $.addClass(link, 'deadlink');
      }
    }
    else if (this.status == 404) {
      $.addClass(link, 'deadlink');
    }
  };
  
  onerror = function() {
    link.style.cursor = '';
  };
  
  $.get('//a.4cdn.org/' + board + '/thread/' + tid + '.json',
    {
      onload: onload,
      onerror: onerror
    }
  );
};

QuotePreview.show = function(link, post, remote) {
  var rect, postHeight, postWidth, doc, docWidth, style, pos, quotes, i, j, qid,
    top, scrollTop, margin, img;
  
  if (remote) {
    Parser.parsePost(post);
    post.style.display = '';
  }
  else {
    post = post.cloneNode(true);
    if (location.hash && location.hash == ('#' + post.id)) {
      post.className += ' highlight';
    }
    post.id = 'quote-preview';
    post.className += ' preview';
    
    if (Config.imageExpansion && (img = $.cls('expanded-thumb', post)[0])) {
      ImageExpansion.contract(img);
    }
  }
  
  if (!link.parentNode.className) {
    quotes = post.querySelectorAll(
      '#' + $.cls('postMessage', post)[0].id + ' > .quotelink'
    );
    if (quotes[1]) {
      qid = '>>' + link.parentNode.parentNode.id.split('_')[1];
      for (i = 0; j = quotes[i]; ++i) {
        if (j.textContent == qid) {
          $.addClass(j, 'dotted');
          break;
        }
      }
    }
  }
  
  rect = link.getBoundingClientRect();
  doc = document.documentElement;
  docWidth = doc.offsetWidth;
  style = post.style;
  
  document.body.appendChild(post);
  
  if (Main.isMobileDevice) {
    style.top = rect.top + link.offsetHeight + window.pageYOffset + 'px';
    
    if ((docWidth - rect.right) < (0 | (docWidth * 0.3))) {
      style.right = docWidth - rect.right + 'px';
    }
    else {
      style.left = rect.left + 'px';
    }
  }
  else {
    if ((docWidth - rect.right) < (0 | (docWidth * 0.3))) {
      pos = docWidth - rect.left;
      style.right = pos + 5 + 'px';
    }
    else {
      pos = rect.left + rect.width;
      style.left = pos + 5 + 'px';
    }
    
    top = rect.top + link.offsetHeight + window.pageYOffset
      - post.offsetHeight / 2 - rect.height / 2;
    
    postHeight = post.getBoundingClientRect().height;
    
    if (doc.scrollTop != document.body.scrollTop) {
      scrollTop = doc.scrollTop + document.body.scrollTop;
    } else {
      scrollTop = document.body.scrollTop;
    }
    
    if (top < scrollTop) {
      style.top = scrollTop + 'px';
    }
    else if (top + postHeight > scrollTop + doc.clientHeight) {
      style.top = scrollTop + doc.clientHeight - postHeight + 'px';
    }
    else {
      style.top = top + 'px';
    }
  }
};

QuotePreview.remove = function(el) {
  var self, cnt;
  
  self = QuotePreview;
  self.out = true;
  
  if (self.highlight) {
    $.removeClass(self.highlight, 'highlight');
    self.highlight = null;
  }
  else if (self.highlightAnti) {
    $.removeClass(self.highlightAnti, 'highlight-anti');
    self.highlightAnti = null
  }
  
  if (el) {
    el.style.cursor = '';
  }
  
  if (cnt = $.id('quote-preview')) {
    document.body.removeChild(cnt);
  }
};

/**
 * Image expansion
 */
var ImageExpansion = {
  activeVideos: [],
  timeout: null
};

ImageExpansion.expand = function(thumb) {
  var img, el, href, ext;
  
  if (Config.imageHover) {
    ImageHover.hide();
  }
  
  href = thumb.parentNode.getAttribute('href');
  
  if (ext = href.match(/\.(?:webm|pdf)$/)) {
    if (!Main.hasMobileLayout && ext[0] == '.webm') {
      return ImageExpansion.expandWebm(thumb);
    }
    return false;
  }
  
  thumb.setAttribute('data-expanding', '1');
  
  img = document.createElement('img');
  img.alt = 'Image';
  img.setAttribute('src', href);
  img.className = 'expanded-thumb';
  img.style.display = 'none';
  img.onerror = this.onError;
  
  thumb.parentNode.insertBefore(img, thumb.nextElementSibling);
  
  if (UA.hasCORS) {
    thumb.style.opacity = '0.75';
    this.timeout = this.checkLoadStart(img, thumb);
  }
  else {
    this.onLoadStart(img, thumb);
  }
  
  return true;
};

ImageExpansion.contract = function(img) {
  var cnt, p;
  
  clearTimeout(this.timeout);
  
  p = img.parentNode;
  cnt = p.parentNode.parentNode;
  
  $.removeClass(p.parentNode, 'image-expanded');
  
  if (Config.centeredThreads) {
    $.removeClass(cnt.parentNode, 'centre-exp');
    cnt.parentNode.style.marginLeft = '';
  }
  
  if (!Main.tid && Config.threadHiding) {
    $.removeClass(p, 'image-expanded-anti');
  }
  
  p.firstChild.style.display = '';
  
  p.removeChild(img);
  
  if (cnt.offsetTop < window.pageYOffset) {
    cnt.scrollIntoView();
  }
};

ImageExpansion.toggle = function(t) {
  if (t.hasAttribute('data-md5')) {
    if (!t.hasAttribute('data-expanding')) {
      return ImageExpansion.expand(t);
    }
  }
  else {
    ImageExpansion.contract(t);
  }
  
  return true;
};

ImageExpansion.expandWebm = function(thumb) {
  var el, link, fileText, left, width, href, maxWidth, self;
  
  self = ImageExpansion;
  
  if (el = document.getElementById('image-hover')) {
    document.body.removeChild(el);
  }
  
  link = thumb.parentNode;
  
  href = link.getAttribute('href');
  
  left = link.getBoundingClientRect().left;
  maxWidth = document.documentElement.clientWidth - left - 25;
  
  el = document.createElement('video');
  el.muted = true;
  el.controls = true;
  el.loop = true;
  el.autoplay = true;
  el.className = 'expandedWebm';
  el.onloadedmetadata = ImageExpansion.fitWebm;
  el.onplay = ImageExpansion.onWebmPlay;
  el.src = href;
  
  link.style.display = 'none';
  link.parentNode.appendChild(el);
  
  fileText = thumb.parentNode.previousElementSibling;
  
  el = document.createElement('span');
  el.className = 'collapseWebm';
  el.innerHTML = '-[<a href="#">Close</a>]';
  el.firstElementChild.addEventListener('click', self.collapseWebm, false);
  
  fileText.appendChild(el);
  
  return true;
};

ImageExpansion.fitWebm = function() {
  var imgWidth, imgHeight, maxWidth, maxHeight, ratio, left, cntEl,
    centerWidth, ofs;
  
  if (Config.centeredThreads) {
    centerWidth = $.cls('opContainer')[0].offsetWidth;
    cntEl = this.parentNode.parentNode.parentNode;
    $.addClass(cntEl, 'centre-exp')
  }
  
  left = this.getBoundingClientRect().left;
  
  maxWidth = document.documentElement.clientWidth - left - 25;
  maxHeight = document.documentElement.clientHeight;
  
  imgWidth = this.videoWidth;
  imgHeight = this.videoHeight;
  
  if (imgWidth > maxWidth) {
    ratio = maxWidth / imgWidth;
    imgWidth = maxWidth;
    imgHeight = imgHeight * ratio;
  }
  
  if (Config.fitToScreenExpansion && imgHeight > maxHeight) {
    ratio = maxHeight / imgHeight;
    imgHeight = maxHeight;
    imgWidth = imgWidth * ratio;
  }
  
  this.style.maxWidth = imgWidth + 'px';
  this.style.maxHeight = imgHeight + 'px';
  
  if (Config.centeredThreads) {
    left = this.getBoundingClientRect().left;
    ofs = this.offsetWidth + left * 2;
    if (ofs > centerWidth) {
      left = Math.floor(($.docEl.clientWidth - ofs) / 2);
      
      if (left > 0) {
        cntEl.style.marginLeft = left + 'px';
      }
    }
    else {
      $.removeClass(cntEl, 'centre-exp')
    }
  }
};

ImageExpansion.onWebmPlay = function(e) {
  var self = ImageExpansion;
  
  if (!self.activeVideos.length) {
    document.addEventListener('scroll', self.onScroll, false);
  }
  
  self.activeVideos.push(this);
};

ImageExpansion.collapseWebm = function(e) {
  var cnt, el, el2;
  
  e.preventDefault();
  
  this.removeEventListener('click', ImageExpansion.collapseWebm, false);
  
  cnt = this.parentNode;
  el = cnt.parentNode.parentNode.getElementsByClassName('expandedWebm')[0];
  
  if (Config.centeredThreads) {
    el2 = el.parentNode.parentNode.parentNode;
    $.removeClass(el2, 'centre-exp')
    el2.style.marginLeft = '';
  }
  
  el.previousElementSibling.style.display = '';
  el.parentNode.removeChild(el);
  cnt.parentNode.removeChild(cnt);
};

ImageExpansion.onScroll = function(e) {
  clearTimeout(ImageExpansion.timeout);
  ImageExpansion.timeout = setTimeout(ImageExpansion.pauseVideos, 500);
};

ImageExpansion.pauseVideos = function() {
  var self, i, el, pos, min, max, nodes;
  
  self = ImageExpansion;
  
  nodes = [];
  min = window.pageYOffset;
  max = window.pageYOffset + $.docEl.clientHeight;
  
  for (i = 0; el = self.activeVideos[i]; ++i) {
    pos = el.getBoundingClientRect();
    if (pos.top + window.pageYOffset > max || pos.bottom + window.pageYOffset < min) {
      el.pause();
    }
    else if (!el.paused){
      nodes.push(el);
    }
  }
  
  if (!nodes.length) {
    document.removeEventListener('scroll', self.onScroll, false);
  }
  
  self.activeVideos = nodes;
};

ImageExpansion.onError = function(e) {
  var thumb, img;
  
  img = e.target;
  thumb = $.qs('img[data-expanding]', img.parentNode);
  
  img.parentNode.removeChild(img);
  thumb.style.opacity = '';
  thumb.removeAttribute('data-expanding');
};

ImageExpansion.onLoadStart = function(img, thumb) {
  var imgWidth, imgHeight, maxWidth, maxHeight, ratio, left, fileEl, cntEl,
    centerWidth, ofs;
  
  thumb.removeAttribute('data-expanding');
  
  fileEl = thumb.parentNode.parentNode;
  
  if (Config.centeredThreads) {
    cntEl = fileEl.parentNode.parentNode;
    centerWidth = $.cls('opContainer')[0].offsetWidth;
    $.addClass(cntEl, 'centre-exp');
  }
  
  left = thumb.getBoundingClientRect().left;
  
  maxWidth = $.docEl.clientWidth - left - 25;
  maxHeight = $.docEl.clientHeight;
  
  imgWidth = img.naturalWidth;
  imgHeight = img.naturalHeight;
  
  if (imgWidth > maxWidth) {
    ratio = maxWidth / imgWidth;
    imgWidth = maxWidth;
    imgHeight = imgHeight * ratio;
  }
  
  if (Config.fitToScreenExpansion && imgHeight > maxHeight) {
    ratio = maxHeight / imgHeight;
    imgHeight = maxHeight;
    imgWidth = imgWidth * ratio;
  }
  
  img.style.maxWidth = imgWidth + 'px';
  img.style.maxHeight = imgHeight + 'px';
  
  $.addClass(fileEl, 'image-expanded');
  
  if (!Main.tid && Config.threadHiding) {
    $.addClass(thumb.parentNode, 'image-expanded-anti');
  }
  
  img.style.display = '';
  thumb.style.display = 'none';
  
  if (Config.centeredThreads) {
    left = img.getBoundingClientRect().left;
    ofs = img.offsetWidth + left * 2;
    if (ofs > centerWidth) {
      left = Math.floor(($.docEl.clientWidth - ofs) / 2);
      
      if (left > 0) {
        cntEl.style.marginLeft = left + 'px';
      }
    }
    else {
      $.removeClass(cntEl, 'centre-exp');
    }
  }
};

ImageExpansion.checkLoadStart = function(img, thumb) {
  if (img.naturalWidth) {
    ImageExpansion.onLoadStart(img, thumb);
    thumb.style.opacity = '';
  }
  else {
    return setTimeout(ImageExpansion.checkLoadStart, 15, img, thumb);
  }
};

/**
 * Image hover
 */
var ImageHover = {};

ImageHover.show = function(thumb) {
  var el, href, ext;
  
  href = thumb.parentNode.getAttribute('href');
  
  if (ext = href.match(/\.(?:webm|pdf)$/)) {
    if (ext[0] == '.webm') {
       ImageHover.showWebm(thumb);
    }
    return;
  }
  
  el = document.createElement('img');
  el.id = 'image-hover';
  el.alt = 'Image';
  el.setAttribute('src', href);
  
  document.body.appendChild(el);
  
  if (UA.hasCORS) {
    el.style.display = 'none';
    this.timeout = ImageHover.checkLoadStart(el, thumb);
  }
  else {
    el.style.left = thumb.getBoundingClientRect().right + 10 + 'px';
  }
};

ImageHover.hide = function() {
  var img;
  clearTimeout(this.timeout);
  if (img = $.id('image-hover')) {
    if (img.play) {
      Tip.hide();
    }
    document.body.removeChild(img);
  }
};

ImageHover.showWebm = function(thumb) {
  var dims, el, bounds, limit, width;
  
  dims = thumb.parentNode.previousElementSibling.textContent.match(/, ([0-9]+)x[0-9]+/);
  width = +dims[1];
  
  el = document.createElement('video');
  el.id = 'image-hover';
  el.src = thumb.parentNode.getAttribute('href');
  el.loop = true;
  el.muted = true;
  el.autoplay = true;
  el.onloadedmetadata = function() { ImageHover.showWebMDuration(this, thumb); };
  
  bounds = thumb.getBoundingClientRect();
  limit = window.innerWidth - bounds.right - 20;
  
  if (width > limit) {
    el.style.maxWidth = limit + 'px';
  }
  
  document.body.appendChild(el);
};

ImageHover.showWebMDuration = function(el, thumb) {
  if (!el.parentNode) {
    return;
  }
  
  var ms = $.prettySeconds(el.duration);
  
  Tip.show(thumb, ms[0] + ':' + ('0' + ms[1]).slice(-2));
};

ImageHover.onLoadStart = function(img, thumb) {
  var bounds, limit;
  
  bounds = thumb.getBoundingClientRect();
  limit = window.innerWidth - bounds.right - 20;
  
  if (img.naturalWidth > limit) {
    img.style.maxWidth = limit + 'px';
  }
  
  img.style.display = '';
};

ImageHover.checkLoadStart = function(img, thumb) {
  if (img.naturalWidth) {
    ImageHover.onLoadStart(img, thumb);
  }
  else {
    return setTimeout(ImageHover.checkLoadStart, 15, img, thumb);
  }
};

/**
 * Quick reply
 */
var QR = {};

QR.init = function() {
  var item;
  
  if (!UA.hasFormData) {
    return;
  }
  
  this.enabled = true;
  this.currentTid = null;
  this.cooldown = null;
  this.timestamp = null;
  this.auto = false;
  
  this.btn = null;
  this.comField = null;
  this.comLength = window.comlen;
  this.lenCheckTimeout = null;
  
  this.preuploadSizeLimit = Main.hasMobileLayout ? 0 : 204800;
  
  this.cdElapsed = 0;
  this.activeDelay = 0;
  
  this.cooldowns = {};
  
  for (item in window.cooldowns) {
    this.cooldowns[item] = window.cooldowns[item] * 1000;
  }
  
  this.captchaDelay = 240500;
  this.captchaInterval = null;
  this.pulse = null;
  this.xhr = null;
  
  this.fileDisabled = !!window.imagelimit;
  
  this.tracked = {};
  
  this.lastTid = localStorage.getItem('4chan-cd-' + Main.board + '-tid');
  
  if (Main.tid && !Main.hasMobileLayout && !Main.threadClosed) {
    QR.addReplyLink();
  }
  
  window.addEventListener('storage', this.syncStorage, false);
};

QR.addReplyLink = function() {
  var cnt, el;
  
  cnt = $.cls('navLinks')[2];
  
  el = document.createElement('div');
  el.className = 'open-qr-wrap';
  el.innerHTML = '[<a href="#" class="open-qr-link" data-cmd="open-qr">Post a Reply</a>]';
  
  cnt.insertBefore(el, cnt.firstChild);
};

QR.lock = function() {
  QR.showPostError('This thread is closed.', 'closed', true);
};

QR.unlock = function() {
  QR.hidePostError('closed');
};

QR.syncStorage = function(e) {
  var key;
  
  if (!e.key) {
    return;
  }
  
  key = e.key.split('-');
  
  if (key[0] != '4chan') {
    return;
  }
  
  if (key[1] == 'cd' && e.newValue && Main.board == key[2]) {
    if (key[3] == 'tid') {
      QR.lastTid = e.newValue;
    }
    else {
      QR.startCooldown();
    }
  }
};

QR.quotePost = function(tid, pid) {
  if (!QR.noCooldown
      && (Main.threadClosed || (!Main.tid && Main.isThreadClosed(tid)))) {
    alert('This thread is closed');
    return;
  }
  QR.show(tid);
  QR.addQuote(pid);
};

QR.addQuote = function(pid) {
  var q, pos, sel, ta;
  
  ta = $.tag('textarea', document.forms.qrPost)[0];
  
  pos = ta.selectionStart;
  
  sel = UA.getSelection();
  
  if (pid) {
    q = '>>' + pid + '\n';
  }
  else {
    q = '';
  }
  
  if (sel) {
    q += '>' + sel.trim().replace(/[\r\n]+/g, '\n>') + '\n';
  }
  
  if (ta.value) {
    ta.value = ta.value.slice(0, pos)
      + q + ta.value.slice(ta.selectionEnd);
  }
  else {
    ta.value = q;
  }
  if (UA.isOpera) {
    pos += q.split('\n').length;
  }
  
  ta.selectionStart = ta.selectionEnd = pos + q.length;
  
  if (ta.selectionStart == ta.value.length) {
    ta.scrollTop = ta.scrollHeight;
  }
  ta.focus();
};

QR.show = function(tid) {
  var i, j, cnt, postForm, form, qrForm, fields, row, spoiler, file,
    el, el2, placeholder, cd, qrError, cookie;
  
  if (QR.currentTid) {
    if (!Main.tid && QR.currentTid != tid) {
      $.id('qrTid').textContent = $.id('qrResto').value = QR.currentTid = tid;
      $.byName('com')[1].value = '';
      
      QR.startCooldown();
    }
    
    if (Main.hasMobileLayout) {
      $.id('quickReply').style.top = window.pageYOffset + 25 + 'px';
    }
    
    return;
  }
  
  QR.currentTid = tid;
  
  postForm = $.id('postForm');
  
  cnt = document.createElement('div');
  cnt.id = 'quickReply';
  cnt.className = 'extPanel reply';
  cnt.setAttribute('data-trackpos', 'QR-position');
  
  if (Main.hasMobileLayout) {
    cnt.style.top = window.pageYOffset + 28 + 'px';
  }
  else if (Config['QR-position']) {
    cnt.style.cssText = Config['QR-position'];
  }
  else {
    cnt.style.right = '0px';
    cnt.style.top = '10%';
  }
  
  cnt.innerHTML =
    '<div id="qrHeader" class="drag postblock">Reply to Thread No.<span id="qrTid">'
    + tid + '</span><img alt="X" src="' + Main.icons.cross + '" id="qrClose" '
    + 'class="extButton" title="Close Window"></div>';
  
  form = postForm.parentNode.cloneNode(false);
  form.setAttribute('name', 'qrPost');
  form.innerHTML =
    '<input type="hidden" value="'
    + $.byName('MAX_FILE_SIZE')[0].value + '" name="MAX_FILE_SIZE">'
    + '<input type="hidden" value="regist" name="mode">'
    + '<input id="qrResto" type="hidden" value="' + tid + '" name="resto">';
  
  qrForm = document.createElement('div');
  qrForm.id = 'qrForm';
  
  fields = postForm.firstElementChild.children;
  for (i = 0, j = fields.length - 1; i < j; ++i) {
    row = document.createElement('div');
    if (fields[i].id == 'captchaFormPart') {
      if (QR.noCaptcha) {
        continue;
      }
      row.id = 'qrCaptchaContainer';
    }
    else {
      placeholder = fields[i].getAttribute('data-type');
      if (placeholder == 'Password' || placeholder == 'Spoilers') {
        continue;
      }
      else if (placeholder == 'File') {
        file = fields[i].children[1].firstChild.cloneNode(false);
        file.tabIndex += 20;
        file.id = 'qrFile';
        file.size = '19';
        file.addEventListener('change', QR.onFileChange, false);
        row.appendChild(file);
        
        if (UA.hasDragAndDrop) {
          $.addClass(file, 'qrRealFile');
          
          file = document.createElement('div');
          file.id = 'qrDummyFile';
          
          el = document.createElement('button');
          el.id = 'qrDummyFileButton';
          el.type = 'button';
          el.textContent = 'Browse…';
          file.appendChild(el);
          
          el = document.createElement('span');
          el.id = 'qrDummyFileLabel';
          el.textContent = 'No file selected.';
          file.appendChild(el);
          
          row.appendChild(file);
        }
        
        file.title = 'Shift + Click to remove the file';
      }
      else {
        row.innerHTML = fields[i].children[1].innerHTML;
        if (row.firstChild.type == 'hidden') {
          el = row.lastChild.previousSibling;
        }
        else {
          el = row.firstChild;
        }
        if (el.tabIndex > 0) {
          el.tabIndex += 20;
        }
        if (el.nodeName == 'INPUT' || el.nodeName == 'TEXTAREA') {
          if (el.name == 'name') {
            if (cookie = Main.getCookie('4chan_name')) {
              el.value = cookie;
            }
          }
          else if (el.name == 'email') {
            el.id = 'qrEmail';
          }
          else if (el.name == 'com') {
            QR.comField = el;
            el.addEventListener('keydown', QR.onKeyDown, false);
            el.addEventListener('paste', QR.onKeyDown, false);
            el.addEventListener('cut', QR.onKeyDown, false);
            if (row.children[1]) {
              row.removeChild(el.nextSibling);
            }
          }
          else if (el.name == 'sub') {
            continue;
          }
          if (placeholder !== null) {
            el.setAttribute('placeholder', placeholder);
          }
        }
        else if ((el.name == 'flag')) {
          if (el2 = el.querySelector('option[selected]')) {
            el2.removeAttribute('selected');
          }
          if ((cookie = Main.getCookie('4chan_flag')) &&
            (el2 = el.querySelector('option[value="' + cookie + '"]'))) {
            el2.setAttribute('selected', 'selected');
          }
        }
      }
    }
    qrForm.appendChild(row);
  }
  
  this.btn = qrForm.querySelector('input[type="submit"]');
  this.btn.previousSibling.className = 'presubmit';
  this.btn.tabIndex += 20;
  
  if (el = postForm.querySelector('.desktop > label > input[name="spoiler"]')) {
    spoiler = document.createElement('span');
    spoiler.id = 'qrSpoiler';
    spoiler.innerHTML = '<label>[<input type="checkbox" tabindex="'
      + (el.tabIndex + 20) + '" value="on" name="spoiler">Spoiler?]</label>';
    file.parentNode.insertBefore(spoiler, file.nextSibling);
  }
  
  form.appendChild(qrForm);
  cnt.appendChild(form);
  
  qrError = document.createElement('div');
  qrError.id = 'qrError';
  cnt.appendChild(qrError);
  
  cnt.addEventListener('click', QR.onClick, false);
  
  document.body.appendChild(cnt);
  
  QR.startCooldown();
  
  if (Main.threadClosed) {
    QR.lock();
  }
  
  if (!window.passEnabled) {
    if (window.captchaReady) {
      if (QR.captchaInterval === null) {
        QR.onCaptchaReady();
      }
      else {
        QR.reloadCaptcha();
      }
    }
    else {
      window.loadRecaptcha();
    }
  }
  
  if (!Main.hasMobileLayout) {
    Draggable.set($.id('qrHeader'));
  }
};

QR.onCaptchaReady = function() {
  if (!$.id('qrCaptchaContainer')) {
    QR.captchaInterval = 1;
    return;
  }
  
  QR.pollCaptcha();
};

QR.onFileChange = function(e) {
  var fsize, maxFilesize;
  
  QR.needPreuploadCaptcha = false;
  
  if (this.value) {
    maxFilesize = window.maxFilesize;
    
    if (this.files) {
      fsize = this.files[0].size;
      if (this.files[0].type == 'video/webm' && window.maxWebmFilesize) {
        maxFilesize = window.maxWebmFilesize;
      }
    }
    else {
      fsize = 0;
    }
    
    if (QR.fileDisabled) {
      QR.showPostError('Image limit reached.', 'imagelimit', true);
    }
    else if (fsize > maxFilesize) {
      QR.showPostError('Error: Maximum file size allowed is '
        + Math.floor(maxFilesize / 1048576) + ' MB', 'filesize', true);
    }
    else {
      QR.hidePostError();
    }
    
    if (fsize >= QR.preuploadSizeLimit) {
      QR.needPreuploadCaptcha = true;
    }
  }
  else {
    QR.hidePostError();
  }
  
  QR.startCooldown();
};

QR.onKeyDown = function(e) {
  if (e.ctrlKey && e.keyCode == 83) {
    var ta, start, end, spoiler;
    
    e.stopPropagation();
    e.preventDefault();
    
    ta = e.target;
    start = ta.selectionStart;
    end = ta.selectionEnd;
  
    if (ta.value) {
      spoiler = '[spoiler]' + ta.value.slice(start, end) + '[/spoiler]';
      ta.value = ta.value.slice(0, start) + spoiler + ta.value.slice(end);
      ta.setSelectionRange(end + 19, end + 19);
    }
    else {
      ta.value = '[spoiler][/spoiler]';
      ta.setSelectionRange(9, 9);
    }
  }
  else if (e.keyCode == 27 && !e.ctrlKey && !e.altKey && !e.shiftKey && !e.metaKey) {
    QR.close();
    return;
  }
  
  clearTimeout(QR.lenCheckTimeout);
  QR.lenCheckTimeout = setTimeout(QR.checkComLength, 500);
};

QR.checkComLength = function() {
  var byteLength, qrError;
  
  if (QR.comLength) {
    byteLength = encodeURIComponent(QR.comField.value).split(/%..|./).length - 1;
    
    if (byteLength > QR.comLength) {
      QR.showPostError('Error: Comment too long ('
        + byteLength + '/' + QR.comLength + ').', 'length', true);
    }
    else {
      QR.hidePostError('length');
    }
  }
};

QR.close = function() {
  var el, cnt = $.id('quickReply');
  
  QR.comField = null;
  QR.currentTid = null;
  
  clearInterval(QR.captchaInterval);
  clearInterval(QR.pulse);
  
  if (QR.xhr) {
    QR.xhr.abort();
    QR.xhr = null;
  }
  
  cnt.removeEventListener('click', QR.onClick, false);
  
  (el = $.id('qrFile')) && el.removeEventListener('change', QR.startCooldown, false);
  (el = $.id('qrEmail')) && el.removeEventListener('change', QR.startCooldown, false);
  $.tag('textarea', cnt)[0].removeEventListener('keydown', QR.onKeyDown, false);
  
  Draggable.unset($.id('qrHeader'));
  
  if (window.RecaptchaState) {
    Recaptcha.destroy();
    window.captchaReady = false;
    if (el = $.id('captchaContainer')) {
      el.innerHTML = '<div class="placeholder">'
        + el.getAttribute('data-placeholder') + '</div>';
    }
  }
  
  document.body.removeChild(cnt);
};

QR.cloneCaptcha = function() {
  var row = $.id('qrCaptchaContainer');
  
  if (!row) {
    return false;
  }
  
  row.innerHTML = '<img id="qrCaptcha" title="Reload" width="300" height="57" src="'
    + $.id('recaptcha_challenge_image').src + '" alt="reCAPTCHA challenge image">'
    + (window.preupload_captcha ? '<input id="qrCapToken" type="hidden" name="captcha_token" disabled>' : '')
    + '<input id="qrCapField" tabindex="25" name="recaptcha_response_field" '
    + 'placeholder="Type the text (Required)" '
    + 'type="text" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">'
    + '<input id="qrChallenge" name="recaptcha_challenge_field" type="hidden" value="'
    + $.id('recaptcha_challenge_field').value + '">';
  
  return true;
};

QR.reloadCaptcha = function(focus) {
  var pulse, poll;
  
  if (QR.noCaptcha || !$.id('recaptcha_image') || !window.RecaptchaState) {
    return;
  }
  
  poll = function() {
    var el;
    clearTimeout(pulse);
    if (el = $.id('recaptcha_challenge_image')) {
      QR.captchaInterval = setInterval(QR.cloneCaptcha, QR.captchaDelay);
      QR.cloneCaptcha();
      if (focus) {
        $.id('qrCapField').focus();
      }
    }
    else {
      pulse = setTimeout(poll, 100);
    }
  };
  clearInterval(QR.captchaInterval);
  Recaptcha.destroy();
  window.loadRecaptcha();
  pulse = setTimeout(poll, 100);
};

QR.pollCaptcha = function() {
  clearTimeout(QR.captchaPollTimeout);
  
  if ($.id('recaptcha_challenge_image')) {
    QR.captchaInterval = setInterval(QR.cloneCaptcha, QR.captchaDelay);
    QR.cloneCaptcha();
  }
  else {
    QR.captchaPollTimeout = setTimeout(QR.pollCaptcha, 100);
  }
};

QR.onClick = function(e) {
  var t = e.target;
  
  if (t.type == 'submit') {
    e.preventDefault();
    QR.submit(e.shiftKey);
  }
  else {
    switch (t.id) {
      case 'qrFile':
        if (e.shiftKey) {
          e.preventDefault();
          QR.resetFile();
        }
        break;
      case 'qrDummyFile':
      case 'qrDummyFileButton':
      case 'qrDummyFileLabel':
        e.preventDefault();
        if (e.shiftKey) {
          QR.resetFile();
        }
        else {
          $.id('qrFile').click();
        }
        break;
      case 'qrCaptcha':
        QR.reloadCaptcha(true);
        break;
      case 'qrClose':
        QR.close();
        break;
    }    
  }
};

QR.submit = function(force) {
  if (force) {
    QR.submitDirect(true);
  }
  else if (!QR.noCaptcha && window.preupload_captcha && QR.needPreuploadCaptcha) {
    QR.submitPreupload();
  }
  else {
    QR.submitDirect();
  }
};

QR.showPostError = function(msg, type, silent) {
  var qrError;
  
  qrError = $.id('qrError');
  
  if (!qrError) {
    return;
  }
  
  qrError.innerHTML = msg;
  qrError.style.display = 'block';
  
  qrError.setAttribute('data-type', type || '');
  
  if (!silent && (document.hidden
    || document.mozHidden
    || document.webkitHidden
    || document.msHidden)) {
    alert('Posting Error');
  }
};

QR.hidePostError = function(type) {
  var el = $.id('qrError');
  
  if (!el.hasAttribute('style')) {
    return;
  }
  
  if (!type || el.getAttribute('data-type') == type) {
    el.removeAttribute('style');
  }
};

QR.resetFile = function() {
  var file, el;
  
  el = document.createElement('input');
  el.id = 'qrFile';
  el.type = 'file';
  el.size = '19';
  el.name = 'upfile';
  el.addEventListener('change', QR.onFileChange, false);
  
  file = $.id('qrFile');
  file.removeEventListener('change', QR.onFileChange, false);
  
  file.parentNode.replaceChild(el, file);
  
  QR.hidePostError('imagelimit');
  
  QR.needPreuploadCaptcha = false;
  
  QR.startCooldown();
};

QR.submitPreupload = function() {
  var token, challenge, response, data;
  
  if (!QR.presubmitChecks()) {
    return;
  }
  
  challenge = $.id('qrChallenge');
  response = $.id('qrCapField');
  
  if (response.value == '') {
    QR.showPostError('You forgot to type in the CAPTCHA.');
    response.focus();
    return;
  }
  
  data = new FormData();
  data.append('mode', 'checkcaptcha');
  data.append('challenge', challenge.value);
  data.append('response', response.value);
  
  QR.xhr = new XMLHttpRequest();
  
  QR.xhr.open('POST', document.forms.post.action, true);
  
  QR.xhr.onerror = function() {
    QR.xhr = null;
    QR.submitDirect();
  };
  
  QR.xhr.onload = function() {
    var el, resp;
    
    QR.xhr = null;
    
    try {
      resp = JSON.parse(this.responseText);
    }
    catch(e) {
      console.log("Couldn't verify captcha.");
      QR.submitDirect();
      return;
    }
    
    if (resp.token) {
      el = $.id('qrCapToken');
      el.value = resp.token;
      el.removeAttribute('disabled');
      
      QR.submitDirect();
    }
    else if (resp.error) {
      QR.reloadCaptcha();
      QR.btn.value = 'Post';
      QR.showPostError(resp.error);
    }
    else {
      if (resp.fail) {
        console.log(resp.fail);
      }
      QR.submitDirect();
    }
  };
  
  token = $.id('qrCapToken');
  token.value = '';
  token.setAttribute('disabled', '1');
  
  QR.btn.value = 'Sending';
  
  QR.xhr.send(data);
};

QR.submitDirect = function(force) {
  var field, formdata, file;
  
  QR.hidePostError();
  
  if (!QR.presubmitChecks(force)) {
    return;
  }
  
  QR.auto = false;
  
  if (!force && (field = $.id('qrCapField')) && field.value == '') {
    QR.showPostError('You forgot to type in the CAPTCHA.');
    field.focus();
    return;
  }
  
  QR.xhr = new XMLHttpRequest();
  
  QR.xhr.open('POST', document.forms.qrPost.action, true);
  
  QR.xhr.withCredentials = true;
  
  QR.xhr.upload.onprogress = function(e) {
    if (e.loaded >= e.total) {
      QR.btn.value = '100%';
    }
    else {
      QR.btn.value = (0 | (e.loaded / e.total * 100)) + '%';
    }
  };
  
  QR.xhr.onerror = function() {
    QR.xhr = null;
    QR.showPostError('Connection error.');
  };
  
  QR.xhr.onload = function() {
    var resp, el, hasFile, ids, tid, pid, tracked;
    
    QR.xhr = null;
    
    QR.btn.value = 'Post';
    
    if (this.status == 200) {
      if (resp = this.responseText.match(/"errmsg"[^>]*>(.*?)<\/span/)) {
        QR.reloadCaptcha(true);
        QR.showPostError(resp[1]);
        return;
      }
      
      if (ids = this.responseText.match(/<!-- thread:([0-9]+),no:([0-9]+) -->/)) {
        tid = ids[1];
        pid = ids[2];
        
        QR.lastTid = tid;
        
        localStorage.setItem('4chan-cd-' + Main.board + '-tid', tid);
        
        hasFile = (el = $.id('qrFile')) && el.value;
        
        QR.setPostTime();
        
        if (Config.persistentQR) {
          $.byName('com')[1].value = '';
          
          if (el = $.byName('spoiler')[2]) {
            el.checked = false;
          }
          
          QR.reloadCaptcha();
          
          if (hasFile) {
            QR.resetFile();
          }
          
          QR.startCooldown();
        }
        else {
          QR.close();
        }
        
        if (Main.tid) {
          if (Config.threadWatcher) {
            ThreadWatcher.setLastRead(pid, tid);
          }
          QR.lastReplyId = +pid;
          Parser.trackedReplies['>>' + pid] = 1;
          Parser.saveTrackedReplies(tid, Parser.trackedReplies);
        }
        else {
          tracked = Parser.getTrackedReplies(tid) || {};
          tracked['>>' + pid] = 1;
          Parser.saveTrackedReplies(tid, tracked);
        }
        
        UA.dispatchEvent('4chanQRPostSuccess', { threadId: tid, postId: pid });
      }
      
      if (ThreadUpdater.enabled) {
        setTimeout(ThreadUpdater.forceUpdate, 500);
      }
    }
    else {
      QR.showPostError('Error: ' + this.status + ' ' + this.statusText);
    }
  };
  
  formdata = new FormData(document.forms.qrPost);
  
  clearInterval(QR.pulse);
  
  QR.btn.value = 'Sending';
  
  QR.xhr.send(formdata);
};

QR.presubmitChecks = function(force) {
  if (QR.xhr) {
    QR.xhr.abort();
    QR.xhr = null;
    QR.showPostError('Aborted.');
    QR.btn.value = 'Post';
    return false;
  }
  
  if (!force && QR.cooldown) {
    if (QR.auto = !QR.auto) {
      QR.btn.value = QR.cooldown + 's (auto)';
    }
    else {
      QR.btn.value = QR.cooldown + 's';
    }
    return false;
  }
  
  return true;
};

QR.getCooldown = function(type) {
  if (QR.currentTid != QR.lastTid) {
    return QR.cooldowns[type];
  }
  else {
    return QR.cooldowns[type + '_intra'];
  }
};

QR.setPostTime = function() {
  return localStorage.setItem('4chan-cd-' + Main.board, Date.now());
};

QR.getPostTime = function() {
  return localStorage.getItem('4chan-cd-' + Main.board);
};

QR.removePostTime = function() {
  return localStorage.removeItem('4chan-cd-' + Main.board);
};

QR.startCooldown = function() {
  var type, el, time;
  
  if (QR.noCooldown || !$.id('quickReply') || QR.xhr) {
    return;
  }
  
  clearInterval(QR.pulse);
  
  type = ((el = $.id('qrFile')) && el.value) ? 'image' : 'reply';
  
  time = QR.getPostTime(type);
  
  if (!time) {
    QR.btn.value = 'Post';
    return;
  }
  
  QR.timestamp = parseInt(time, 10);
  
  QR.activeDelay = QR.getCooldown(type);
  
  QR.cdElapsed = Date.now() - QR.timestamp;
  
  QR.cooldown = Math.floor((QR.activeDelay - QR.cdElapsed) / 1000);
  
  if (QR.cooldown <= 0 || QR.cdElapsed < 0) {
    QR.cooldown = false;
    QR.removePostTime(type);
    return;
  }
  
  QR.btn.value = QR.cooldown + 's';
  
  QR.pulse = setInterval(QR.onPulse, 1000);
};

QR.onPulse = function() {
  QR.cdElapsed = Date.now() - QR.timestamp;
  QR.cooldown = Math.floor((QR.activeDelay - QR.cdElapsed) / 1000);
  if (QR.cooldown <= 0) {
    clearInterval(QR.pulse);
    QR.btn.value = 'Post';
    QR.cooldown = false;
    if (QR.auto) {
      QR.submit();
    }
  }
  else {
    QR.btn.value = QR.cooldown + (QR.auto ? 's (auto)' : 's');
  }
};

/**
 * Thread hiding
 */
var ThreadHiding = {};

ThreadHiding.init = function() {
  this.threshold = 43200000; // 12 hours
  
  this.hidden = {};
  
  this.load();
  
  this.purge();
};

ThreadHiding.clear = function(silent) {
  var i, id, key, msg;
  
  this.load();
  
  i = 0;
  
  for (id in this.hidden) {
    ++i;
  }
  
  key = '4chan-hide-t-' + Main.board;
  
  if (!silent) {
    if (!i) {
      alert("You don't have any hidden threads on /" + Main.board + '/');
      return;
    }
    
    msg = 'This will unhide ' + i + ' thread' + (i > 1 ? 's' : '') + ' on /' + Main.board + '/';
    
    if (!confirm(msg)) {
      return;
    }
    
    localStorage.removeItem(key);
  }
  else {
    localStorage.removeItem(key);
  }
};

ThreadHiding.isHidden = function(tid) {
  var sa = $.id('sa' + tid);
  
  return !sa || sa.hasAttribute('data-hidden');
};

ThreadHiding.toggle = function(tid) {
  if (this.isHidden(tid)) {
    this.show(tid);
  }
  else {
    this.hide(tid);
  }
  this.save();
};

ThreadHiding.show = function(tid) {
  var sa, th;
  
  th = $.id('t' + tid);
  
  sa = $.id('sa' + tid);
  sa.removeAttribute('data-hidden');
  
  if (Main.hasMobileLayout) {
    sa.textContent = 'Hide';
    $.removeClass(sa, 'mobile-tu-show');
    $.cls('postLink', th)[0].appendChild(sa);
    
    th.style.display = null;
    $.removeClass(th.nextElementSibling, 'mobile-hr-hidden');
  }
  else {
    sa.firstChild.src = Main.icons.minus;
    $.removeClass(th, 'post-hidden');
  }
  
  delete this.hidden[tid];
};

ThreadHiding.hide = function(tid) {
  var sa, th;
  
  th = $.id('t' + tid);
  
  if (Main.hasMobileLayout) {
    th.style.display = 'none';
    $.addClass(th.nextElementSibling, 'mobile-hr-hidden');
    
    sa = $.id('sa' + tid);
    sa.setAttribute('data-hidden', tid);
    sa.textContent = 'Show Hidden Thread';
    $.addClass(sa, 'mobile-tu-show');
    
    th.parentNode.insertBefore(sa, th);
  }
  else {
    if (Config.hideStubs && !$.cls('stickyIcon', th)[0]) {
      th.style.display = th.nextElementSibling.style.display = 'none';
    }
    else {
      sa = $.id('sa' + tid);
      sa.setAttribute('data-hidden', tid);
      sa.firstChild.src = Main.icons.plus;
      th.className += ' post-hidden';
    }
  }
  
  this.hidden[tid] = Date.now();
};

ThreadHiding.load = function() {
  var storage;
  
  if (storage = localStorage.getItem('4chan-hide-t-' + Main.board)) {
    this.hidden = JSON.parse(storage);
  }
};

ThreadHiding.purge = function() {
  var i, hasHidden, lastPurged, key;
  
  key = '4chan-purge-t-' + Main.board;
  
  lastPurged = localStorage.getItem(key);
  
  for (i in this.hidden) {
    hasHidden = true;
    break;
  }
  
  if (!hasHidden) {
    return;
  }
  
  if (!lastPurged || lastPurged < Date.now() - this.threshold) {
    $.get('//a.4cdn.org/' + Main.board + '/threads.json',
    {
      onload: function() {
        var i, j, t, p, pages, threads, alive;
        
        if (this.status == 200) {
          alive = {};
          pages = JSON.parse(this.responseText);
          for (i = 0; p = pages[i]; ++i) {
            threads = p.threads;
            for (j = 0; t = threads[j]; ++j) {
              if (ThreadHiding.hidden[t.no]) {
                alive[t.no] = 1;
              }
            }
          }
          ThreadHiding.hidden = alive;
          ThreadHiding.save();
          localStorage.setItem(key, Date.now());
        }
        else {
          console.log('Bad status code while purging threads');
        }
      },
      onerror: function() {
        console.log('Error while purging hidden threads');
      }
    });
  }
};

ThreadHiding.save = function() {
  for (var i in this.hidden) {
    localStorage.setItem('4chan-hide-t-' + Main.board,
      JSON.stringify(this.hidden)
    );
    return;
  }
  localStorage.removeItem('4chan-hide-t-' + Main.board);
};

/**
 * Reply hiding
 */
var ReplyHiding = {};

ReplyHiding.init = function() {
  this.threshold = 7 * 86400000;
  this.hidden = {};
  this.load();
};

ReplyHiding.isHidden = function(pid) {
  var sa = $.id('sa' + pid);
  
  return !sa || sa.hasAttribute('data-hidden');
};

ReplyHiding.toggle = function(pid) {
  if (this.isHidden(pid)) {
    this.show(pid);
  }
  else {
    this.hide(pid);
  }
  this.save();
};

ReplyHiding.show = function(pid) {
  var post, sa;
  
  post = $.id('pc' + pid);
  
  $.removeClass(post, 'post-hidden');
  
  sa = $.id('sa' + pid);
  sa.removeAttribute('data-hidden');
  sa.firstChild.src = Main.icons.minus;
  
  delete this.hidden[pid];
};

ReplyHiding.hide = function(pid) {
  var post, sa;
  
  post = $.id('pc' + pid);
  post.className += ' post-hidden';
  
  sa = $.id('sa' + pid);
  sa.setAttribute('data-hidden', pid);
  sa.firstChild.src = Main.icons.plus;
  
  this.hidden[pid] = Date.now();
};

ReplyHiding.load = function() {
  var storage;
  
  if (storage = localStorage.getItem('4chan-hide-r-' + Main.board)) {
    this.hidden = JSON.parse(storage);
  }
};

ReplyHiding.purge = function() {
  var tid, now;
  
  now = Date.now();
  
  for (tid in this.hidden) {
    if (now - this.hidden[tid] > this.threshold) {
      delete this.hidden[tid];
    }
  }
  this.save();
};

ReplyHiding.save = function() {
  for (var i in this.hidden) {
    localStorage.setItem('4chan-hide-r-' + Main.board,
      JSON.stringify(this.hidden)
    );
    return;
  }
  localStorage.removeItem('4chan-hide-r-' + Main.board);
};

/**
 * Thread watcher
 */
var ThreadWatcher = {};

ThreadWatcher.init = function() {
  var cnt, jumpTo, rect, el;
  
  this.listNode = null;
  this.charLimit = 45;
  this.watched = {};
  this.isRefreshing = false;
  
  if (Main.hasMobileLayout) {
    el = document.createElement('a');
    el.href = '#';
    el.textContent = 'TW';
    el.addEventListener('click', ThreadWatcher.toggleList, false);
    cnt = $.id('settingsWindowLinkMobile');
    cnt.parentNode.insertBefore(el, cnt);
    cnt.parentNode.insertBefore(document.createTextNode(' '), cnt);
  }
  
  if (location.hash && (jumpTo = location.hash.split('lr')[1])) {
    if (jumpTo = $.id('pc' + jumpTo)) {
      if (jumpTo.nextElementSibling) {
        jumpTo = jumpTo.nextElementSibling;
        if (el = $.id('p' + jumpTo.id.slice(2))) {
          $.addClass(el, 'highlight');
        }
      }
      
      rect = jumpTo.getBoundingClientRect();
      
      if (rect.top < 0 || rect.bottom > document.documentElement.clientHeight) {
        window.scrollBy(0, rect.top);
      }
    }
    
    if (window.history && history.replaceState) {
      history.replaceState(null, '', location.href.split('#', 1)[0]);
    }
  }
  
  cnt = document.createElement('div');
  cnt.id = 'threadWatcher';
  cnt.className = 'extPanel reply';
  cnt.setAttribute('data-trackpos', 'TW-position');
  
  if (Main.hasMobileLayout) {
    cnt.style.display = 'none';
  }
  else {
    if (Config['TW-position']) {
      cnt.style.cssText = Config['TW-position'];
    }
    else {
      cnt.style.left = '10px';
      cnt.style.top = '380px';
    }
    
    if (Config.fixedThreadWatcher) {
      cnt.style.position = 'fixed';
    }
    else {
      cnt.style.position = '';
    }
  }
  
  cnt.innerHTML = '<div class="drag" id="twHeader">'
    + (Main.hasMobileLayout ? ('<img id="twClose" class="pointer" src="'
    + Main.icons.cross + '" alt="X">') : '')
    + 'Thread Watcher'
    + (UA.hasCORS ? ('<img id="twPrune" class="pointer right" src="'
    + Main.icons.refresh + '" alt="R" title="Refresh"></div>') : '</div>');
  
  this.listNode = document.createElement('ul');
  this.listNode.id = 'watchList';
  
  this.load();
  
  if (Main.tid) {
    this.refreshCurrent();
  }
  
  this.build();
  
  cnt.appendChild(this.listNode);
  document.body.appendChild(cnt);
  cnt.addEventListener('mouseup', this.onClick, false);
  Draggable.set($.id('twHeader'));
  window.addEventListener('storage', this.syncStorage, false);
  
  if (Main.hasMobileLayout) {
    if (Main.tid) {
      ThreadWatcher.initMobileButtons();
    }
  }
  else if (!Main.tid && this.canAutoRefresh()) {
    this.refresh();
  }
};

ThreadWatcher.toggleList = function(e) {
  var el = $.id('threadWatcher');
  
  e && e.preventDefault();
  
  if (!Main.tid && ThreadWatcher.canAutoRefresh()) {
    ThreadWatcher.refresh();
  }
  
  if (el.style.display == 'none') {
    el.style.top = (window.pageYOffset + 30) + 'px';
    el.style.display = '';
  }
  else {
    el.style.display = 'none';
  }
};

ThreadWatcher.syncStorage = function(e) {
  var key;
  
  if (!e.key) {
    return;
  }
  
  key = e.key.split('-');
  
  if (key[0] == '4chan' && key[1] == 'watch' && e.newValue != e.oldValue) {
    ThreadWatcher.watched = JSON.parse(e.newValue);
    ThreadWatcher.build(true);
  }
};

ThreadWatcher.load = function() {
  if (storage = localStorage.getItem('4chan-watch')) {
    this.watched = JSON.parse(storage);
  }
};

ThreadWatcher.build = function(rebuildButtons) {
  var i, html, tuid, key, nodes, cls;
  
  html = '';
  
  for (key in this.watched) {
    tuid = key.split('-');
    html += '<li id="watch-' + key
      + '"><span class="pointer" data-cmd="unwatch" data-id="'
      + tuid[0] + '" data-board="' + tuid[1] + '">&times;</span> <a href="'
      + Main.linkToThread(tuid[0], tuid[1]) + '#lr' + this.watched[key][1] + '"';
    
    if (this.watched[key][1] == -1) {
      html += ' class="deadlink">';
    }
    else {
      if (this.watched[key][3]) {
        cls = 'archivelink';
      }
      else {
        cls = false;
      }
      if (this.watched[key][2]) {
        html += ' class="' + (cls ? (cls + ' ') : '')
          + 'hasNewReplies">(' + this.watched[key][2] + ') ';
      }
      else {
        html += (cls ? ('class="' + cls + '"') : '') + '>';
      }
    }
    
    html += '/' + tuid[1] + '/ - ' + this.watched[key][0] + '</a></li>';
  }
  
  if (rebuildButtons) {
    ThreadWatcher.rebuildButtons();
  }
  
  ThreadWatcher.listNode.innerHTML = html;
};

ThreadWatcher.rebuildButtons = function() {
  var i, buttons, key;
  
  buttons = $.cls('wbtn');
  
  for (i = 0; btn = buttons[i]; ++i) {
    key = btn.getAttribute('data-id') + '-' + Main.board;
    if (ThreadWatcher.watched[key]) {
      if (!btn.hasAttribute('data-active')) {
        btn.src = Main.icons.watched;
        btn.setAttribute('data-active', '1');
      }
    }
    else {
      if (btn.hasAttribute('data-active')) {
        btn.src = Main.icons.notwatched;
        btn.removeAttribute('data-active');
      }
    }
  }
};

ThreadWatcher.initMobileButtons = function() {
  var el, cnt, key, ref;
  
  el = document.createElement('img');
  
  key = Main.tid + '-' + Main.board;
  
  if (ThreadWatcher.watched[key]) {
    el.src = Main.icons.watched;
    el.setAttribute('data-active', '1');
  }
  else {
    el.src = Main.icons.notwatched;
  }
  
  el.className = 'extButton wbtn wbtn-' + key;
  el.setAttribute('data-cmd', 'watch');
  el.setAttribute('data-id', Main.tid);
  el.alt = 'W';
  
  cnt = document.createElement('span');
  cnt.className = 'mobileib button';
  
  cnt.appendChild(el);
  
  if (ref = $.cls('navLinks')[0]) {
    ref.appendChild(document.createTextNode(' '));
    ref.appendChild(cnt);
  }
  
  if (ref = $.cls('navLinks')[3]) {
    ref.appendChild(document.createTextNode(' '));
    ref.appendChild(cnt.cloneNode(true));
  }
};

ThreadWatcher.onClick = function(e) {
  var t = e.target;
  
  if (t.hasAttribute('data-id')) {
    ThreadWatcher.toggle(
      t.getAttribute('data-id'),
      t.getAttribute('data-board')
    );
  }
  else if (t.id == 'twPrune' && !ThreadWatcher.isRefreshing) {
    ThreadWatcher.refresh();
  }
  else if (t.id == 'twClose') {
    ThreadWatcher.toggleList();
  }
};

ThreadWatcher.toggle = function(tid, board, synced) {
  var i, key, label, lastReply, thread;
  
  key = tid + '-' + (board || Main.board);
  
  if (this.watched[key]) {
    delete this.watched[key];
  }
  else {
    if (label = $.cls('subject', $.id('pi' + tid))[0].textContent) {
      label = label.slice(0, this.charLimit);
    }
    else if (label = $.id('m' + tid).innerHTML) {
      label = label.replace(/(?:<br>)+/g, ' ')
        .replace(/<[^>]*?>/g, '').slice(0, this.charLimit);
    }
    else {
      label = 'No.' + tid;
    }
    
    if ((thread = $.id('t' + tid)).children[1]) {
      lastReply = thread.lastElementChild.id.slice(2);
    }
    else {
      lastReply = tid;
    }
    
    this.watched[key] = [ label, lastReply, 0 ];
  }
  this.save();
  this.load();
  this.build(true);
};

ThreadWatcher.save = function() {
  ThreadWatcher.sortByBoard();
  
  localStorage.setItem('4chan-watch', JSON.stringify(ThreadWatcher.watched));
};

ThreadWatcher.sortByBoard = function() {
  var i, self, key, sorted, keys;
  
  self = ThreadWatcher;
  
  sorted = {};
  keys = [];
  
  for (key in self.watched) {
    keys.push(key);
  }
  
  keys.sort(function(a, b) {
    a = a.split('-')[1];
    b = b.split('-')[1];
    
    if (a < b) {
      return -1;
    }
    if (a > b) {
      return 1;
    }
    return 0;
  });
  
  for (i = 0; key = keys[i]; ++i) {
    sorted[key] = self.watched[key];
  }
  
  self.watched = sorted;
};

ThreadWatcher.canAutoRefresh = function() {
  var time;
  
  if (time = localStorage.getItem('4chan-tw-timestamp')) {
    return Date.now() - (+time) >= 60000;
  }
  return false;
};

ThreadWatcher.setRefreshTimestamp = function() {
  localStorage.setItem('4chan-tw-timestamp', Date.now());
};

ThreadWatcher.refresh = function() {
  var i, to, key, total, img;
  
  if (total = $.id('watchList').children.length) {
    i = to = 0;
    img = $.id('twPrune');
    img.src = Main.icons.rotate;
    ThreadWatcher.isRefreshing = true;
    ThreadWatcher.setRefreshTimestamp();
    for (key in ThreadWatcher.watched) {
      setTimeout(ThreadWatcher.fetch, to, key, ++i == total ? img : null);
      to += 200;
    }
  }
};

ThreadWatcher.refreshCurrent = function(rebuild) {
  var key, thread, lastReply;
  
  key = Main.tid + '-' + Main.board;
  
  if (this.watched[key]) {
    if ((thread = $.id('t' + Main.tid)).children[1]) {
      lastReply = thread.lastElementChild.id.slice(2);
    }
    else {
      lastReply = Main.tid;
    }
    if (this.watched[key][1] < lastReply) {
      this.watched[key][1] = lastReply;
    }
    
    this.watched[key][2] = 0;
    this.save();
    
    if (rebuild) {
      this.build();
    }
  }
};

ThreadWatcher.setLastRead = function(pid, tid) {
  var key = tid + '-' + Main.board;
  
  if (this.watched[key]) {
    this.watched[key][1] = pid;
    this.watched[key][2] = 0;
    this.save();
    this.build();
  }
};

ThreadWatcher.onRefreshEnd = function(img) {
  img.src = Main.icons.refresh;
  this.isRefreshing = false;
  this.save();
  this.load();
  this.build();
};

ThreadWatcher.fetch = function(key, img) {
  var tuid, xhr, li, method;
  
  li = $.id('watch-' + key);
  
  if (ThreadWatcher.watched[key][1] == -1) {
    delete ThreadWatcher.watched[key];
    li.parentNode.removeChild(li);
    if (img) {
      ThreadWatcher.onRefreshEnd(img);
    }
    return;
  }
  
  tuid = key.split('-'); // tid, board
  
  xhr = new XMLHttpRequest();
  xhr.onload = function() {
    var i, newReplies, posts, lastReply;
    if (this.status == 200) {
      posts = Parser.parseThreadJSON(this.responseText);
      lastReply = ThreadWatcher.watched[key][1];
      newReplies = 0;
      for (i = posts.length - 1; i >= 1; i--) {
        if (posts[i].no <= lastReply) {
          break;
        }
        ++newReplies;
      }
      if (newReplies > ThreadWatcher.watched[key][2]) {
        ThreadWatcher.watched[key][2] = newReplies;
      }
      if (posts[0].archived) {
        ThreadWatcher.watched[key][3] = 1;
      }
    }
    else if (this.status == 404) {
      ThreadWatcher.watched[key][1] = -1;
    }
    if (img) {
      ThreadWatcher.onRefreshEnd(img);
    }
  };
  if (img) {
    xhr.onerror = xhr.onload;
  }
  xhr.open('GET', '//a.4cdn.org/' + tuid[1] + '/thread/' + tuid[0] + '.json');
  xhr.send(null);
};

/**
 * Thread expansion
 */
var ThreadExpansion = {};

ThreadExpansion.init = function() {
  this.enabled = UA.hasCORS;
};

ThreadExpansion.expandComment = function(link) {
  var ids, tid, pid, abbr;
  
  if (!(ids = link.getAttribute('href').match(/^(?:thread\/)([0-9]+)#p([0-9]+)$/))) {
    return;
  }
  
  tid = ids[1];
  pid = ids[2];
  
  abbr = link.parentNode;
  abbr.textContent = 'Loading...';
  
  $.get('//a.4cdn.org/' + Main.board + '/thread/' + tid + '.json',
    {
      onload: function() {
        var i, msg, com, post, posts;
        
        if (this.status == 200) {
          msg = $.id('m' + pid);
          
          posts = Parser.parseThreadJSON(this.responseText);
          
          if (tid == pid) {
            post = posts[0];
          }
          else {
            for (i = posts.length - 1; i > 0; i--) {
              if (posts[i].no == pid) {
                post = posts[i];
                break;
              }
            }
          }
          
          if (post) {
            post = Parser.buildHTMLFromJSON(post, Main.board);
            
            msg.innerHTML = $.cls('postMessage', post)[0].innerHTML;
            
            if (Parser.prettify) {
              Parser.parseMarkup(msg);
            }
            if (window.jsMath) {
              Parser.parseMathOne(msg);
            }
          }
          else {
            abbr.textContent = "This post doesn't exist anymore.";
          }
        }
        else if (this.status == 404) {
          abbr.textContent = "This thread doesn't exist anymore.";
        }
        else {
          abbr.textContent = 'Connection Error';
          console.log('ThreadExpansion: ' + this.status + ' ' + this.statusText);
        }
      },
      onerror: function() {
        abbr.textContent = 'Connection Error';
        console.log('ThreadExpansion: xhr failed');
      }
    }
  );
};

ThreadExpansion.toggle = function(tid) {
  var thread, msg, expmsg, summary, tmp;
  
  thread = $.id('t' + tid);
  summary = thread.children[1];
  if (thread.hasAttribute('data-truncated')) {
    msg = $.id('m' + tid);
    expmsg = msg.nextSibling;
  }
  
  if ($.hasClass(thread, 'tExpanded')) {
    thread.className = thread.className.replace(' tExpanded', ' tCollapsed');
    summary.children[0].src = Main.icons.plus;
    summary.children[1].style.display = 'inline';
    summary.children[2].style.display = 'none';
    if (msg) {
      tmp = msg.innerHTML;
      msg.innerHTML = expmsg.textContent;
      expmsg.textContent = tmp;
    }
  }
  else if ($.hasClass(thread, 'tCollapsed')) {
    thread.className = thread.className.replace(' tCollapsed', ' tExpanded');
    summary.children[0].src = Main.icons.minus;
    summary.children[1].style.display = 'none';
    summary.children[2].style.display = 'inline';
    if (msg) {
      tmp = msg.innerHTML;
      msg.innerHTML = expmsg.textContent;
      expmsg.textContent = tmp;
    }
  }
  else {
    summary.children[0].src = Main.icons.rotate;
    ThreadExpansion.fetch(tid);
  }
};

ThreadExpansion.fetch = function(tid) {
  $.get('//a.4cdn.org/' + Main.board + '/thread/' + tid + '.json',
    {
      onload: function() {
        var i, p, n, frag, thread, tail, posts, count, msg, metacap,
          expmsg, summary, abbr;
        
        thread = $.id('t' + tid);
        summary = thread.children[1];
        
        if (this.status == 200) {
          tail = $.cls('reply', thread);
          
          posts = Parser.parseThreadJSON(this.responseText);
          
          if (!Config.revealSpoilers && posts[0].custom_spoiler) {
            Parser.setCustomSpoiler(Main.board, posts[0].custom_spoiler);
          }
          
          frag = document.createDocumentFragment();
          
          if (tail[0]) {
            tail = +tail[0].id.slice(1);
            
            for (i = 1; p = posts[i]; ++i) {
              if (p.no < tail) {
                n = Parser.buildHTMLFromJSON(p, Main.board);
                n.className += ' rExpanded';
                frag.appendChild(n);
              }
              else {
                break;
              }
            }
          }
          else {
            for (i = 1; p = posts[i]; ++i) {
              n = Parser.buildHTMLFromJSON(p, Main.board);
              n.className += ' rExpanded';
              frag.appendChild(n);
            }
          }
          
          msg = $.id('m' + tid);
          if ((abbr = $.cls('abbr', msg)[0])
            && /^Comment/.test(abbr.textContent)) {
            thread.setAttribute('data-truncated', '1');
            expmsg = document.createElement('div');
            expmsg.style.display = 'none';
            expmsg.textContent = msg.innerHTML;
            msg.parentNode.insertBefore(expmsg, msg.nextSibling);
            if (metacap = $.cls('capcodeReplies', msg)[0]) {
              msg.innerHTML = posts[0].com + '<br><br>';
              msg.appendChild(metacap);
            }
            else {
              msg.innerHTML = posts[0].com;
            }
            if (Parser.prettify) {
              Parser.parseMarkup(msg);
            }
            if (window.jsMath) {
              Parser.parseMathOne(msg);
            }
          }
          
          thread.insertBefore(frag, summary.nextSibling);
          Parser.parseThread(tid, 1, i - 1);
          
          thread.className += ' tExpanded';
          summary.children[0].src = Main.icons.minus;
          summary.children[1].style.display = 'none';
          summary.children[2].style.display = 'inline';
        }
        else if (this.status == 404) {
          summary.children[0].src = Main.icons.plus;
          summary.children[0].display = 'none';
          summary.children[1].textContent = "This thread doesn't exist anymore.";
        }
        else {
          summary.children[0].src = Main.icons.plus;
          console.log('ThreadExpansion: ' + this.status + ' ' + this.statusText);
        }
      },
      onerror: function() {
        $.id('t' + tid).children[1].children[0].src = Main.icons.plus;
        console.log('ThreadExpansion: xhr failed');
      }
    }
  );
};

/**
 * Thread updater
 */
var ThreadUpdater = {};

ThreadUpdater.init = function() {
  if (!UA.hasCORS) {
    return;
  }
  
  this.enabled = true;
  
  this.pageTitle = document.title;
  
  this.unreadCount = 0;
  this.auto = this.hadAuto = false;
  
  this.delayId = 0;
  this.delayIdHidden = 4;
  this.delayRange = [ 10, 15, 20, 30, 60, 90, 120, 180, 240, 300 ];
  this.timeLeft = 0;
  this.interval = null;
  
  this.lastModified = '0';
  this.lastReply = null;
  
  this.currentIcon = null;
  this.iconPath = '//s.4cdn.org/image/';
  this.iconNode = document.head.querySelector('link[rel="shortcut icon"]');
  this.iconNode.type = 'image/x-icon';
  this.defaultIcon = this.iconNode.getAttribute('href').replace(this.iconPath, '');
  
  this.deletionQueue = {};
  
  if (Config.updaterSound) {
    this.audioEnabled = false;
    this.audio = document.createElement('audio');
    this.audio.src = '//s.4cdn.org/media/beep.ogg';
  }
  
  this.hidden = 'hidden';
  this.visibilitychange = 'visibilitychange';
  
  this.adRefreshDelay = 1000;
  this.adDebounce = 0;
  this.ads = {};
  
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
  
  this.initAds();
  this.initControls();
  
  document.addEventListener('scroll', this.onScroll, false);
  
  if (Config.alwaysAutoUpdate || sessionStorage.getItem('4chan-auto-' + Main.tid)) {
    this.start();
  }
};

ThreadUpdater.buildMobileControl = function(el, bottom) {
  var wrap, cnt, ctrl, cb, label, oldBtn, btn;
  
  bottom = (bottom ? 'Bot' : '');
  
  wrap = document.createElement('div');
  wrap.className = 'btn-row';
  
  // Update button
  oldBtn = el.parentNode;
  
  btn = oldBtn.cloneNode(true);
  btn.textContent = 'Update';
  btn.setAttribute('data-cmd', 'update');
  
  wrap.appendChild(btn);
  cnt = el.parentNode.parentNode;
  ctrl = document.createElement('span');
  ctrl.className = 'mobileib button';
  
  // Auto checkbox
  label = document.createElement('label');
  cb = document.createElement('input');
  cb.type = 'checkbox';
  cb.setAttribute('data-cmd', 'auto');
  this['autoNode' + bottom] = cb;
  label.appendChild(cb);
  label.appendChild(document.createTextNode('Auto'));
  ctrl.appendChild(label);
  wrap.appendChild(document.createTextNode(' '));
  wrap.appendChild(ctrl);
  
  // Status label
  label = document.createElement('div');
  label.className = 'mobile-tu-status';
  
  wrap.appendChild(this['statusNode' + bottom] = label);
  
  cnt.appendChild(wrap);
  
  // Remove Update button
  oldBtn.parentNode.removeChild(oldBtn);
  
  $.id('mpostform').parentNode.style.marginTop = '';
};

ThreadUpdater.buildDesktopControl = function(bottom) {
  var frag, el, label, navlinks;
  
  bottom = (bottom ? 'Bot' : '');
  
  frag = document.createDocumentFragment();
  
  // Update button
  frag.appendChild(document.createTextNode(' ['));
  el = document.createElement('a');
  el.href = '';
  el.textContent = 'Update';
  el.setAttribute('data-cmd', 'update');
  frag.appendChild(el);
  frag.appendChild(document.createTextNode(']'));
  
  // Auto checkbox
  frag.appendChild(document.createTextNode(' ['));
  label = document.createElement('label');
  el = document.createElement('input');
  el.type = 'checkbox';
  el.title = 'Fetch new replies automatically';
  el.setAttribute('data-cmd', 'auto');
  this['autoNode' + bottom] = el;
  label.appendChild(el);
  label.appendChild(document.createTextNode('Auto'));
  frag.appendChild(label);
  frag.appendChild(document.createTextNode('] '));
  
  if (Config.updaterSound) {
    // Sound checkbox
    frag.appendChild(document.createTextNode(' ['));
    label = document.createElement('label');
    el = document.createElement('input');
    el.type = 'checkbox';
    el.title = 'Play a sound on new replies to your posts';
    el.setAttribute('data-cmd', 'sound');
    this['soundNode' + bottom] = el;
    label.appendChild(el);
    label.appendChild(document.createTextNode('Sound'));
    frag.appendChild(label);
    frag.appendChild(document.createTextNode('] '));
  }
  
  // Status label
  frag.appendChild(
    this['statusNode' + bottom] = document.createElement('span')
  );
  
  if (bottom) {
    navlinks = $.cls('navLinks' + bottom)[0];
  }
  else {
    navlinks = $.cls('navLinks')[1];
  }
  
  if (navlinks) {
    navlinks.appendChild(frag);
  }
};

ThreadUpdater.initControls = function() {
  var i, j, frag, el, label, navlinks;
  
  // Mobile
  if (Main.hasMobileLayout) {
    this.buildMobileControl($.id('refresh_top'));
    this.buildMobileControl($.id('refresh_bottom'), true);
  }
  // Desktop
  else {
    this.buildDesktopControl();
    this.buildDesktopControl(true);
  }
};

ThreadUpdater.start = function() {
  this.auto = this.hadAuto = true;
  this.autoNode.checked = this.autoNodeBot.checked = true;
  this.force = this.updating = false;
  this.lastUpdated = Date.now();
  if (this.hidden) {
    document.addEventListener(this.visibilitychange,
      this.onVisibilityChange, false);
  }
  this.delayId = 0;
  this.timeLeft = this.delayRange[0];
  this.pulse();
  sessionStorage.setItem('4chan-auto-' + Main.tid, 1);
};

ThreadUpdater.stop = function(manual) {
  clearTimeout(this.interval);
  this.auto = this.updating = this.force = false;
  this.autoNode.checked = this.autoNodeBot.checked = false;
  if (this.hidden) {
    document.removeEventListener(this.visibilitychange,
      this.onVisibilityChange, false);
  }
  if (manual) {
    this.setStatus('');
    this.setIcon(null);
  }
  sessionStorage.removeItem('4chan-auto-' + Main.tid);
};

ThreadUpdater.pulse = function() {
  var self = ThreadUpdater;
  
  if (self.timeLeft == 0) {
    self.update();
  }
  else {
    self.setStatus(self.timeLeft--);
    self.interval = setTimeout(self.pulse, 1000);
  }
};

ThreadUpdater.adjustDelay = function(postCount)
{
  if (postCount == 0) {
    if (!this.force) {
      if (this.delayId < this.delayRange.length - 1) {
        ++this.delayId;
      }
    }
  }
  else {
    this.delayId = document[this.hidden] ? this.delayIdHidden : 0;
  }
  this.timeLeft = this.delayRange[this.delayId];
  if (this.auto) {
    this.pulse();
  }
};

ThreadUpdater.onVisibilityChange = function(e) {
  var self = ThreadUpdater;
  
  if (document[self.hidden] && self.delayId < self.delayIdHidden) {
    self.delayId = self.delayIdHidden;
  }
  else {
    self.delayId = 0;
    self.refreshAds();
  }
  
  self.timeLeft = self.delayRange[0];
  self.lastUpdated = Date.now();
  clearTimeout(self.interval);
  self.pulse();
};

ThreadUpdater.onScroll = function(e) {
  if (ThreadUpdater.hadAuto &&
      (document.documentElement.scrollHeight
      <= (window.innerHeight + window.pageYOffset)
      && !document[ThreadUpdater.hidden])) {
    ThreadUpdater.clearUnread();
  }
  
  ThreadUpdater.refreshAds();
};

ThreadUpdater.clearUnread = function() {
  if (!this.dead) {
    this.setIcon(null);
  }
  if (this.lastReply) {
    this.unreadCount = 0;
    document.title = this.pageTitle;
    $.removeClass(this.lastReply, 'newPostsMarker');
    this.lastReply = null;
  }
};

ThreadUpdater.forceUpdate = function() {
  ThreadUpdater.force = true;
  ThreadUpdater.update();
};

ThreadUpdater.toggleAuto = function() {
  if (this.updating) {
    return;
  }
  this.auto ? this.stop(true) : this.start();
};

ThreadUpdater.toggleSound = function() {
  this.soundNode.checked = this.soundNodeBot.checked =
    this.audioEnabled = !this.audioEnabled;
};

ThreadUpdater.update = function() {
  var self, now = Date.now();
  
  self = ThreadUpdater;
  
  if (self.updating) {
    return;
  }
  
  clearTimeout(self.interval);
  
  self.updating = true;
  
  self.setStatus('Updating...');
  
  $.get('//a.4cdn.org/' + Main.board + '/thread/' + Main.tid + '.json',
    {
      onload: self.onload,
      onerror: self.onerror
    },
    {
      'If-Modified-Since': self.lastModified
    }
  );
};

ThreadUpdater.initAds = function() {
  var i, id, adIds = [ '_top_ad', '_middle_ad', '_bottom_ad' ];
  
  for (i = 0; id = adIds[i]; ++i) {
    ThreadUpdater.ads[id] = {
      time: 0,
      seenOnce: false,
      isStale: false
    };
  }
};

ThreadUpdater.invalidateAds = function() {
  var id, self = ThreadUpdater;
  
  for (id in self.ads) {
    meta = self.ads[id];
    if (meta.seenOnce) {
      meta.isStale = true;
    }
  }
};

ThreadUpdater.refreshAds = function() {
  var self, now, el, id, ad, meta, hidden, docHeight, offset;
  
  self = ThreadUpdater;
  
  now = Date.now();
  
  if (now - self.adDebounce < 100) {
    return;
  }
  
  self.adDebounce = now;
  
  hidden = document[self.hidden];
  docHeight = document.documentElement.clientHeight;
  
  for (id in self.ads) {
    meta = self.ads[id];
    
    if (hidden) {
      continue;
    }
    
    ad = window[id];
    
    if (!ad) {
      continue;
    }
    
    el = $.id(ad.D);
    
    if (!el) {
      continue;
    }
    
    offset = el.getBoundingClientRect();
    
    if (offset.top < 0 || offset.bottom > docHeight) {
      continue;
    }
    
    meta.seenOnce = true;
    
    if (!meta.isStale || now - meta.time < self.adRefreshDelay) {
      continue;
    }
    
    meta.time = now;
    meta.isStale = false;
    
    ados_refresh(ad, 0, false);
  }
};

ThreadUpdater.markDeletedReplies = function(newposts) {
  var i, j, posthash, oldposts, el;
  
  posthash = {};
  for (i = 0; j = newposts[i]; ++i) {
    posthash['pc' + j.no] = 1;
  }
  
  oldposts = $.cls('replyContainer');
  for (i = 0; j = oldposts[i]; ++i) {
    if (!posthash[j.id] && !$.hasClass(j, 'deleted')) {
      if (this.deletionQueue[j.id]) {
        el = document.createElement('img');
        el.src = Main.icons2.trash;
        el.className = 'trashIcon';
        el.title = 'This post has been deleted';
        $.addClass(j, 'deleted');
        $.cls('postNum', j)[1].appendChild(el);
        delete this.deletionQueue[j.id];
      }
      else {
        this.deletionQueue[j.id] = 1;
      }
    }
  }
};

ThreadUpdater.onload = function() {
  var i, el, state, self, nodes, thread, newposts, frag, lastrep, lastid,
    spoiler, op, doc, autoscroll, count, fromQR, lastRepPos;
  
  self = ThreadUpdater;
  nodes = [];
  
  self.setStatus('');
  
  if (this.status == 200) {
    self.lastModified = this.getResponseHeader('Last-Modified');
    
    thread = $.id('t' + Main.tid);
    
    lastrep = thread.children[thread.childElementCount - 1];
    lastid = +lastrep.id.slice(2);
    
    newposts = Parser.parseThreadJSON(this.responseText);
    
    state = !!newposts[0].archived;
    if (window.thread_archived !== undefined && state != window.thread_archived) {
      QR.enabled && $.id('quickReply') && QR.lock();
      Main.setThreadState('archived', state);
    }
    
    state = !!newposts[0].closed;
    if (state != Main.threadClosed) {
      if (newposts[0].archived) {
        state = false;
      }
      else if (QR.enabled && $.id('quickReply')) {
        if (state) {
          QR.lock();
        }
        else {
          QR.unlock();
        }
      }
      Main.setThreadState('closed', state);
    }
    
    state = !!newposts[0].sticky;
    if (state != Main.threadSticky) {
      Main.setThreadState('sticky', state);
    }
    
    state = !!newposts[0].imagelimit;
    if (QR.enabled && state != QR.fileDisabled) {
      QR.fileDisabled = state;
    }
    
    if (!Config.revealSpoilers && newposts[0].custom_spoiler) {
      Parser.setCustomSpoiler(Main.board, newposts[0].custom_spoiler);
    }
    
    for (i = newposts.length - 1; i >= 0; i--) {
      if (newposts[i].no <= lastid) {
        break;
      }
      nodes.push(newposts[i]);
    }
    
    count = nodes.length;
    
    if (count == 1 && QR.lastReplyId == nodes[0].no) {
      fromQR = true;
      QR.lastReplyId = null;
    }
    
    if (!fromQR) {
      self.markDeletedReplies(newposts);
    }
    
    if (count) {
      doc = document.documentElement;
      
      autoscroll = (
        Config.autoScroll
        && document[self.hidden]
        && doc.scrollHeight == (window.innerHeight + window.pageYOffset)
      );
      
      frag = document.createDocumentFragment();
      for (i = nodes.length - 1; i >= 0; i--) {
        frag.appendChild(Parser.buildHTMLFromJSON(nodes[i], Main.board));
      }
      thread.appendChild(frag);
      
      lastRepPos = lastrep.offsetTop;
      
      Parser.hasYouMarkers = false;
      Parser.hasHighlightedPosts = false;
      Parser.parseThread(thread.id.slice(1), -nodes.length);
      
      if (lastRepPos != lastrep.offsetTop) {
        window.scrollBy(0, lastrep.offsetTop - lastRepPos);
      }
      
      if (!fromQR) {
        if (!self.force && doc.scrollHeight > window.innerHeight) {
          if (!self.lastReply && lastid != Main.tid) {
            (self.lastReply = lastrep.lastChild).className += ' newPostsMarker';
          }
          if (Parser.hasYouMarkers) {
            self.setIcon('rep');
            if (self.audioEnabled && document[self.hidden]) {
              self.audio.play();
            }
          }
          else if (Parser.hasHighlightedPosts && self.currentIcon !== 'rep') {
            self.setIcon('hl');
          }
          else if (self.unreadCount == 0) {
            self.setIcon('new');
          }
          self.unreadCount += count;
          document.title = '(' + self.unreadCount + ') ' + self.pageTitle;
        }
        else {
          self.setStatus(count + ' new post' + (count > 1 ? 's' : ''));
        }
      }
      
      if (autoscroll) {
        window.scrollTo(0, document.documentElement.scrollHeight);
      }
      
      if (Config.threadWatcher) {
        ThreadWatcher.refreshCurrent(true);
      }
      
      if (Config.threadStats) {
        op = newposts[0];
        ThreadStats.update(op.replies, op.images, op.bumplimit, op.imagelimit);
      }
      
      self.invalidateAds();
      self.refreshAds();
      
      UA.dispatchEvent('4chanThreadUpdated', { count: count });
    }
    else {
      self.setStatus('No new posts');
    }
    
    if (newposts[0].archived) {
      self.setError('This thread is archived');
      if (!self.dead) {
        self.setIcon('dead');
        window.thread_archived = true;
        self.dead = true;
        self.stop();
      }
    }
  }
  else if (this.status == 304 || this.status == 0) {
    self.setStatus('No new posts');
  }
  else if (this.status == 404) {
    self.setIcon('dead');
    self.setError('This thread has been pruned or deleted');
    self.dead = true;
    self.stop();
    return;
  }
  
  self.lastUpdated = Date.now();
  self.adjustDelay(nodes.length);
  self.updating = self.force = false;
};

ThreadUpdater.onerror = function() {
  var self = ThreadUpdater;
  
  if (UA.isOpera && !this.statusText && this.status == 0) {
    self.setStatus('No new posts');
  }
  else {
    self.setError('Connection Error');
  }
  
  self.lastUpdated = Date.now();
  self.adjustDelay(0);
  self.updating = self.force = false;
};

ThreadUpdater.setStatus = function(msg) {
  this.statusNode.textContent = this.statusNodeBot.textContent = msg;
};

ThreadUpdater.setError = function(msg) {
  this.statusNode.innerHTML
    = this.statusNodeBot.innerHTML
    = '<span class="tu-error">' + msg + '</span>';
};

ThreadUpdater.setIcon = function(type) {
  var icon;
  
  if (type === null) {
    icon = this.defaultIcon;
  }
  else {
    icon = this.icons[Main.type + type];
  }
  
  this.currentIcon = type;
  this.iconNode.href = this.iconPath + icon;
  document.head.appendChild(this.iconNode);
};

ThreadUpdater.icons = {
  wsnew: 'favicon-ws-newposts.ico',
  nwsnew: 'favicon-nws-newposts.ico',
  wsrep: 'favicon-ws-newreplies.ico',
  nwsrep: 'favicon-nws-newreplies.ico',
  wsdead: 'favicon-ws-deadthread.ico',
  nwsdead: 'favicon-nws-deadthread.ico',
  wshl: 'favicon-ws-newfilters.ico',
  nwshl: 'favicon-nws-newfilters.ico'
};

/**
 * Thread stats
 */
var ThreadStats = {};

ThreadStats.init = function() {
  var i, cnt;
  
  this.nodeTop = document.createElement('div');
  this.nodeTop.className = 'thread-stats';
  this.nodeBot = this.nodeTop.cloneNode(false);
  
  cnt = $.cls('navLinks');
  cnt[1] && cnt[1].appendChild(this.nodeTop);
  cnt[2] && cnt[2].appendChild(this.nodeBot);
  
  this.pageNumber = null;
  this.update(null, null, window.bumplimit, window.imagelimit);
  
  if (!window.thread_archived) {
    this.updatePageNumber();
    this.pageInterval = setInterval(this.updatePageNumber, 3 * 60000);
  }
};

ThreadStats.update = function(replies, images, isBumpFull, isImageFull) {
  var stats, repStr, imgStr, pageStr, stateStr;
  
  if (replies === null) {
    replies = $.cls('replyContainer').length;
    images = $.cls('fileText').length - ($.id('fT' + Main.tid) ? 1 : 0);
  }
  
  stats = [];
  
  if (Main.threadSticky) {
    stats.push('Sticky');
  }
  
  if (window.thread_archived) {
    stats.push('Archived');
  }
  else if (Main.threadClosed) {
    stats.push('Closed');
  }
  
  if (isBumpFull) {
    stats.push('<em data-tip="Bump limit reached">' + replies + '</em>');
  }
  else {
    stats.push('<span data-tip="Replies">' + replies + '</span>');
  }
  
  if (isImageFull) {
    stats.push('<em data-tip="Image limit reached">' + images + '</em>');
  }
  else {
    stats.push('<span data-tip="Images">' + images + '</span>');
  }
  
  if (!window.thread_archived) {
    stats.push('<span data-tip="Page" class="ts-page">' + (this.pageNumber || '?') + '</span>');
  }
  
  this.nodeTop.innerHTML = this.nodeBot.innerHTML
    = stats.join(' / ');
};

ThreadStats.updatePageNumber = function() {
  $.get('//a.4cdn.org/' + Main.board + '/threads.json',
    {
      onload: ThreadStats.onCatalogLoad,
      onerror: ThreadStats.onCatalogError
    }
  );
};

ThreadStats.onCatalogLoad = function() {
  var self, i, j, page, post, threads, catalog, tid, nodes;
  
  self = ThreadStats;
  
  if (this.status == 200) {
    tid = +Main.tid;
    catalog = JSON.parse(this.responseText);
    for (i = 0; page = catalog[i]; ++i) {
      threads = page.threads;
      for (j = 0; post = threads[j]; ++j) {
        if (post.no == tid) {
          nodes = $.cls('ts-page');
          nodes[0].textContent = nodes[1].textContent = page.page
          self.pageNumber = page.page;
          return;
        }
      }
    }
    clearInterval(self.pageInterval);
  }
  else {
    ThreadStats.onCatalogError();
  }
};

ThreadStats.onCatalogError = function() {
  console.log('ThreadStats: couldn\'t get the catalog (' + this.status + ')');
};

/**
 * Filter
 */
var Filter = {};

Filter.init = function() {
  this.entities = document.createElement('div');
  Filter.load();
};

Filter.onClick = function(e) {
  var cmd;
  
  if (cmd = e.target.getAttribute('data-cmd')) {
    switch (cmd) {
      case 'filters-add':
        Filter.add();
        break;
      case 'filters-save':
        Filter.save();
        Filter.close();
        break;
      case 'filters-close':
        Filter.close();
        break;
      case 'filters-palette':
        Filter.openPalette(e.target);
        break;
      case 'filters-palette-close':
        Filter.closePalette();
        break;
      case 'filters-palette-clear':
        Filter.clearPalette();
        break;
      case 'filters-up':
        Filter.moveUp(e.target.parentNode.parentNode);
        break;
      case 'filters-del':
        Filter.remove(e.target.parentNode.parentNode);
        break;
      case 'filters-help-open':
        Filter.openHelp();
        break;
      case 'filters-help-close':
        Filter.closeHelp();
        break;
    }
  }
};

Filter.onPaletteClick = function(e) {
  var cmd;
  
  if (cmd = e.target.getAttribute('data-cmd')) {
    switch (cmd) {
      case 'palette-pick':
        Filter.pickColor(e.target);
        break;
      case 'palette-clear':
        Filter.pickColor(e.target, true);
        break;
      case 'palette-close':
        Filter.closePalette();
        break;
    }
  }
};

Filter.exec = function(cnt, pi, msg, tid) {
  var trip, name, com, uid, sub, fname, f, filters, hit, currentBoard;
  
  if (Parser.trackedReplies && Parser.trackedReplies['>>' + pi.id.slice(2)]) {
    return false;
  }
  
  currentBoard = Main.board;
  filters = Filter.activeFilters;
  hit = false;
  
  for (i = 0; f = filters[i]; ++i) {
    // boards
    if (f.boards && !f.boards[currentBoard]) {
      continue;
    }
    // tripcode
    if (f.type == 0) {
      if ((trip !== undefined || (trip = pi.getElementsByClassName('postertrip')[0])
        ) && f.pattern == trip.textContent) {
        hit = true;
        break;
      }
    }
    // name
    else if (f.type == 1) {
      if ((name || (name = pi.getElementsByClassName('name')[0]))
        && f.pattern == name.textContent) {
        hit = true;
        break;
      }
    }
    // comment
    else if (f.type == 2) {
      if (com === undefined) {
        this.entities.innerHTML
          = msg.innerHTML.replace(/<br>/g, '\n').replace(/[<[^>]+>/g, '');
        com = this.entities.textContent;
      }
      if (f.pattern.test(com)) {
        hit = true;
        break;
      }
    }
    // user id
    else if (f.type == 4) {
      if ((uid ||
          ((uid = pi.getElementsByClassName('posteruid')[0])
            && (uid = uid.firstElementChild.textContent)
          )
        ) && f.pattern == uid) {
        hit = true;
        break;
      }
    }
    // subject
    else if (!Main.tid && f.type == 5) {
      if ((sub ||
          ((sub = pi.getElementsByClassName('subject')[0])
            && (sub = sub.textContent)
          )
        ) && f.pattern.test(sub)) {
        hit = true;
        break;
      }
    }
    // filename
    else if (f.type == 6) {
      if (fname === undefined) {
        if ((fname = pi.parentNode.getElementsByClassName('fileText')[0])) {
          fname = fname.firstElementChild.textContent;
        }
        else {
          fname = '';
        }
      }
      if (f.pattern.test(fname)) {
        hit = true;
        break;
      }
    }
  }
  
  if (hit) {
    if (f.hide) {
      cnt.className += ' post-hidden';
      el = document.createElement('span');
      if (!tid) {
        el.textContent = '[View]';
        el.setAttribute('data-filtered', '1');
      }
      else {
        el.innerHTML = '[<a data-filtered="1" href="thread/' + tid + '">View</a>]';
      }
      el.className = 'filter-preview';
      pi.appendChild(el);
      return true;
    }
    else {
      cnt.className += ' filter-hl';
      cnt.style.boxShadow = '-3px 0 ' + f.color;
      Parser.hasHighlightedPosts = true;
    }
  }
  return false;
};

Filter.load = function() {
  var i, j, w, f, rawFilters, rawPattern, fid, regexEscape, regexType,
    wordSepS, wordSepE, words, inner, regexWildcard, replaceWildcard;
  
  this.activeFilters = [];
  
  if (!(rawFilters = localStorage.getItem('4chan-filters'))) {
    return;
  }
  
  rawFilters = JSON.parse(rawFilters);
  
  regexEscape = new RegExp('(\\'
    + ['/', '.', '*', '+', '?', '(', ')', '[', ']', '{', '}', '\\', '^', '$' ].join('|\\')
    + ')', 'g');
  regexType = /^\/(.*)\/(i?)$/;
  wordSepS = '(?=.*\\b';
  wordSepE = '\\b)';
  regexWildcard = /\\\*/g;
  replaceWildcard = '[^\\s]*';
  
  try {
    for (fid = 0; f = rawFilters[fid]; ++fid) {
      if (f.active && f.pattern != '') {
        // Boards
        if (f.boards) {
          tmp = f.boards.split(/[^a-z0-9]+/i);
          boards = {};
          for (i = 0; j = tmp[i]; ++i) {
            boards[j] = true;
          }
        }
        else {
          boards = false;
        }
        
        rawPattern = f.pattern;
        // Name, Tripcode or ID, string comparison
        if (!f.type || f.type == 1 || f.type == 4) {
          pattern = rawPattern;
        }
        // /RegExp/
        else if (match = rawPattern.match(regexType)) {
          pattern = new RegExp(match[1], match[2]);
        }
        // "Exact match"
        else if (rawPattern[0] == '"' && rawPattern[rawPattern.length - 1] == '"') {
          pattern = new RegExp(rawPattern.slice(1, -1).replace(regexEscape, '\\$1'));
        }
        // Full words, AND operator
        else {
          words = rawPattern.split(' ');
          pattern = '';
          for (i = 0, j = words.length; i < j; ++i) {
            inner = words[i]
              .replace(regexEscape, '\\$1')
              .replace(regexWildcard, replaceWildcard);
            pattern += wordSepS + inner + wordSepE;
          }
          pattern = new RegExp('^' + pattern, 'im');
        }
        //console.log('Resulting pattern: ' + pattern);
        this.activeFilters.push({
          type: f.type,
          pattern: pattern,
          boards: boards,
          color: f.color,
          hide: f.hide
        });
      }
    }
  }
  catch (e) {
    alert('There was an error processing one of the filters: '
      + e + ' in: ' + rawPattern);
  }
};

Filter.addSelection = function() {
  var text, type, node, sel = UA.getSelection(true);
  
  if (Filter.open() === false) {
    return;
  }
  
  if (typeof sel == 'string') {
    text = sel.trim();
  }
  else {
    node = sel.anchorNode.parentNode;
    text = sel.toString().trim();
    
    if ($.hasClass(node, 'name')) {
      type = 1;
    }
    else if ($.hasClass(node, 'postertrip')) {
      type = 0;
    }
    else if ($.hasClass(node, 'subject')) {
      type = 5;
    }
    else if ($.hasClass(node, 'posteruid') || $.hasClass(node, 'hand')) {
      type = 4;
    }
    else if ($.hasClass(node, 'fileText')) {
      type = 6;
    }
    else {
      type = 2;
    }
  }
  
  Filter.add(text, type);
};

Filter.openHelp = function() {
  var cnt;
  
  if ($.id('filtersHelp')) {
    return;
  }
  
  cnt = document.createElement('div');
  cnt.id = 'filtersHelp';
  cnt.className = 'UIPanel';
  cnt.setAttribute('data-cmd', 'filters-help-close');
  cnt.innerHTML = '\
<div class="extPanel reply"><div class="panelHeader">Filters &amp; Highlights Help\
<span><img alt="Close" title="Close" class="pointer" data-cmd="filters-help-close" src="'
+ Main.icons.cross + '"></span></div>\
<h4>Tripcode, Name and ID filters:</h4>\
<ul><li>Those use simple string comparison.</li>\
<li>Type them exactly as they appear on 4chan, including the exclamation mark for tripcode filters.</li>\
<li>Example: <code>!Ep8pui8Vw2</code></li></ul>\
<h4>Comment, Subject and E-mail filters:</h4>\
<ul><li><strong>Matching whole words:</strong></li>\
<li><code>feel</code> &mdash; will match <em>"feel"</em> but not <em>"feeling"</em>. This search is case-insensitive.</li></ul>\
<ul><li><strong>AND operator:</strong></li>\
<li><code>feel girlfriend</code> &mdash; will match <em>"feel"</em> AND <em>"girlfriend"</em> in any order.</li></ul>\
<ul><li><strong>Exact match:</strong></li>\
<li><code>"that feel when"</code> &mdash; place double quotes around the pattern to search for an exact string</li></ul>\
<ul><li><strong>Wildcards:</strong></li>\
<li><code>feel*</code> &mdash; matches expressions such as <em>"feel"</em>, <em>"feels"</em>, <em>"feeling"</em>, <em>"feeler"</em>, etc…</li>\
<li><code>idolm*ster</code> &mdash; this can match <em>"idolmaster"</em> or <em>"idolm@ster"</em>, etc…</li></ul>\
<ul><li><strong>Regular expressions:</strong></li>\
<li><code>/feel when no (girl|boy)friend/i</code></li>\
<li><code>/^(?!.*touhou).*$/i</code> &mdash; NOT operator.</li>\
<li><code>/^>/</code> &mdash; comments starting with a quote.</li>\
<li><code>/^$/</code> &mdash; comments with no text.</li></ul>\
<h4>Colors:</h4>\
<ul><li>The color field can accept any valid CSS color:</li>\
<li><code>red</code>, <code>#0f0</code>, <code>#00ff00</code>, <code>rgba( 34, 12, 64, 0.3)</code>, etc…</li></ul>\
<h4>Boards:</h4>\
<ul><li>A space separated list of boards on which the filter will be active. Leave blank to apply to all boards.</li></ul>\
<h4>Shortcut:</h4>\
<ul><li>If you have <code>Keyboard shortcuts</code> enabled, pressing <kbd>F</kbd> will add the selected text to your filters.</li></ul>';

  document.body.appendChild(cnt);
  cnt.addEventListener('click', this.onClick, false);
};

Filter.closeHelp = function() {
  var cnt;
  
  if (cnt = $.id('filtersHelp')) {
    cnt.removeEventListener('click', this.onClick, false);
    document.body.removeChild(cnt);
  }
};

Filter.open = function() {
  var i, f, cnt, menu, html, rawFilters, filterId, filterList;
  
  if ($.id('filtersMenu')) {
    return false;
  }
  
  cnt = document.createElement('div');
  cnt.id = 'filtersMenu';
  cnt.className = 'UIPanel';
  cnt.style.display = 'none';
  cnt.setAttribute('data-cmd', 'filters-close');
  cnt.innerHTML = '\
<div class="extPanel reply"><div class="panelHeader">Filters &amp; Highlights\
<span><img alt="Help" class="pointer" title="Help" data-cmd="filters-help-open" src="'
+ Main.icons.help
+ '"><img alt="Close" title="Close" class="pointer" data-cmd="filters-close" src="'
+ Main.icons.cross + '"></span></div>\
<table><thead><tr>\
<th>Order</th>\
<th>On</th>\
<th>Pattern</th>\
<th>Boards</th>\
<th>Type</th>\
<th>Color</th>\
<th>Hide</th>\
<th>Del</th>\
</tr></thead><tbody id="filter-list"></tbody><tfoot><tr><td colspan="8">\
<button data-cmd="filters-add">Add</button>\
<button class="right" data-cmd="filters-save">Save</button>\
</td></tr></tfoot></table></div>';
  
  document.body.appendChild(cnt);
  cnt.addEventListener('click', this.onClick, false);
  
  filterList = $.id('filter-list');
  
  if (rawFilters = localStorage.getItem('4chan-filters')) {
    rawFilters = JSON.parse(rawFilters);
    for (i = 0; f = rawFilters[i]; ++i) {
      filterList.appendChild(this.buildEntry(f, i));
    }
  }
  
  cnt.style.display = '';
};

Filter.close = function() {
  var cnt;
  
  if (cnt = $.id('filtersMenu')) {
    this.closePalette();
    cnt.removeEventListener('click', this.onClick, false);
    document.body.removeChild(cnt);
  }
};

Filter.moveUp = function(el) {
  var prev;
  
  if (prev = el.previousElementSibling) {
    el.parentNode.insertBefore(el, prev);
  }
};

Filter.add = function(pattern, type, boards) {
  var filter, id, el;
  
  filter = {
    active: true,
    type: type || 0,
    pattern: pattern || '',
    boards: boards || '',
    color: '',
    hide: false
  };
  
  id = this.getNextFilterId();
  el = this.buildEntry(filter, id);
  
  $.id('filter-list').appendChild(el);
  $.cls('fPattern', el)[0].focus();
};

Filter.remove = function(tr) {
  $.id('filter-list').removeChild(tr);
};

Filter.save = function() {
  var i, rawFilters, entries, tr, f, color, type;
  
  rawFilters = [];
  entries = $.id('filter-list').children;
  
  for (i = 0; tr = entries[i]; ++i) {
    type = tr.children[4].firstChild;
    f = {
      active: tr.children[1].firstChild.checked,
      pattern: tr.children[2].firstChild.value,
      boards: tr.children[3].firstChild.value,
      type: +type.options[type.selectedIndex].value,
      hide: tr.children[6].firstChild.checked
    }
    
    color = tr.children[5].firstChild;
    
    if (!color.hasAttribute('data-nocolor')) {
      f.color = color.style.backgroundColor;
    }
    
    rawFilters.push(f);
  }
  
  if (rawFilters[0]) {
    localStorage.setItem('4chan-filters', JSON.stringify(rawFilters));
  }
  else {
    localStorage.removeItem('4chan-filters');
  }
};

Filter.getNextFilterId = function() {
  var i, j, max, entries = $.id('filter-list').children;
  
  if (!entries.length) {
    return 0;
  }
  else {
    max = 0;
    for (i = 0; j = entries[i]; ++i) {
      j = +j.id.slice(7);
      if (j > max) {
        max = j;
      }
    }
    return max + 1;
  }
};

Filter.buildEntry = function(filter, id) {
  var tr, html, sel;
  
  tr = document.createElement('tr');
  tr.id = 'filter-' + id;
  
  html = '';
  
  // Move up
  html += '<td><span data-cmd="filters-up" class="pointer">&uarr;</span></td>';
  
  // On
  html += '<td><input type="checkbox"'
    + (filter.active ? ' checked="checked"></td>' : '></td>');
  
  // Pattern
  html += '<td><input class="fPattern" type="text" value="'
    + filter.pattern.replace(/"/g, '&quot;') + '"></td>';
  
  // Boards
  html += '<td><input class="fBoards" type="text" value="'
    + (filter.boards !== undefined ? filter.boards : '') + '"></td>';
  
  // FIXME
  if (filter.type === 3) {
    filter.type = 4;
  }
  
  // Type
  sel = [ '', '', '', '', '', '', '' ];
  sel[filter.type] = ' selected="selected"';
  
  html += '<td><select size="1"><option value="0"'
    + sel[0] + '>Tripcode</option><option value="1"'
    + sel[1] + '>Name</option><option value="2"'
    + sel[2] + '>Comment</option><option value="4"'
    + sel[4] + '>ID</option><option value="5"'
    + sel[5] + '>Subject</option><option value="6"'
    + sel[6] + '>Filename</option></select></td>';
  
  // Color
  html += '<td><span data-cmd="filters-palette" title="Change Color" class="colorbox fColor" ';
  
  if (!filter.color) {
    html += ' data-nocolor="1">&#x2215;';
  }
  else {
    html += ' style="background-color:' + filter.color + '">';
  }
  html += '</span></td>';
  
  // Hide
  html += '<td><input type="checkbox"'
    + (filter.hide ? ' checked="checked"></td>' : '></td>');
  
  // Del
  html += '<td><span data-cmd="filters-del" class="pointer fDel">&times;</span></td>';
  
  tr.innerHTML = html;
  
  return tr;
}

Filter.buildPalette = function(id) {
  var i, j, cnt, html, colors, rowCount, colCount;
  
  colors = [
    ['#E0B0FF', '#F2F3F4', '#7DF9FF', '#FFFF00'],
    ['#FBCEB1', '#FFBF00', '#ADFF2F', '#0047AB'],
    ['#00A550', '#007FFF', '#AF0A0F', '#B5BD68']
  ];
  
  rowCount = colors.length;
  colCount = colors[0].length;
  
  html = '<div id="colorpicker" class="reply extPanel"><table><tbody>';
  
  for (i = 0; i < rowCount; ++i) {
    html += '<tr>'
    for (j = 0; j < colCount; ++j) {
      html += '<td><div data-cmd="palette-pick" class="colorbox" style="background:'
        + colors[i][j] + '"></div></td>';
    }
    html += '</tr>'
  }
  
  html += '</tbody></table>Custom\
<div id="palette-custom"><input id="palette-custom-input" type="text">\
<div id="palette-custom-ok" data-cmd="palette-pick" title="Select Color" class="colorbox"></div></div>\
[<a href="javascript:;" data-cmd="palette-close">Close</a>]\
[<a href="javascript:;" data-cmd="palette-clear">Clear</a>]</div>';
  
  cnt = document.createElement('div');
  cnt.id = 'filter-palette';
  cnt.setAttribute('data-target', id);
  cnt.setAttribute('data-cmd', 'palette-close');
  cnt.className = 'UIMenu';
  cnt.innerHTML = html;
  
  return cnt;
};

Filter.openPalette = function(target) {
  var el, pos, id, picker;
  
  Filter.closePalette();
  
  pos = target.getBoundingClientRect();
  id = target.parentNode.parentNode.id.slice(7);
  
  el = Filter.buildPalette(id);
  document.body.appendChild(el);
  
  $.id('filter-palette').addEventListener('click', Filter.onPaletteClick, false);
  $.id('palette-custom-input').addEventListener('keyup', Filter.setCustomColor, false);
  
  picker = el.firstElementChild;
  picker.style.cssText = 'top:' + pos.top + 'px;left:'
    + (pos.left - picker.clientWidth - 10) + 'px;';
};

Filter.closePalette = function() {
  var el;
  
  if (el = $.id('filter-palette')) {
    $.id('filter-palette').removeEventListener('click', Filter.onPaletteClick, false);
    $.id('palette-custom-input').removeEventListener('keyup', Filter.setCustomColor, false);
    el.parentNode.removeChild(el);
  }
};

Filter.pickColor = function(el, clear) {
  var id, target;
  
  id = $.id('filter-palette').getAttribute('data-target');
  target = $.id('filter-' + id);
  
  if (!target) {
    return;
  }
  
  target = $.cls('colorbox', target)[0];
  
  if (clear === true) {
    target.setAttribute('data-nocolor', '1');
    target.innerHTML = '&#x2215;';
    target.style.background = '';
  }
  else {
    target.removeAttribute('data-nocolor');
    target.innerHTML = '';
    target.style.background = el.style.backgroundColor;
  }
  
  Filter.closePalette();
};

Filter.setCustomColor = function() {
  var input, box;
  
  input = $.id('palette-custom-input');
  box = $.id('palette-custom-ok');
  
  box.style.backgroundColor = input.value;
};

/**
 * ID colors
 */
var IDColor = {
  css: 'padding: 0 5px; border-radius: 6px; font-size: 0.8em;',
  ids: {}
};

IDColor.init = function() {
  var style;
  
  if (window.user_ids) {
    this.enabled = true;
    
    style = document.createElement('style');
    style.setAttribute('type', 'text/css');
    style.textContent = '.posteruid .hand {' + this.css + '}';
    document.head.appendChild(style);
  }
};

IDColor.compute = function(str) {
  var rgb, hash;
  
  rgb = [];
  hash = $.hash(str);
  
  rgb[0] = (hash >> 24) & 0xFF;
  rgb[1] = (hash >> 16) & 0xFF;
  rgb[2] = (hash >> 8) & 0xFF;
  rgb[3] = ((rgb[0] * 0.299) + (rgb[1] * 0.587) + (rgb[2] * 0.114)) > 125;
  
  this.ids[str] = rgb;
  
  return rgb;
};

IDColor.apply = function(uid) {
  var rgb;
  
  rgb = IDColor.ids[uid.textContent] || IDColor.compute(uid.textContent);
  uid.style.cssText = '\
    background-color: rgb(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ');\
    color: ' + (rgb[3] ? 'black;' : 'white;');
};

IDColor.applyRemote = function(uid) {
  this.apply(uid);
  uid.style.cssText += this.css;
};

/**
 * SWF embed
 */
var SWFEmbed = {};

SWFEmbed.init = function() {
  if (Main.tid) {
    this.processThread();
  }
  else {
    this.processIndex();
  }
};

SWFEmbed.processThread = function() {
  var fileText, el;
  
  fileText = $.id('fT' + Main.tid);
  
  if (!fileText) {
    return;
  }
  
  el = document.createElement('a');
  el.href = 'javascript:;';
  el.textContent = 'Embed';
  el.addEventListener('click', SWFEmbed.toggleThread, false);
  
  fileText.appendChild(document.createTextNode('-['));
  fileText.appendChild(el);
  fileText.appendChild(document.createTextNode(']'));
};

SWFEmbed.processIndex = function() {
  var i, tr, el, cnt, nodes, srcIndex, src;
  
  srcIndex = 2;
  
  cnt = $.cls('postblock')[0];
  
  if (!cnt) {
    return;
  }
  
  tr = cnt.parentNode;
  
  el = document.createElement('td');
  el.className = 'postblock';
  tr.insertBefore(el, tr.children[srcIndex].nextElementSibling);
  
  cnt = $.cls('flashListing')[0];
  
  if (!cnt) {
    return;
  }
  
  nodes = $.tag('tr', cnt);
  
  for (i = 1; tr = nodes[i]; ++i) {
    src = tr.children[srcIndex].firstElementChild;
    el = document.createElement('td');
    el.innerHTML = '[<a href="' + src.href + '">Embed</a>]';
    el.firstElementChild.addEventListener('click', SWFEmbed.embedIndex, false);
    tr.insertBefore(el, tr.children[srcIndex].nextElementSibling);
  };
};

SWFEmbed.toggleThread = function(e) {
  var cnt, link, el, post, maxWidth, ratio, width, height;
  
  if (cnt = $.id('swf-embed')) {
    cnt.parentNode.removeChild(cnt);
    e.target.textContent = 'Embed';
    return;
  }
  
  link = $.tag('a', e.target.parentNode)[0];
  
  maxWidth = document.documentElement.clientWidth - 100;
  
  width = +link.getAttribute('data-width');
  height = +link.getAttribute('data-height');
  
  if (width > maxWidth) {
    ratio = width / height;
    width = maxWidth;
    height = Math.round(maxWidth / ratio);
  }
  
  cnt = document.createElement('div');
  cnt.id = 'swf-embed';
  
  el = document.createElement('embed');
  el.setAttribute('allowScriptAccess', 'never');
  el.type = 'application/x-shockwave-flash';
  el.width = width;
  el.height = height;
  el.src = link.href;
  
  cnt.appendChild(el);
  
  post = $.id('m' + Main.tid);
  post.insertBefore(cnt, post.firstChild);
  
  $.cls('thread')[0].scrollIntoView(true);
  
  e.target.textContent = 'Remove';
};

SWFEmbed.embedIndex = function(e) {
  var el, cnt, header, icon, backdrop, width, height, cntWidth, cntHeight,
    maxWidth, maxHeight, docWidth, docHeight, margins, headerHeight, fileName;
  
  e.preventDefault();
  
  margins = 10;
  headerHeight = 20;
  
  el = e.target.parentNode.parentNode.children[2].firstElementChild;
  
  fileName = el.getAttribute('title') || el.textContent;
  
  cntWidth = width = +el.getAttribute('data-width');
  cntHeight = height = +el.getAttribute('data-height');
  
  docWidth = document.documentElement.clientWidth;
  docHeight = document.documentElement.clientHeight;
  
  maxWidth = docWidth - margins;
  maxHeight = docHeight - margins - headerHeight;
  
  ratio = width / height;
  
  if (cntWidth > maxWidth) {
    cntWidth = maxWidth;
    cntHeight = Math.round(maxWidth / ratio);
  }
  
  if (cntHeight > maxHeight) {
    cntHeight = maxHeight;
    cntWidth = Math.round(maxHeight * ratio);
  }
  
  el = document.createElement('embed');
  el.setAttribute('allowScriptAccess', 'never');
  el.src = e.target.href;
  el.width = '100%';
  el.height = '100%';
  
  cnt = document.createElement('div');
  cnt.style.position = 'fixed';
  cnt.style.width = cntWidth + 'px';
  cnt.style.height = cntHeight + 'px';
  cnt.style.top = '50%';
  cnt.style.left = '50%';
  cnt.style.marginTop = (-cntHeight / 2 - headerHeight / 2) + 'px';
  cnt.style.marginLeft = (-cntWidth / 2) + 'px';
  cnt.style.background = 'white';
  
  header = document.createElement('div');
  header.id = 'swf-embed-header';
  header.className = 'postblock';
  header.textContent = fileName + ', ' + width + 'x' + height;
  
  icon = document.createElement('img');
  icon.id = 'swf-embed-close';
  icon.className = 'pointer';
  icon.src = Main.icons.cross;
  
  header.appendChild(icon);
  
  cnt.appendChild(header);
  cnt.appendChild(el);
  
  backdrop = document.createElement('div');
  backdrop.id = 'swf-embed';
  backdrop.style.cssText = 'width: 100%; height: 100%; position: fixed;\
  top: 0; left: 0; background: rgba(128, 128, 128, 0.5)';
  
  backdrop.appendChild(cnt);
  backdrop.addEventListener('click', SWFEmbed.onBackdropClick, false);
  
  document.body.appendChild(backdrop);
};

SWFEmbed.onBackdropClick = function(e) {
  var backdrop = $.id('swf-embed');
  
  if (e.target === backdrop || e.target.id == 'swf-embed-close') {
    backdrop.removeEventListener('click', SWFEmbed.onBackdropClick, false);
    backdrop.parentNode.removeChild(backdrop);
  }
};

/**
 * Media
 */
var Media = {};

Media.init = function() {
  this.matchSC = /(?:soundcloud\.com|snd\.sc)\/[^\s<]+(?:<wbr>)?[^\s<]*/g;
  this.matchYT = /(?:youtube\.com\/watch\?[^\s]*?v=|youtu\.be\/)[^\s<]+(?:<wbr>)?[^\s<]*(?:<wbr>)?[^\s<]*/g;
  this.toggleYT = /(?:v=|\.be\/)([a-zA-Z0-9_-]{11})/;
  this.timeYT = /#t=([ms0-9]+)/;
  this.matchVocaroo = /vocaroo\.com\/i\/([a-z0-9]{12})/gi;
  
  this.map = {
    yt: this.toggleYouTube,
    sc: this.toggleSoundCloud,
    vocaroo: this.toggleVocaroo
  };
};

Media.parseSoundCloud = function(msg) {
  msg.innerHTML = msg.innerHTML.replace(this.matchSC, this.replaceSoundCloud);
};

Media.replaceSoundCloud = function(link) {
  return '<span>' + link + '</span> [<a href="javascript:;" data-cmd="embed" data-type="sc">Embed</a>]';
};

Media.toggleSoundCloud = function(node) {
  var xhr, url;
  
  if (node.textContent == 'Remove') {
    node.parentNode.removeChild(node.nextElementSibling);
    node.textContent = 'Embed';
  }
  else if (node.textContent == 'Embed') {
    url = node.previousElementSibling.textContent;
    
    xhr = new XMLHttpRequest();
    xhr.open('GET', '//soundcloud.com/oembed?show_artwork=false&'
      + 'maxwidth=500px&show_comments=false&format=json&url='
      + 'http://' + url);
    xhr.onload = function() {
      var el;
      
      if (this.status == 200 || this.status == 304) {
        el = document.createElement('div');
        el.className = 'media-embed';
        el.innerHTML = JSON.parse(this.responseText).html;
        node.parentNode.insertBefore(el, node.nextElementSibling);
        node.textContent = 'Remove';
      }
      else {
        node.textContent = 'Error';
        console.log('SoundCloud Error (HTTP ' + this.status + ')');
      }
    };
    node.textContent = 'Loading...';
    xhr.send(null);
  }
};

Media.parseYouTube = function(msg) {
  msg.innerHTML = msg.innerHTML.replace(this.matchYT, this.replaceYouTube);
};

Media.replaceYouTube = function(link) {
  return '<span>' + link + '</span> [<a href="javascript:;" data-cmd="embed" data-type="yt">Embed</a>]';
};

Media.showYTPreview = function(link) {
  var cnt, img, vid, aabb, x, y, tw, th, pad;
  
  tw = 320; th = 180; pad = 5;
  
  aabb = link.getBoundingClientRect();
  
  vid = link.previousElementSibling.textContent.match(this.toggleYT)[1];
  
  if (aabb.right + tw + pad > $.docEl.clientWidth) {
    x = aabb.left - tw - pad;
  }
  else {
    x = aabb.right + pad;
  }
  
  y = aabb.top - th / 2 + aabb.height / 2;
  
  img = document.createElement('img');
  img.width = tw;
  img.height = th;
  img.alt = '';
  img.src = '//i1.ytimg.com/vi/' + encodeURIComponent(vid) + '/mqdefault.jpg';
  
  cnt = document.createElement('div');
  cnt.id = 'yt-preview';
  cnt.className = 'reply';
  cnt.style.left = (x + window.pageXOffset) + 'px';
  cnt.style.top = (y + window.pageYOffset) + 'px';
  
  cnt.appendChild(img);
  
  document.body.appendChild(cnt);
};

Media.removeYTPreview = function() {
  var el;
  
  if (el = $.id('yt-preview')) {
    document.body.removeChild(el);
  }
}

Media.toggleYouTube = function(node) {
  var vid, time, el, url;
  
  if (node.textContent == 'Remove') {
    node.parentNode.removeChild(node.nextElementSibling);
    node.textContent = 'Embed';
  }
  else {
    url = node.previousElementSibling.textContent;
    vid = url.match(this.toggleYT);
    time = url.match(this.timeYT);
    
    if (vid && (vid = vid[1])) {
      vid = encodeURIComponent(vid);
      
      if (time && (time = time[1])) {
        vid += '#t=' + encodeURIComponent(time);
      }
      
      el = document.createElement('div');
      el.className = 'media-embed';
      el.innerHTML = '<iframe src="//www.youtube.com/embed/'
        + vid
        + '" width="640" height="360" frameborder="0"></iframe>'
      
      node.parentNode.insertBefore(el, node.nextElementSibling);
      
      node.textContent = 'Remove';
    }
    else {
      node.textContent = 'Error';
    }
  }
};

Media.parseVocaroo = function(msg) {
  msg.innerHTML = msg.innerHTML.replace(this.matchVocaroo, this.replaceVocaroo);
};

Media.replaceVocaroo = function(link) {
  return '<span>' + link + '</span> [<a href="javascript:;" data-cmd="embed" data-type="vocaroo">Embed</a>]';
};

Media.toggleVocaroo = function(node) {
  var vid, time, el, url;
  
  if (node.textContent == 'Remove') {
    node.parentNode.removeChild(node.nextElementSibling);
    node.textContent = 'Embed';
  }
  else {
    url = node.previousElementSibling.textContent;
    vid = url.match(Media.matchVocaroo);
    
    if (vid && (vid = vid[0].split('/').pop())) {
      vid = encodeURIComponent(vid);
      
      el = document.createElement('div');
      el.className = 'media-embed';
      el.innerHTML = '<embed width="220" height="140" class="media-embed" '
        + 'src="//vocaroo.com/mediafoo.swf?playMediaID=' + vid + '&autoplay=0">';
      
      node.parentNode.insertBefore(el, node.nextElementSibling);
      
      node.textContent = 'Remove';
    }
    else {
      node.textContent = 'Error';
    }
  }
};

Media.toggleEmbed = function(node) {
  var fn, type = node.getAttribute('data-type');
  
  if (type && (fn = Media.map[type])) {
    fn.call(this, node);
  }
};

/**
 * Custom CSS
 */
var CustomCSS = {};

CustomCSS.init = function() {
  var style, css;
  if (css = localStorage.getItem('4chan-css')) {
    style = document.createElement('style');
    style.id = 'customCSS';
    style.setAttribute('type', 'text/css');
    style.textContent = css;
    document.head.appendChild(style);
  }
};

CustomCSS.open = function() {
  var cnt, ta, data;
  
  if ($.id('customCSSMenu')) {
    return;
  }
  
  cnt = document.createElement('div');
  cnt.id = 'customCSSMenu';
  cnt.className = 'UIPanel';
  cnt.setAttribute('data-cmd', 'css-close');
  cnt.innerHTML = '\
<div class="extPanel reply"><div class="panelHeader">Custom CSS\
<span><img alt="Close" title="Close" class="pointer" data-cmd="css-close" src="'
+ Main.icons.cross + '"></span></div>\
<textarea id="customCSSBox"></textarea>\
<div class="center"><button data-cmd="css-save">Save CSS</button></div>\
</td></tr></tfoot></table></div>';
  
  document.body.appendChild(cnt);
  
  cnt.addEventListener('click', this.onClick, false);
  
  ta = $.id('customCSSBox');
  
  if (data = localStorage.getItem('4chan-css')) {
    ta.textContent = data;
  }
  
  ta.focus();
};

CustomCSS.save = function() {
  var ta, style;
  
  if (ta = $.id('customCSSBox')) {
    localStorage.setItem('4chan-css', ta.value);
    if (Config.customCSS && (style = $.id('customCSS'))) {
      document.head.removeChild(style);
      CustomCSS.init();
    }
  }
};

CustomCSS.close = function() {
  var cnt;
  
  if (cnt = $.id('customCSSMenu')) {
    cnt.removeEventListener('click', this.onClick, false);
    document.body.removeChild(cnt);
  }
};

CustomCSS.onClick = function(e) {
  var cmd;
  
  if (cmd = e.target.getAttribute('data-cmd')) {
    switch (cmd) {
      case 'css-close':
        CustomCSS.close();
        break;
      case 'css-save':
        CustomCSS.save();
        CustomCSS.close();
        break;
    }
  }
};

/**
 * Keyboard shortcuts
 */
var Keybinds = {};

Keybinds.init = function() {
  this.map = {
    // A
    65: function() {
      if (ThreadUpdater.enabled) ThreadUpdater.toggleAuto();
    },
    // F
    70: function() {
      if (Config.filter) {
        Filter.addSelection();
      }
    },
    // Q
    81: function() {
      if (QR.enabled && Main.tid) {
        QR.quotePost(Main.tid);
      }
    },
    // R
    82: function() {
      if (ThreadUpdater.enabled) ThreadUpdater.forceUpdate();
    },
    // W
    87: function() {
      if (Config.threadWatcher && Main.tid) ThreadWatcher.toggle(Main.tid);
    },
    // B
    66: function() {
      var el;
      (el = $.cls('prev')[0]) && (el = $.tag('form', el)[0]) && el.submit();
    },
    // C
    67: function() {
      location.href = '/' + Main.board + '/catalog';
    },
    // N
    78: function() {
      var el;
      (el = $.cls('next')[0]) && (el = $.tag('form', el)[0]) && el.submit();
    },
    // I
    73: function() {
      location.href = '/' + Main.board + '/';
    }
  };
  
  document.addEventListener('keydown', this.resolve, false);
};

Keybinds.resolve = function(e) {
  var bind, el = e.target;
  
  if (el.nodeName == 'TEXTAREA' || el.nodeName == 'INPUT') {
    return;
  }
  
  bind = Keybinds.map[e.keyCode];
  
  if (bind && !e.altKey && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
    e.preventDefault();
    e.stopPropagation();
    bind();
  }
};

Keybinds.open = function() {
  var cnt;
  
  if ($.id('keybindsHelp')) {
    return;
  }
  
  cnt = document.createElement('div');
  cnt.id = 'keybindsHelp';
  cnt.className = 'UIPanel';
  cnt.setAttribute('data-cmd', 'keybinds-close');
  cnt.innerHTML = '\
<div class="extPanel reply"><div class="panelHeader">Keyboard Shortcuts\
<span><img data-cmd="keybinds-close" class="pointer" alt="Close" title="Close" src="'
+ Main.icons.cross + '"></span></div>\
<ul>\
<li><strong>Global</strong></li>\
<li><kbd>A</kbd> &mdash; Toggle auto-updater</li>\
<li><kbd>Q</kbd> &mdash; Open Quick Reply</li>\
<li><kbd>R</kbd> &mdash; Update thread</li>\
<li><kbd>W</kbd> &mdash; Watch/Unwatch thread</li>\
<li><kbd>B</kbd> &mdash; Previous page</li>\
<li><kbd>N</kbd> &mdash; Next page</li>\
<li><kbd>I</kbd> &mdash; Return to index</li>\
<li><kbd>C</kbd> &mdash; Open catalog</li>\
<li><kbd>F</kbd> &mdash; Filter selected text</li>\
</ul><ul>\
<li><strong>Quick Reply (always enabled)</strong></li>\
<li><kbd>Ctrl + Click</kbd> the post number &mdash; Quote without linking</li>\
<li><kbd>Ctrl + S</kbd> &mdash; Spoiler tags</li>\
<li><kbd>Esc</kbd> &mdash; Close the Quick Reply</li>\
</ul>';

  document.body.appendChild(cnt);
  cnt.addEventListener('click', this.onClick, false);
};

Keybinds.close = function() {
  var cnt;
  
  if (cnt = $.id('keybindsHelp')) {
    cnt.removeEventListener('click', this.onClick, false);
    document.body.removeChild(cnt);
  }
};

Keybinds.onClick = function(e) {
  var cmd;
  
  if ((cmd = e.target.getAttribute('data-cmd')) && cmd == 'keybinds-close') {
    Keybinds.close();
  }
};

/**
 * Reporting
 */
var Report = {
  init: function() {
    window.addEventListener('message', Report.onMessage, false);
  }
};

Report.onMessage = function(e) {
  var id;
  
  if (e.origin === 'https://sys.4chan.org' && /^done-report/.test(e.data)) {
    id = e.data.split('-')[2];
    
    if (Config.threadHiding && $.id('t' + id)) {
      if (!ThreadHiding.isHidden(id)) {
        ThreadHiding.hide(id);
        ThreadHiding.save();
      }
      
      return;
    }
    
    if ($.id('p' + id)) {
      if (!ReplyHiding.isHidden(id)) {
        ReplyHiding.hide(id);
        ReplyHiding.save();
      }
      
      return;
    }
  }
};

Report.open = function(pid, board) {
  window.open('https://sys.4chan.org/'
    + (board || Main.board) + '/imgboard.php?mode=report&no=' + pid
    , Date.now(),
    "toolbar=0,scrollbars=0,location=0,status=1,menubar=0,resizable=1,width=600,height=170");
};

/**
 * Custom Menu
 */
var CustomMenu = {};

CustomMenu.reset = function() {
  var i, el, full, custom, navs;
  
  full = $.cls('boardList');
  custom = $.cls('customBoardList');
  navs = $.cls('show-all-boards');
  
  for (i = 0; el = navs[i]; ++i) {
    el.removeEventListener('click', CustomMenu.reset, false);
  }
  
  for (i = custom.length - 1; el = custom[i]; i--) {
    full[i].style.display = null;
    el.parentNode.removeChild(el);
  }
};

CustomMenu.apply = function(str) {
  var i, j, el, cntBottom, board, navs, boardList, more;
  
  if (!str) {
    return;
  }
  
  boardList = str.split(/[^0-9a-z]/i);
  
  cnt = document.createElement('span');
  cnt.className = 'customBoardList';
  
  for (i = 0; board = boardList[i]; ++i) {
    if (i) {
      cnt.appendChild(document.createTextNode(' / '));
    }
    else {
      cnt.appendChild(document.createTextNode('['));
    }
    el = document.createElement('a');
    el.textContent = board;
    el.href = '//boards.4chan.org/' + board + '/';
    cnt.appendChild(el);
  }
  
  cnt.appendChild(document.createTextNode(']'));
  
  cnt.appendChild(document.createTextNode(' ['));
  el = document.createElement('a');
  el.textContent = '…';
  el.title = 'Show all';
  el.className = 'show-all-boards pointer';
  cnt.appendChild(el);
  cnt.appendChild(document.createTextNode('] '));
  
  cntBottom = cnt.cloneNode(true);
  
  navs = $.cls('boardList');
  
  for (i = 0; el = navs[i]; ++i) {
    el.style.display = 'none';
    el.parentNode.insertBefore(i ? cntBottom : cnt, el);
  }
  
  navs = $.cls('show-all-boards');
  
  for (i = 0; el = navs[i]; ++i) {
    el.addEventListener('click', CustomMenu.reset, false);
  }
};

CustomMenu.onClick = function(e) {
  var t;
  
  if ((t = e.target) == document) {
    return;
  }

  if (t.hasAttribute('data-close')) {
    CustomMenu.closeEditor();
  }
  else if (t.hasAttribute('data-save')) {
    CustomMenu.save();
  }
};

CustomMenu.showEditor = function() {
  var cnt;
  
  cnt = document.createElement('div');
  cnt.id = 'customMenu';
  cnt.className = 'UIPanel';
  cnt.setAttribute('data-close', '1');
  cnt.innerHTML = '\
<div class="extPanel reply"><div class="panelHeader">Custom Board List\
<span><img alt="Close" title="Close" class="pointer" data-close="1" src="'
+ Main.icons.cross + '"></a></span></div>\
<input placeholder="Example: jp tg mu" id="customMenuBox" type="text" value="">\
<div class="center"><button data-save="1">Save</button></div></div>';

  document.body.appendChild(cnt);
  
  if (Config.customMenuList) {
    $.id('customMenuBox').value = Config.customMenuList;
  }
  
  cnt.addEventListener('click', CustomMenu.onClick, false);
};

CustomMenu.closeEditor = function() {
  var el;
  
  if (el = $.id('customMenu')) {
    el.removeEventListener('click', CustomMenu.onClick, false);
    document.body.removeChild(el);
  }
};

CustomMenu.save = function() {
  var input;

  if (input = $.id('customMenuBox')) {
    Config.customMenuList = input.value;
  }
  
  CustomMenu.closeEditor();
};

/**
 * Draggable helper
 */
var Draggable = {
  el: null,
  key: null,
  scrollX: null,
  scrollY: null,
  dx: null, dy: null, right: null, bottom: null,
  
  set: function(handle) {
    handle.addEventListener('mousedown', Draggable.startDrag, false);
  },
  
  unset: function(handle) {
    handle.removeEventListener('mousedown', Draggable.startDrag, false);
  },
  
  startDrag: function(e) {
    var self, doc, offs;
    
    if (this.parentNode.hasAttribute('data-shiftkey') && !e.shiftKey) {
      return;
    }
    
    e.preventDefault();
    
    self = Draggable;
    doc = document.documentElement;
    
    self.el = this.parentNode;
    
    self.key = self.el.getAttribute('data-trackpos');
    offs = self.el.getBoundingClientRect();
    self.dx = e.clientX - offs.left;
    self.dy = e.clientY - offs.top;
    self.right = doc.clientWidth - offs.width;
    self.bottom = doc.clientHeight - offs.height;
    
    if (getComputedStyle(self.el, null).position != 'fixed') {
      self.scrollX = window.pageXOffset;
      self.scrollY = window.pageYOffset;
    }
    else {
      self.scrollX = self.scrollY = 0;
    }
    
    document.addEventListener('mouseup', self.endDrag, false);
    document.addEventListener('mousemove', self.onDrag, false);
  },
  
  endDrag: function(e) {
    document.removeEventListener('mouseup', Draggable.endDrag, false);
    document.removeEventListener('mousemove', Draggable.onDrag, false);
    if (Draggable.key) {
      Config[Draggable.key] = Draggable.el.style.cssText;
      Config.save();
    }
    delete Draggable.el;
  },
  
  onDrag: function(e) {
    var left, top, style;
    
    left = e.clientX - Draggable.dx + Draggable.scrollX;
    top = e.clientY - Draggable.dy + Draggable.scrollY;
    style = Draggable.el.style;
    if (left < 1) {
      style.left = '0';
      style.right = '';
    }
    else if (Draggable.right < left) {
      style.left = '';
      style.right = '0';
    }
    else {
      style.left = (left / document.documentElement.clientWidth * 100) + '%';
      style.right = '';
    }
    if (top < 1) {
      style.top = '0';
      style.bottom = '';
    }
    else if (Draggable.bottom < top) {
      style.bottom = '0';
      style.top = '';
    }
    else {
      style.top = (top / document.documentElement.clientHeight * 100) + '%';
      style.bottom = '';
    }
  }
};

/**
 * User Agent
 */
var UA = {};

UA.init = function() {
  document.head = document.head || $.tag('head')[0];
  
  this.isOpera = Object.prototype.toString.call(window.opera) == '[object Opera]';
  
  this.hasCORS = 'withCredentials' in new XMLHttpRequest;
  
  this.hasFormData = 'FormData' in window;
  
  this.hasDragAndDrop = false; /*'draggable' in document.createElement('div');*/
};

UA.dispatchEvent = function(name, detail) {
  var e = document.createEvent('Event');
  e.initEvent(name, false, false);
  if (detail) {
    e.detail = detail;
  }
  document.dispatchEvent(e);
};

UA.getSelection = function(raw) {
  var sel;
  
  if (UA.isOpera && typeof (sel = document.getSelection()) == 'string') {}
  else {
    sel = window.getSelection();
    
    if (!raw) {
      sel = sel.toString();
    }
  }
  
  return sel;
};

/**
 * Config
 */
var Config = {
  quotePreview: true,
  backlinks: true,
  quickReply: true,
  threadUpdater: true,
  threadHiding: true,
  
  alwaysAutoUpdate: false,
  topPageNav: false,
  threadWatcher: false,
  imageExpansion: true,
  fitToScreenExpansion: false,
  threadExpansion: true,
  alwaysDepage: false,
  localTime: true,
  stickyNav: false,
  keyBinds: false,
  inlineQuotes: false,

  filter: false,
  revealSpoilers: false,
  imageHover: false,
  threadStats: true,
  IDColor: true,
  noPictures: false,
  embedYouTube: true,
  embedSoundCloud: false,
  updaterSound: false,

  customCSS: false,
  autoScroll: false,
  hideStubs: false,
  compactThreads: false,
  centeredThreads: false,
  dropDownNav: false,
  classicNav: false,
  fixedThreadWatcher: false,
  persistentQR: false,
  forceHTTPS: false,
  reportButton: false,
  
  disableAll: false
};

var ConfigMobile = {
  embedYouTube: false,
  compactThreads: false
};

Config.load = function() {
  if (storage = localStorage.getItem('4chan-settings')) {
    storage = JSON.parse(storage);
    $.extend(Config, storage);
    
    if (Main.getCookie('https') === '1') {
      Config.forceHTTPS = true;
    }
    else {
      Config.forceHTTPS = false;
    }
  }
  else {
    Main.firstRun = true;
  }
};

Config.loadFromURL = function() {
  var cmd, data;
  
  cmd = location.href.split('=', 2);
  
  if (/#cfg$/.test(cmd[0])) {
    try {
      data = JSON.parse(decodeURIComponent(cmd[1]));
      
      history.replaceState(null, '', location.href.split('#', 1)[0]);
      
      $.extend(Config, JSON.parse(data.settings));
      
      Config.save();
      
      if (data.filters) {
        localStorage.setItem('4chan-filters', data.filters);
      }
      
      if (data.css) {
        localStorage.setItem('4chan-css', data.css);
      }
      
      if (data.catalogFilters) {
        localStorage.setItem('catalog-filters', data.catalogFilters);
      }
      
      if (data.catalogSettings) {
        localStorage.setItem('catalog-settings', data.catalogSettings);
      }
      
      return true;
    }
    catch (e) {
      console.log(e);
    }
  }
  
  return false;
};

Config.toURL = function() {
  var data, cfg = {};
  
  cfg.settings = localStorage.getItem('4chan-settings');
  
  if (data = localStorage.getItem('4chan-filters')) {
    cfg.filters = data;
  }
  
  if (data = localStorage.getItem('4chan-css')) {
    cfg.css = data;
  }
  
  if (data = localStorage.getItem('catalog-filters')) {
    cfg.catalogFilters = data;
  }
  
  if (data = localStorage.getItem('catalog-settings')) {
    cfg.catalogSettings = data;
  }
  
  return encodeURIComponent(JSON.stringify(cfg));
};

Config.save = function() {
  localStorage.setItem('4chan-settings', JSON.stringify(Config));
  
  if (Config.forceHTTPS) {
    Main.setCookie('https', 1);
  }
  else {
    Main.removeCookie('https');
  }
};

/**
 * Settings menu
 */
var SettingsMenu = {};

// [ Name, Subtitle, available on mobile?, is sub-option?, is mobile only? ]
SettingsMenu.options = {
  'Quotes &amp; Replying': {
    quotePreview: [ 'Quote preview', 'Show post when mousing over post links', true ],
    backlinks: [ 'Backlinks', 'Show who has replied to a post', true ],
    inlineQuotes: [ 'Inline quote links', 'Clicking quote links will inline expand the quoted post, Shift-click to bypass inlining' ],
    quickReply: [ 'Quick Reply', 'Quickly respond to a post by clicking its post number', true ],
    persistentQR: [ 'Persistent Quick Reply', 'Keep Quick Reply window open after posting' ]
  },
  'Monitoring': {
    threadUpdater: [ 'Thread updater', 'Append new posts to bottom of thread without refreshing the page', true ],
    alwaysAutoUpdate:[ 'Auto-update by default', 'Always auto-update threads', true ],
    threadWatcher: [ 'Thread Watcher', 'Keep track of threads you\'re watching and see when they receive new posts', true ],
    autoScroll: [ 'Auto-scroll with auto-updated posts', 'Automatically scroll the page as new posts are added' ],
    updaterSound: [ 'Sound notification', 'Play a sound when somebody replies to your post(s)' ],
    fixedThreadWatcher: [ 'Pin Thread Watcher to the page', 'Thread Watcher will scroll with you' ],
    threadStats: [ 'Thread statistics', 'Display post and image counts on the right of the page, <em>italics</em> signify bump/image limit has been met' ],
  },
  'Filters &amp; Post Hiding': {
    filter: [ 'Filter and highlight specific threads/posts [<a href="javascript:;" data-cmd="filters-open">Edit</a>]', 'Enable pattern-based filters' ],
    threadHiding: [ 'Thread hiding [<a href="javascript:;" data-cmd="thread-hiding-clear">Clear History</a>]', 'Hide entire threads by clicking the minus button', true ],
    hideStubs: [ 'Hide thread stubs', "Don't display stubs of hidden threads" ]
  },
  'Navigation': {
    threadExpansion: [ 'Thread expansion', 'Expand threads inline on board indexes', true ],
    dropDownNav: [ 'Use persistent drop-down navigation bar', '' ],
    classicNav: [ 'Use traditional board list', '', false, true ],
    customMenu: [ 'Custom board list [<a href="javascript:;" data-cmd="custom-menu-edit">Edit</a>]', 'Only show selected boards in top and bottom board lists' ],
    alwaysDepage: [ 'Always use infinite scroll', 'Enable infinite scroll by default, so reaching the bottom of the board index will load subsequent pages' ],
    topPageNav: [ 'Page navigation at top of page', 'Show the page switcher at the top of the page, hold Shift and drag to move' ],
    stickyNav: [ 'Navigation arrows', 'Show top and bottom navigation arrows, hold Shift and drag to move' ],
    keyBinds: [ 'Use keyboard shortcuts [<a href="javascript:;" data-cmd="keybinds-open">Show</a>]', 'Enable handy keyboard shortcuts for common actions' ]
  },
  'Images &amp; Media': {
    imageExpansion: [ 'Image expansion', 'Enable inline image expansion, limited to browser width', true ],
    fitToScreenExpansion: [ 'Fit expanded images to screen', 'Limit expanded images to both browser width and height' ],
    imageHover: [ 'Image hover', 'Mouse over images to view full size, limited to browser size' ],
    revealSpoilers: [ "Don't spoiler images", 'Show image thumbnail and original filename instead of spoiler placeholders' ],
    noPictures: [ 'Hide thumbnails', 'Don\'t display thumbnails while browsing', true ],
    embedYouTube: [ 'Embed YouTube links', 'Embed YouTube player into replies' ],
    embedSoundCloud: [ 'Embed SoundCloud links', 'Embed SoundCloud player into replies' ],
    embedVocaroo: [ 'Embed Vocaroo links', 'Embed Vocaroo player into replies' ]
  },
  'Miscellaneous': {
    customCSS: [ 'Custom CSS [<a href="javascript:;" data-cmd="css-open">Edit</a>]', 'Include your own CSS rules', true ],
    IDColor: [ 'Color user IDs', 'Assign unique colors to user IDs on boards that use them', true ],
    compactThreads: [ 'Force long posts to wrap', 'Long posts will wrap at 75% browser width' ],
    centeredThreads: [ 'Center threads', 'Align threads to the center of page', false ],
    reportButton: [ 'Report button', 'Add a report button next to posts for easy reporting', true, false, true ],
    localTime: [ 'Convert dates to local time', 'Convert 4chan server time (US Eastern Time) to your local time', true ],
    forceHTTPS: [ 'Always use HTTPS', 'Rewrite 4chan URLs to always use HTTPS', true ]
  }
};

SettingsMenu.save = function() {
  var i, options, el, key;
  
  options = $.id('settingsMenu').getElementsByClassName('menuOption');
  
  for (i = 0; el = options[i]; ++i) {
    key = el.getAttribute('data-option');
    Config[key] = el.type == 'checkbox' ? el.checked : el.value;
  }
  
  Config.save();
  
  SettingsMenu.close();
  location.href = location.href.replace(/#.+$/, '');
};

SettingsMenu.toggle = function() {
  if ($.id('settingsMenu')) {
    SettingsMenu.close();
  }
  else {
    SettingsMenu.open();
  }
};

SettingsMenu.open = function() {
  var i, cat, categories, key, html, cnt, opts, mobileOpts, el;
  
  if (Main.firstRun) {
    if (el = $.id('settingsTip')) {
      el.parentNode.removeChild(el);
    }
    if (el = $.id('settingsTipBottom')) {
      el.parentNode.removeChild(el);
    }
    Config.save();
  }
  
  cnt = document.createElement('div');
  cnt.id = 'settingsMenu';
  cnt.className = 'UIPanel';
  
  html = '<div class="extPanel reply"><div class="panelHeader">Settings'
    + '<span><img alt="Close" title="Close" class="pointer" data-cmd="settings-toggle" src="'
    + Main.icons.cross + '"></a>'
    + '</span></div><ul>';
  
  html += '<ul><li id="settings-exp-all">[<a href="#" data-cmd="settings-exp-all">Expand All Settings</a>]</li></ul>';
  
  if (Main.hasMobileLayout) {
    categories = {};
    for (cat in SettingsMenu.options) {
      mobileOpts = {};
      opts = SettingsMenu.options[cat];
      for (key in opts) {
        if (opts[key][2]) {
          mobileOpts[key] = opts[key];
        }
      }
      for (i in mobileOpts) {
        categories[cat] = mobileOpts;
        break;
      }
    }
  }
  else {
    categories = SettingsMenu.options;
  }
  
  for (cat in categories) {
    opts = categories[cat];
    html += '<ul><li class="settings-cat-lbl">'
      + '<img alt="" class="settings-expand" src="' + Main.icons.plus + '">'
      + '<span class="settings-expand pointer">'
      + cat + '</span></li><ul class="settings-cat">';
    for (key in opts) {
      // Mobile layout only?
      if (opts[key][4] && !Main.hasMobileLayout) {
        continue;
      }
      html += '<li' + (opts[key][3] ? ' class="settings-sub">' : '>')
        + '<label><input type="checkbox" class="menuOption" data-option="'
        + key + '"' + (Config[key] ? ' checked="checked">' : '>')
        + opts[key][0] + '</label>'
        + (opts[key][1] !== false ? '</li><li class="settings-tip'
        + (opts[key][3] ? ' settings-sub">' : '">') + opts[key][1] : '')
        + '</li>';
    }
    html += '</ul></ul>';
  }
  
  html += '</ul><ul><li class="settings-off">'
    + '<label title="Completely disable the native extension (overrides any checked boxes)">'
    + '<input type="checkbox" class="menuOption" data-option="disableAll"'
    + (Config.disableAll ? ' checked="checked">' : '>')
    + 'Disable the native extension</label></li></ul>'
    + '<div class="center"><button data-cmd="settings-export">Export Settings</button>'
    + '<button data-cmd="settings-save">Save Settings</button></div>';
  
  cnt.innerHTML = html;
  cnt.addEventListener('click', SettingsMenu.onClick, false);
  document.body.appendChild(cnt);
  
  if (Main.firstRun) {
    SettingsMenu.expandAll();
  }
  
  (el = $.cls('menuOption', cnt)[0]) && el.focus();
};

SettingsMenu.showExport = function() {
  var cnt, str, el;
  
  if ($.id('exportSettings')) {
    return;
  }
  
  str = location.href.replace(location.hash, '') + '#cfg=' + Config.toURL();
  
  cnt = document.createElement('div');
  cnt.id = 'exportSettings';
  cnt.className = 'UIPanel';
  cnt.setAttribute('data-cmd', 'export-close');
  cnt.innerHTML = '\
<div class="extPanel reply"><div class="panelHeader">Export Settings\
<span><img data-cmd="export-close" class="pointer" alt="Close" title="Close" src="'
+ Main.icons.cross + '"></span></div>\
<p class="center">Copy and save the URL below, and visit it from another \
browser or computer to restore your extension and catalog settings.</p>\
<p class="center">\
<input class="export-field" type="text" readonly="readonly" value="' + str + '"></p>\
<p style="margin-top:15px" class="center">Alternatively, you can drag the link below into your \
bookmarks bar and click it to restore.</p>\
<p class="center">[<a target="_blank" href="'
+ str + '">Restore 4chan Settings</a>]</p>';

  document.body.appendChild(cnt);
  cnt.addEventListener('click', this.onExportClick, false);
  el = $.cls('export-field', cnt)[0];
  el.focus();
  el.select();
};

SettingsMenu.closeExport = function() {
  var cnt;
  
  if (cnt = $.id('exportSettings')) {
    cnt.removeEventListener('click', this.onExportClick, false);
    document.body.removeChild(cnt);
  }
};

SettingsMenu.onExportClick = function(e) {
  var el;
  
  if (e.target.id == 'exportSettings') {
    e.preventDefault();
    e.stopPropagation();
    SettingsMenu.closeExport();
  }
};

SettingsMenu.expandAll = function() {
  var i, el, nodes = $.cls('settings-expand');
  
  for (i = 0; el = nodes[i]; ++i) {
    el.src = Main.icons.minus;
    el.parentNode.nextElementSibling.style.display = 'block';
  }
};

SettingsMenu.toggleCat = function(t) {
  var icon, disp, el = t.parentNode.nextElementSibling;
  
  if (!el.style.display) {
    disp = 'block';
    icon = 'minus';
  }
  else {
    disp = '';
    icon = 'plus';
  }
  
  el.style.display = disp;
  t.parentNode.firstElementChild.src = Main.icons[icon];
};

SettingsMenu.onClick = function(e) {
  var el, t, i, j;
  
  t = e.target;
  
  if ($.hasClass(t, 'settings-expand')) {
    SettingsMenu.toggleCat(t);
  }
  else if (t.getAttribute('data-cmd') == 'settings-exp-all') {
    e.preventDefault();
    SettingsMenu.expandAll();
  }
  else if (t.id == 'settingsMenu' && (el = $.id('settingsMenu'))) {
    e.preventDefault();
    SettingsMenu.close(el);
  }
};

SettingsMenu.close = function(el) {
  if (el = (el || $.id('settingsMenu'))) {
    el.removeEventListener('click', SettingsMenu.onClick, false);
    document.body.removeChild(el);
  }
};

/**
 * Main
 */
var Main = {};

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

Main.init = function() {
  var params;
  
  document.addEventListener('DOMContentLoaded', Main.run, false);
  
  Main.now = Date.now();
  
  UA.init();
  
  Config.load();
  
  if (Config.forceHTTPS && location.protocol != 'https:') {
    location.href = location.href.replace(/^http:/, 'https:');
    return;
  }
  
  if (Main.firstRun && Config.loadFromURL()) {
    Main.firstRun = false;
  }
  
  if (Main.stylesheet = Main.getCookie(style_group)) {
    Main.stylesheet = Main.stylesheet.toLowerCase().replace(/ /g, '_');
  }
  else {
    Main.stylesheet =
      style_group == 'nws_style' ? 'yotsuba_new' : 'yotsuba_b_new';
  }
  
  Main.passEnabled = Main.getCookie('pass_enabled');
  QR.noCaptcha = QR.noCaptcha || Main.passEnabled;
  
  Main.initIcons();
  
  Main.addCSS();
  
  Main.type = style_group.split('_')[0];
  
  params = location.pathname.split(/\//);
  Main.board = params[1];
  Main.page = params[2];
  Main.tid = params[3];
  
  Report.init();
  
  if (Config.IDColor) {
    IDColor.init();
  }
  
  if (Config.customCSS) {
    CustomCSS.init();
  }
  
  if (Config.keyBinds) {
    Keybinds.init();
  }
  
  UA.dispatchEvent('4chanMainInit');
};

Main.initPersistentNav = function() {
  var el, top, bottom;
  
  top = $.id('boardNavDesktop');
  bottom = $.id('boardNavDesktopFoot');
  
  if (Config.classicNav) {
    el = document.createElement('div');
    el.className = 'pageJump';
    el.innerHTML = '<a href="#bottom">&#9660;</a>'
      + '<a href="javascript:void(0);" id="settingsWindowLinkClassic">Settings</a>'
      + '<a href="//www.4chan.org" target="_top">Home</a></div>';
    
    top.appendChild(el);
    
    $.id('settingsWindowLinkClassic')
      .addEventListener('click', SettingsMenu.toggle, false);
    
    $.addClass(top, 'persistentNav');
  }
  else {
    top.style.display = 'none';
    $.removeClass($.id('boardNavMobile'), 'mobile');
  }
  
  bottom.style.display = 'none';
  
  $.addClass(document.body, 'hasDropDownNav');
};

Main.checkMobileLayout = function() {
  var mobile, desktop;
  
  if (window.matchMedia) {
    return window.matchMedia('(max-width: 480px)').matches
      && localStorage.getItem('4chan_never_show_mobile') != 'true';
  }
  
  mobile = $.id('boardNavMobile');
  desktop = $.id('boardNavDesktop');
  
  return mobile && desktop && mobile.offsetWidth > 0 && desktop.offsetWidth == 0;
};

Main.run = function() {
  var thread;
  
  document.removeEventListener('DOMContentLoaded', Main.run, false);
  
  document.addEventListener('click', Main.onclick, false);
  
  $.id('settingsWindowLink').addEventListener('click', SettingsMenu.toggle, false);
  $.id('settingsWindowLinkBot').addEventListener('click', SettingsMenu.toggle, false);
  $.id('settingsWindowLinkMobile').addEventListener('click', SettingsMenu.toggle, false);
  
  if (Config.disableAll) {
    return;
  }
  
  Main.hasMobileLayout = Main.checkMobileLayout();
  Main.isMobileDevice = /Mobile|Android|Dolfin|Opera Mobi|PlayStation Vita|Nintendo DS/.test(navigator.userAgent);
  
  if (Main.hasMobileLayout) {
    $.extend(Config, ConfigMobile);
  }
  else {
    $.id('bottomReportBtn').style.display = 'none';
    
    if (Main.isMobileDevice) {
      $.addClass(document.body, 'isMobileDevice');
    }
  }
  
  if (Main.firstRun && Main.isMobileDevice) {
    Config.topPageNav = false;
    Config.dropDownNav = true;
  }
  
  if (Config.dropDownNav && !Main.hasMobileLayout) {
    Main.initPersistentNav();
  }
  
  $.addClass(document.body, Main.stylesheet);
  $.addClass(document.body, Main.type);
  
  if (Config.compactThreads) {
    $.addClass(document.body, 'compact');
  }
  else if (Config.centeredThreads) {
    $.addClass(document.body, 'centeredThreads');
  }
  
  if (Config.noPictures) {
    $.addClass(document.body, 'noPictures');
  }
  
  if (Config.customMenu) {
    CustomMenu.apply(Config.customMenuList);
  }
  
  if (Config.quotePreview || Config.imageHover|| Config.filter) {
    thread = $.id('delform');
    thread.addEventListener('mouseover', Main.onThreadMouseOver, false);
    thread.addEventListener('mouseout', Main.onThreadMouseOut, false);
  }
  
  if (!Main.hasMobileLayout) {
    Main.initGlobalMessage();
  }
  
  if (Config.stickyNav) {
    Main.setStickyNav();
  }
  
  if (Config.threadExpansion) {
    ThreadExpansion.init();
  }
  
  if (Config.threadWatcher) {
    ThreadWatcher.init();
  }
  
  if (Config.filter) {
    Filter.init();
  }
  
  if (Config.embedSoundCloud || Config.embedYouTube || Config.embedVocaroo) {
    Media.init();
  }
  
  ReplyHiding.init();
  
  if (Config.quotePreview) {
    QuotePreview.init();
  }
  
  Parser.init();
  
  if (Main.tid) {
    Main.threadClosed = !document.forms.post;
    Main.threadSticky = !!$.cls('stickyIcon', $.id('pi' + Main.tid))[0];
    
    if (Config.threadStats) {
      ThreadStats.init();
    }
    
    Parser.parseThread(Main.tid);
    
    if (Config.threadUpdater) {
      ThreadUpdater.init();
    }
  }
  else {
    if (!Main.page) {
      Depager.init();
    }
    
    if (Config.topPageNav) {
      Main.setPageNav();
    }
    if (Config.threadHiding) {
      ThreadHiding.init();
      Parser.parseBoard();
    }
    else {
      Parser.parseBoard();
    }
  }
  
  if (Main.board === 'f') {
    SWFEmbed.init();
  }
  
  if (Config.quickReply) {
    QR.init();
  }
  
  ReplyHiding.purge();
};

Main.isThreadClosed = function(tid) {
  return window.thread_archived || ((el = $.id('pi' + tid)) && $.cls('closedIcon', el)[0])
};

Main.setThreadState = function(state, mode) {
  var cnt, el, ref, cap;
  
  cap = state.charAt(0).toUpperCase() + state.slice(1);
  
  if (mode) {
    cnt = $.cls('postNum', $.id('pi' + Main.tid))[0];
    el = document.createElement('img');
    el.className = state + 'Icon retina';
    el.title = cap;
    el.src = Main.icons2[state];
    if (state == 'sticky' && (ref = $.cls('closedIcon', cnt)[0])) {
      cnt.insertBefore(el, ref);
      cnt.insertBefore(document.createTextNode(' '), ref);
    }
    else {
      cnt.appendChild(document.createTextNode(' '));
      cnt.appendChild(el);
    }
  }
  else {
    if (el = $.cls(state + 'Icon', $.id('pi' + Main.tid))[0]) {
      el.parentNode.removeChild(el.previousSibling);
      el.parentNode.removeChild(el);
    }
  }
  
  Main['thread' + cap] = mode;
};

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

Main.icons2 = {
  archived: 'archived.gif',
  closed: 'closed.gif',
  sticky: 'sticky.gif',
  trash: 'trash.gif'
},

Main.initIcons = function() {
  var key, paths, url;
  
  paths = {
    yotsuba_new: 'futaba/',
    futaba_new: 'futaba/',
    yotsuba_b_new: 'burichan/',
    burichan_new: 'burichan/',
    tomorrow: 'tomorrow/',
    photon: 'photon/'
  };
  
  url = '//s.4cdn.org/image/'
  
  if (window.devicePixelRatio >= 2) {
    for (key in Main.icons) {
      Main.icons[key] = Main.icons[key].replace('.', '@2x.');
    }
    for (key in Main.icons2) {
      Main.icons2[key] = Main.icons2[key].replace('.', '@2x.');
    }
  }
  
  for (key in Main.icons2) {
    Main.icons2[key] = url + Main.icons2[key];
  }
  
  url += 'buttons/' + paths[Main.stylesheet];
  for (key in Main.icons) {
    Main.icons[key] = url + Main.icons[key];
  }
};

Main.setPageNav = function() {
  var el, cnt;
  
  cnt = document.createElement('div');
  cnt.setAttribute('data-shiftkey', '1');
  cnt.setAttribute('data-trackpos', 'TN-position');
  cnt.className = 'topPageNav';
  
  if (Config['TN-position']) {
    cnt.style.cssText = Config['TN-position'];
  }
  else {
    cnt.style.left = '10px';
    cnt.style.top = '50px';
  }
  
  el = $.cls('pagelist')[0]
  
  if (!el) {
    return;
  }
  
  el = el.cloneNode(true);
  cnt.appendChild(el);
  Draggable.set(el);
  document.body.appendChild(cnt);
};

Main.initGlobalMessage = function() {
  var msg, btn, thisTs, oldTs;
  
  if ((msg = $.id('globalMessage')) && msg.textContent) {
    msg.nextElementSibling.style.clear = 'both';
    
    btn = document.createElement('img');
    btn.id = 'toggleMsgBtn';
    btn.className = 'extButton';
    btn.setAttribute('data-cmd', 'toggleMsg');
    btn.alt = 'Toggle';
    btn.title = 'Toggle announcement';
    
    oldTs = localStorage.getItem('4chan-global-msg');
    thisTs = msg.getAttribute('data-utc');
    
    if (oldTs && thisTs <= oldTs) {
      msg.style.display = 'none';
      btn.style.opacity = '0.5';
      btn.src = Main.icons.plus;
    }
    else {
      btn.src = Main.icons.minus;
    }
    
    msg.parentNode.insertBefore(btn, msg);
  }
};

Main.toggleGlobalMessage = function() {
  var msg, btn;
  
  msg = $.id('globalMessage');
  btn = $.id('toggleMsgBtn');
  if (msg.style.display == 'none') {
    msg.style.display = '';
    btn.src = Main.icons.minus;
    btn.style.opacity = '1';
    localStorage.removeItem('4chan-global-msg');
  }
  else {
    msg.style.display = 'none';
    btn.src = Main.icons.plus;
    btn.style.opacity = '0.5';
    localStorage.setItem('4chan-global-msg', msg.getAttribute('data-utc'));
  }
};

Main.setStickyNav = function() {
  var cnt, hdr;
  
  cnt = document.createElement('div');
  cnt.id = 'stickyNav';
  cnt.className = 'extPanel reply';
  cnt.setAttribute('data-shiftkey', '1');
  cnt.setAttribute('data-trackpos', 'SN-position');
  
  if (Config['SN-position']) {
    cnt.style.cssText = Config['SN-position'];
  }
  else {
    cnt.style.right = '10px';
    cnt.style.top = '50px';
  }
  
  hdr = document.createElement('div');
  hdr.innerHTML = '<img class="pointer" src="'
    +  Main.icons.up + '" data-cmd="totop" alt="▲" title="Top">'
    + '<img class="pointer" src="' +  Main.icons.down
    + '" data-cmd="tobottom" alt="▼" title="Bottom">';
  Draggable.set(hdr);
  
  cnt.appendChild(hdr);
  document.body.appendChild(cnt);
};

Main.getCookie = function(name) {
  var i, c, ca, key;
  
  key = name + "=";
  ca = document.cookie.split(';');
  
  for (i = 0; c = ca[i]; ++i) {
    while (c.charAt(0) == ' ') {
      c = c.substring(1, c.length);
    }
    if (c.indexOf(key) == 0) {
      return decodeURIComponent(c.substring(key.length, c.length));
    }
  }
  return null;
};

Main.setCookie = function(name, value) {
  var date = new Date();
  
  date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000));
  
  document.cookie = name + '=' + value
    + '; expires=' + date.toGMTString()
    + '; path=/; domain=boards.4chan.org';
};

Main.removeCookie = function(name) {
  document.cookie = name + '='
    + '; expires=Thu, 01 Jan 1970 00:00:01 GMT;'
    + '; path=/; domain=boards.4chan.org';
};

Main.onclick = function(e) {
  var t, cmd, tid;
  
  if ((t = e.target) == document) {
    return;
  }
  
  if (cmd = t.getAttribute('data-cmd')) {
    id = t.getAttribute('data-id');
    switch (cmd) {
      case 'update':
        e.preventDefault();
        ThreadUpdater.forceUpdate();
        break;
      case 'post-menu':
        e.preventDefault();
        PostMenu.open(t);
        break;
      case 'auto':
        ThreadUpdater.toggleAuto();
        break;
      case 'totop':
      case 'tobottom':
        if (!e.shiftKey) {
          location.href = '#' + cmd.slice(2);
        }
        break;
      case 'hide':
        ThreadHiding.toggle(id);
        break;
      case 'watch':
        ThreadWatcher.toggle(id);
        break;
      case 'hide-r':
        ReplyHiding.toggle(id);
        break;
      case 'expand':
        ThreadExpansion.toggle(id);
        break;
      case 'open-qr':
        e.preventDefault();
        QR.show(Main.tid);
        $.tag('textarea', document.forms.qrPost)[0].focus();
        break;
      case 'depage':
        e.preventDefault();
        Depager.toggle();
        break;
      case 'report':
        Report.open(id, t.getAttribute('data-board'));
        break;
      case 'filter-sel':
        e.preventDefault();
        Filter.addSelection();
        break;
      case 'embed':
        Media.toggleEmbed(t);
        break
      case 'sound':
        ThreadUpdater.toggleSound();
        break;
      case 'toggleMsg':
        Main.toggleGlobalMessage();
        break;
      case 'settings-toggle':
        SettingsMenu.toggle();
        break;
      case 'settings-save':
        SettingsMenu.save();
        break;
      case 'keybinds-open':
        Keybinds.open();
        break;
      case 'filters-open':
        Filter.open();
        break;
      case 'thread-hiding-clear':
        ThreadHiding.clear();
        break;
      case 'css-open':
        CustomCSS.open();
        break;
      case 'settings-export':
        SettingsMenu.showExport();
        break;
      case 'export-close':
        SettingsMenu.closeExport();
        break;
      case 'custom-menu-edit':
        CustomMenu.showEditor();
        break;
    }
  }
  else if (!Config.disableAll) {
    if (QR.enabled && t.title == 'Reply to this post') {
      e.preventDefault();
      tid = Main.tid || t.previousElementSibling.getAttribute('href').split('#')[0].split('/')[1];
      QR.quotePost(tid, !e.ctrlKey && t.textContent);
    }
    else if (Config.imageExpansion && e.which == 1 && t.parentNode
      && $.hasClass(t.parentNode, 'fileThumb')
      && t.parentNode.nodeName == 'A'
      && !$.hasClass(t.parentNode, 'deleted')) {
      
      if (ImageExpansion.toggle(t)) {
        e.preventDefault();
      }
    }
    else if (Config.inlineQuotes && e.which == 1 && $.hasClass(t, 'quotelink')) {
      if (!e.shiftKey) {
        QuoteInline.toggle(t, e);
      }
      else {
        e.preventDefault();
        window.location = t.href;
      }
    }
    else if (Config.threadExpansion && t.parentNode && $.hasClass(t.parentNode, 'abbr')) {
      e.preventDefault();
      ThreadExpansion.expandComment(t);
    }
    else if (Main.isMobileDevice && Config.quotePreview) {
      if ($.hasClass(t, 'quotelink')
        && (cmd = t.getAttribute('href').match(QuotePreview.regex))
        && cmd[1] != 'rs') {
        e.preventDefault();
      }
    }
  }
};

Main.onThreadMouseOver = function(e) {
  var t = e.target;
  
  if (Config.quotePreview
    && $.hasClass(t, 'quotelink')
    && !$.hasClass(t, 'deadlink')
    && !$.hasClass(t, 'linkfade')) {
    QuotePreview.resolve(e.target);
  }
  else if (Config.imageHover && t.hasAttribute('data-md5')
    && !$.hasClass(t.parentNode, 'deleted')) {
    ImageHover.show(t);
  }
  else if (Config.embedYouTube && t.getAttribute('data-type') === 'yt' && !Main.hasMobileLayout) {
    Media.showYTPreview(t);
  }
  else if (Config.filter && t.hasAttribute('data-filtered')) {
    QuotePreview.show(t,
      t.href ? t.parentNode.parentNode.parentNode : t.parentNode.parentNode);
  }
};

Main.onThreadMouseOut = function(e) {
  var t = e.target;
  
  if (Config.quotePreview && $.hasClass(t, 'quotelink')) {
    QuotePreview.remove(t);
  }
  else if (Config.imageHover && t.hasAttribute('data-md5')) {
    ImageHover.hide();
  }
  else if (Config.embedYouTube && t.getAttribute('data-type') === 'yt' && !Main.hasMobileLayout) {
    Media.removeYTPreview();
  }
  else if (Config.filter && t.hasAttribute('data-filtered')) {
    QuotePreview.remove(t);
  }
};

Main.linkToThread = function(tid, board, post) {
  return '//' + location.host + '/'
    + (board || Main.board) + '/thread/'
    + tid + (post > 0 ? ('#p' + post) : '');
};

Main.addCSS = function() {
  var style, css = '\
body.hasDropDownNav {\
  margin-top: 45px;\
}\
.extButton.threadHideButton {\
  float: left;\
  margin-right: 5px;\
  margin-top: -1px;\
}\
.extButton.replyHideButton {\
  margin-top: 1px;\
}\
div.op > span .postHideButtonCollapsed {\
  margin-right: 1px;\
}\
.dropDownNav #boardNavMobile, {\
  display: block !important;\
}\
.extPanel {\
  border: 1px solid rgba(0, 0, 0, 0.20);\
}\
.tomorrow .extPanel {\
  border: 1px solid #111;\
}\
.extButton,\
img.pointer {\
  width: 18px;\
  height: 18px;\
}\
.extControls {\
  display: inline;\
  margin-left: 5px;\
}\
.extButton {\
  cursor: pointer;\
  margin-bottom: -4px;\
}\
.trashIcon {\
  width: 16px;\
  height: 16px;\
  margin-bottom: -2px;\
  margin-left: 5px;\
}\
.threadUpdateStatus {\
  margin-left: 0.5ex;\
}\
.futaba_new .stub,\
.burichan_new .stub {\
  line-height: 1;\
  padding-bottom: 1px;\
}\
.stub .extControls,\
.stub .wbtn,\
.stub input {\
  display: none;\
}\
.stub .threadHideButton {\
  float: none;\
  margin-right: 2px;\
}\
div.post div.postInfo {\
  width: auto;\
  display: inline;\
}\
.right {\
  float: right;\
}\
.center {\
  display: block;\
  margin: auto;\
}\
.pointer {\
  cursor: pointer;\
}\
.drag {\
  cursor: move !important;\
  user-select: none !important;\
  -moz-user-select: none !important;\
  -webkit-user-select: none !important;\
}\
#quickReport,\
#quickReply {\
  display: block;\
  position: fixed;\
  padding: 2px;\
  font-size: 10pt;\
}\
#qrepHeader,\
#qrHeader {\
  text-align: center;\
  margin-bottom: 1px;\
  padding: 0;\
  height: 18px;\
  line-height: 18px;\
}\
#qrepClose,\
#qrClose {\
  float: right;\
}\
#quickReport iframe {\
  overflow: hidden;\
}\
#quickReport {\
  height: 190px;\
}\
#qrForm > div {\
  clear: both;\
}\
#quickReply input[type="text"],\
#quickReply textarea,\
#quickReply #recaptcha_response_field {\
  border: 1px solid #aaa;\
  font-family: arial,helvetica,sans-serif;\
  font-size: 10pt;\
  outline: medium none;\
  width: 296px;\
  padding: 2px;\
  margin: 0 0 1px 0;\
}\
#quickReply textarea {\
  min-width: 296px;\
  float: left;\
}\
#quickReply input::-moz-placeholder,\
#quickReply textarea::-moz-placeholder {\
  color: #aaa !important;\
  opacity: 1 !important;\
}\
#quickReply input[type="submit"] {\
  width: 83px;\
  margin: 0;\
  font-size: 10pt;\
  float: left;\
}\
#quickReply #qrCapField {\
  display: block;\
  margin-top: 1px;\
}\
#qrCaptcha {\
  width: 300px;\
  height: 53px;\
  cursor: pointer;\
  border: 1px solid #aaa;\
  display: block;\
}\
#quickReply input.presubmit {\
  margin-right: 1px;\
  width: 212px;\
  float: left;\
}\
#qrFile {\
  width: 215px;\
  margin-right: 5px;\
}\
.qrRealFile {\
  position: absolute;\
  left: 0;\
  visibility: hidden;\
}\
.yotsuba_new #qrFile {\
  color:black;\
}\
#qrSpoiler {\
  display: inline;\
}\
#qrError {\
  width: 292px;\
  display: none;\
  font-family: monospace;\
  background-color: #E62020;\
  font-size: 12px;\
  color: white;\
  padding: 3px 5px;\
  text-shadow: 0 1px rgba(0, 0, 0, 0.20);\
  clear: both;\
}\
#qrError a:hover,\
#qrError a {\
  color: white !important;\
  text-decoration: underline;\
}\
#twHeader {\
  font-weight: bold;\
  text-align: center;\
  height: 17px;\
}\
.futaba_new #twHeader,\
.burichan_new #twHeader {\
  line-height: 1;\
}\
#twPrune {\
  margin-left: 3px;\
  margin-top: -1px;\
}\
#twClose {\
  float: left;\
  margin-top: -1px;\
}\
#threadWatcher {\
  max-width: 265px;\
  display: block;\
  position: absolute;\
  padding: 3px;\
}\
#watchList {\
  margin: 0;\
  padding: 0;\
  user-select: none;\
  -moz-user-select: none;\
  -webkit-user-select: none;\
}\
#watchList li:first-child {\
  margin-top: 3px;\
  padding-top: 2px;\
  border-top: 1px solid rgba(0, 0, 0, 0.20);\
}\
.photon #watchList li:first-child {\
  border-top: 1px solid #ccc;\
}\
.yotsuba_new #watchList li:first-child {\
  border-top: 1px solid #d9bfb7;\
}\
.yotsuba_b_new #watchList li:first-child {\
  border-top: 1px solid #b7c5d9;\
}\
.tomorrow #watchList li:first-child {\
  border-top: 1px solid #111;\
}\
#watchList a {\
  text-decoration: none;\
}\
#watchList li {\
  overflow: hidden;\
  white-space: nowrap;\
  text-overflow: ellipsis;\
}\
div.post div.image-expanded {\
  display: table;\
}\
div.op div.file .image-expanded-anti {\
  margin-left: -3px;\
}\
#quote-preview {\
  display: block;\
  position: absolute;\
  top: 0;\
  padding: 3px 6px 6px 3px;\
  margin: 0;\
}\
#quote-preview .dateTime {\
  white-space: nowrap;\
}\
.yotsuba_new #quote-preview.highlight,\
.yotsuba_b_new #quote-preview.highlight {\
  border-width: 1px 2px 2px 1px !important;\
  border-style: solid !important;\
}\
.yotsuba_new #quote-preview.highlight {\
  border-color: #D99F91 !important;\
}\
.yotsuba_b_new #quote-preview.highlight {\
  border-color: #BA9DBF !important;\
}\
.yotsuba_b_new .highlight-anti,\
.burichan_new .highlight-anti {\
  border-width: 1px !important;\
  background-color: #bfa6ba !important;\
}\
.yotsuba_new .highlight-anti,\
.futaba_new .highlight-anti {\
  background-color: #e8a690 !important;\
}\
.tomorrow .highlight-anti {\
  background-color: #111 !important;\
  border-color: #111;\
}\
.photon .highlight-anti {\
  background-color: #bbb !important;\
}\
.op.inlined {\
  display: block;\
}\
#quote-preview .inlined,\
#quote-preview .postMenuBtn,\
#quote-preview .extButton,\
#quote-preview .extControls {\
  display: none;\
}\
.hasNewReplies {\
  font-weight: bold;\
}\
.archivelink {\
  opacity: 0.5;\
}\
.deadlink {\
  text-decoration: line-through !important;\
}\
div.backlink {\
  font-size: 0.8em !important;\
  display: inline;\
  padding: 0;\
  padding-left: 5px;\
}\
.backlink.mobile {\
  padding: 3px 5px;\
  display: block;\
  clear: both;\
  line-height: 2;\
}\
.op .backlink.mobile,\
#quote-preview .backlink.mobile {\
  display: none !important;\
}\
.backlink.mobile .quoteLink {\
  padding-right: 2px;\
}\
.backlink span {\
  padding: 0;\
}\
.burichan_new .backlink a,\
.yotsuba_b_new .backlink a {\
  color: #34345C !important;\
}\
.burichan_new .backlink a:hover,\
.yotsuba_b_new .backlink a:hover {\
  color: #dd0000 !important;\
}\
.expbtn {\
  margin-right: 3px;\
  margin-left: 2px;\
}\
.tCollapsed .rExpanded {\
  display: none;\
}\
#stickyNav {\
  position: fixed;\
  font-size: 0;\
}\
#stickyNav img {\
  vertical-align: middle;\
}\
.tu-error {\
  color: red;\
}\
.topPageNav {\
  position: absolute;\
}\
.yotsuba_b_new .topPageNav {\
  border-top: 1px solid rgba(255, 255, 255, 0.25);\
  border-left: 1px solid rgba(255, 255, 255, 0.25);\
}\
.newPostsMarker:not(#quote-preview) {\
  box-shadow: 0 3px red;\
}\
#toggleMsgBtn {\
  float: left;\
  margin-bottom: 6px;\
}\
.panelHeader {\
  font-weight: bold;\
  font-size: 16px;\
  text-align: center;\
  margin-bottom: 5px;\
  margin-top: 5px;\
  padding-bottom: 5px;\
  border-bottom: 1px solid rgba(0, 0, 0, 0.20);\
}\
.yotsuba_new .panelHeader {\
  border-bottom: 1px solid #d9bfb7;\
}\
.yotsuba_b_new .panelHeader {\
  border-bottom: 1px solid #b7c5d9;\
}\
.tomorrow .panelHeader {\
  border-bottom: 1px solid #111;\
}\
.panelHeader span {\
  position: absolute;\
  right: 5px;\
  top: 5px;\
}\
.UIMenu,\
.UIPanel {\
  position: fixed;\
  width: 100%;\
  height: 100%;\
  z-index: 9002;\
  top: 0;\
  left: 0;\
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
#settingsMenu > div {\
  top: 25px;;\
  vertical-align: top;\
  max-height: 85%;\
}\
.extPanel input[type="text"],\
.extPanel textarea {\
  border: 1px solid #AAA;\
  outline: none;\
}\
.UIPanel .center {\
  margin-bottom: 5px;\
}\
.UIPanel button {\
  display: inline-block;\
  margin-right: 5px;\
}\
.UIPanel code {\
  background-color: #eee;\
  color: #000000;\
  padding: 1px 4px;\
  font-size: 12px;\
}\
.UIPanel ul {\
  list-style: none;\
  padding: 0;\
  margin: 0 0 10px;\
}\
.UIPanel .export-field {\
  width: 385px;\
}\
#settingsMenu label input {\
  margin-right: 5px;\
}\
.tomorrow #settingsMenu ul {\
  border-bottom: 1px solid #282a2e;\
}\
.settings-off {\
  padding-left: 3px;\
}\
.settings-cat-lbl {\
  font-weight: bold;\
  margin: 10px 0 5px;\
  padding-left: 5px;\
}\
.settings-cat-lbl img {\
  vertical-align: text-bottom;\
  margin-right: 5px;\
  cursor: pointer;\
  width: 18px;\
  height: 18px;\
}\
.settings-tip {\
  font-size: 0.85em;\
  margin: 2px 0 5px 0;\
  padding-left: 23px;\
}\
#settings-exp-all {\
  padding-left: 7px;\
  text-align: center;\
}\
#settingsMenu .settings-cat {\
  display: none;\
  margin-left: 3px;\
}\
#customCSSMenu textarea {\
  display: block;\
  max-width: 100%;\
  min-width: 100%;\
  -moz-box-sizing: border-box;\
  box-sizing: border-box;\
  height: 200px;\
  margin: 0 0 5px;\
  font-family: monospace;\
}\
#customCSSMenu .right,\
#settingsMenu .right {\
  margin-top: 2px;\
}\
#settingsMenu label {\
  display: inline-block;\
  user-select: none;\
  -moz-user-select: none;\
  -webkit-user-select: none;\
}\
#filtersHelp > div {\
  width: 600px;\
  left: 50%;\
  margin-left: -300px;\
}\
#filtersHelp h4 {\
  font-size: 15px;\
  margin: 20px 0 0 10px;\
}\
#filtersHelp h4:before {\
  content: "»";\
  margin-right: 3px;\
}\
#filtersHelp ul {\
  padding: 0;\
  margin: 10px;\
}\
#filtersHelp li {\
  padding: 3px 0;\
  list-style: none;\
}\
#filtersMenu table {\
  width: 100%;\
}\
#filtersMenu th {\
  font-size: 12px;\
}\
#filtersMenu tbody {\
  text-align: center;\
}\
#filtersMenu select,\
#filtersMenu .fPattern,\
#filtersMenu .fBoards,\
#palette-custom-input {\
  padding: 1px;\
  font-size: 11px;\
}\
#filtersMenu select {\
  width: 75px;\
}\
#filtersMenu tfoot td {\
  padding-top: 10px;\
}\
#keybindsHelp li {\
  padding: 3px 5px;\
}\
.fPattern {\
  width: 110px;\
}\
.fBoards {\
  width: 25px;\
}\
.fColor {\
  width: 60px;\
}\
.fDel {\
  font-size: 16px;\
}\
.filter-preview {\
  cursor: default;\
  margin-left: 3px;\
}\
#quote-preview iframe,\
#quote-preview .filter-preview {\
  display: none;\
}\
.post-hidden .extButton,\
.post-hidden:not(#quote-preview) .postInfo {\
  opacity: 0.5;\
}\
.post-hidden:not(.thread) .postInfo {\
  padding-left: 5px;\
}\
.post-hidden:not(#quote-preview) input,\
.post-hidden:not(#quote-preview) .replyContainer,\
.post-hidden:not(#quote-preview) .summary,\
.post-hidden:not(#quote-preview) .op .file,\
.post-hidden:not(#quote-preview) .file,\
.post-hidden .wbtn,\
.post-hidden .postNum span,\
.post-hidden:not(#quote-preview) .backlink,\
div.post-hidden:not(#quote-preview) div.file,\
div.post-hidden:not(#quote-preview) blockquote.postMessage {\
  display: none;\
}\
.click-me {\
  border-radius: 5px;\
  margin-top: 5px;\
  padding: 2px 5px;\
  position: absolute;\
  font-weight: bold;\
  z-index: 2;\
  white-space: nowrap;\
}\
.yotsuba_new .click-me,\
.futaba_new .click-me {\
  color: #800000;\
  background-color: #F0E0D6;\
  border: 2px solid #D9BFB7;\
}\
.yotsuba_b_new .click-me,\
.burichan_new .click-me {\
  color: #000;\
  background-color: #D6DAF0;\
  border: 2px solid #B7C5D9;\
}\
.tomorrow .click-me {\
  color: #C5C8C6;\
  background-color: #282A2E;\
  border: 2px solid #111;\
}\
.photon .click-me {\
  color: #333;\
  background-color: #ddd;\
  border: 2px solid #ccc;\
}\
.click-me:before {\
  content: "";\
  border-width: 0 6px 6px;\
  border-style: solid;\
  left: 50%;\
  margin-left: -6px;\
  position: absolute;\
  width: 0;\
  height: 0;\
  top: -6px;\
}\
.yotsuba_new .click-me:before,\
.futaba_new .click-me:before {\
  border-color: #D9BFB7 transparent;\
}\
.yotsuba_b_new .click-me:before,\
.burichan_new .click-me:before {\
  border-color: #B7C5D9 transparent;\
}\
.tomorrow .click-me:before {\
  border-color: #111 transparent;\
}\
.photon .click-me:before {\
  border-color: #ccc transparent;\
}\
.click-me:after {\
  content: "";\
  border-width: 0 4px 4px;\
  top: -4px;\
  display: block;\
  left: 50%;\
  margin-left: -4px;\
  position: absolute;\
  width: 0;\
  height: 0;\
}\
.yotsuba_new .click-me:after,\
.futaba_new .click-me:after {\
  border-color: #F0E0D6 transparent;\
  border-style: solid;\
}\
.yotsuba_b_new .click-me:after,\
.burichan_new .click-me:after {\
  border-color: #D6DAF0 transparent;\
  border-style: solid;\
}\
.tomorrow .click-me:after {\
  border-color: #282A2E transparent;\
  border-style: solid;\
}\
.photon .click-me:after {\
  border-color: #DDD transparent;\
  border-style: solid;\
}\
#image-hover {\
  position: fixed;\
  max-width: 100%;\
  max-height: 100%;\
  top: 0px;\
  right: 0px;\
  z-index: 9002;\
}\
.thread-stats {\
  float: right;\
  margin-right: 5px;\
  cursor: default;\
}\
.compact .thread {\
  max-width: 75%;\
}\
.dotted {\
  text-decoration: none;\
  border-bottom: 1px dashed;\
}\
.linkfade {\
  opacity: 0.5;\
}\
#quote-preview .linkfade {\
  opacity: 1.0;\
}\
kbd {\
  background-color: #f7f7f7;\
  color: black;\
  border: 1px solid #ccc;\
  border-radius: 3px 3px 3px 3px;\
  box-shadow: 0 1px 0 #ccc, 0 0 0 2px #fff inset;\
  font-family: monospace;\
  font-size: 11px;\
  line-height: 1.4;\
  padding: 0 5px;\
}\
.deleted {\
  opacity: 0.66;\
}\
.noPictures a.fileThumb img:not(.expanded-thumb) {\
  opacity: 0;\
}\
.noPictures.futaba_new a.fileThumb,\
.noPictures.yotsuba_new a.fileThumb {\
  border: 1px solid #800;\
}\
.noPictures.burichan_new a.fileThumb,\
.noPictures.yotsuba_b_new a.fileThumb {\
  border: 1px solid #34345C;\
}\
.noPictures.tomorrow a.fileThumb:not(.expanded-thumb) {\
  border: 1px solid #C5C8C6;\
}\
.noPictures.photon a.fileThumb:not(.expanded-thumb) {\
  border: 1px solid #004A99;\
}\
.spinner {\
  margin-top: 2px;\
  padding: 3px;\
  display: table;\
}\
#settings-presets {\
  position: relative;\
  top: -1px;\
}\
#colorpicker { \
  position: fixed;\
  text-align: center;\
}\
.colorbox {\
  font-size: 10px;\
  width: 16px;\
  height: 16px;\
  line-height: 17px;\
  display: inline-block;\
  text-align: center;\
  background-color: #fff;\
  border: 1px solid #aaa;\
  text-decoration: none;\
  color: #000;\
  cursor: pointer;\
  vertical-align: top;\
}\
#palette-custom-input {\
  vertical-align: top;\
  width: 45px;\
  margin-right: 2px;\
}\
#qrDummyFile {\
  float: left;\
  margin-right: 5px;\
  width: 220px;\
  cursor: default;\
  -moz-user-select: none;\
  -webkit-user-select: none;\
  -ms-user-select: none;\
  user-select: none;\
  white-space: nowrap;\
  text-overflow: ellipsis;\
  overflow: hidden;\
}\
#qrDummyFileLabel {\
  margin-left: 3px;\
}\
.depageNumber {\
  position: absolute;\
  right: 5px;\
}\
.depagerEnabled .depagelink {\
  font-weight: bold;\
}\
.depagerEnabled strong {\
  font-weight: normal;\
}\
.depagelink {\
  display: inline-block;\
  padding: 4px 0;\
  cursor: pointer;\
  text-decoration: none;\
}\
.burichan_new .depagelink,\
.futaba_new .depagelink {\
  text-decoration: underline;\
}\
#customMenuBox {\
  margin: 0 auto 5px auto;\
  width: 385px;\
  display: block;\
}\
.preview-summary {\
  display: block;\
}\
#swf-embed-header {\
  padding: 0 0 0 3px;\
  font-weight: normal;\
  height: 20px;\
  line-height: 20px;\
}\
.yotsuba_new #swf-embed-header,\
.yotsuba_b_new #swf-embed-header {\
  height: 18px;\
  line-height: 18px;\
}\
#swf-embed-close {\
  position: absolute;\
  right: 0;\
  top: 1px;\
}\
.open-qr-wrap {\
  text-align: center;\
  width: 200px;\
  position: absolute;\
  margin-left: 50%;\
  left: -100px;\
}\
.postMenuBtn {\
  margin-left: 5px;\
  text-decoration: none;\
  line-height: 1em;\
  display: inline-block;\
  -webkit-transition: -webkit-transform 0.1s;\
  -moz-transition: -moz-transform 0.1s;\
  transition: transform 0.1s;\
  width: 1em;\
  height: 1em;\
  text-align: center;\
  outline: none;\
  opacity: 0.8;\
}\
.postMenuBtn:hover{\
  opacity: 1;\
}\
.yotsuba_new .postMenuBtn,\
.futaba_new .postMenuBtn {\
  color: #000080;\
}\
.tomorrow .postMenuBtn {\
  color: #5F89AC !important;\
}\
.tomorrow .postMenuBtn:hover {\
  color: #81a2be !important;\
}\
.photon .postMenuBtn {\
  color: #FF6600 !important;\
}\
.photon .postMenuBtn:hover {\
  color: #FF3300 !important;\
}\
.menuOpen {\
  -webkit-transform: rotate(90deg);\
  -moz-transform: rotate(90deg);\
  -ms-transform: rotate(90deg);\
  transform: rotate(90deg);\
}\
.settings-sub label:before {\
  border-bottom: 1px solid;\
  border-left: 1px solid;\
  content: " ";\
  display: inline-block;\
  height: 8px;\
  margin-bottom: 5px;\
  width: 8px;\
}\
.settings-sub {\
  margin-left: 25px;\
}\
.settings-tip.settings-sub {\
  padding-left: 32px;\
}\
.centeredThreads .opContainer {\
  display: block;\
}\
.centeredThreads .postContainer {\
  margin: auto;\
  width: 75%;\
}\
.centeredThreads .sideArrows {\
  display: none;\
}\
.centre-exp {\
  width: auto !important;\
  clear: both;\
}\
.centeredThreads .expandedWebm {\
  float: none;\
}\
.centeredThreads .summary {\
  margin-left: 12.5%;\
  display: block;\
}\
.centre-exp div.op{\
  display: table;\
}\
#yt-preview { position: absolute; }\
#yt-preview img { display: block; }\
\
@media only screen and (max-width: 480px) {\
#threadWatcher {\
  max-width: none;\
  padding: 3px 0;\
  left: 0;\
  width: 100%;\
  border-left: none;\
  border-right: none;\
}\
#watchList {\
  padding: 0 10px;\
}\
.btn-row {\
  margin-top: 5px;\
}\
.image-expanded .mFileInfo {\
  display: none !important;\
}\
.mobile-report {\
  float: right;\
  font-size: 11px;\
  margin-bottom: 3px;\
  margin-left: 10px;\
}\
.mobile-report:after {\
  content: "]";\
}\
.mobile-report:before {\
  content: "[";\
}\
.nws .mobile-report:after {\
  color: #800000;\
}\
.nws .mobile-report:before {\
  color: #800000;\
}\
.ws .mobile-report {\
  color: #34345C;\
}\
.nws .mobile-report {\
  color:#0000EE;\
}\
.reply .mobile-report {\
  margin: 5px 5px 0 5px;\
}\
.postLink .mobileHideButton {\
  margin-right: 3px;\
}\
.board .mobile-hr-hidden {\
  margin-top: 10px !important;\
}\
.board > .mobileHideButton {\
  margin-top: -20px !important;\
}\
.board > .mobileHideButton:first-child {\
  margin-top: 10px !important;\
}\
.extButton.threadHideButton {\
  float: none;\
  margin: 0;\
  margin-bottom: 5px;\
}\
.mobile-post-hidden {\
  display: none;\
}\
#toggleMsgBtn {\
  display: none;\
}\
.mobile-tu-status {\
  height: 20px;\
  line-height: 20px;\
}\
.mobile-tu-show {\
  width: 150px;\
  margin: auto;\
  display: block;\
  text-align: center;\
}\
.button input {\
  margin: 0 3px 0 0;\
  position: relative;\
  top: -2px;\
  border-radius: 0;\
  height: 10px;\
  width: 10px;\
}\
.UIPanel > div {\
  width: 320px;\
  margin-left: -160px;\
}\
.UIPanel .export-field {\
  width: 300px;\
}\
.yotsuba_new #quote-preview.highlight,\
#quote-preview {\
  border-width: 1px !important;\
}\
.yotsuba_new #quote-preview.highlight {\
  border-color: #D9BFB7 !important;\
}\
#quickReply input[type="text"],\
#quickReply textarea,\
.extPanel input[type="text"],\
.extPanel textarea {\
  font-size: 16px;\
}\
#quickReply {\
  position: absolute;\
  left: 50%;\
  margin-left: -154px;\
}\
}\
';
  
  style = document.createElement('style');
  style.setAttribute('type', 'text/css');
  style.textContent = css;
  document.head.appendChild(style);
};

Main.init();
