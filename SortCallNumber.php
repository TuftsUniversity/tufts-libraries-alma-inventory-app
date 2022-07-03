<?php

$callnumbers = array(
'230.2 B277e 2015',
'231.09 H967o',
'232 M934s',
'232.09 L975c 2016',
'233.5 R725s',
'233.5 V431i',
'233.5 Y21i 2004'
);

for ($i=0; $i<sizeof($callnumbers); $i++) {
  print('before-->' . $callnumbers[$i] . '<--');
  print("\n");
  $normlc = normalizeDewey($callnumbers[$i]);
  print(' after-->' . $normlc . '<--');
  print("\n");
}


//an adaptation of Koha's Dewey sort routine
//GPL info goes here
		//problem call numbers
		/*
		709.04 M453
	  704.94978 S727
		759.06 E96
		759.1H766
		759.1N
		*/
		//759.06 E96 should display as 759_060000000000000_E96
		//$callNum = '759.06 E96';

		/*********************************************************************
		 * SortDeweyObject  Takes in two Obects contaning Dewey Call # elements
     * defined as call_number, normalizes, then sorts them
     * Can use usort or uasort to sort arrays based on the call number
		 *********************************************************************/
     /*********************************************************************
      * SortDewey Takes in two Dewey Call #'s, Normalizes, then sorts them
      * Can use usort or uasort to sort arrays based on the call number
      *********************************************************************/
     function SortDewey($right, $left)
     {
         $right = normalizeDewey($right);
         $left = normalizeDewey($left);
         return (strcmp($right, $left));
     } // end SortLC
     /*********************************************************************/

		function SortDeweyObject($right, $left)
		{
		    $right = normalizeDewey($right["call_number"]);
		    $left = normalizeDewey($left["call_number"]);
		    return (strcmp($right, $left));
		} // end SortLC

	function normalizeDewey($callNum){
        //Insert ! when lowercase letter comes after number
        $init = preg_replace('/([0-9])(?=[a-z])/','$1!', $callNum);
		//make all characters lowercase... sort works better this way for dewey...
		$init = strtolower($init);
		//get rid of leading whitespace
		$init = preg_replace('/^\s+/', '', $init);
		//get rid of extra whitespace at end of string
		$init = preg_replace('/\s+$/', '', $init);
		//get rid of &nbsp; at end of string
		$init = preg_replace('/\&/', '', $init);
	    //remove any slashes
		$init = preg_replace('/\//', '', $init);
		//remove any backslashes
		$init = stripslashes($init);
		// replace newline characters
		$init = preg_replace('/\n/','', $init);

		//set digit group count
		$digit_group_count = 0;
		//declare first digit group index variable
		$first_digit_group_idx;

		//split string into tokens by . or space
		$tokens = preg_split( '/\.|\s+/', $init);

		//loop through the tokens
		for($i=0;$i<sizeof($tokens);$i++){
			//if the token begins and ends with digits
			if(preg_match("/^\d+$/", $tokens[$i])){
				//increment the number of digit groups
				$digit_group_count++;
				//if it's the first one, store its index in first_digit_group_idx
				if (1 == $digit_group_count) {
                $first_digit_group_idx = $i;
            }
        //if there is a second group of digits, expand it to 15 places, adding 0s
        if (2 == $digit_group_count) {
            if ($i - $first_digit_group_idx == 1) {
                    $tokens[$i] = str_pad($tokens[$i], 15, "0", STR_PAD_RIGHT);
                    //$tokens[$i] =~ tr/ /0/;
                } else {
                $tokens[$first_digit_group_idx] .= '_000000000000000';
              }
            }
			}

		}

		if (1 == $digit_group_count) {
        $tokens[$first_digit_group_idx] .= '_000000000000000';
    }

    $key = implode("_", $tokens);
		return $key;
	}

?>
