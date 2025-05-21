
function deweyCallClass() {
}

// deweyCallClass.returnNormLcCall(call_number)
// returns a "normalized" call number
deweyCallClass.prototype.returnNormLcCall = function(call_number) {
  //Insert ! when lowercase letter comes after number
  //var init = call_number.replace(/([0-9])(?=[a-z])/,'$1!');
  //Insert ! when any (case) letter comes after number
  var init = call_number.replace(/([0-9])(?=[a-zA-Z])/,'$1!');

  //make all characters lowercase... sort works better this way for dewey...
  init = init.toLowerCase();

  //get rid of leading whitespace
  init = init.replace(/^\s+/, '');

  //get rid of extra whitespace at end of string
  init = init.replace(/\s+$/, '');

  //get rid of &nbsp; at end of string
  init = init.replace(/\&/, '');

  //remove any slashes
  init = init.replace(/\//, '');

  //remove any backslashes
  init = init
    .replace(/\\(.?)/g, function (s, n1) {
      switch (n1) {
        case '\\':
          return '\\'
        case '0':
          return '\u0000'
        case '':
          return ''
        default:
          return n1
      }
    });


  //replace newline characters
  init = init.replace(/\n/,'');

  //set digit group count
  var digit_group_count = 0;

  //declare first digit group index variable
  var first_digit_group_idx;

  //split string into tokens by . or space
  var tokens = init.split(/\.|\s+/);

  //loop through the tokens
  for(var i=0; i<tokens.length; i++){

    //if the token begins and ends with digits
    if (tokens[i].match(/^\d+$/)) {

      //increment the number of digit groups
      digit_group_count++;

      //if it's the first one, store its index in first_digit_group_idx
      if (1 == digit_group_count) {
        first_digit_group_idx = i;
      }

      //if there is a second group of digits, expand it to 15 places, adding 0s
      if (2 == digit_group_count) {
        if (i - first_digit_group_idx == 1) {
          tokens[i] = tokens[i].padEnd(15, '0');
        } else {
          tokens[first_digit_group_idx] += '_000000000000000';
        }
      }
    }
  
  }

  if (1 == digit_group_count) {
    tokens[first_digit_group_idx] += '_000000000000000';
  }

  key = tokens.join('_');

  return key;
}

// deweyCallClass.localeCompare(b,a)
// replicates functionality of the normal compare function 
// so that it may be used in external sorting operations:
// 
// A negative number if the reference string (a) occurs before the 
// given string (b); 
// positive if the reference string (a) occurs after 
// the compare string (b); 
// 0 if they are equivalent.
deweyCallClass.prototype.localeCompare = function (a, b) {
  try {
    var a_norm = this.returnNormLcCall(a),
      b_norm = this.returnNormLcCall(b);
            
      return ( a_norm < b_norm ? -1 : (a_norm > b_norm ? 1 : 0) );
  }
  catch (err) {
    // console.log("error")
  }
}

// deweyCallClass.sortCallNumbers()
// takes an array of call numbers and returns a sorted array of call 
// numbers in their original format.
// Using something like the following works to sort as well:
// var loc = new deweyCallClass();
// var sorted_array = loc.unsorted_callnumber_array.sort(function(a,b) {return loc.localeCompare(a,b)});
deweyCallClass.prototype.sortCallNumbers = function (callnumbers) {
  // also bind the scope of this to the sort function to be able to call
  // this.localeCompare
  var sorted = callnumbers.sort(function (a,b) {
    return this.localeCompare(a,b);
  }.bind(this));
  
  return sorted;
}

