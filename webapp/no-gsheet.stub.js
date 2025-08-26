(function(){
  // Minimal stub to satisfy barcode.js without any Sheets calls
  function buildCsvFromNodes(nodes){
    var $rows = (window.jQuery && nodes && nodes.jquery) ? nodes : $(nodes);
    var lines = [];
    $rows.each(function(){
      var out = [];
      $(this).find('th,td:not(.noexport)').each(function(){
        var t = $(this).text();
        if (t == null) t = '';
        t = (''+t).replace(/\r?\n/g, ' ').replace(/"/g, '""');
        out.push('"' + t + '"');
      });
      lines.push(out.join(','));
    });
    return lines.join('\n');
  }

  window.GSheet = function(){
    this.props = { folderid: "", folderidtest: "" }; // placeholders
    this.makeCsv = function(nodes){ return buildCsvFromNodes(nodes); };
    this.gsheet  = function(){ console.log('Google Sheets export disabled'); };
  };
})();
