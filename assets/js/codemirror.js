
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
}

function cm_save() {
  var txt = textarea1.getDoc().getValue();
  document.getElementById("text").value = txt;
  document.getElementById("edform").submit();
}
