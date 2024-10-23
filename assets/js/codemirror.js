
var orig;

function closeHandler(event) {
  let txt = textarea1.getDoc().getValue();
  if (txt != orig) {
      event.preventDefault();
      event.returnValue = true
  }
}

function cm_setup(mode) {
  let srcedit = document.getElementById("srcedit");
  orig = srcedit.innerHTML;
  textarea1 = CodeMirror.fromTextArea(document.getElementById("srcedit"), {
    lineNumbers: true,
    mode: mode,
    extraKeys: {
      "Ctrl-S": function(instance) {
	cm_save();
      }
    }
  });
  // This doesn't seem to work
  setTimeout(function() {
    textarea1.focus();
  }, 100);
}

function cm_save() {
  var txt = textarea1.getDoc().getValue();
  orig = txt;
  document.getElementById("text").value = txt;
  document.getElementById("edform").submit();
}

window.addEventListener("beforeunload",closeHandler);
