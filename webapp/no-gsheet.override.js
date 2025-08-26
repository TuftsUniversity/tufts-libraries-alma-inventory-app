(function(){
  // Hide only the Sheets export button
  var style = document.createElement('style');
  style.textContent = '#exportGsheet{display:none!important}';
  document.head.appendChild(style);

  // Replace downloadToFile with a non-popup version
  window.downloadToFile = function (content, filename, contentType) {
    try {
      var a = document.createElement('a');
      var file = new Blob([content], {type: contentType || 'text/csv'});
      a.href = URL.createObjectURL(file);
      a.download = filename || 'export.csv';
      document.body.appendChild(a);
      a.click();
      setTimeout(function(){
        URL.revokeObjectURL(a.href);
        document.body.removeChild(a);
      }, 0);
    } catch(e) {
      console.error('Download failed', e);
    }
  };

  // Just in case the button exists, neutralize its click
  $('#exportGsheet').off('click').on('click', function(e){
    e.preventDefault();
    console.log('Google Sheets export disabled');
  });
})();
