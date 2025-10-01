/*
Barcode Scanning Inventory – no Google Sheets dependency
- CSV is built locally and downloaded (no network)
- No location dropdown enforcement
*/

var dialog, dialogBulk, dialogLegend;      // UI dialogs
var testArr = [];                          // demo barcodes (Alt+S)
var sr = 1;                                // session row counter

// Status colors/labels
var STAT_FAIL = "FAIL";
var COLORMAP = [
  {status: "PASS",       color: "white",        nickname: "white",           desc: "Information is valid.  No action required."},
  {status: STAT_FAIL,    color: "pink",         nickname: "pink",            desc: "Retrieval failed.  Try to refresh again.  File a ticket with <a href=\"mailto:support@carli.illinois.edu\">CARLI Support</a> if the issue persists."},
  {status: "NOT-FOUND",  color: "coral",        nickname: "red",             desc: "No Alma data for barcode."},
  {status: "META-CALL",  color: "darkorange",   nickname: "electric orange", desc: "Bad Call Number."},
  {status: "META-TTL",   color: "lightskyblue", nickname: "blue",            desc: "Bad Title."},
  {status: "META-VOL",   color: "lightgreen",   nickname: "mint green",      desc: "Bad Volume."},
  {status: "PULL-STAT",  color: "goldenrod",    nickname: "goldenrod",       desc: "Incorrect status code."},
  {status: "PULL-LOC",   color: "yellow",       nickname: "yellow",          desc: "Incorrect location."},
  {status: "PULL-SUPP",  color: "tan",          nickname: "tan",             desc: "Bib is marked as suppressed."},
  {status: "PULL-HSUPP", color: "violet",       nickname: "purple",          desc: "Holding is suppressed."},
  {status: "PULL-DUE",   color: "chartreuse",   nickname: "electric green",  desc: "Item is checked out in Alma."},
  {status: "PULL-MULT",  color: "grey",         nickname: "grey",            desc: "Multiple issues."}
];
var STATUSES = [];

/* ===== Helpers (no gsheet) ===== */

// Build a CSV line from a set of TH/TD cells (excluding .noexport)
function rowToCsv($cells){
  var out = [];
  $cells.each(function(){
    var t = $(this).text();
    if (t == null) t = '';
    t = (''+t).replace(/\r?\n/g, ' ').replace(/"/g, '""'); // sanitize
    out.push('"' + t + '"');
  });
  return out.join(',');
}

// Build CSV from the table (#restable): header + reversed data rows
function buildCsvFromTable(){
  var $header = $('#restable tr.header').first();
  var headerLine = rowToCsv($header.find('th,td:not(.noexport)'));
  //alert("header line" + headerLine)
  var dataLines = [];
  $('#restable tr.datarow').each(function(){
    dataLines.push(rowToCsv($(this).find('th,td:not(.noexport)')));
  });
  dataLines.reverse(); // keep previous behavior (newest first)
  // Split into array, remove the first blank header, and rejoin
  let headers = headerLine.split(",");
  if (headers[0] === '""' || headers[0] === "") {
    headers.shift();
  }
  // Join back into a string
  headerLine = headers.join(",");
  return headerLine + '\n' + dataLines.join('\n');
}

// Download a file (no popup window)
function downloadToFile(content, filename, contentType) {
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
}

/* ===== UI & init ===== */

function colorInit() {
  var ruletemplate = "tr.STATUS td, tr.STATUS button, tr.STATUS th, #laststatus.STATUS {background-color: COLOR;}\n";
  var cssrules = $("<style type='text/css'> </style>").appendTo("head");
  var cssbuf = "";
  var colorRow = "<tr class='STATUS'><th class='legnick'>NICK</th><td class='legstat'>STATUS</td><td class='legdesc'>DESC</td></tr>";
  var colorTbl = $("<table id='legend'/>").appendTo("#legend-div");
  for (var i=0; i<COLORMAP.length; i++) {
    var status   = COLORMAP[i].status;
    var color    = COLORMAP[i].color;
    var nickname = COLORMAP[i].nickname;
    var desc     = COLORMAP[i].desc;
    STATUSES.push(status);
    cssbuf += ruletemplate.replace(/STATUS/g, status).replace(/COLOR/g, color);
    colorTbl.append($(colorRow
      .replace(/STATUS/g, status)
      .replace(/COLOR/g, color)
      .replace(/NICK/g, nickname)
      .replace(/DESC/g, desc)));
  }
  cssrules.text(cssbuf);
}

function getTestCodes(){
  var re = /.*test=([0-9\-,]+)(&.*)?$/;
  if (re.test(document.location.search)) {
    var m = re.exec(document.location.search);
    if (m.length > 1) $("#test").val(m[1]);
  }
}

$(document).ready(function(){
  getTestCodes();
  colorInit();
  initDialogs();
  bindEvents();
  populateLibs(); // safe no-op if dropdowns not present

  var s = $("#test").val();
  if (s) {
    loadDemonstrationBarcodes(s);
  } else if ('barcodes' in localStorage) {
    if (localStorage.barcodes) {
      restoreAutoSaveBarcodes();
    } else {
      barcodeDialog();
    }
  } else {
    barcodeDialog();
  }
});

function initDialogs() {
  dialog = $("#dialog-form").dialog({
    autoOpen : false,
    height : 600,
    width  : 700,
    modal  : true,
    buttons: {
      "Add Barcode" : function(){ addCurrentBarcode(); },
      "Done"        : function(){
        dialog.dialog("close");
        $("#gsheetdiv").show();
        $("#beepdiv").show();
      }
    },
    close: function(){
      $("#gsheetdiv").show();
      $("#beepdiv").show();
    }
  });

  dialogBulk = $("#dialog-bulk").dialog({
    autoOpen : false,
    height : 500,
    width  : 400,
    modal  : true,
    buttons: {
      "Add Barcodes": function(){
        var codeArr = $("#barcodes").val().split("\n");
        var title = codeArr.length + " barcodes will be added.  Click OK to continue.";
        mydialog("Confirm Bulk Add", title, function(){
          dialogBulk.dialog("close");
          dialog.dialog("close");
          for (var i=0; i<codeArr.length; i++) {
            if (isDuplicateBarcode(codeArr[i])) {
              console.log('DUPLICATE barcode=' + codeArr[i] + ' ; Skipping...');
              continue;
            }
            addBarcode(codeArr[i], false);
          }
        });
      },
      "Cancel": function(){ dialogBulk.dialog("close"); }
    }
  });

  dialogLegend = $("#legend-div").dialog({
    autoOpen : false,
    height : 650,
    width  : 750,
    modal  : false,
    buttons: { "Done": function(){ dialogLegend.dialog("close"); } }
  });
}

function bindEvents() {
  $("#addb").on("click", function(){
    $("tr.current").removeClass("current");
    barcodeDialog();
  });

  $(document).bind('keypress', function(e){
    if (e.keyCode == 13) {
      $("button.ui-button:first:enabled").click();
      return false;
    }
  });

  $("#barcode").on("keyup",  function(){ valBarcode(); });
  $("#barcode").on("change", function(){ valBarcode(); });

  $("#loadBarcodes").on("click", function(){
    let input = document.createElement('input');
    input.type = 'file';
    input.onchange = _this => {
      let files = Array.from(input.files);
      const reader = new FileReader();
      reader.onload = function (event) {
        const data = $.csv.toObjects(event.target.result);
        let barcodes = '';
        let i = 0;
        let barcode_key = 'barcode';
        for (let line of data) {
          if (i == 0) {
            for (k in line) {
              let matches = k.match(/^(barcode[s]*)$/i);
              if (matches && matches.length > 0) barcode_key = matches[0];
            }
          }
          let b = line[barcode_key] || '';
          if (!b) { console.log('barcode is undefined'); continue; }
          if (b.match(/^="[0-9]+"$/)) { b = b.replace(/^="/, "").replace(/"$/, ""); }
          if (i++ > 0) barcodes += "\n";
          barcodes += b;
        }
        $("#barcodes").val(barcodes);
      };
      reader.readAsText(files[0]);
    };
    input.click();
  });

  // CSV download (no gsheet)
  $("#downloadCsv").on("click", function(e){
    e.preventDefault();
    var cnt = $("tr.datarow").length;
    if (cnt == 0) {
      var msg = $("<div>There is no data to export.  Please scan some barcodes</div>");
      mydialog("No data available", msg, function(){ barcodeDialog(); });
      return;
    }
    var ssname = makeSpreadsheetName() + '.csv';
    var csv = buildCsvFromTable().replace(/\r/g, "");
    downloadToFile(csv, ssname, 'text/csv');

    var msg2 = $("<div>Please confirm that <b>"+cnt+"</b> barcodes were successfully exported. Click <b>OK</b> delete those barcodes from this page.</div>");
    mydialog("Clear Barcode Table?", msg2, function() {
      $("tr.datarow").remove();
      autosave();
      barcodeDialog();
    });
  });

  $("button.lastbutt").on("click", function() {
    var status = $(this).attr("status");
    var tr = getCurrentRow();
    tr.removeClass(STATUSES.join(" ")).addClass(status);
    tr.find("td.status").text(status);
    $("#laststatus").text(status).removeClass(STATUSES.join(" ")).addClass(status);
    tr.find("td.status_msg").text($(this).attr("status_msg"));
    autosave();
  });

  $("button.rescan").on("click", function() {
    refreshTableRow(getCurrentRow());
  });

  $("#doBulk").on("click", function(){ bulkDialog(); });

  $("#legend-button").on("click", function(){
    dialogLegend.dialog("option", "title", "Status Legend").dialog("open");
  });
}

/* ===== Demo support (Alt+S) ===== */

function loadDemonstrationBarcodes(s){
  testArr = s.split(",");
  barcodeDialog();
  var cnt = testArr.length;
  var msg = $("<div>A list of <b>"+cnt+"</b> barcodes have been provided for testing.<br/><br/>Click <b>Alt-S</b> to simulate scanning with these barcodes</div>");
  mydialog("Confirm", msg, function() {
    $(document).on("keydown", function(e){
      if (e.altKey && e.key=="s") {
        if (testArr.length > 0) {
          var s = testArr.shift();
          $("#barcode").val(s);
          if (valBarcode()) addCurrentBarcode();
        }
      }
    });
  });
}

/* ===== Session restore / UI ===== */

function restoreAutoSaveBarcodes(){
  var arr = localStorage.barcodes.split("!!!!");
  var cnt = arr.length;
  var msg = $("<div>A list of <b>"+cnt+"</b> barcodes exist from a prior session<br/><br/>Click <b>OK</b> to load them.<br/><br/>Click <b>CANCEL</b> to start with an empty list.</div>");
  mydialog("Add Autosave Barcodes?", msg, function() {
    for (var i=0; i<cnt; i++) {
      var rowarr = arr[i].split("||");
      restoreRow(rowarr);
    }
    barcodeDialog();
  });
}

function getCurrentRow() {
  var tr = $("tr.datarow.current");
  if (tr.length == 0) {
    tr = $("tr.datarow:first");
    tr.addClass("current");
  }
  return tr;
}

function barcodeDialog() {
  $("#gsheetdiv").show();
  $("#beepdiv").show();

  var tr = getCurrentRow();
  $("#lastbarcode").text(tr.find("th.barcode").text());
  $("#bcCall").text(tr.find("td.call_number").text());
  $("#bcTitle").text(tr.find("td.title").text());
  $("#bcVol").text(tr.find("td.volume").text());

  var status = tr.find("td.status").text();
  $("#lbreset").attr("status", status);
  $("#laststatus").text(status).removeClass(STATUSES.join(" ")).addClass(status);
  $("#lbreset").attr("status_msg", tr.find("td.status_msg").text());

  var cnt = testArr.length;
  var title = cnt > 0 ? "Add Barcode (Demo Scans:" + cnt + ")" : "Add Barcode";
  dialog.dialog("option", "title", title).dialog("open");
  $("#barcode").focus();
}

function bulkDialog() {
  $("#barcodes").val("");
  dialogBulk.dialog("option", "title", "Bulk Add Barcodes").dialog("open");
}

function makeSpreadsheetName() {
  $("td.call_number").removeClass("has_val");
  $("td.call_number").each(function(){ if ($(this).text() != "") $(this).addClass("has_val"); });

  var start = $("tr.datarow td.call_number.has_val:first").text() || "NA";
  var end   = $("tr.datarow td.call_number.has_val:last").text()  || "NA";
  $("td.call_number").removeClass("has_val");
  return end + "--" + start;
}

/* ===== Row ops ===== */

function delrow(cell) {
  var tr = $(cell).parents("tr");
  var prevtr = tr.prev("tr.datarow");
  tr.remove();
  if (prevtr.is("tr")) setLcSortStat(prevtr);
  autosave();
}

function refreshTableRow(tr) {
  $("tr.current").removeClass("current");
  tr.removeClass(STATUSES.join(" ")).addClass("new current");
  processCodes(true);
}

function refreshrow(cell) { refreshTableRow($(cell).parents("tr")); }

function addCurrentBarcode() {
  $("#bcCall").text("");
  $("#bcTitle").text("");
  $("#bcVol").text("");
  var v = $("#barcode").val();
  addBarcode(v, true);
  $("#barcode").val("").focus();
  $("#message").text("Barcode " + v + " added. Scan the next barcode.");
}

function addBarcode(barcode, show) {
  if (!barcode) return;
  var tr = getNewRow(true, barcode);
  tr.append($("<td class='location_code'/>"));
  tr.append($("<td class='call_number'/>"));
  tr.append($("<td class='volume'/>"));
  tr.append($("<td class='title'/>"));
  tr.append($("<td class='process'/>"));
  tr.append($("<td class='temp_location'/>"));
  tr.append($("<td class='bib_supp'/>"));
  tr.append($("<td class='hold_supp'/>"));
  tr.append($("<td class='record_num'/>"));
  tr.append($("<td class='status'/>"));
  tr.append($("<td class='status_msg'/>"));
  tr.append($("<td class='timestamp'/>"));
  $("#restable tr.header").after(tr);
  processCodes(show);
}

function getNewRow(processRow, barcode) {
  sr++;
  $("tr.current").removeClass("current");
  var tr = $("<tr/>");
  tr.addClass(processRow ? "datarow new current" : "datarow current");
  tr.attr("barcode", barcode);
  tr.append(getButtonCell());
  tr.append($("<th class='barcode'>" + barcode + "</th>"));
  return tr;
}

function getButtonCell() {
  var td = $("<td class='noexport action'/>");
  td.append($("<button onclick='javascript:delrow(this);'><i class='material-icons'>delete</i></button>"));
  td.append($("<button onclick='javascript:refreshrow(this);'><i class='material-icons'>refresh</i></button>"));
  return td;
}

function restoreRow(rowarr) {
  if (!rowarr || rowarr.length != 13) return;

  var barcode = rowarr.shift();
  var tr = getNewRow(false, barcode);

  tr.append($("<td class='location_code'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='call_number'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='volume'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='title'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='process'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='temp_location'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='bib_supp'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='hold_supp'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='record_num'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='status'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='status_msg'>" + rowarr.shift() + "</td>"));
  tr.append($("<td class='timestamp'>" + rowarr.shift() + "</td>"));
  tr.addClass(tr.find("td.status").text());
  $("#restable tr.header").after(tr);
  setLcSortStat(tr);
  autosave();
}

/* ===== Persistence ===== */

function autosave() {
  var arr = [];
  $("tr.datarow").each(function() {
    var rowarr = [];
    $(this).find("th,td:not(.noexport)").each(function() {
      rowarr.push($(this).text());
    });
    arr.push(rowarr.join("||"));
  });
  localStorage.barcodes = arr.reverse().join("!!!!");
}

/* ===== Status & parsing ===== */

function setRowStatus(tr, status, status_msg, show) {
  tr.find("td.status").text(status);
  tr.removeClass("processing").addClass(status);
  if (status_msg != null) tr.find("td.status_msg").text(status_msg);
  if (status != 'PASS') soundBeep();
  autosave();
  processCodes(show);
  if ($("#lastbarcode").text() == tr.attr("barcode")) {
    $("#laststatus").text(status).removeClass().addClass(status);
  }
  if (show) barcodeDialog();
}

function updateRowStat(tr) {
  if (!(tr.hasClass("bib_check") && tr.hasClass("hold_check"))) return;

  var stat = tr.find("td.status").text();
  var statmsg = tr.find("td.status_msg").text();
  if (tr.hasClass("bib_supp")) {
    statmsg += "Bib suppressed. ";
    stat = (stat == "PASS") ? "PULL-SUPP" : "PULL-MULT";
  } else if (tr.hasClass("hold_supp")) {
    statmsg += "Holding suppressed. ";
    stat = (stat == "PASS") ? "PULL-HSUPP" : "PULL-MULT";
  } else {
    return;
  }
  setRowStatus(tr, stat, statmsg, false);
}

function getBarcodeFromUrl(url) {
  var match = /.*item_barcode=(.+)$/.exec(url);
  if (match == null) return "";
  return (match.length > 1) ? match[1] : "";
}

function getArray(json, name) { if (json == null) return {}; return (name in json) ? json[name] : {}; }
function getValueWithDef(json, name, def) { if (json == null) return def; return (name in json) ? json[name] : def; }
function getValue(json, name) { return getValueWithDef(json, name, ""); }
function getArrayValue(json, aname, vname) { return getArrayValueWithDef(json, aname, vname, ""); }
function getArrayValueWithDef(json, aname, vname, def) { if (json == null) return def; return getValueWithDef(getArray(json, aname), vname, def); }

function parseResponse(barcode, json) {
  var resdata = {};
  if ('errorsExist' in json) {
    var status = "NOT-FOUND";
    var status_msg = "--";
    var errorList = getArray(json, "errorList");
    var errorArr = ('error' in errorList) ? errorList["error"] : [];
    if (errorArr.length > 0) {
      var error = errorArr[0];
      status_msg = getValueWithDef(error, 'errorCode', "--") + ": " + getValueWithDef(error, 'errorMessage', "--");
    }
    resdata = { "barcode": barcode, "status": status, "status_msg": status_msg };
  } else {
    var status = "PASS";
    var status_msg = "Barcode Found. ";

    var bibData = getArray(json, "bib_data");
    var bibLink = getValue(bibData, "link");

    var holdingData = getArray(json, "holding_data");
    var holdingLink = getValue(holdingData, "link");

    var itemData = getArray(json, "item_data");
    var loc = getArrayValue(itemData, "location", "value");
    var tempLoc = getArrayValue(holdingData, "temp_location", "");

    var process = getArrayValue(itemData, "process_type", "value")
      .replace(/_/g," ")
      .replace(/WORK ORDER.*/,"Work Order");

    var date = new Date();
    var m = date.getMonth() + 1;
    var timestamp = date.getFullYear()+"-"+
      ((m < 10) ? "0" + m : m) + "-" +
      ((date.getDay() < 10) ? "0" + date.getDay() : date.getDay()) + "_" +
      ((date.getHours() < 10) ? "0" + date.getHours() : date.getHours()) + ":" +
      ((date.getMinutes() < 10) ? "0" + date.getMinutes() : date.getMinutes()) + ":" +
      ((date.getSeconds() < 10) ? "0" + date.getSeconds() : date.getSeconds());

    var callno = getValue(holdingData, "call_number");
    if (callno == "") {
      status = "META-CALL";
      status_msg = "Empty call number. ";
    }

    // Location/temporary location checks intentionally removed

    if (process == "LOAN") {
      status = (status == "PASS") ? "PULL-DUE" : "PULL-MULT";
      status_msg += "Item is on LOAN. ";
    } else if (process == "CLAIM RETURNED LOAN") {
      status = (status == "PASS") ? "PULL-DUE" : "PULL-MULT";
      status_msg += "Item is CLAIM RETURNED. ";
    } else if (process == "LOST LOAN") {
      status = (status == "PASS") ? "PULL-DUE" : "PULL-MULT";
      status_msg += "Item is LOST. ";
    } else if (process != "") {
      status = (status == "PASS") ? "PULL-STAT" : "PULL-MULT";
      status_msg += "Item has a process status. ";
    }

    resdata = {
      "barcode"       : barcode,
      "bib_id"        : getValue(bibData, "mms_id"),
      "holding_id"    : getValue(holdingData, "holding_id"),
      "record_num"    : getValue(itemData, "pid"),
      "location_code" : loc,
      "process"       : process,
      "temp_location" : tempLoc,
      "volume"        : getValue(itemData, "description"),
      "call_number"   : callno,
      "title"         : getValue(bibData, "title"),
      "bibLink"       : bibLink,
      "holdingLink"   : holdingLink,
      "timestamp"     : timestamp,
      "status"        : status,
      "status_msg"    : status_msg
    };
  }
  return resdata;
}

/* ===== Processing ===== */

function soundBeep(force) {
  if (force || $('#beep').is(":checked")) {
    var frequency = $('#frequency').val();
    var volume = $('#volume').val();
    beep(200, frequency, volume);
  }
}

function processCodes(show) {
  if ($("#restable tr.processing").length > 0) return;
  var tr = $("#restable tr.new:last");
  if (tr.length == 0) return;

  tr.removeClass("new").addClass("processing");
  var barcode = tr.attr("barcode");

  if (!isValidBarcode(barcode)) {
    setRowStatus(tr, STAT_FAIL, "Invalid item barcode", show);
    soundBeep(false);
    return;
  }

  var url = API_REDIRECT + "?apipath=" + encodeURIComponent(API_SERVICE) + "items&item_barcode=" + barcode;

  $.getJSON(url, function(rawdata){
    var data = parseResponse(getBarcodeFromUrl(this.url), rawdata);
    var resbarcode = data["barcode"];
    var tr = $("#restable tr[barcode="+resbarcode+"]");

    for (key in data) {
      var val = data[key] == null ? "" : data[key];
      if (key == "bibLink" || key == "holdingLink") {
        continue;
      } else if (key == "bib_id" || key == "holding_id") {
        tr.attr(key, val);
      } else {
        tr.find("td."+key).text(val);
      }
    }

    var urlb = API_REDIRECT + "?apipath=" + encodeURIComponent(data["bibLink"]);
    $.getJSON(urlb, function(bib){
      if ((getValue(bib, "suppress_from_publishing") == "true")) {
        $("tr[bib_id=" + bib["mms_id"] + "] td.bib_supp").text("X");
        tr.addClass("bib_supp");
      }
      tr.addClass("bib_check");
      updateRowStat(tr);
    });

    var urlh = API_REDIRECT + "?apipath=" + encodeURIComponent(data["holdingLink"]);
    $.ajax({
      url: urlh,
      type: 'GET',
      dataType: 'json',
      success: function(hdata, textStatus, jqXHR) {
        var headers = jqXHR.getAllResponseHeaders();
        var headerMap = headersToMap(headers);
        $("#apicalls").text('API calls remaining: ' + headerMap['x-exl-api-remaining']);
        if ((getValue(hdata, "suppress_from_publishing") == "true")) {
          $("tr[holding_id=" + hdata["holding_id"] + "] td.hold_supp").text("X");
          tr.addClass("hold_supp");
        }
        tr.addClass("hold_check");
        updateRowStat(tr);
      },
      error: function() { /* ignore */ }
    });

    setLcSortStat(tr);
    setRowStatus(tr, tr.find("td.status").text(), null, show);
  }).fail(function(jqXHR){
    if (jqXHR.status == 404) {
      setRowStatus(tr, "NOT-FOUND", "--", show);
    } else {
      setRowStatus(tr, STAT_FAIL, "Connection Error", show);
    }
  });
}

function headersToMap(headerStr) {
  const arr = headerStr.trim().split(/[\r\n]+/);
  const headerMap = {};
  arr.forEach((line) => {
    const parts = line.split(": ");
    headerMap[parts.shift()] = parts.join(": ");
  });
  return headerMap;
}

/* ===== Call number sort ===== */

function setLcSortStat(tr) {
  var tdcall = tr.find("td.call_number");
  tdcall.removeClass("lcfirst lcequal lcnext lcprev");
  var call_number = tdcall.text();
  var lcsorter = null;
  let cnType = $('#cnType option:selected').val();
  if (cnType == "dewey") {
    lcsorter = new deweyCallClass();
  } else {
    lcsorter = new locCallClass();
  }
  var normlc = "";
  try { normlc = lcsorter.returnNormLcCall(call_number); } catch(e) {}
  tdcall.attr("title", normlc);

  var prev = tr.next("tr").find("td.call_number").attr("title");
  if (!prev) {
    tdcall.addClass("lcfirst");
  } else if (normlc == prev) {
    tdcall.addClass("lcequal");
  } else if (normlc > prev) {
    tdcall.addClass("lcnext");
  } else {
    tdcall.addClass("lcprev");
    soundBeep();
  }
}

/* ===== Validation ===== */

function isValidBarcode(barcode) { return BARCODE_REGEX.test(barcode); }
function isDuplicateBarcode(barcode) { return ($("tr[barcode="+barcode+"]").length > 0); }

function valBarcode() {
  var bc = $("#barcode");
  var msg = $("#message");
  bc.addClass("ui-state-error");
  $("button.ui-button:first").attr("disabled", true);

  var v = bc.val();
  if (!v) {
    return false;
  } else if (!isValidBarcode(v)) {
    msg.text(BARCODE_MSG);
    return false;
  } else if (isDuplicateBarcode(v)) {
    msg.text("Duplicate barcode");
    return false;
  } else {
    msg.text("Barcode appears to be valid");
    bc.removeClass("ui-state-error");
    $("button.ui-button:first").attr("disabled", false);
    return true;
  }
}

/* ===== Modal helper & optional dropdown loaders (no-ops if missing) ===== */

function mydialog(title, mymessage, func) {
  $("#dialog-msg").html(mymessage);
  $("#dialog-msg").dialog({
    resizable: false,
    height: "auto",
    width: 400,
    modal: true,
    title: title,
    buttons: {
      OK:     function() { $(this).dialog("close"); func(); },
      Cancel: function() { $(this).dialog("close"); }
    }
  });
}

// Only runs if #libSelected exists
function populateLibs() {
  if (!$('#libSelected').length) return;
  $('#libSelected').find('option').remove().end();

  var url = API_REDIRECT + "?apipath=" + encodeURIComponent(API_SERVICE) + "conf/libraries";
  $.ajax({
    url: url, type: 'GET', dataType: 'json',
    success: function(json, textStatus, jqXHR) {
      var headerMap = headersToMap(jqXHR.getAllResponseHeaders());
      $("#apicalls").text('API calls remaining: ' + headerMap['x-exl-api-remaining']);
      if ('errorsExist' in json) {
        var errorList = getArray(json, "errorList");
        var errorArr  = ('error' in errorList) ? errorList["error"] : [];
        if (errorArr.length > 0) {
          var error = errorArr[0];
          console.error(getValueWithDef(error, 'errorCode', "--") + ": " + getValueWithDef(error, 'errorMessage', "--"));
        }
      } else {
        var libs = getArray(json, 'library');
        for (let lib of libs) {
          $('#libSelected').append($('<option>', { value: lib.code, text: lib.name }));
        }
        if ($('#libSelected option').length) $('#libSelected').prop('selectedIndex', 0).trigger('change');
      }
    },
    error: function(){ console.log('Failed to load libraries'); }
  });
}

// Only runs if #locSelected exists
function populateLocs() {
  if (!$('#locSelected').length) return;
  $('#locSelected').find('option').remove().end();

  let libId = $('#libSelected option:selected').val();
  var url = API_REDIRECT + "?apipath=" + encodeURIComponent(API_SERVICE) + "conf/libraries/" + libId + "/locations";
  $.ajax({
    url: url, type: 'GET', dataType: 'json',
    success: function(json, textStatus, jqXHR) {
      var headerMap = headersToMap(jqXHR.getAllResponseHeaders());
      $("#apicalls").text('API calls remaining: ' + headerMap['x-exl-api-remaining']);
      if ('errorsExist' in json) {
        var errorList = getArray(json, "errorList");
        var errorArr  = ('error' in errorList) ? errorList["error"] : [];
        if (errorArr.length > 0) {
          var error = errorArr[0];
          console.error(getValueWithDef(error, 'errorCode', "--") + ": " + getValueWithDef(error, 'errorMessage', "--"));
        }
      } else {
        var locs = getArray(json, 'location');
        for (let loc of locs) {
          $('#locSelected').append($('<option>', { value: loc.code, text: loc.name + ' (' + loc.code + ')' }));
        }
        if ($('#locSelected option').length) $('#locSelected').prop('selectedIndex', 0).trigger('change');
      }
    },
    error: function(){ console.log('Failed to load locations'); }
  });
}

