/*
 * Navigation tools
 */
function searchShow() {
  let res = document.getElementById("search-results");

  if (res.classList.contains('search-hide')) {
    res.classList.remove('search-hide');
  }
  res.classList.add('search-show');
}
function searchHide() {
  let res = document.getElementById("search-results");
  if (res.classList.contains('search-show')) {
    res.classList.remove('search-show');
  }
  res.classList.add('search-hide');
}

function searchClear(fid) {
  let go = document.getElementById(fid);
  if (go === null) return;
  go.value = '';
  //~ if (navdata.list.innerHTML.slice(0,1) != ' ') navShowTree();
  searchHide();
}

function searchSite(fid) {
  let go = document.getElementById(fid);
  if (go === null) return;
  if (go.value == "" || go.value.length < 2) {
    searchHide();
    return;
  }

  let res = document.getElementById('search-results');
  if (res === null) return;

  let term = go.value.toLowerCase();

  let html =  '';
  let matches = 0;
  for (let i=0; i < pg_index.length ; i++) {
    let l=0;
    let found = -1;
    for (l=0; l < pg_index[i]['text'].length ; l++) {
      found = pg_index[i]["text"][l].toLowerCase().indexOf(term);
      if (found != -1) break;
    }
    if (found == -1) continue;

    console.log('Line: '+l);
    ++matches;

    html += '<li class="search-item"><a href="/'+pg_index[i]['url']+'"  class="search-item">';
    html += '  <strong  class="search-item">'+pg_index[i]['title']+'</strong>';
    html += '  </a><br/>';
    let j = l - 3;
    if (j < 0) j = 0;
    console.log('Line OUT: '+j);


    let br = '';
    for ( ; j < pg_index[i]['text'].length && j < l+3; j++) {
      html += br; br= '<br/>';
      if (j == l) {
	html += pg_index[i]['text'][j].substr(0,found) +
		 '<span class="search-match">' + pg_index[i]['text'][j].substr(found,term.length) + '</span>' +
		 pg_index[i]['text'][j].substr(found+term.length);
      } else {
	html += pg_index[i]['text'][j];
      }
    }

    html += '</li>';
  }
  if (matches == 0) {
    res.innerHTML = 'No matches found!';
  } else {
    if (matches == 1) {
      html = "One match found<br/><ul>" + html + "</ul>";
    } else {
      html = matches + " matches found.<br/><ul>" + html + "</ul>";
    }
    res.innerHTML = html;
  }

  searchShow();
}


window.onclick = function(event) {
  if (event.target.matches('.search-box')) return;
  if (event.target.matches('.search-result')) return;
  if (event.target.matches('.search-ctl')) return;
  if (event.target.matches('.search-item')) return;
  searchHide();
};
window.onblur = function() {
  searchHide();
};

document.getElementById("search-input")
  .addEventListener("keyup", function(event) {
    event.preventDefault();
    if (event.keyCode === 13) {
      searchSite("search-input");
    }
  });
document.getElementById("search-input").oninput = function() {
  searchSite("search-input");
};



















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

