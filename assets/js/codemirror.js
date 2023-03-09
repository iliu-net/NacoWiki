
function cm_setup(mode) {
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
  document.getElementById("text").value = txt;
  document.getElementById("edform").submit();
}
