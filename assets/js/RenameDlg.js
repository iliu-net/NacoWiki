function dlg_rename(obj,name) {
  var input = prompt("Please enter a new name:",name);
  if (input == null) return false;

  obj.href = obj.href + '?do=rename&name=' + encodeURI(input);
  console.log(obj.href);
  console.log(input);
  console.log(obj);
  return true;
}
