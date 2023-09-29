function dropdownCloseAll(id = "") {
  var dropdowns = document.getElementsByClassName("dropdown-content");
  var i;
  for (i = 0; i < dropdowns.length; i++) {
    var openDropdown = dropdowns[i];
    if (openDropdown.id == id) continue;
    if (openDropdown.classList.contains('dropdown-show')) {
      openDropdown.classList.remove('dropdown-show');
    }
  }
}
function dropbtnClick(id) {
  var dl = document.getElementById(id);
  if (dl === null) return true;
  dropdownCloseAll(id);
  dl.classList.toggle('dropdown-show');
}
window.onclick = function(event) {
  //~ console.log(event);
  if (!event.target.matches('.dropbtn') && !event.target.matches('.dropdown-content') && !event.target.matches('.dropctl')) {
    dropdownCloseAll();
  }
};
window.onblur = function() {
  dropdownCloseAll();
};

function attachFile(frm,id) {
  // Set-up form so that it auto-submits
  var f = document.getElementById(frm);
  if (f === null) return true;
  f.onchange = function() {
    f.submit();
  };
  // Open the hidden Input File field.
  var c = document.getElementById(id);
  if (c === null) return true;
  c.click();
}
