var $ = {};

$.id = function(id) {
  return document.getElementById(id);
};

$.cls = function(klass, root) {
  return (root || document).getElementsByClassName(klass);
};

$.tag = function(tag, root) {
  return (root || document).getElementsByTagName(tag);
};

$.extend = function(destination, source) {
  for (var key in source) {
    destination[key] = source[key];
  }
};

$.on = function(n, e, h) {
  n.addEventListener(e, h, false);
};

$.off = function(n, e, h) {
  n.removeEventListener(e, h, false);
};

$.readCookie = function(name) {
  var i, c, ca, key;
  
  key = name + '=';
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

if (!document.documentElement.classList) {
  $.hasClass = function(el, klass) {
    return (' ' + el.className + ' ').indexOf(' ' + klass + ' ') != -1;
  };
  
  $.addClass = function(el, klass) {
    el.className = (el.className === '') ? klass : el.className + ' ' + klass;
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

$.toggleClass = function(el, klass) {
  if ($.hasClass(el, klass)) {
    $.removeClass(el, klass);
  }
  else {
    $.addClass(el, klass);
  }
};

var UA = {};

UA.init = function() {
  document.head = document.head || $.tag('head')[0];
  
  this.hasContextMenu = 'HTMLMenuItemElement' in window;
  
  this.hasWebStorage = (function() {
    var kv = 'catalog';
    try {
      localStorage.setItem(kv, kv);
      localStorage.removeItem(kv);
      return true;
    } catch (e) {
      return false;
    }
  })();
  
  this.hasSessionStorage = (function() {
    var kv = 'catalog';
    try {
      sessionStorage.setItem(kv, kv);
      sessionStorage.removeItem(kv);
      return true;
    } catch (e) {
      return false;
    }
  })();
  
  this.hasCORS = 'withCredentials' in new XMLHttpRequest();
  
  this.isMobileDevice = /Mobile|Android|Dolfin|Opera Mobi|PlayStation Vita|Nintendo DS/.test(navigator.userAgent);
};

UA.dispatchEvent = function(name, detail) {
  var e = document.createEvent('Event');
  e.initEvent(name, false, false);
  if (detail) {
    e.detail = detail;
  }
  document.dispatchEvent(e);
};

var FC = function() {
  
  var self = this,
  
  options = {
    orderby: 'alt',
    large: false,
    extended: true,
    imgdel: '//s.4cdn.org/image/filedeleted-res.gif',
    imgspoiler: '//s.4cdn.org/image/spoiler',
    nofile: '//s.4cdn.org/image/nofile.png',
    smallsize: 150,
    tipdelay: 250,
    filterColors: [
      ['#E0B0FF', '#F2F3F4', '#7DF9FF', '#FFFF00'],
      ['#FBCEB1', '#FFBF00', '#ADFF2F', '#0047AB'],
      ['#00A550', '#007FFF', '#AF0A0F', '#B5BD68']
    ]
  },
  
  capcodeMap = {
    admin: 'Administrator',
    mod: 'Moderator',
    developer: 'Developer',
    manager: 'Manager',
    founder: 'Founder',
    verified: 'Verified'
  },
  
  keybinds = {
    83: focusQuickfilter, // S
    82: refreshWindow, // R
    88: cycleOrder // X
  },
  
  catalog = {},
  
  basicSettings = [ 'orderby', 'large', 'extended' ],
  
  activeTheme = {},
  
  activeStyleGroup,
  activeStyleSheet,
  
  activeFilters = {},
  
  hasTooltip = false,
  tooltipTimeout = null,
  
  pinnedThreads = {},
  
  hiddenThreads = {},
  hiddenThreadsCount = 0,
  
  filteredThreadsCount = 0,
  
  hasThreadWatcher = false,
  hasDropDownNav = false,
  hasClassicNav = false,
  hasAutoHideNav = false,
  altCaptcha = false,
  
  quickFilterPattern = false,
  
  hiddenMode = false,
  
  $threads,
  $qfCtrl,
  $teaserCtrl,
  $sizeCtrl,
  $orderCtrl,
  $filterPalette,
  
  ctxCmds;
  
  if (window.devicePixelRatio >= 2) {
    options.imgdel.replace('.', '@2x.');
    options.nofile.replace('.', '@2x.');
  }
  
  UA.init();
  
  loadTheme();
  
  self.init = function() {
    var extConfig, el, val, fn;
    
    FC.hasMobileLayout = checkMobileLayout();
    
    applyTheme(activeTheme, true);
    
    $threads = $.id('threads');
    $qfCtrl = $.id('qf-ctrl');
    $teaserCtrl = $.id('teaser-ctrl');
    $sizeCtrl = $.id('size-ctrl');
    $orderCtrl = $.id('order-ctrl');
    
    $.on($qfCtrl, 'click', toggleQuickfilter);
    $.on($.id('filters-clear-hidden'), 'click', toggleHiddenThreads);
    $.on($.id('filters-clear-hidden-bottom'), 'click', toggleHiddenThreads);
    $.on($.id('qf-clear'), 'click', toggleQuickfilter);
    $.on($.id('settingsWindowLink'), 'click', showThemeEditor);
    $.on($.id('settingsWindowLinkBot'), 'click', showThemeEditor);
    $.on($.id('settingsWindowLinkMobile'), 'click', showThemeEditor);
    $.on($.id('filters-ctrl'), 'click', showFilters);
    $.on($teaserCtrl, 'change', onTeaserChange);
    $.on($sizeCtrl, 'change', onSizeChange);
    $.on($orderCtrl, 'change', onOrderChange);
    $.on($threads, 'mouseover', onThreadMouseOver);
    $.on($threads, 'mouseout', onThreadMouseOut);
    $.on($.id('togglePostFormLinkMobile'), 'click', togglePostFormMobile);
    $.on(document, 'click', onClick);
    
    loadSettings();
    
    bindGlobalShortcuts();
    
    initGlobalMessage();
    
    if (UA.hasContextMenu) {
      buildContextMenu();
    }
    
    window.Config = {};
    
    if (UA.hasWebStorage) {
      if (extConfig = localStorage.getItem('4chan-settings')) {
        extConfig = JSON.parse(extConfig);
        
        window.Config = extConfig;
        
        if (!extConfig.disableAll) {
          CustomMenu.initCtrl(extConfig.dropDownNav, extConfig.classicNav);
          /*
          if (location.host === 'boards.4channel.org' && extConfig.showNWSBoards) {
            CustomMenu.showNWSBoards();
          }
          */
          if (extConfig.filter) {
            ThreadWatcher.hasFilters = true;
          }
          
          if (extConfig.threadWatcher) {
            hasThreadWatcher = true;
            ThreadWatcher.init();
          }
          
          if (extConfig.customMenu) {
            CustomMenu.apply(extConfig.customMenuList);
          }
          
          if (extConfig.dropDownNav !== false && !FC.hasMobileLayout) {
            hasDropDownNav = true;
            hasClassicNav = extConfig.classicNav;
            hasAutoHideNav = extConfig.autoHideNav;
            showDropDownNav();
          }
          
          altCaptcha = extConfig.altCaptcha;
        }
      }
      else if (UA.isMobileDevice && !FC.hasMobileLayout) {
        hasDropDownNav = true;
        showDropDownNav();
      }
      else {
        CustomMenu.initCtrl(false, false);
      }
      
      if (window.css_event && activeStyleSheet === '_special') {
        fn = window['fc_' + window.css_event + '_init'];
        fn && fn();
      }
    }
    
    if (el = document.forms.post.flag) {
      if ((val = $.readCookie('4chan_flag')) && (el = el.querySelector('option[value="' + val + '"]'))) {
        el.setAttribute('selected', 'selected');
      }
    }
    
    setOrder(options.orderby, true);
    setLarge(options.large, true);
    setExtended(options.extended, true);
    
    UA.dispatchEvent('4chanMainInit');
  };
  
  function showDropDownNav() {
    var el, top, bottom;
    
    top = $.id('boardNavDesktop');
    bottom = $.id('boardNavDesktopFoot');
    
    if (hasClassicNav) {
      el = document.createElement('div');
      el.className = 'pageJump';
      el.innerHTML = '<a href="#bottom">&#9660;</a>'
        + '<a href="javascript:void(0);" id="settingsWindowLinkClassic">Settings</a>'
        + '<a href="//www.' + $L.d(catalog.slug) + '" target="_top">Home</a></div>';
      
      top.appendChild(el);
      
      $.id('settingsWindowLinkClassic')
        .addEventListener('click', showThemeEditor, false);
      
      $.addClass(top, 'persistentNav');
    }
    else {
      top.style.display = 'none';
      $.removeClass($.id('boardNavMobile'), 'mobile');
    }
    
    if (hasAutoHideNav) {
      StickyNav.init(hasClassicNav);
    }
    
    bottom.style.display = 'none';
    
    $.addClass(document.body, 'hasDropDownNav');
  }
  
  function hideDropDownNav() {
    var el, top, bottom;
    
    top = $.id('boardNavDesktop');
    bottom = $.id('boardNavDesktopFoot');
    
    if (hasClassicNav) {
      if (el = $.cls('pageJump', top)[0]) {
        $.id('settingsWindowLinkClassic')
          .removeEventListener('click', showThemeEditor, false);
        top.removeChild(el);
      }
      
      $.removeClass(top, 'persistentNav');
    }
    else {
      top.style.display = '';
      $.addClass($.id('boardNavMobile'), 'mobile');
    }
    
    if (hasAutoHideNav) {
      StickyNav.destroy(hasClassicNav);
    }
    
    bottom.style.display = '';
    
    $.removeClass(document.body, 'hasDropDownNav');
  }
  
  self.loadCatalog = function(c) {
    var query;
    
    catalog = c;
    
    $.addClass(document.body, activeStyleSheet.toLowerCase().replace(/ /g, '_'));
    
    initStyleSwitcher();
    loadFilters();
    loadStorage();
    
    if (UA.hasSessionStorage && !location.hash && (query = sessionStorage.getItem('4chan-catalog-search'))) {
      if (catalog.slug != sessionStorage.getItem('4chan-catalog-search-board')) {
        sessionStorage.removeItem('4chan-catalog-search');
        sessionStorage.removeItem('4chan-catalog-search-board');
        query = null;
      }
    }
    else if (location.hash && (query = location.hash.match(/#s=(.+)/))) {
      query = decodeURIComponent(query[1].replace(/\+/g, ' '));
    }
    
    if (query) {
      toggleQuickfilter();
      $.id('qf-box').value = query;
      applyQuickfilter();
    }
    else {
      buildThreads();
    }
  };
  
  function initGlobalMessage() {
    var msg, btn, thisTs, oldTs;
    
    if (!UA.hasWebStorage || FC.hasMobileLayout) {
      return;
    }
    
    if ((msg = $.id('globalMessage')) && msg.textContent) {
      msg.nextElementSibling.style.clear = 'both';
      
      btn = document.createElement('span');
      btn.id = 'toggleMsgBtn';
      btn.setAttribute('data-cmd', 'toggleMsg');
      btn.title = 'Toggle announcement';
      
      oldTs = localStorage.getItem('4chan-global-msg');
      thisTs = msg.getAttribute('data-utc');
      
      if (oldTs && thisTs <= oldTs) {
        msg.style.display = 'none';
        btn.style.opacity = '0.5';
        btn.className = 'expandIcon';
      }
      else {
        btn.className = 'collapseIcon';
      }
      
      $.on(btn, 'click', toggleGlobalCatalogMessage);
      msg.parentNode.insertBefore(btn, msg);
    }
  }
  
  function toggleGlobalCatalogMessage() {
    var msg, btn;
    
    msg = $.id('globalMessage');
    btn = $.id('toggleMsgBtn');
    if (msg.style.display == 'none') {
      msg.style.display = '';
      btn.className = 'collapseIcon';
      btn.style.opacity = '1';
      localStorage.removeItem('4chan-global-msg');
    }
    else {
      msg.style.display = 'none';
      btn.className = 'expandIcon';
      btn.innerHTML = '<span class="mobile">View Important Announcement</span>';
      btn.style.opacity = '0.5';
      localStorage.setItem('4chan-global-msg', msg.getAttribute('data-utc'));
    }
    
    //StorageSync.sync('4chan-global-msg');
  }
  
  function togglePostFormMobile() {
    var el = document.getElementById('postForm');
    
    if (el.style.display == 'table') {
      el.style.display = '';
      this.textContent = 'Start a New Thread';
    }
    else {
      el.style.display = 'table';
      this.textContent = 'Close Post Form';
      window.initRecaptcha();
      window.initTCaptcha();
    }
  }
  
  function getRegexSpecials() {
    var specials = ['/', '.', '*', '+', '?', '(', ')', '[', ']', '{', '}', '\\' ];
    return new RegExp('(\\' + specials.join('|\\') + ')', 'g');
  }
  
  function getThreadPage(tid) {
    return (0 | (catalog.threads[tid].b / catalog.pagesize)) + 1;
  }
  
  function initStyleSwitcher() {
    var i, selector, nodes, ss;
    
    selector = $.id('styleSelector');
    nodes = selector.children;
    
    for (i = 0; ss = nodes[i]; ++i) {
      if (ss.value == activeStyleSheet) {
        selector.selectedIndex = i;
      }
    }
    
    $.on(selector, 'change', onStyleSheetChange);
  }
  
  function onStyleSheetChange() {
    var expires;
    
    if (this.value !== '_special') {
      expires = new Date();
      
      expires.setTime(expires.getTime() + 31536000000);
      
      document.cookie = activeStyleGroup + '=' + this.value + '; expires='
        + expires.toGMTString() + '; path=/; domain=' + $L.d(catalog.slug);
      
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
    
    refreshWindow();
  }
  
  function refreshWindow(e) {
    if (e && e.shiftKey) {
      return;
    }
    location.href = location.href;
  }
  
  function debounce(delay, fn) {
    var timeout;
    
    return function() {
      var args = arguments, context = this;
      
      clearTimeout(timeout);
      timeout = setTimeout(function () {
        fn.apply(context, args);
      }, delay);
    };
  }
  
  function focusQuickfilter() {
    if ($.hasClass($qfCtrl, 'active')) {
      clearQuickfilter(true);
    }
    else {
      toggleQuickfilter();
    }
  }
  
  function toggleQuickfilter() {
    var qfBox, qfcnt = $.id('qf-cnt');
    if ($.hasClass($qfCtrl, 'active')) {
      clearQuickfilter();
      qfcnt.style.display = 'none';
      $.removeClass($qfCtrl, 'active');
    }
    else {
      qfcnt.style.display = 'inline';
      qfBox = $.id('qf-box');
      if (!qfcnt.hasAttribute('data-built')) {
        qfcnt.setAttribute('data-built', '1');
        $.on(qfBox, 'keyup', debounce(250, applyQuickfilter));
        $.on(qfBox, 'keydown', function(e) {
          if (e.keyCode == '27') {
            toggleQuickfilter();
          }
        });
      }
      qfBox.focus();
      qfBox.value = '';
      $.addClass($qfCtrl, 'active');
    }
  }
  
  function applyQuickfilter() {
    var regexEscape, qfstr;
    
    if ((qfstr = $.id('qf-box').value) !== '') {
      if (UA.hasSessionStorage) {
        sessionStorage.setItem('4chan-catalog-search', qfstr);
        sessionStorage.setItem('4chan-catalog-search-board', catalog.slug);
      }
      regexEscape = getRegexSpecials();
      $.id('search-term').textContent = $.id('search-term-bottom').textContent = qfstr;
      $.id('search-label').style.display = $.id('search-label-bottom').style.display = 'inline';
      qfstr = qfstr.replace(regexEscape, '\\$1');
      quickFilterPattern = new RegExp(qfstr, 'i');
      buildThreads();
    } else {
      clearQuickfilter();
    }
  }
  
  function clearQuickfilter(focus) {
    var qf = $.id('qf-box');
    $.id('search-label').style.display = $.id('search-label-bottom').style.display = 'none';
    if (focus) {
      qf.value = '';
      qf.focus();
    }
    else {
      if (UA.hasSessionStorage) {
        sessionStorage.removeItem('4chan-catalog-search');
      }
      quickFilterPattern = false;
      buildThreads();
    }
  }
  
  function buildContextMenu() {
    ctxCmds = {
      pin: toggleThreadPin,
      hide: toggleThreadHide,
      report: reportThread
    };
    
    $.id('ctxmenu-main').innerHTML = 
      '<menuitem label="Unpin all threads"></menuitem>';
    
    $.id('ctxmenu-thread').innerHTML = 
      '<menuitem label="Pin/Unpin" data-cmd="pin"></menuitem>' +
      '<menuitem label="Hide/Unhide" data-cmd="hide"></menuitem>' +
      '<menuitem label="Report" data-cmd="report"></menuitem>';
    
    $.on($.id('ctxmenu-main'), 'click', clearPinnedThreads);
    $.on($.id('ctxmenu-thread'), 'click', onThreadContextClick);
  }
  
  function bindGlobalShortcuts() {
    var el, tid;
    if (UA.hasWebStorage) {
      $.on($threads, 'mousedown', function(e) {
        el = e.target;
        if (el.className.indexOf('thumb') != -1) {
          tid = el.getAttribute('data-id');
          if (e.which == 3) {
            $threads.setAttribute('contextmenu', 'ctxmenu-thread');
            $.id('ctxmenu-thread').target = tid;
          }
          else if (e.which == 1 && e.altKey) {
            toggleThreadPin(tid);
            return false;
          }
          else if (e.which == 1 && e.shiftKey) {
            toggleThreadHide(tid);
            return false;
          }
        }
        else if (e.which == 3) {
          $threads.setAttribute('contextmenu', 'ctxmenu-main');
        }
      });
    }
    if (!activeTheme.nobinds) {
      $.on(document, 'keyup', processKeybind);
    }
  }
  
  function toggleThreadPin(tid) {
    if (pinnedThreads[tid] >= 0) {
      delete pinnedThreads[tid];
    }
    else {
      pinnedThreads[tid] = catalog.threads[tid].r || 0;
    }
    localStorage.setItem('4chan-pin-' + catalog.slug, JSON.stringify(pinnedThreads));
    buildThreads();
  }
  
  function toggleThreadHide(tid) {
    if (hiddenMode) {
      delete hiddenThreads[tid];
      --hiddenThreadsCount;
    }
    else {
      hiddenThreads[tid] = true;
      ++hiddenThreadsCount;
    }
    
    localStorage.setItem('4chan-hide-t-' + catalog.slug, JSON.stringify(hiddenThreads));
    
    $.id('thread-' + tid).style.display = 'none';
    
    setHiddenCount(hiddenThreadsCount);
    
    if (hiddenThreadsCount === 0) {
      setHiddenMode(false);
    }
  }
  
  function setHiddenMode(state) {
    hiddenMode = state;
    
    $.id('filters-clear-hidden').textContent =
      $.id('filters-clear-hidden-bottom').textContent = state ? 'Back' : 'Show';
    
    buildThreads();
  }
  
  function setProcessedCount(type, num) {
    var label = type + '-label', count = type + '-count';
    
    if (num > 0) {
      $.id(count).textContent = $.id(count + '-bottom').textContent = num;
      $.id(label).style.display = $.id(label + '-bottom').style.display = 'inline';
    }
    else {
      $.id(label).style.display = $.id(label + '-bottom').style.display = 'none';
    }
  }
  
  function setHiddenCount(num) {
    setProcessedCount('hidden', num);
  }
  
  function setFilteredCount(num) {
    setProcessedCount('filtered', num);
  }
  
  function reportThread(tid) {
    var height, altc;
    
    if (window.passEnabled || !window.grecaptcha) {
      height = 175;
    }
    else if (altCaptcha) {
      height = 320;
      altc = '&altc=1';
    }
    else {
      height = 510;
      altc = '';
    }
    
    window.open(
      'https://sys.' + $L.d(catalog.slug) + '/' + catalog.slug +
      '/imgboard.php?mode=report&no=' + tid + altc,
      Date.now(), 
      'toolbar=0,scrollbars=1,location=0,status=1,menubar=0,resizable=1,width=380,height=' + height
    );
  }
  
  function onThreadContextClick(e) {
    var cmd = e.target.getAttribute('data-cmd');
    ctxCmds[cmd]($.id('ctxmenu-thread').target);
  }
  
  function processKeybind(e) {
    var el = e.target;
    if (el.nodeName == 'TEXTAREA' || el.nodeName == 'INPUT') {
      return;
    }
    if (keybinds[e.keyCode]) {
      keybinds[e.keyCode](e);
    }
  }
  
  function toggleHiddenThreads(e) {
    var el;
    
    e.preventDefault();
    
    if (hiddenThreadsCount > 0) {
      if ((el = $.id('filters-clear-hidden')).textContent == 'Show') {
        setHiddenMode(true);
      }
      else {
        setHiddenMode(false);
      }
    }
  }
  
  function clearPinnedThreads() {
    pinnedThreads = {};
    localStorage.removeItem('4chan-pin-' + catalog.slug);
    buildThreads();
    return false;
  }
  
  function onClick(e) {
    var t = e.target, tid;
    
    if ((t = e.target) == document) {
      return;
    }
    
    if (tid = t.getAttribute('data-watch')) {
      ThreadWatcher.toggle(
        tid,
        catalog.slug,
        catalog.threads[tid].sub,
        catalog.threads[tid].teaser,
        catalog.threads[tid].lr.id
      );
    }
    else if (tid = t.getAttribute('data-hide')) {
      e.preventDefault();
      toggleThreadHide(tid);
    }
    else if (tid = t.getAttribute('data-pin')) {
      e.preventDefault();
      toggleThreadPin(tid);
    }
    else if (tid = t.getAttribute('data-report')) {
      e.preventDefault();
      reportThread(tid);
    }
    else if (tid = t.getAttribute('data-post-menu')) {
      e.preventDefault();
      PostMenu.open(t, tid, hasThreadWatcher, hiddenThreads[tid], pinnedThreads[tid]);
    }
    else if (t.hasAttribute('data-cm-edit')) {
      e.preventDefault();
      CustomMenu.showEditor(true);
    }
    else if (t.id == 'backdrop') {
      if (!panelHidden($.id('filters'))) {
        if (!panelHidden($.id('filters-protip'))) {
          closeFiltersHelp();
        }
        else {
          closeFilters();
        }
      }
      else if (!panelHidden($.id('theme'))) {
        closeThemeEditor();
      }
    }
    else if (e.target.id == 'filter-palette') {
      closeFilterPalette();
    }
  }
  
  function buildFilterPalette() {
    var i, j, table, palette, rows, cols, tr, td, foot;
    
    $filterPalette = $.id('filter-palette');
    
    table = $.id('filter-color-table');
    palette = $.tag('tbody', table)[0];
    rows = options.filterColors.length;
    
    if (rows > 0) {
      cols = options.filterColors[0].length;
      foot = $.tag('tfoot', table)[0];
      for (i = foot.children.length - 1; i >= 0; i--) {
        foot.children[i].firstElementChild.setAttribute('colspan', cols);
      }
    }
    for (i = 0; i < rows; ++i) {
      tr = document.createElement('tr');
      for (j = 0; j < cols; ++j) {
        td = document.createElement('td');
        td.innerHTML = '<span class="button clickbox" style="background:'
          + options.filterColors[i][j] + '"></span>';
        $.on(td.firstElementChild, 'click', selectFilterColor);
        tr.appendChild(td);
      }
      palette.appendChild(tr);
    }
  }
  
  function showFilterPalette(el) {
    var picker, pos = el.getBoundingClientRect();
    
    if (!$filterPalette) {
      buildFilterPalette();
    }
    
    $.removeClass($filterPalette, 'hidden');
    $filterPalette.setAttribute('data-target', el.id.split('-')[2]);
    
    picker = $filterPalette.firstElementChild;
    picker.style.cssText = 'top:' + pos.top + 'px;left:'
      + (pos.left - picker.clientWidth - 10) + 'px;';
  }
  
  function showFiltersHelp() {
    var el = $.id('filters-protip');
    el.style.top = window.pageYOffset + 50 + 'px';
    $.removeClass(el, 'hidden');
  }
  
  function closeFiltersHelp() {
    $.addClass($.id('filters-protip'), 'hidden');
  }
  
  function onFiltersClick(e) {
    var t = e.target;
    
    if (t.id == 'filters-close')
      closeFilters();
    else if (t.id == 'filters-add')
      addEmptyFilter();
    else if (t.id == 'filters-save') {
      saveFilters();
      closeFilters();
    }
    else if (t.hasAttribute('data-active'))
      toggleFilter(t, 'active');
    else if (t.hasAttribute('data-hide'))
      toggleFilter(t, 'hide', 'top');
    else if (t.hasAttribute('data-top'))
      toggleFilter(t, 'top', 'hide');
    else if ($.hasClass(t, 'filter-color'))
      showFilterPalette(t);
    else if (t.hasAttribute('data-target'))
      deleteFilter(t);
    else if (t.hasAttribute('data-up'))
      moveFilterUp(t);
  }
  
  function moveFilterUp(el) {
    var tr, prev;
    
    tr = el.parentNode.parentNode;
    prev = tr.previousElementSibling;
    
    if (prev) {
      tr.parentNode.insertBefore(tr, prev);
    }
  }
  
  function onFiltersSearch(e) {
    var i, el, nodes, cnt, str;
    
    if (e && (e.keyCode == 27)) {
      this.value = '';
    }
    
    str = this.value.toLowerCase();
    
    nodes = document.getElementsByClassName('filter-pattern');
    
    cnt = document.getElementById('filter-list');
    
    cnt.style.display = 'none';
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.value.toLowerCase().indexOf(str) === -1) {
        el.parentNode.parentNode.style.display = 'none';
      }
      else {
        el.parentNode.parentNode.style.display = '';
      }
    }
    
    cnt.style.display = '';
  }
  
  function showFilters() {
    var i, filtersPanel, rawFilters, filterList, filterId, el;
    
    filtersPanel = $.id('filters');
    
    if (!filtersPanel) {
      filtersPanel = FC.panelHTML.build('filters', 'panel hidden');
      FC.panelHTML.build('filters-protip', 'panel hidden');
      FC.panelHTML.build('filter-palette', 'hidden');
    }
    
    if (!filtersPanel.hasAttribute('data-built')) {
      $.on(filtersPanel, 'click', onFiltersClick);
      
      $.on($.id('filter-palette-close'), 'click', closeFilterPalette);
      $.on($.id('filter-palette-clear'), 'click', clearFilterColor);
      
      $.on($.id('filters-help-open'), 'click', showFiltersHelp);
      $.on($.id('filters-help-close'), 'click', closeFiltersHelp);
      
      $.on($.id('filter-rgb'), 'keyup', filterSetCustomColor);
      $.on($.id('filter-rgb-ok'), 'click', selectFilterColor);
      
      $.on($.id('filters-search'), 'keyup', onFiltersSearch);
      
      filtersPanel.setAttribute('data-built', '1');
    }
    else {
      $.id('filters-search').value = '';
    }
    
    rawFilters = localStorage.getItem('catalog-filters');
    filterId = 0;
    
    if (rawFilters) {
      filterList = $.id('filter-list');
      rawFilters = JSON.parse(rawFilters);
      for (i in rawFilters) {
        filterList.appendChild(buildFilter(rawFilters[i], filterId));
        ++filterId;
      }
      updateFilterHitCount();
    }
    
    filtersPanel.style.top = window.pageYOffset + 60 + 'px';
    
    $.removeClass(filtersPanel, 'hidden');
    
    if (el = $.cls('filter-active', filtersPanel)[0]) {
      el.focus();
    }
    
    toggleBackdrop();
  }
  
  function closeFilters() {
    var i, filterList, nodes;
    
    $.id('filters-msg').style.display = 'none';
    $.addClass($.id('filters'), 'hidden');
    
    filterList = $.id('filter-list');
    nodes = $.tag('tr', filterList);
    for (i = nodes.length - 1; i >= 0; i--) {
      filterList.removeChild(nodes[i]);
    }
    
    closeFilterPalette();
    toggleBackdrop();
  }
  
  function closeFilterPalette() {
    if ($filterPalette && !$.hasClass($filterPalette, 'hidden')) {
      $.addClass($filterPalette, 'hidden');
    }
  }
  
  // Loads patterns from the localStorage and builds regexps
  function loadFilters() {
    if (!UA.hasWebStorage) return;
    
    activeFilters = {};
    
    var rawFilters = localStorage.getItem('catalog-filters');
    if (!rawFilters) return;
    
    rawFilters = JSON.parse(rawFilters);
    
    var
      rf, fid, v, w, wordcount,
      wordSepS, wordSepE,
      regexType = /^\/(.*)\/(i?)$/,
      regexOrNorm = /\s*\|+\s*/g,
      regexWc = /\\\*/g, replWc = '[^\\s]*',
      regexEscape = getRegexSpecials(),
      match, inner, words, rawPattern, pattern, orOp, orCluster, type;
      
    wordSepS = '(?=.*\\b';
    wordSepE = '\\b)';
    
    try {
      for (fid in rawFilters) {
        rf = rawFilters[fid];
        if (rf.active && rf.pattern !== '') {
          if (rf.boards && rf.boards.split(' ').indexOf(catalog.slug) == -1) {
            continue;
          }
          rawPattern = rf.pattern;
          if (rawPattern.charAt(0) == '#') {
            type = (rawPattern.charAt(1) == '#') ? 2 : 1;
            pattern = new RegExp(rawPattern.slice(type).replace(regexEscape, '\\$1'));
          }
          else {
            type = 0;
            if (match = rawPattern.match(regexType)) {
              pattern = new RegExp(match[1], match[2]);
            }
            else if (rawPattern.charAt(0) == '"' && rawPattern.charAt(rawPattern.length - 1) == '"') {
              pattern = new RegExp(rawPattern.slice(1, -1).replace(regexEscape, '\\$1'));
            }
            else {
              words = rawPattern.replace(regexOrNorm, '|').split(' ');
              pattern = '';
              wordcount = words.length;
              for (w = 0; w < wordcount; ++w) {
                if (words[w].indexOf('|') != -1) {
                  orOp = words[w].split('|');
                  orCluster = [];
                  for (v = orOp.length - 1; v >= 0; v--) {
                    if (orOp[v] !== '') {
                      orCluster.push(orOp[v].replace(regexEscape, '\\$1'));
                    }
                  }
                  inner = orCluster.join('|').replace(regexWc, replWc);
                  pattern += wordSepS + '(' + inner + ')' + wordSepE;
                }
                else {
                  inner = words[w].replace(regexEscape, '\\$1').replace(regexWc, replWc);
                  pattern += wordSepS + inner + wordSepE;
                }
              }
              pattern = new RegExp('^' + pattern, 'i');
            }
          }
          //console.log('Resulting regex: ' + pattern);
          activeFilters[fid] = {
            type: type,
            pattern: pattern,
            boards: rf.boards,
            fid: fid,
            hidden: rf.hidden,
            color: rf.color,
            top: rf.top,
            hits: 0
          };
        }
      }
    }
    catch (err) {
      alert('There was an error processing one of the filters: '
        + err + ' in: ' + rf.pattern);
    }
  }
  
  function saveFilters() {
    var i, j, f, rawFilters, filterList, msg, rows, color;
    
    rawFilters = {};
    filterList = $.id('filter-list');
    rows = filterList.children;
    
    for (i = 0; j = rows[i]; ++i) {
      f = {
        active: $.cls('filter-active', j)[0].checked ? 1 : 0,
        pattern: $.cls('filter-pattern', j)[0].value,
        boards: $.cls('filter-boards', j)[0].value,
        hidden: $.cls('filter-hide', j)[0].checked ? 1 : 0,
        top: $.cls('filter-top', j)[0].checked ? 1 : 0
      };
      color = $.cls('filter-color', j)[0];
      if (!color.hasAttribute('data-nocolor')) {
        f.color = color.style.backgroundColor;
      }
      rawFilters[i] = f;
    }
    
    if (rawFilters[0]) {
      localStorage.setItem('catalog-filters', JSON.stringify(rawFilters));
    }
    else {
      localStorage.removeItem('catalog-filters');
    }
    
    msg = $.id('filters-msg');
    msg.innerHTML = 'Done';
    msg.className = 'msg-ok';
    msg.style.display = 'inline';
    setTimeout(function() { msg.style.display = 'none'; }, 2000);
    
    loadFilters();
    buildThreads();
    updateFilterHitCount();
  }
  
  function filterSetCustomColor() {
    var filterRgbOk;
    
    filterRgbOk = $.id('filter-rgb-ok');
    
    filterRgbOk.style.backgroundColor = this.value;
  }
  
  function buildFilter(filter, id) {
    var td, tr, span, input;
    
    tr = document.createElement('tr');
    tr.id = 'filter-' + id;
    
    // Move up
    td = document.createElement('td');
    span = document.createElement('span');
    span.setAttribute('data-up', id);
    span.className = 'pointer';
    span.innerHTML = '&uarr;';
    td.appendChild(span);
    tr.appendChild(td);
    
    // On
    td = document.createElement('td');
    input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = !!filter.active;
    input.className = 'filter-active';
    td.appendChild(input);
    tr.appendChild(td);
    
    // Pattern
    td = document.createElement('td');
    input = document.createElement('input');
    input.type = 'text';
    input.value = filter.pattern;
    input.className = 'filter-pattern';
    td.appendChild(input);
    tr.appendChild(td);
    
    // Boards
    td = document.createElement('td');
    input = document.createElement('input');
    input.type = 'text';
    input.value = filter.boards;
    input.className = 'filter-boards';
    td.appendChild(input);
    tr.appendChild(td);
    
    // Color
    td = document.createElement('td');
    span = document.createElement('span');
    span.id = 'filter-color-' + id;
    span.title = 'Change Color';
    span.className = 'button clickbox filter-color';
    if (!filter.color) {
      span.setAttribute('data-nocolor', '1');
      span.innerHTML = '&#x2215;';
    }
    else {
      span.style.background = filter.color;
    }
    td.appendChild(span);
    tr.appendChild(td);
    
    // Hide
    td = document.createElement('td');
    input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = !!filter.hidden;
    input.className = 'filter-hide';
    td.appendChild(input);
    tr.appendChild(td);
    
    // Top
    td = document.createElement('td');
    input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = !!filter.top;
    input.className = 'filter-top';
    td.appendChild(input);
    tr.appendChild(td);
    
    // Del
    td = document.createElement('td');
    span = document.createElement('span');
    span.setAttribute('data-target', id);
    span.className = 'pointer';
    span.innerHTML = '&times;';
    td.appendChild(span);
    tr.appendChild(td);
    
    // Match count
    td = document.createElement('td');
    td.id = 'fhc-' + id;
    td.className = 'filter-hits';
    tr.appendChild(td);
    
    return tr;
  }
  
  function selectFilterColor(clear) {
    var target = $.id('filter-color-' + $filterPalette.getAttribute('data-target'));
    if (clear === true) {
      target.setAttribute('data-nocolor', '1');
      target.innerHTML = '&#x2215;';
      target.style.background = '';
    }
    else {
      target.removeAttribute('data-nocolor');
      target.innerHTML = '';
      target.style.background = this.style.backgroundColor;
    }
    closeFilterPalette();
  }
  
  function clearFilterColor() {
    selectFilterColor(true);
  }
  
  function addEmptyFilter() {
    var filter = {
      active: 1,
      pattern: '',
      boards: '',
      color: '',
      hidden: 0,
      top: 0,
      hits: 0
    };
    $.id('filter-list').appendChild(buildFilter(filter, getNextFilterId()));
  }
  
  function getNextFilterId() {
    var i, j, max, rows = $.id('filter-list').children;
    
    if (!rows.length) {
      return 0;
    }
    else {
      max = 0;
      for (i = 0; j = rows[i]; ++i) {
        j = +j.id.slice(7);
        if (j > max) {
          max = j;
        }
      }
      return max + 1;
    }
  }
  
  function deleteFilter(t) {
    var el = $.id('filter-' + t.getAttribute('data-target'));
    el.parentNode.removeChild(el);
  }
  
  function toggleFilter(el, type, xor) {
    var attr = 'data-' + type, xorEle;
    
    if (el.getAttribute(attr) == '0') {
      el.setAttribute(attr, '1');
      $.addClass(el, 'active');
      el.innerHTML = '&#x2714;';
      if (xor) {
        xorEle = $.cls('filter-' + xor, el.parentNode.parentNode)[0];
        xorEle.setAttribute('data-' + xor, '0');
        $.removeClass(xorEle, 'active');
        xorEle.innerHTML = '';
      }
    }
    else {
      el.setAttribute(attr, '0');
      $.removeClass(el, 'active');
      el.innerHTML = '';
    }
  }
  
  function updateFilterHitCount() {
    var i, j, rows = $.id('filter-list').children;
    for (i = 0; j = rows[i]; ++i) {
      $.id('fhc-' + j.id.slice(7))
        .innerHTML = activeFilters[i] ? 'x' + activeFilters[i].hits : '';
    }
  }
  
  function panelHidden(el) {
    return el && $.hasClass(el, 'hidden');
  }
  
  function showThemeEditor() {
    var themePanel, el, theme;
    
    if (!UA.hasWebStorage) {
      alert("Your browser doesn't support Local Storage");
      return;
    }
    
    themePanel = $.id('theme');
    
    if (!themePanel) {
      themePanel = FC.panelHTML.build('theme', 'panel hidden');
    }
    
    theme = localStorage.getItem('catalog-theme');
    theme = theme ? JSON.parse(theme) : {};
    
    $.id('theme-nobinds').checked = !!theme.nobinds;
    $.id('theme-nospoiler').checked = !!theme.nospoiler;
    $.id('theme-newtab').checked = !!theme.newtab;
    $.id('theme-tw').checked = hasThreadWatcher;
    $.id('theme-ddn').checked = hasDropDownNav;
    
    if (theme.css) {
      $.id('theme-css').value = theme.css;
    }
    
    $.on($.id('theme-save'), 'click', saveTheme);
    $.on($.id('theme-close'), 'click', closeThemeEditor);
      
    $.id('theme-msg').style.display = 'none';
    
    themePanel.style.top = window.pageYOffset + 60 + 'px';
    $.removeClass(themePanel, 'hidden');
    
    if (el = $.tag('input', themePanel)[0]) {
      el.focus();
    }
    
    toggleBackdrop();
    
    document.dispatchEvent(new CustomEvent('4chanCatalogThemeEditorReady'));
  }
  
  function closeThemeEditor() {
    $.off($.id('theme-save'), 'click', saveTheme);
    $.off($.id('theme-close'), 'click', closeThemeEditor);
    
    $.addClass($.id('theme'), 'hidden');
    toggleBackdrop();
  }
  
  function toggleBackdrop() {
    $.toggleClass($.id('backdrop'), 'hidden');
  }
  
  function loadTheme() {
    var customTheme;
    
    if (UA.hasWebStorage && (customTheme = localStorage.getItem('catalog-theme'))) {
      activeTheme = JSON.parse(customTheme);
    }
  }
  
  function applyTheme(customTheme, nocss) {
    if (customTheme.nobinds) {
      if (activeTheme.nobinds != customTheme.nobinds) {
        $.off(document, 'keyup', processKeybind);
      }
    }
    else {
      if (activeTheme.nobinds != customTheme.nobinds) {
        $.on(document, 'keyup', processKeybind);
      }
    }
    
    if (customTheme.nospoiler) {
      $.addClass(document.body, 'reveal-img-spoilers');
    }
    else {
      $.removeClass(document.body, 'reveal-img-spoilers');
    }
    
    if (!nocss) {
      self.applyCSS(customTheme);
    }
    
    document.dispatchEvent(new CustomEvent('4chanCatalogThemeApplied'));
  }
  
  self.applyCSS = function(customTheme, style_group, css_version) {
    var style, ss;
    
    if (!customTheme) {
      customTheme = activeTheme;
    }
    
    // Preferred stylesheet
    if (style_group !== undefined) {
      if (!(ss = $.readCookie(style_group))) {
        ss = style_group == 'nws_style' ? 'Yotsuba New' : 'Yotsuba B New';
      }
      
      activeStyleGroup = style_group;
      
      if (window.css_event && localStorage.getItem('4chan_stop_css_event') !== `${window.css_event}-${window.css_event_v}`) {
        activeStyleSheet = '_special'
        ss = window.css_event;
      }
      else {
        activeStyleSheet = ss;
      }
      
      style = document.createElement('link');
      style.type = 'text/css';
      style.id = 'base-css';
      style.rel = 'stylesheet';
      style.setAttribute('href', '//s.4cdn.org/css/catalog_'
        + ss.toLowerCase().replace(/ /g, '_') + '.' + css_version + '.css');
      document.head.insertBefore(style, $.id('mobile-css'));
    }
    
    // Custom CSS
    if (style = $.id('custom-css')) {
      document.head.removeChild(style);
    }
    
    if (customTheme.css) {
      style = document.createElement('style');
      style.type = 'text/css';
      style.id = 'custom-css';
      
      if (style.styleSheet) {
        style.styleSheet.cssText = customTheme.css;
      }
      else {
        style.innerHTML = customTheme.css;
      }
      document.head.appendChild(style);
    }
  };
  
  // Applies and saves the theme to localStorage
  function saveTheme() {
    var i, css, tw, ddn, extConfig, customTheme = {};
    
    if ($.id('theme-nobinds').checked) {
      customTheme.nobinds = true;
    }
    
    if ($.id('theme-nospoiler').checked) {
      customTheme.nospoiler = true;
    }
    
    if ($.id('theme-newtab').checked) {
      customTheme.newtab = true;
    }
    
    tw = $.id('theme-tw').checked;
    
    ddn = $.id('theme-ddn').checked;
    
    if (extConfig = localStorage.getItem('4chan-settings')) {
      extConfig = JSON.parse(extConfig);
    }
    else {
      extConfig = {};
    }
    
    if (tw != hasThreadWatcher) {
      if (tw) {
        ThreadWatcher.init();
        extConfig.disableAll = false;
      }
      else {
        ThreadWatcher.unInit();
      }
    }
    
    if (ddn != hasDropDownNav) {
      if (ddn) {
        showDropDownNav();
        extConfig.disableAll = false;
      }
      else {
        hideDropDownNav();
      }
    }
    
    extConfig.threadWatcher = tw;
    extConfig.dropDownNav = ddn;
    localStorage.setItem('4chan-settings', JSON.stringify(extConfig));
    //StorageSync.sync('4chan-settings');
    
    hasThreadWatcher = tw;
    hasDropDownNav = ddn;
    
    if ((css = $.id('theme-css').value) !== '') {
      customTheme.css = css;
    }
    
    applyTheme(customTheme);
    
    localStorage.removeItem('catalog-theme');
    
    for (i in customTheme) {
      localStorage.setItem('catalog-theme', JSON.stringify(customTheme));
      break;
    }
    
    //StorageSync.sync('catalog-theme');
    
    activeTheme = customTheme;
    
    buildThreads();
    closeThemeEditor();
  }
  
  function loadThreadList(key) {
    var i, threads, mod = false, ft = 0;
    
    if (threads = localStorage.getItem(key)) {
      ft = +Object.keys(catalog.threads).pop();
      threads = JSON.parse(threads);
      for (i in threads) {
        if (!catalog.threads[i] && i < ft) {
          delete threads[i];
          mod = true;
        }
      }
      for (i in threads) {
        if (mod) { localStorage.setItem(key, JSON.stringify(threads)); }
        return threads;
      }
      localStorage.removeItem(key);
    }
    return {};
  }
  
  function loadStorage() {
    if (UA.hasWebStorage) {
      hiddenThreads = loadThreadList('4chan-hide-t-' + catalog.slug);
      pinnedThreads = loadThreadList('4chan-pin-' + catalog.slug);
    }
  }
  
  function loadSettings() {
    var settings;
    if (UA.hasWebStorage && (settings = localStorage.getItem('catalog-settings'))) {
      $.extend(options, JSON.parse(settings));
    }
  }    
  
  function saveSettings() {
    var i, key, settings;
    if (!UA.hasWebStorage) {
      return;
    }
    settings = {};
    for (i = basicSettings.length - 1; i >= 0; i--) {
      key = basicSettings[i];
      settings[key] = options[key];
    }
    localStorage.setItem('catalog-settings', JSON.stringify(settings));
    //StorageSync.sync('catalog-settings');
  }
  
  function setExtended(mode, init) {
    var cls = '';
    if (mode) {
      $teaserCtrl.selectedIndex = 1;
      cls = 'extended-';
      options.extended = true;
    }
    else {
      $teaserCtrl.selectedIndex = 0;
      options.extended = false;
    }
    if (options.large) {
      cls += 'large';
    }
    else {
      cls += 'small';
    }
    $threads.className = cls;
    if (!init) {
      saveSettings();
    }
  }
  
  function setLarge(mode, init) {
    var cls = options.extended ? 'extended-' : '';
    if (mode) {
      $sizeCtrl.selectedIndex = 1;
      cls += 'large';
      options.large = true;
    }
    else {
      $sizeCtrl.selectedIndex = 0;
      cls += 'small';
      options.large = false;
    }
    $threads.className = cls;
    if (!init) {
      saveSettings();
      buildThreads();
    }
  }
  
  function setOrder(order, init) {
    var o = { alt: 0, absdate: 1, date: 2, r: 3 };
    if (o[order] !== undefined) {
      $orderCtrl.selectedIndex = o[order];
      options.orderby = order;
    }
    else {
      $orderCtrl.selectedIndex = 0;
      options.orderby = 'date';
    }
    if (!init) {
      saveSettings();
      buildThreads();
    }
  }
  
  function onTeaserChange() {
    setExtended($teaserCtrl.options[$teaserCtrl.selectedIndex].value == 'on');
  }
  
  function onOrderChange() {
    setOrder($orderCtrl.options[$orderCtrl.selectedIndex].value);
  }
  
  function onSizeChange() {
    setLarge($sizeCtrl.options[$sizeCtrl.selectedIndex].value == 'large');
  }
  
  function cycleOrder() {
    if (options.orderby == 'date') {
      setOrder('alt');
    }
    else if (options.orderby == 'alt') {
      setOrder('r');
    }
    else if (options.orderby == 'r') {
      setOrder('absdate');
    }
    else {
      setOrder('date');
    }
  }
  
  function sortThreadList(threadList) {
    var order = options.orderby;
    
    if (order == 'date') {
      threadList.sort(function(a, b) {
        if (a.id > b.id) return -1;
        if (a.id < b.id) return 1;
        return 0;
      });
    }
    else if (order == 'absdate' && !catalog.no_lr) {
      threadList.sort(function(a, b) {
        a = a.entry.lr.id;
        b = b.entry.lr.id;
        if (a > b) return -1;
        if (a < b) return 1;
        return 0;
      });
    }
    else if (order == 'r') {
      threadList.sort(function(a, b) {
        var 
          a = a.entry.r || 0,
          b = b.entry.r || 0;
        if (a > b) return -1;
        if (a < b) return 1;
        return 0;
      });
    }
    else { // alt
      threadList.sort(function(a, b) {
        if (a.entry.b < b.entry.b) return -1;
        if (a.entry.b > b.entry.b) return 1;
        return 0;
      });
    }
  }
  
  function getFilteredThreads() {
    var i, id, entry, hl, onTop, pinned, teaser, tripcode, af, threads, fid,
      filtered;
    
    filtered = 0;
    
    threads = [];
    
    threadloop: for (id in catalog.threads) {
      id = +id;
      entry = catalog.threads[id];
      hl = onTop = pinned = false;
      
      if (entry.sub) {
        teaser = '<b>' + entry.sub + '</b>';
        if (entry.teaser) {
          teaser += ': ' + entry.teaser;
        }
      }
      else {
        teaser = entry.teaser;
      }
      
      if (hiddenMode) {
        if (!hiddenThreads[id]) {
          continue;
        }
        ++hiddenThreadsCount;
      }
      else if(!quickFilterPattern) {
        if (hiddenThreads[id]) {
          ++hiddenThreadsCount;
          continue;
        }
        if (pinnedThreads[id] >= 0) {
          pinned = onTop = true;
        }
        else {
          if (entry.capcode) {
            tripcode = (entry.trip || '') + '!#' + entry.capcode;
          }
          else {
            tripcode = entry.trip;
          }
          for (fid in activeFilters) {
            af = activeFilters[fid];
            if ((af.type == 0 && (af.pattern.test(teaser) || af.pattern.test(entry.file)))
              || (af.type == 1 && af.pattern.test(tripcode))
              || (af.type == 2 && af.pattern.test(entry.author))) {
              if (af.hidden) {
                ++filtered;
                af.hits += 1;
                continue threadloop;
              }
              hl = af;
              onTop = !!af.top;
              af.hits += 1;
              break;
            }
          }
        }
      }
      else if (!quickFilterPattern.test(teaser) && !quickFilterPattern.test(entry.file)) {
        continue;
      }
      
      if (pinnedThreads[id] >= 0) {
        pinned = onTop = true;
      }
      
      threads.push(
        {
          id: id,
          entry: entry,
          pinned: pinned,
          onTop: onTop,
          hl: hl
        }
      );
    }
    
    filteredThreadsCount = filtered;
    
    return threads;
  }
  
  function formatImageThreads(threads) {
    var
      i, k, id, entry, item, thread, hl, onTop, pinned, spoiler,
      rDiff, html, provider, contentUrl,
      pinhl, newtab, watchKey, teaser, topHtml, stickyHtml,
      ratio, maxSize, imgWidth, imgHeight, calcSize,
      capcodeReplies, capcodeReply, capcodeTitle, page;
    
    provider = '//boards.' + $L.d(catalog.slug) + '/' + catalog.slug + '/thread/';
    contentUrl = 'i.4cdn.org/' + catalog.slug + '/';
    
    calcSize = !options.large;
    newtab = activeTheme.newtab ? 'target="_blank" ' : '';
    
    if (catalog.custom_spoiler) {
      spoiler = options.imgspoiler + '-' + catalog.slug + catalog.custom_spoiler + '.png';
    }
    else {
      spoiler = options.imgspoiler + '.png';
    }
    
    html = '';
    topHtml = '';
    stickyHtml = '';
    
    for (i = 0; item = threads[i]; ++i) {
      id = item.id;
      entry = item.entry;
      hl = item.hl;
      onTop = item.onTop;
      pinned = item.pinned;
      
      if (entry.sub) {
        teaser = '<b>' + entry.sub + '</b>';
        if (entry.teaser) {
          teaser += ': ' + entry.teaser;
        }
      }
      else {
        teaser = entry.teaser;
      }
      
      thread = '<div id="thread-' + id + '" class="thread">';
      
      if (hasThreadWatcher) {
        watchKey = id + '-' + catalog.slug;
        thread += '<span id="leaf-' + id + '" data-watch="' + id + '" '
          + (ThreadWatcher.watched[watchKey] ?
            'title="Unwatch" class="unwatchIcon"></span>' :
            'title="Watch" class="watchIcon"></span>');
      }
      
      thread += '<a ' + newtab + 'href="' + provider + id + '"'
        + (entry.imgspoiler ? ' class="imgspoiler"' : '')
        + '><img loading="lazy" alt="" id="thumb-' + id + '" class="thumb';
      
      if (hl.color) {
        pinhl = ' hl" style="border-color: ' + hl.color;
      }
      else if (pinned) {
        pinhl = ' pinned';
      }
      else {
        pinhl = '';
      }
      
      if (entry.imgurl) {
        if (entry.imgspoiler && !activeTheme.nospoiler) {
          thread += pinhl + '" src="' + spoiler;
        }
        else {
          imgWidth = entry.tn_w;
          imgHeight = entry.tn_h;
          
          if (calcSize) {
            maxSize = options.smallsize;
            if (imgWidth > maxSize) {
              ratio = maxSize / imgWidth;
              imgWidth = maxSize;
              imgHeight = imgHeight * ratio;
            }
            if (imgHeight > maxSize) {
              ratio = maxSize / imgHeight;
              imgHeight = maxSize;
              imgWidth = imgWidth * ratio;
            }
          }
          thread += pinhl + '" width="' + imgWidth
            + '" height="' + imgHeight + '" src="//'
            + contentUrl + entry.imgurl + 's.jpg';
        }
      }
      else if (entry.imgdel) {
        thread += ' imgdel' + pinhl + '" src="' + options.imgdel;
      }
      else {
        thread += ' nofile' + pinhl + '" src="' + options.nofile;
      }
      
      thread += '" data-id="' + id + '" /></a>';
      
      if (entry.sticky || entry.closed || entry.capcodereps) {
        thread += '<div class="threadIcons">';
        if (entry.sticky) {
          thread += '<span title="Sticky" class="threadIcon stickyIcon"></span>';
        }
        if (entry.closed) {
          thread += '<span title="Closed" class="threadIcon closedIcon"></span>';
        }
        if (entry.capcodereps) {
          capcodeReplies = entry.capcodereps.split(',');
          for (k = 0; capcodeReply = capcodeReplies[k]; ++k) {
            if (capcodeTitle = capcodeMap[capcodeReply]) {
              thread += '<span title="'
                + capcodeTitle + ' Replies" class="threadIcon '
                + capcodeReply + 'Icon"></span>';
            }
          }
        }
        thread += '</div>';
      }
      
      thread += '<div title="(R)eplies / (I)mage Replies'
        + (onTop ? ' / (P)age' : '') + '" id="meta-' + id + '" class="meta">';
      
      if (entry.bumplimit) {
        thread += '<i>R: <b>' + entry.r + '</b></i>';
      }
      else {
        thread += 'R: <b>' + entry.r + '</b>';
      }
      if (pinned) {
        rDiff = entry.r - pinnedThreads[id];
        if (rDiff > 0) {
          thread += ' (+' + rDiff + ')';
          pinnedThreads[id] = entry.r;
        }
        else {
          thread += '(+0)';
        }
      }
      if (entry.i) {
        if (entry.imagelimit) {
          thread += ' / <i>I: <b>' + entry.i + '</b></i>';
        }
        else {
          thread += ' / I: <b>' + entry.i + '</b>';
        }
      }
      
      if (onTop && (page = getThreadPage(id)) >= 0) {
        thread += ' / P: <b>' + page + '</b>';
      }
      
      thread += '<a href="#" class="postMenuBtn" title="Thread Menu" '
        + 'data-post-menu="' + id + '">▶</a>';
      
      thread += '</div>';
      
      if (teaser) {
        thread += '<div class="teaser';
        if (hl.color) {
          thread += ' style="color:' + hl.color;
        }
        thread += '">' + teaser + '</div>';
      }
      
      if (window.partyHats) {
        thread = '<div class="party-cnt">' + thread
          + '</div><img class="party-hat" src="//s.4cdn.org/image/'
          + window.partyHats + '"></div>';
      }
      else {
        thread += '</div>';
      }
      
      if (entry.sticky) {
        stickyHtml += thread;
      }
      else if (onTop) {
        topHtml += thread;
      }
      else {
        html += thread;
      }
    }
    
    topHtml = stickyHtml + topHtml;
    
    if (quickFilterPattern && (html === '' && topHtml === '')) {
      html = '<div class="error">Nothing Found</div>';
    }
    else if (topHtml) {
      html = topHtml + html + '<div class="clear"></div>';
    }
    else {
      html += '<div class="clear"></div>';
    }
    
    return html;
  }
  
  function formatTextThreads(threads) {
    var
      i, id, entry, item, thread, hl, onTop, pinned,
      rDiff, html, provider,
      pinhl, newtab, topHtml, aTag;
    
    provider = '//boards.' + $L.d(catalog.slug) + '/' + catalog.slug + '/thread/';
    
    newtab = activeTheme.newtab ? 'target="_blank" ' : '';
    
    html = '';
    topHtml = '';
    
    for (i = 0; item = threads[i]; ++i) {
      id = item.id;
      entry = item.entry;
      hl = item.hl;
      onTop = item.onTop;
      pinned = item.pinned;
      
      if (hl.color) {
        pinhl = ' class="hl" style="box-shadow: -3px 0 ' + hl.color + '"';
      }
      else if (pinned) {
        pinhl = ' class="pinned"';
      }
      else {
        pinhl = '';
      }
      
      aTag = '<a ' + newtab + 'href="' + provider + id + '">';
      
      thread = '<tr id="thread-' + id + '"' + pinhl
        + '><td class="txt-no">' + aTag
        + '»</a></td><td class="txt-sub">' + aTag + entry.sub
        + '</a></td><td class="txt-rep">';
      
      if (entry.bumplimit) {
        thread += '<i>' + entry.r + '</i>';
      }
      else {
        thread += entry.r;
      }
      
      if (pinned) {
        rDiff = entry.r - pinnedThreads[id];
        if (rDiff > 0) {
          thread += ' (+' + rDiff + ')';
          pinnedThreads[id] = entry.r;
        }
        else {
          thread += '(+0)';
        }
      }
      
      thread += '</td><td class="txt-date" data-id="' + id + '">' + entry.date
        + '</td><td class="txt-ctrl"><a href="#" class="postMenuBtn" title="Thread Menu" '
        + 'data-post-menu="' + id + '">▶</a></td></tr>';
      
      if (onTop) {
        topHtml += thread;
      }
      else {
        html += thread;
      }
    }
    
    if (quickFilterPattern && (html === '' && topHtml === '')) {
      html = '<div class="error">Nothing Found</div>';
    }
    else if (topHtml) {
      html = topHtml + html + '<div class="clear"></div>';
    }
    else {
      html += '<div class="clear"></div>';
    }
    
    html = '<table><thead><tr><th class="txt-no"></th><th class="txt-sub">Subject</th><th class="txt-rep">Replies</th>'
      + '<th class="txt-date">Date</th><th class="txt-ctrl"></th></tr></thead><tbody>' + html + '</tbody></table>';
    
    return html;
  }
  
  function buildThreads() {
    var i, tip, fid, threads;
    
    if (catalog.count === 0) {
      return;
    }
    
    if ($threads.hasChildNodes()) {
      if (tip = document.getElementById('th-tip')) {
        document.body.removeChild(tip);
      }
      $threads.textContent = '';
    }
    
    hiddenThreadsCount = 0;
    filteredThreadsCount = 0;
    
    for (fid in activeFilters) {
      activeFilters[fid].hits = 0;
    }
    
    threads = getFilteredThreads();
    
    sortThreadList(threads);
    
    if (!window.text_only) {
      $threads.innerHTML = formatImageThreads(threads);
    }
    else {
      $threads.innerHTML = formatTextThreads(threads);
    }
    
    for (i in pinnedThreads) {
      localStorage.setItem('4chan-pin-' + catalog.slug, JSON.stringify(pinnedThreads));
      break;
    }
    
    setFilteredCount(filteredThreadsCount);
    
    setHiddenCount(hiddenThreadsCount);
  }
  
  function onThreadMouseOver(e) {
    var t = e.target;
    
    if ($.hasClass(t, 'thumb') || (window.text_only && $.hasClass(t, 'txt-date'))) {
      clearTimeout(tooltipTimeout);
      if (hasTooltip) {
        hideTooltip();
      }
      tooltipTimeout = setTimeout(showTooltip, options.tipdelay, t);
    }
  }
  
  function onThreadMouseOut() {
    clearTimeout(tooltipTimeout);
    if (hasTooltip) {
      hideTooltip();
    }
  }
  
  function showTooltip(t) {
    var now, tip, el, rect, docWidth, style, page, tid, thread, top,
      bottom, docHeight, left;
    
    now = Date.now() / 1000;
    
    rect = t.getBoundingClientRect();
    docWidth = document.documentElement.offsetWidth;
    
    tid = t.getAttribute('data-id');
    
    if (!tid) {
      return;
    }
    
    thread = catalog.threads[tid];
    
    if (page = getThreadPage(tid)) {
      page = '<span class="post-page">Page ' + page + '</span>';
    }
    else {
      page = '';
    }
    
    if (thread.sub && !window.text_only) {
      tip = '<span class="post-subject">' + thread.sub + '</span>';
    }
    else {
      tip = 'Posted';
    }
    
    tip += ' by <span class="'
      + (thread.capcode ? (thread.capcode + '-capcode ') : '')
      + 'post-author">' + (thread.author || catalog.anon);
    
    if (thread.trip) {
      tip += ' <span class="post-tripcode">' + thread.trip + '</span>';
    }
    
    if (thread.capcode) {
      tip += ' ## '
        + capcodeMap[thread.capcode];
    }
    
    tip += '</span> ';
    
    if (catalog.flags && thread.country) {
      tip += '<div class="flag flag-' + thread.country.toLowerCase() + '"></div> ';
    }
    
    tip += '<span class="post-ago">'
      + getDuration(now - thread.date)
      + ' ago</span>' + page;
    
    if ((!options.extended && thread.teaser) || window.text_only) {
      tip += '<p class="post-teaser">' + thread.teaser + '</p>';
    }
    
    if (thread.lr.date) {
      tip += '<div class="post-last">Last reply by <span class="'
      + (thread.lr.capcode ? (thread.lr.capcode + '-capcode ') : '')
      + 'post-author">' + thread.lr.author;
      
      if (thread.lr.trip) {
        tip += ' <span class="post-tripcode">' + thread.lr.trip + '</span>';
      }
      
      if (thread.lr.capcode) {
        tip += ' ## '
          + thread.lr.capcode.charAt(0).toUpperCase()
          + thread.lr.capcode.slice(1);
      }
      
      if (thread.lr.date) {
        tip += '</span> <span class="post-ago">'
          + getDuration(now - thread.lr.date)
          + ' ago</span>';
      }
      else {
        tip += '</span>';
      }
    }
    
    el = document.createElement('div');
    el.id = 'post-preview';
    el.innerHTML = tip;
    document.body.appendChild(el);
    
    if ((docWidth - rect.right) < (0 | (docWidth * 0.3))) {
      left = rect.left - el.offsetWidth - 5;
    }
    else {
      left = rect.left + rect.width + 5;
    }
    
    docHeight = document.documentElement.clientHeight;
    
    bottom = rect.top + el.offsetHeight;
    
    if (bottom > docHeight) {
      top = rect.top - (bottom - docHeight) - 20;
    }
    else {
      top = rect.top;
    }
    
    if (top < 0) {
      top = 3;
    }
    
    style = el.style;
    style.left = left + window.pageXOffset + 'px';
    style.top = top + window.pageYOffset + 'px';
    
    hasTooltip = true;
  }
  
  function hideTooltip() {
    document.body.removeChild($.id('post-preview'));
    hasTooltip = false;
  }
  
  function getDuration(delta, precise) {
    var count, head, tail;
    if (delta < 2) {
      return 'less than a second';
    }
    if (precise && delta < 300) {
      return (0 | delta) + ' seconds';
    }
    if (delta < 60) {
      return (0 | delta) + ' seconds';
    }
    if (delta < 3600) {
      count = 0 | (delta / 60);
      if (count > 1) {
        return count + ' minutes';
      }
      else {
        return 'one minute';
      }
    }
    if (delta < 86400) {
      count = 0 | (delta / 3600);
      if (count > 1) {
        head = count + ' hours';
      }
      else {
        head = 'one hour';
      }
      tail = 0 | (delta / 60 - count * 60);
      if (tail > 1) {
        head += ' and ' + tail + ' minutes';
      }
      return head;
    }
    count = 0 | (delta / 86400);
    if (count > 1) {
      head = count + ' days';
    }
    else {
      head = 'one day';
    }
    tail = 0 | (delta / 3600 - count * 24);
    if (tail > 1) {
      head += ' and ' + tail + ' hours';
    }
    return head;
  }
};

var Filter = {};

Filter.init = function() {
  this.entities = document.createElement('div');
  Filter.load();
};

Filter.match = function(post, board) {
  var i, com, f, filters, hit;
  
  hit = false;
  filters = Filter.activeFilters;
  
  for (i = 0; f = filters[i]; ++i) {
    // boards
    if (!f.boards[board]) {
      continue;
    }
    // tripcode
    if (f.type == 0) {
      if (f.pattern === post.trip) {
        hit = true;
        break;
      }
    }
    // name
    else if (f.type == 1) {
      if (f.pattern === post.name) {
        hit = true;
        break;
      }
    }
    // comment
    else if (f.type == 2 && post.com) {
      if (com === undefined) {
        this.entities.innerHTML
          = post.com.replace(/<br>/g, '\n').replace(/[<[^>]+>/g, '');
        com = this.entities.textContent;
      }
      if (f.pattern.test(com)) {
        hit = true;
        break;
      }
    }
    // user id
    else if (f.type == 4) {
      if (f.pattern === post.id) {
        hit = true;
        break;
      }
    }
    // subject
    else if (f.type == 5) {
      if (f.pattern.test(post.sub)) {
        hit = true;
        break;
      }
    }
    // filename
    else if (f.type == 6) {
      if (f.pattern.test(post.filename)) {
        hit = true;
        break;
      }
    }
  }
  
  return hit;
};

FC.getDocTopOffset = function() {
  if (window.Config.dropDownNav && !window.Config.autoHideNav) {
    return $.id(
      window.Config.classicNav ? 'boardNavDesktop' : 'boardNavMobile'
    ).offsetHeight;
  }
  else {
    return 0;
  }
};

Filter.load = function() {
  var i, j, f, rawFilters, rawPattern, fid, regexEscape, regexType,
    wordSepS, wordSepE, words, inner, regexWildcard, replaceWildcard, boards,
    pattern, match, tmp;
  
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
      if (f.active && f.pattern !== '') {
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
          hide: f.hide,
          auto: f.auto
        });
      }
    }
  }
  catch (e) {
    alert('There was an error processing one of the filters: '
      + e + ' in: ' + rawPattern);
  }
};

/**
 * Thread watcher
 */
var ThreadWatcher = {
  hasFilters: false
};

ThreadWatcher.init = function() {
  var cnt, pos, el;
  
  if (this.hasFilters) {
    Filter.init();
  }
  
  this.listNode = null;
  this.charLimit = 45;
  this.watched = {};
  this.blacklisted = {};
  this.isRefreshing = false;
  
  if (FC.hasMobileLayout) {
    el = document.createElement('a');
    el.href = '#';
    el.textContent = 'TW';
    el.addEventListener('click', ThreadWatcher.toggleList, false);
    cnt = $.id('settingsWindowLinkMobile');
    cnt.parentNode.insertBefore(el, cnt);
    cnt.parentNode.insertBefore(document.createTextNode(' '), cnt);
  }
  
  cnt = document.createElement('div');
  cnt.id = 'threadWatcher';
  cnt.setAttribute('data-trackpos', 'TW-position');
  
  if (FC.hasMobileLayout) {
    cnt.style.display = 'none';
  }
  else {
    if (window.Config['TW-position']) {
      cnt.style.cssText = window.Config['TW-position'];
    }
    else {
      cnt.style.left = '10px';
      cnt.style.top = '75px';
    }
  }
  
  cnt.innerHTML = '<div class="drag" id="twHeader">'
    + (FC.hasMobileLayout ? ('<div id="twClose" class="icon closeIcon"></div>') : '')
    + 'Thread Watcher'
    + (UA.hasCORS ? ('<div id="twPrune" class="icon refreshIcon" title="Refresh"></div></div>') : '</div>');
  
  this.listNode = document.createElement('ul');
  this.listNode.id = 'watchList';
  
  this.load();
  
  this.build();
  
  cnt.appendChild(this.listNode);
  document.body.appendChild(cnt);
  cnt.addEventListener('mouseup', this.onClick, false);
  Draggable.set($.id('twHeader'));
  window.addEventListener('storage', this.syncStorage, false);
  
  if (!FC.hasMobileLayout && this.canAutoRefresh()) {
    this.refresh();
  }
};

ThreadWatcher.unInit = function() {
  var cnt;
  
  if (cnt = $.id('threadWatcher')) {
    cnt.removeEventListener('mouseup', this.onClick, false);
    Draggable.unset($.id('twHeader'));
    window.removeEventListener('storage', this.syncStorage, false);
    document.body.removeChild(cnt);
  }
};

ThreadWatcher.toggleList = function(e) {
  var el = $.id('threadWatcher');
  
  e && e.preventDefault();
  
  if (ThreadWatcher.canAutoRefresh()) {
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
    ThreadWatcher.load();
    ThreadWatcher.build();
  }
};

ThreadWatcher.load = function() {
  var storage;
  
  if (storage = localStorage.getItem('4chan-watch')) {
    this.watched = JSON.parse(storage);
  }
  if (storage = localStorage.getItem('4chan-watch-bl')) {
    this.blacklisted = JSON.parse(storage);
  }
};

ThreadWatcher.build = function() {
  var html, tuid, key, cls;
  
  html = '';
  
  for (key in this.watched) {
    tuid = key.split('-');
    html += '<li id="watch-' + key
      + '"><span class="pointer" data-cmd="unwatch" data-id="'
      + tuid[0] + '" data-board="' + tuid[1] + '">&times;</span> <a href="'
      + this.linkToThread(tuid[0], tuid[1], this.watched[key][1]) + '"';
    
    if (this.watched[key][1] == -1) {
      html += ' class="deadlink">';
    }
    else {
      cls = [];
      
      if (this.watched[key][3]) {
        cls.push('archivelink');
      }
      
      if (this.watched[key][4]) {
        cls.push('hasYouReplies');
        html += ' title="This thread has replies to your posts"';
      }
      
      if (this.watched[key][2]) {
        html += ' class="' + (cls[0] ? (cls.join(' ') + ' ') : '')
          + 'hasNewReplies">(' + this.watched[key][2] + ') ';
      }
      else {
        html += (cls[0] ? ('class="' + cls.join(' ') + '"') : '') + '>';
      }
    }
    
    html += '/' + tuid[1] + '/ - ' + this.watched[key][0] + '</a></li>';
  }
  
  ThreadWatcher.listNode.innerHTML = html;
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
    ThreadWatcher.refreshWithAutoWatch();
  }
  else if (t.id == 'twClose') {
    ThreadWatcher.toggleList();
  }
};

ThreadWatcher.generateLabel = function(sub, com, tid) {
  var label;
  
  if (label = sub) {
    label = label.slice(0, this.charLimit);
  }
  else if (label = com) {
    label = label.replace(/(?:<br>)+/g, ' ')
      .replace(/<[^>]*?>/g, '').slice(0, this.charLimit);
  }
  else {
    label = 'No.' + tid;
  }
  
  return label;
};

ThreadWatcher.toggle = function(tid, board, sub, com, lr) {
  var key, label, lastReply, icon;
  
  key = tid + '-' + board;
  icon = $.id('leaf-' + tid);
  
  if (this.watched[key]) {
    delete this.watched[key];
    if (icon) {
      icon.className = 'watchIcon';
      icon.title = 'Watch';
    }
  }
  else {
    label = ThreadWatcher.generateLabel(sub, com, tid);
    
    lastReply = lr || tid;
    
    this.watched[key] = [ label, lastReply, 0 ];
    
    icon.className = 'unwatchIcon';
    icon.title = 'Unwatch';
  }
  this.save();
  this.load();
  this.build();
};

ThreadWatcher.addRaw = function(post, board) {
  var key, label;
  
  key = post.no + '-' + board;
  
  if (this.watched[key]) {
    return;
  }
  
  label = ThreadWatcher.generateLabel(post.sub, post.com, post.no);
  
  this.watched[key] = [ label, 0, 0 ];
};

ThreadWatcher.save = function() {
  var i;
  
  ThreadWatcher.sortByBoard();
  
  localStorage.setItem('4chan-watch', JSON.stringify(ThreadWatcher.watched));
  
  //StorageSync.sync('4chan-watch');
  
  for (i in ThreadWatcher.blacklisted) {
    localStorage.setItem('4chan-watch-bl', JSON.stringify(ThreadWatcher.blacklisted));
    //StorageSync.sync('4chan-watch-bl');
    break;
  }
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
  //StorageSync.sync('4chan-tw-timestamp');
};

ThreadWatcher.refreshWithAutoWatch = function() {
  var i, f, count, board, boards, img;
  
  if (!this.hasFilters) {
    this.refresh();
    return;
  }
  
  Filter.load();
  
  boards = {};
  count = 0;
  
  for (i = 0; f = Filter.activeFilters[i]; ++i) {
    if (!f.auto || !f.boards) {
      continue;
    }
    for (board in f.boards) {
      if (boards[board]) {
        continue;
      }
      boards[board] = true;
      ++count;
    }
  }
  
  if (!count) {
    this.refresh();
    return;
  }
  
  img = $.id('twPrune');
  img.className = 'icon rotateIcon';
  this.isRefreshing = true;
  
  this.fetchCatalogs(boards, count);
};

ThreadWatcher.fetchCatalogs = function(boards, count) {
  var to, board, catalogs, meta;
  
  catalogs = {};
  meta = { count: count };
  to = 0;
  
  for (board in boards) {
    setTimeout(ThreadWatcher.fetchCatalog, to, board, catalogs, meta);
    to += 200;
  }
};

ThreadWatcher.parseCatalogJSON = function(data) {
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

ThreadWatcher.fetchCatalog = function(board, catalogs, meta) {
  var xhr;
  
  xhr = new XMLHttpRequest();
  xhr.open('GET', '//a.4cdn.org/' + board + '/catalog.json');
  xhr.onload = function() {
    meta.count--;
    catalogs[board] = ThreadWatcher.parseCatalogJSON(this.responseText);
    if (!meta.count) {
      ThreadWatcher.onCatalogsLoaded(catalogs);
    }
  };
  xhr.onerror = function() {
    meta.count--;
    if (!meta.count) {
      ThreadWatcher.onCatalogsLoaded(catalogs);
    }
  };
  xhr.send(null);
};

ThreadWatcher.onCatalogsLoaded = function(catalogs) {
  var i, j, board, page, pages, threads, thread, key, blacklisted;
  
  $.id('twPrune').className = 'icon rotateIcon';
  this.isRefreshing = false;
  
  blacklisted = {};
  
  for (board in catalogs) {
    pages = catalogs[board];
    for (i = 0; page = pages[i]; ++i) {
      threads = page.threads;
      for (j = 0; thread = threads[j]; ++j) {
        key = thread.no + '-' + board;
        if (this.blacklisted[key]) {
          blacklisted[key] = 1;
          continue;
        }
        if (Filter.match(thread, board)) {
          this.addRaw(thread, board);
        }
      }
    }
  }
  
  this.blacklisted = blacklisted;
  this.build(true);
  this.refresh();
};

ThreadWatcher.refresh = function() {
  var i, to, key, total, img;
  
  if (total = $.id('watchList').children.length) {
    i = to = 0;
    img = $.id('twPrune');
    img.className = 'icon rotateIcon';
    ThreadWatcher.isRefreshing = true;
    ThreadWatcher.setRefreshTimestamp();
    for (key in ThreadWatcher.watched) {
      setTimeout(ThreadWatcher.fetch, to, key, ++i == total ? img : null);
      to += 200;
    }
  }
};

ThreadWatcher.onRefreshEnd = function(img) {
  img.className = 'icon refreshIcon';
  this.isRefreshing = false;
  this.save();
  this.load();
  this.build();
};

ThreadWatcher.parseThreadJSON = function(data) {
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

ThreadWatcher.getTrackedReplies = function(board, tid) {
  var tracked = null;
  
  if (tracked = localStorage.getItem('4chan-track-' + board + '-' + tid)) {
    tracked = JSON.parse(tracked);
  }
  
  return tracked;
};

ThreadWatcher.fetch = function(key, img) {
  var tuid, xhr, li;
  
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
    var i, newReplies, posts, lastReply, trackedReplies, dummy, quotelinks, q, j;
    if (this.status == 200) {
      posts = ThreadWatcher.parseThreadJSON(this.responseText);
      lastReply = ThreadWatcher.watched[key][1];
      newReplies = 0;
      
      if (!ThreadWatcher.watched[key][4]) {
        trackedReplies = ThreadWatcher.getTrackedReplies(tuid[1], tuid[0]);
        
        if (trackedReplies) {
          dummy = document.createElement('div');
        }
      }
      else {
        trackedReplies = null;
      }
      
      for (i = posts.length - 1; i >= 1; i--) {
        if (posts[i].no <= lastReply) {
          break;
        }
        ++newReplies;
        
        if (trackedReplies) {
          dummy.innerHTML = posts[i].com;
          quotelinks = $.cls('quotelink', dummy);
          
          if (!quotelinks[0]) {
            continue;
          }
          
          for (j = 0; q = quotelinks[j]; ++j) {
            if (trackedReplies[q.textContent]) {
              ThreadWatcher.watched[key][4] = 1;
              trackedReplies = null;
              break;
            }
          }
        }
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

ThreadWatcher.linkToThread = function(tid, board, post) {
  return '//' + location.host + '/'
    + board + '/thread/'
    + tid + (post > 0 ? ('#p' + post) : '');
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
    
    self.offsetTop = FC.getDocTopOffset();
    
    document.addEventListener('mouseup', self.endDrag, false);
    document.addEventListener('mousemove', self.onDrag, false);
  },
  
  endDrag: function() {
    document.removeEventListener('mouseup', Draggable.endDrag, false);
    document.removeEventListener('mousemove', Draggable.onDrag, false);
    if (Draggable.key && window.Config) {
      window.Config[Draggable.key] = Draggable.el.style.cssText;
      localStorage.setItem('4chan-settings', JSON.stringify(window.Config));
      //StorageSync.sync('4chan-settings');
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
    if (top <= Draggable.offsetTop) {
      style.top = Draggable.offsetTop + 'px';
      style.bottom = '';
    }
    else if (Draggable.bottom < top &&
      Draggable.el.clientHeight < document.documentElement.clientHeight) {
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
 * Custom Menu
 */
var CustomMenu = {
  dropDownNav: false,
  classicNav: false
};

CustomMenu.initCtrl = function(dropDownNav, classicNav) {
  var el, cnt;
  
  CustomMenu.dropDownNav = dropDownNav;
  CustomMenu.classicNav = classicNav;
  
  el = document.createElement('span');
  el.className = 'custom-menu-ctrl';
  el.innerHTML = '[<a data-cm-edit title="Edit Menu" href="#">Edit</a>]';
  
  if (CustomMenu.dropDownNav && !CustomMenu.classicNav && !FC.hasMobileLayout) {
    cnt = $.id('boardSelectMobile').parentNode;
    cnt.insertBefore(el, cnt.lastChild);
  }
  else {
    cnt = $.cls('boardList');
    cnt[0] && cnt[0].appendChild(el);
    cnt[1] && cnt[1].appendChild(el.cloneNode(true));
  }
};
/*
CustomMenu.showNWSBoards = function() {
  var i, el, nodes, len;
  
  nodes = $.cls('nwsb');
  len = nodes.length;
  
  for (i = len - 1; el = nodes[i]; i--) {
    $.removeClass(el, 'nwsb');
  }
};
*/
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
  var i, el, cntBottom, board, navs, boardList, cnt;
  
  if (!str) {
    if (CustomMenu.dropDownNav && !CustomMenu.classicNav && !FC.hasMobileLayout) {
      if (el = $.cls('customBoardList')[0]) {
        el.parentNode.removeChild(el);
      }
    }
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
    el.href = '//boards.' + $L.d(board) + '/' + board + (board !== 'f' ? '/catalog' : '');
    cnt.appendChild(el);
  }
  
  cnt.appendChild(document.createTextNode(']'));
  
  if (CustomMenu.dropDownNav && !CustomMenu.classicNav && !FC.hasMobileLayout) {
    if (el = $.cls('customBoardList')[0]) {
      el.parentNode.removeChild(el);
    }
    navs = $.id('boardSelectMobile');
    navs && navs.parentNode.insertBefore(cnt, navs.nextSibling);
  }
  else {
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
    CustomMenu.save($.id('customMenu').hasAttribute('data-standalone'));
  }
};

CustomMenu.showEditor = function(standalone) {
  var cnt, extConfig;
  
  cnt = document.createElement('div');
  cnt.id = 'customMenu';
  cnt.className = 'panel';
  cnt.setAttribute('data-close', '1');
  
  if (standalone === true) {
    cnt.setAttribute('data-standalone', '1');
  }
  
  cnt.innerHTML = '\
<div class="reply"><div class="panelHeader">Custom Board List\
<span class="panelCtrl"><span data-close="1" class="icon closeIcon"></span></span></div>\
<input placeholder="Example: jp tg mu" id="customMenuBox" type="text" value="">\
<div class="center"><button data-save="1">Save</button></div></div>';

  document.body.appendChild(cnt);
  
  cnt.style.top = window.pageYOffset
    + (0 | (document.documentElement.clientHeight / 2) - (cnt.offsetHeight / 2)) + 'px';
  
  $.removeClass($.id('backdrop'), 'hidden');
  
  extConfig = CustomMenu.getConfig();
  
  if (extConfig.customMenuList) {
    $.id('customMenuBox').value = extConfig.customMenuList;
  }
  
  cnt.addEventListener('click', CustomMenu.onClick, false);
};

CustomMenu.closeEditor = function() {
  var el;
  
  if (el = $.id('customMenu')) {
    el.removeEventListener('click', CustomMenu.onClick, false);
    document.body.removeChild(el);
    $.addClass($.id('backdrop'), 'hidden');
  }
};

CustomMenu.save = function(standalone) {
  var input, extConfig;

  if (input = $.id('customMenuBox')) {
    if (standalone === true) {
      CustomMenu.apply(input.value);
      
      extConfig = CustomMenu.getConfig();
      
      extConfig.customMenu = true;
      extConfig.customMenuList = input.value;
      
      localStorage.setItem('4chan-settings', JSON.stringify(extConfig));
      //StorageSync.sync('4chan-settings');
    }
  }
  
  CustomMenu.closeEditor();
};

CustomMenu.getConfig = function() {
  var extConfig;
  
  if (extConfig = localStorage.getItem('4chan-settings')) {
    return JSON.parse(extConfig);
  }
  else {
    return {};
  }
};

function checkMobileLayout() {
  var mobile, desktop;
  
  if (window.matchMedia) {
    return window.matchMedia('(max-width: 480px)').matches
      && localStorage.getItem('4chan_never_show_mobile') != 'true';
  }
  
  mobile = $.id('boardNavMobile');
  desktop = $.id('boardNavDesktop');
    
  return mobile && desktop && mobile.offsetWidth > 0 && desktop.offsetWidth === 0;
}

var StickyNav = {
  thres: 5,
  pos: 0,
  timeout: null,
  el: null,
  
  init: function(classicNav) {
    this.el = classicNav ? $.id('boardNavDesktop') : $.id('boardNavMobile');
    $.addClass(this.el, 'autohide-nav');
    window.addEventListener('scroll', this.onScroll, false);
  },
  
  destroy: function(classicNav) {
    this.el = classicNav ? $.id('boardNavDesktop') : $.id('boardNavMobile');
    $.removeClass(this.el, 'autohide-nav');
    window.removeEventListener('scroll', this.onScroll, false);
  },
  
  onScroll: function() {
    clearTimeout(StickyNav.timeout);
    StickyNav.timeout = setTimeout(StickyNav.checkScroll, 50);
  },
  
  checkScroll: function() {
    var thisPos;
    
    thisPos = window.pageYOffset;
    
    if (Math.abs(StickyNav.pos - thisPos) <= StickyNav.thres) {
      return;
    }
    
    if (thisPos < StickyNav.pos) {
      StickyNav.el.style.top = '';
    }
    else {
      StickyNav.el.style.top = '-' + StickyNav.el.offsetHeight + 'px';
    }
    
    StickyNav.pos = thisPos;
  }
};

FC.panelHTML = {
  build: function(id, cls) {
    var el;
    
    el = document.createElement('div');
    el.id = id;
    el.className = cls;
    el.innerHTML = FC.panelHTML[id];
    
    document.body.appendChild(el);
    
    return el;
  },
  
  'theme': '<div class="panelHeader">Settings<span id="theme-close" class="icon closeIcon" title="Close"></span></div>\
  <h4>Options</h4>\
  <ul class="clickset">\
    <li class="desktop"><label><input id="theme-nobinds" type="checkbox"> Disable keybinds</label></li>\
    <li><label><input id="theme-nospoiler" type="checkbox"> Don\'t spoiler images</label></li>\
    <li><label><input id="theme-newtab" type="checkbox"> Open threads in a new tab</label></li>\
    <li class="desktop"><label><input id="theme-tw" type="checkbox"> Thread Watcher</label></li>\
    <li class="desktop"><label><input id="theme-ddn" type="checkbox"> Use drop-down navigation</label></li>\
  </ul>\
  <h4 class="desktop">Shortcuts</h4>\
  <ul class="clickset desktop">\
    <li><kbd>R</kbd> &mdash; Refresh current page</li>\
    <li><kbd>X</kbd> &mdash; Reorder threads</li>\
    <li><kbd>S</kbd> &mdash; Open search box, <kbd>Esc</kbd> to close</li>\
    <li><kbd>Shift</kbd> <kbd title="Left Mouse Button">LMB</kbd> &mdash; Hide threads</li>\
    <li><kbd>Alt</kbd> <kbd title="Left Mouse Button">LMB</kbd> &mdash; Pin threads</li>\
    <li><kbd title="Right Mouse Button">RMB</kbd> &mdash; Threads context menu (Firefox only)</li>\
  </ul>\
  <h4>Custom CSS</h4>\
  <textarea id="theme-css" rows="4" cols="45"></textarea>\
  <div id="theme-btns">\
    <span id="theme-msg"></span>\
    <div class="center"><button id="theme-save">Save Settings</button></div>\
  </div>',
  
  'filters-protip': '<div class="panelHeader">Filters &amp; Highlights Help<span id="filters-help-close" class="icon closeIcon" title="Close"></span></div>\
  <h4>Patterns</h4>\
  <ul><li><ul>\
      <li><strong>Matching whole words:</strong></li>\
      <li><code>feel</code> &mdash; will match <em>"feel"</em> but not <em>"feeling"</em>. This search is case-insensitive.</li>\
    </ul></li>\
    <li><ul>\
      <li><strong>AND operator:</strong></li>\
      <li><code>feel girlfriend</code> &mdash; will match <em>"feel"</em> AND <em>"girlfriend"</em> in any order.</li>\
    </ul></li>\
    <li><ul>\
      <li><strong>OR operator:</strong></li>\
      <li><code>feel|girlfriend</code> &mdash; will match <em>"feel"</em> OR <em>"girlfriend"</em>.</li>\
    </ul></li>\
    <li><ul>\
      <li><strong>Mixing both operators:</strong></li>\
      <li><code>girlfriend|boyfriend feel</code> &mdash; matches <em>"feel"</em> AND <em>"girlfriend"</em>, or <em>"feel"</em> AND <em>"boyfriend"</em>.</li>\
    </ul></li>\
    <li><ul>\
      <li><strong>Exact match search:</strong></li>\
      <li><code>"that feel when"</code> &mdash; place double quotes around the pattern to search for an exact string</li>\
    </ul></li>\
    <li><ul>\
      <li><strong>Wildcards:</strong></li>\
      <li><code>feel*</code> &mdash; matches expressions such as <em>"feel"</em>, <em>"feels"</em>, <em>"feeling"</em>, <em>"feeler"</em>, etc&hellip;</li>\
      <li><code>idolm*ster</code> &mdash; this can match <em>"idolmaster"</em> or <em>"idolm@ster"</em>, etc&hellip;</li>\
    </ul></li>\
    <li><ul>\
      <li><strong>Filtering by name or tripcode:</strong></li>\
      <li>Prefix the pattern with <code>#</code> to search by <em>tripcode</em>: <code>#!Ep8pui8Vw2</code></li>\
      <li>Prefix the pattern with <code>##</code> to search by <em>name</em>: <code>##Anonymous</code></li>\
      <li>To filter by <em>capcode</em>: <code>#!#admin</code>, <code>#!#mod</code>, <code>#!#developer</code></li>\
    </ul></li>\
    <li><ul>\
      <li><strong>It is also possible to filter by regular expression:</strong></li>\
      <li><code>/^(?=.*detachable)(?=.*hats).*$/i</code> &mdash; AND operator.</li>\
      <li><code>/^(?!.*touhou).*$/i</code> &mdash; NOT operator.</li>\
      <li><code>/^&amp;gt;/</code> &mdash; threads starting with a quote (<em>"&gt;"</em> character as an html entity).</li>\
      <li><code>/^$/</code> &mdash; threads with no text.</li>\
    </ul></li>\
  </ul>\
  <hr>\
  <h4>Controls</h4>\
  <ul>\
    <li><strong>On</strong> &mdash; enables or disables the filter.</li>\
    <li><strong>Boards</strong> &mdash; space separated list of boards on which the filter will be active. Leave blank to apply to all boards.</li>\
    <li><strong>Hide</strong> &mdash; hides matched threads.</li>\
    <li><strong>Top</strong> &mdash; puts matched threads on top of the list.</li>\
  </ul>',
  
  'filter-palette': '<div id="colorpicker" class="panel"><table id="filter-color-table"><tbody></tbody><tfoot>\
    <tr><td>Custom</td></tr>\
    <tr><td class="middle-txt"><input class="custom-rgb" type="text" name="custom-rgb" value="" id="filter-rgb"><span title="Select Color" id="filter-rgb-ok" class="button clickbox"></span></td></tr>\
    <tr><td>\
      <span class="btn-wrap"><span id="filter-palette-close" class="button">Close</span></span>\
      <span class="btn-wrap"><span id="filter-palette-clear" class="button">Clear</span></span>\
    </td></tr>\
    </tfoot></table></div>',
  
  'filters': '<div class="panelHeader"><input placeholder="Search" type="text" id="filters-search">Filters &amp; Highlights<span id="filters-help-open" class="icon helpIcon" title="Help"></span><span id="filters-close" class="icon closeIcon" title="Close"></span></div>\
  <table id="filter-table">\
    <thead><tr>\
      <th>Order</th>\
      <th>On</th>\
      <th>Pattern</th>\
      <th>Boards</th>\
      <th>Color</th>\
      <th>Hide</th>\
      <th>Top</th>\
      <th>Del</th>\
      <th></th>\
    </tr></thead>\
    <tbody id="filter-list"></tbody>\
    <tfoot><tr><td colspan="9">\
      <button id="filters-add" class="left">Add</button>\
      <span class="right"><span id="filters-msg"></span><button id="filters-save">Save</button></span>\
    </td></tr></tfoot></table>'
};

var PostMenu = {
  activeBtn: null
};

PostMenu.open = function(btn, pid, hasThreadWatcher, hidden, pinned) {
  var div, html, btnPos, left, limit, tr;
  
  if (PostMenu.activeBtn == btn) {
    PostMenu.close();
    return;
  }
  
  PostMenu.close();
  
  tr = btn.parentNode.parentNode;
  
  html = '<ul><li data-report="' + pid + '">Report thread</li>'
    + '<li data-pin="' + pid + '">'
      + (pinned ? 'Unpin' : 'Pin') + ' thread</li>'
    + '<li data-hide="' + pid + '">'
      + (hidden ? 'Unhide' : 'Hide') + ' thread</li>';
  
  if (hasThreadWatcher) {
    html += '<li data-watch="' + pid + '">'
      + (ThreadWatcher.watched[pid + '-' + catalog.slug] ? 'Remove from' : 'Add to')
      + ' watch list</li>';
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
  
  UA.dispatchEvent('4chanPostMenuReady', { postId: pid, isOP: true, node: div.firstElementChild });
  
  document.body.appendChild(div);
  
  left = btnPos.left + window.pageXOffset;
  limit = document.documentElement.clientWidth - div.offsetWidth;
  
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