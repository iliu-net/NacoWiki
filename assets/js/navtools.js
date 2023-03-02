/*
 * Navigation tools
 */
navdata = null;

function navGotoPage(fid) {
  var go = document.getElementById(fid);
  if (go === null) return;
  if (go.value == "") return;

  var url = window.location.href;
  url = url.split('?')[0];
  window.location.href = url + '?go=' + go.value;
}
function navGotoClear(fid) {
  var go = document.getElementById(fid);
  if (go === null) return;
  if (go.value == "") return;
  go.value = '';
  if (navdata.list.innerHTML.slice(0,1) != ' ') navShowTree();
}

function navGotFileList(err,data) {
  if (navdata === null) return;
  if (err !== null) {
    alert("Error #"+err+" while retrieving file list");
  } else {
    if (data.status == "error") {
      alert("REST API Error\n"+data.msg);
    } else {
      navdata.dirs = data.output[0];
      navdata.files = data.output[1];
      navdata.page  = data.output[2];
      navdata.base  = data.output[3];
      navMakeTree();
      navShowTree();
    }
  }
}

function basename(path) {
   return path.split('/').reverse()[0];
}
function dirname(path) {
  let t = path.split('/');
  t.pop();
  return t.join('/');
}

function nvAddNode(tree, node, type, dec) {
  let j = node.split("/");
  let ptr = tree;
  for (let i=0; i < j.length ; i++) {
    if (j[i].slice(0,1) == '.') break;
    if (!(j[i] in ptr)) {
      // Not in PTR
      if (i+1 == j.length) {
	// We should be able to create it
	ptr[j[i]] = {
	  'path': "/" + node + dec,
	  'type': type,
	};
	if (type == 'd') ptr[j[i]].content = {};
      } else {
	// Orphan node!
	console.log(node);
      }
    } else {
      // Yup found it...
      ptr = ptr[j[i]].content;
    }
  }
}

function nvAddList(tree,lst, type,dec) {
  for (let i=0;i < lst.length ; i++) {
    nvAddNode(tree, lst[i], type, dec);
  }
}

function nvToggleNodeCtl(name) {
  html = '<a href="javascript:void(0)" class="dropctl"';
  html += ' onclick="nvToggleNode(';
  html += "'" + name + "');";
  html += '" id="ctl_path:'+name+'" style="display:inline">#</a>';
  return html;
}

function nvMakeHTML(tree) {
  let html = '';
  for (const prop in tree) {
    html += '<div>';
    if (tree[prop].type == 'd') {
      html += nvToggleNodeCtl(tree[prop].path);
    } else {
      html += '&nbsp;&nbsp; '; // &#x22a1
    }
    html += '<a href="' + navdata.base + tree[prop].path + '"';
    //~ console.log("CMP "+ tree[prop].path+" vs "+navdata.page);
    if (tree[prop].path == navdata.page) {
      html+= ' class="navlist-hilite"';
    }
    html += ' style="display:inline">' + prop + '</a>';
    html += '<a href="javascript:void()" onclick="navCopyLink(';
    html += "'" + tree[prop].path + "'";
    html += ')" style="display:inline"> &#x29C9;</a>';

    if (tree[prop].type == 'd') {
      html += '<div class="navlist-bulkhide navlist-hide navlist-folder" id="tg_path:'+tree[prop].path + '">';
      html += nvMakeHTML(tree[prop].content);
      html += '</div>';
    }
    html += '</div>';
  }
  return html;
}

function nvMarkItem(ul,txt) {
  let path = ul.id.split(':')[1];
  //~ console.log(path);
  let ctl = document.getElementById('ctl_path:'+path);
  if (ctl === null) return;
  ctl.innerHTML = txt;
}

function nvHideList(ul) {
  ul.classList.remove('navlist-show');
  ul.classList.add('navlist-hide');
  nvMarkItem(ul,'&#x229e; ');
}
function nvShowList(ul) {
  ul.classList.remove('navlist-hide');
  ul.classList.add('navlist-show');
  nvMarkItem(ul,'&#x229f; ');
}

function nvToggleNode(name) {
  ul = document.getElementById('tg_path:'+name);
  if (ul === null) return;
  if (ul.classList.contains('navlist-show')) {
    nvHideList(ul);
  } else {
    nvShowList(ul);
  }
}

function navShowTree() {
  navdata.list.innerHTML = ' '+nvMakeHTML(navdata.tree);

  // Hide everything...
  var uls = document.getElementsByClassName("navlist-bulkhide");
  for (let i=0;i< uls.length;i++) {
    nvHideList(uls[i]);
  }
  // Show path:
  let x = navdata.page;
  while (x) {
    let c = document.getElementById('tg_path:'+x+'/');
    x = dirname(x);
    if (c === null) continue;
    nvShowList(c);
  }
}


function navMakeTree() {
  navdata.tree = {};
  navdata.dirs.sort();
  navdata.files.sort();
  nvAddList(navdata.tree, navdata.dirs, 'd', '/');
  nvAddList(navdata.tree, navdata.files, 'f', '');
}


function navtoolsClick(wid, lid, fid) {
  dropbtnClick(wid);
  if (navdata === null) {
    navdata = {
      "list": document.getElementById(lid),
      "input": document.getElementById(fid),
      "term": null,
    };
    var url = window.location.href;
    url = url.split('?')[0];
    url = url + "?api=page-list";

    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.responseType = 'json';
    xhr.onload = function() {
      var status = xhr.status;
      if (status === 200) {
        navGotFileList(null, xhr.response);
      } else {
        navGotFileList(status, xhr.response);
      }
    };
    xhr.send();
  }
}

function navSearchFile() {
  let term = navdata.input.value.toLowerCase();
  if (term.length < 3) {
    if (navdata.list.innerHTML.slice(0,1) != ' ') {
      navShowTree();
    }
  } else {
    if (navdata.term !== null && navdata.term != term) return;
    html = '\n';
    for (let i=0;i<navdata.files.length;i++) {
      let found = navdata.files[i].toLowerCase().indexOf(term);
      if (found == -1) continue;
      html += '<a href='+navdata.base+'/'+navdata.files[i]+'">';
      html += navdata.files[i];
      html += '</a>';
    }
    navdata.list.innerHTML = html;
  }
}

// From: https://stackoverflow.com/questions/400212/how-do-i-copy-to-the-clipboard-in-javascript
// Copies a string to the clipboard. Must be called from within an
// event handler such as click. May return false if it failed, but
// this is not always possible. Browser support for Chrome 43+,
// Firefox 42+, Safari 10+, Edge and Internet Explorer 10+.
// Internet Explorer: The clipboard feature may be disabled by
// an administrator. By default a prompt is shown the first
// time the clipboard is used (per session).
function copyToClipboard(text) {
    if (window.clipboardData && window.clipboardData.setData) {
        // Internet Explorer-specific code path to prevent textarea being shown while dialog is visible.
        return window.clipboardData.setData("Text", text);

    }
    else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
        var textarea = document.createElement("textarea");
        textarea.textContent = text;
        textarea.style.position = "fixed";  // Prevent scrolling to bottom of page in Microsoft Edge.
        document.body.appendChild(textarea);
        textarea.select();
        try {
            return document.execCommand("copy");  // Security exception may be thrown by some browsers.
        }
        catch (ex) {
            console.warn("Copy to clipboard failed.", ex);
            return prompt("Copy to clipboard: Ctrl+C, Enter", text);
        }
        finally {
            document.body.removeChild(textarea);
        }
    }
}

function navCopyLink(text) {
  let pagedir = dirname(navdata.page) + '/';
  if (text.slice(0,pagedir.length) == pagedir) {
    // This can be made relative
    text = text.slice(pagedir.length,text.length)
  }
  let name = basename(text);
  if (name != '') name = '|' + name;

  copyToClipboard('[[' + text + name + ']]');
}

