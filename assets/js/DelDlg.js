
function dlgClick(mode) {
  let url = window.location.href;
  if (mode) {
    url += '&confirm=yes';
  } else {
    url = url.split('?')[0];
  }
  window.location.href = url;
}
