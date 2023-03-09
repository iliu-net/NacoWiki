//
// Enable Highlight.js syntax highlighting
//
hljs.highlightAll();

//
// Make it so that Ctrl+E starts editing
//
document.onkeydown = function (e) {
  e = e || window.event;
  if (e.ctrlKey && e.code == "KeyE") {
    hlink = document.getElementById("editLink");
    if (hlink) {
      window.location = hlink.href;
      return false;
    }
  }

}
