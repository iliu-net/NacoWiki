
function getRadioValue(name) {
  var ele = document.getElementsByName(name);
  for (i = 0; i < ele.length; i++) {
    if (ele[i].checked) return ele[i].value;
  }
  return "";
}
function testfn() {
  v_a = getRadioValue("v_a");
  v_b = getRadioValue("v_b");
  msg = '';
  if (v_a == "") {
    msg = "No value specified as Version A";
  }
  if (v_b == "") {
    if (msg == "") {
      msg  = "No value specified as Version B";
    } else {
      msg = "No versions specified for comparison";
    }
  }
  if (msg != "") {
    alert(msg);
    return false;
  }
  if (v_a == v_b) {
    alert("No comparison possible.\nVersion A and Version B are the same!");
    return false;
  }
  var newUrl = window.location.origin + window.location.pathname;
  newUrl = newUrl + "?do=vcompare&a="+v_a+"&b="+v_b;
  window.location.href = newUrl;
  return false;
}
