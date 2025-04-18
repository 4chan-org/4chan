var Parser = {}

Parser.init = function() {
  var staticPath = '//static.4chan.org/image/';
  
  var tail = window.devicePixelRatio >= 2 ? '@2x.gif' : '.gif';
  
  this.icons = {
    admin: staticPath + 'adminicon' + tail,
    mod: staticPath + 'modicon' + tail,
    dev: staticPath + 'developericon' + tail,
    del: staticPath + 'filedeleted-res' + tail
  };
};

function buildHTMLFromJSON(data) {
  var
    container = document.createElement('div'),
    isOP = false,
    
    userId,
    fileDims = '',
    imgSrc = '',
    fileBuildStart = '',
    fileBuildEnd = '',
    fileInfo = '',
    fileHtml = '',
    fileThumb,
    fileSize = '',
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
    noFilename,
    maxSize = 150,
    ratio, imgWidth, imgHeight,
    
    imgDir = '//images.4chan.org/' + data.board + '/src';
  
  noLink = 'res/' + data.resto + '#p' + data.no;
  quoteLink = 'res/' + data.resto + '#q' + data.no;
  
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
  }
  
  if (data.email) {
    emailStart = '<a href="mailto:' + data.email.replace(/ /g, '%20') + '" class="useremail">';
    emailEnd = '</a>';
  }
  
  if (data.country) {
    flag = ' <img src="//static.4chan.org/image/country/'
      + (data.board == 'pol' ? 'troll/' : '')
      + data.country.toLowerCase() + '.gif" alt="'
      + data.country + '" title="' + data.country_name + '" class="countryFlag">';
  }
  else {
    flag = '';
  }

  if (data.ext) {
    shortFile = longFile = data.filename + data.ext;
    if (data.filename.length > 30) {
      shortFile = data.filename.slice(0, 25) + '(...)' + data.ext;
    }

    if (!data.tn_w && !data.tn_h && data.ext == '.gif') {
      data.tn_w = data.w;
      data.tn_h = data.h;
    }
    if (data.fsize >= 1048576) {
      fileSize = ((0 | (data.fsize / 1048576 * 100 + 0.5)) / 100) + ' M';
    }
    else if (data.fsize > 1024) {
      fileSize = (0 | (data.fsize / 1024 + 0.5)) + ' K';
    }
    else {
      fileSize = data.fsize + ' ';
    }
    
    fileThumb = '//images.4chan.org/bans/thumb/' + data.board + '/' + data.thumb + 's.jpg';
    
    imgWidth = data.tn_w;
    imgHeight = data.tn_h;
    
    if (imgWidth > maxSize) {
      ratio = maxSize / imgWidth;
      imgWidth = maxSize;
      imgHeight = imgHeight * ratio;
    }
    if (imgHeight > maxSize) {
      ratio = maxSize / imgHeight;
      imgWidth = imgWidth * ratio;
      imgHeight = maxSize;
    }
    
    imgSrc = '<a class="fileThumb' + fileClass + '" href="' + imgDir + '/'
      + data.tim + data.ext + '" target="_blank"><img src="' + fileThumb
      + '" alt="' + fileSize + 'B" data-md5="' + data.md5
      + '" style="height: ' + imgHeight + 'px; width: '
      + imgWidth + 'px;"></a>';
    
    fileDims = data.ext == '.pdf' ? 'PDF' : data.w + 'x' + data.h;
    fileInfo = '<span class="fileText" id="fT' + data.no
      + '">File: <a href="' + imgDir + '/' + data.tim + data.ext
      + '" target="_blank">' + data.tim + data.ext + '</a>-(' + fileSize
      + 'B, ' + fileDims
      + (noFilename ? '' : (', <span title="' + longFile + '">'
      + shortFile + '</span>')) + ')</span>';
    
    fileBuildStart = fileInfo ? '<div class="fileInfo">' : '';
    fileBuildEnd = fileInfo ? '</div>' : '';
    
    fileHtml = '<div id="f' + data.no + '" class="file">'
      + fileBuildStart + fileInfo + fileBuildEnd + imgSrc + '</div>';
  }
  else if (data.filedeleted) {
    fileHtml = '<div id="f' + data.no + '" class="file"><span class="fileThumb"><img src="'
      + Parser.icons.del + '" class="fileDeletedRes" alt="File deleted."></span></div>';
  }
  
  if (data.trip) {
    tripcode = ' <span class="postertrip">' + data.trip + '</span>';
  }
  
  name = data.name || '';
  
  subject = data.sub || '';
  
  container.id = 'p' + data.no;
  container.className = 'post reply' + highlight + (data.ws_board ? ' ws' : ' nws');
  container.innerHTML =
    '<div class="postInfo desktop" id="pi' + data.no + '">' +
      '<input type="checkbox" name="' + data.no + '" value="delete"> ' +
      '<span class="subject">' + subject + '</span> ' +
      '<span class="nameBlock' + capcodeClass + '">' + emailStart +
        '<span class="name">' + name + '</span>' +
        tripcode + capcodeStart + emailEnd + capcode + userId + flag +
      ' </span> ' +
      '<span class="dateTime" data-utc="' + data.time + '">' + data.now + '</span> ' +
      '<span class="postNum desktop">' +
        '<a href="' + noLink + '" title="Highlight this post">No.</a><a href="' +
        quoteLink + '" title="Quote this post">' + data.no + '</a>' +
      '</span>' +
    '</div>' + fileHtml +
    '<blockquote class="postMessage" id="m' + data.no + '">'
    + (data.com || '') + '</blockquote>';
  
  return container;
}

function showPreview(e) {
    var rect, postHeight, doc, docWidth, style, pos, top, scrollTop, link, post, match, bid;
    
    if (e.target.nodeName == 'A' && (match = e.target.className.match(/^bannedPost_([0-9]+)/))) {
      link = e.target;
      bid = match[1];
    }
    else {
      return;
    }
    
    post = buildHTMLFromJSON(window['banjson_' + bid]);
    
    post.id = 'quote-preview';
    
    rect = link.getBoundingClientRect();
    doc = document.documentElement;
    docWidth = doc.offsetWidth;
    style = post.style;
    
    document.body.appendChild(post);
    
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

function removePreview() {
  if (cnt = document.getElementById('quote-preview')) {
    document.body.removeChild(cnt);
  }
}

function run() { 
  Parser.init();
  addCSS();
  document.addEventListener('mouseover', showPreview, false);
  document.addEventListener('mouseout', removePreview, false);
}

function addCSS() {
  var style;
  
  style = document.createElement('style');
  style.setAttribute('type', 'text/css');
  style.textContent = '\
#quote-preview {\
  display: block;\
  position: absolute;\
  padding: 3px 6px 6px 3px;\
  margin: 0;\
  text-align: left;\
  border-width: 1px 2px 2px 1px;\
  border-style: solid;\
}\
#quote-preview.nws {\
  color: #800000;\
  border-color: #D9BFB7;\
}\
#quote-preview.ws {\
  color: #000;\
  border-color: #B7C5D9;\
}\
#quote-preview.ws a {\
  color: #34345C;\
}\
#quote-preview input {\
  margin: 3px 3px 3px 4px;\
}\
.ws.reply {\
  background-color: #D6DAF0;\
}\
.nws.reply {\
  background-color: #F0E0D6;\
}\
.subject {\
  font-weight: bold;\
}\
.ws .subject {\
  color: #0F0C5D;\
}\
.nws .subject {\
  color: #CC1105;\
}\
.quote {\
  color: #789922;\
}\
.quotelink,\
.deadlink {\
  color: #789922 !important;\
}\
.ws .useremail .postertrip,\
.ws .useremail .name {\
  color: #34345C !important;\
}\
.nws .useremail .postertrip,\
.nws .useremail .name {\
  color: #0000EE !important;\
}\
.nameBlock {\
  display: inline-block;\
}\
.name {\
  color: #117743;\
  font-weight: bold;\
}\
.postertrip {\
  color: #117743;\
  font-weight: normal !important;\
}\
.postNum a {\
  text-decoration: none;\
}\
.ws .postNum a {\
  color: #000 !important;\
}\
.nws .postNum a {\
  color: #800000 !important;\
}\
.fileInfo {\
  margin-left: 20px;\
}\
.fileThumb {\
  float: left;\
  margin: 3px 20px 5px;\
}\
.fileThumb img {\
  border: none;\
  float: left;\
}\
s {\
  background-color: #000000 !important;\
}\
.capcode {\
  font-weight: bold !important;\
}\
.nameBlock.capcodeAdmin span.name, span.capcodeAdmin span.name a, span.capcodeAdmin span.postertrip, span.capcodeAdmin strong.capcode {\
  color: #FF0000 !important;\
}\
.nameBlock.capcodeMod span.name, span.capcodeMod span.name a, span.capcodeMod span.postertrip, span.capcodeMod strong.capcode {\
  color: #800080 !important;\
}\
.nameBlock.capcodeDeveloper span.name, span.capcodeDeveloper span.name a, span.capcodeDeveloper span.postertrip, span.capcodeDeveloper strong.capcode {\
  color: #0000F0 !important;\
}\
.identityIcon {\
  height: 16px;\
  margin-bottom: -3px;\
  width: 16px;\
}\
.postMessage {\
  margin: 13px 40px 13px 40px;\
}\
.countryFlag {\
  margin-bottom: -1px;\
  padding-top: 1px;\
}\
.fileDeletedRes {\
  height: 13px;\
  width: 127px;\
}\
span.fileThumb, span.fileThumb img {\
  float: none !important;\
  margin-bottom: 0 !important;\
  margin-top: 0 !important;\
}\
';
  
  (document.head || document.getElementsByTagName('head')[0]).appendChild(style);
}

document.addEventListener('DOMContentLoaded', run, false);
