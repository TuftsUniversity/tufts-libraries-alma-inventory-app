'use strict';

const express = require('express');
const fs = require('fs');
const ini = require('ini');
const axios = require('axios');

global.orgs = loadOrgs();

function ts_log_error(str) {
  console.log(new Date(), str);
}

function handleError(res, message, error){
    var errorObject = {
        message: message,
        error: error
    };
    res.status(404);
    res.send(errorObject);
}

function loadOrgs() {
  let orgs = [];
  const dir = '/var/data/';
  let regExp = new RegExp('^local\.prop\.(.*)');
  fs.readdir(dir, (err, files) => {
    if (err) {
        throw err;
    }

    files.forEach(file => {
      console.log('file:', file);
      let matches = file.match(regExp);
      if (matches) {
        let org = matches[1]
        console.log('org:', org);
        let desc = getOrgInfo(org, 'Description');
        console.log('desc:', desc);

        orgs.push( {
            org: org,
            desc: desc
          }
        );
      }
    });
  });

  return orgs;
}

function getApiKey(org) {
  return getOrgInfo(org, 'ALMA_APIKEY');
}

function getOrgInfo(org, name) {
  var propPath = '/var/data/local.prop.' + org;
  if(! fs.existsSync(propPath)) {
    ts_log_error('File not found: ' + propPath);
    return "";
  }

  const ini_data = ini.parse(fs.readFileSync(propPath, 'utf-8'))
  return ini_data[name];
}

// Constants
const PORT = 80;
const HOST = '0.0.0.0';

const app = express();
app.set("view engine", "pug");


app.get('/org/:org/redirect.js*', async (req, res) => {
  var APIKEY = getApiKey(req.params.org);
  console.log('APIKEY = ' + APIKEY);

  let qs = {
    'apikey': APIKEY
  }
  for(var k in req.query) {
    if (k == "apipath") continue;
    qs[k] = req.query[k];
  }
  try {
    let ret = await axios.get(req.query.apipath,
      {
        params: qs,
        headers: {
          'Accept': 'application/json',
        }
      }
    )
    res.json(ret.data);
    
  } catch (error) {
    ts_log_error(error);
    handleError(res, error.message);
  }

});

app.get('/org/:org/*', async (req, res) => {
  var url = require('url').parse(req.url);
  var urlPath = url.pathname;
  console.log('');
  console.log('Got url 1: ' + urlPath);

  var org = req.params.org;
  var oldUrl = urlPath;
  console.log('Old url string: ' + oldUrl);
  let regExp = new RegExp('^/org/' + org + '[/]*');
  let newUrl = oldUrl.replace(regExp, '');
  console.log('New url string: ' + newUrl);
  urlPath = newUrl;

  console.log('Got url: ' + urlPath);
  console.log('sendFile: ' + __dirname + "/" + urlPath);
  res.sendFile( __dirname + "/" + urlPath );
});

app.get('/*', async (req, res) => {
  console.log('orgs:', orgs);
  //res.sendFile( __dirname + "/select.html" );
  res.render('select', { orgs: orgs });
});

app.listen(PORT, HOST);
console.log(`Running on http://${HOST}:${PORT}`);
