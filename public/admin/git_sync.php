<?php
// pull latest code from git origin

echo "<pre>";
// check if we can run commands
$disabled = explode(',', ini_get('disable_functions')); 
//print_r($disabled);
print exec('which bash');


function bv_git_sync($branch, $path){

	echo "
	Branch: $branch || Path: $path || Stashing server changes, if any:
	";
	passthru("cd $path; git stash save 'server state - $branch ".date(DATE_RFC822)."' ", $ret);
	echo $ret;

	echo "
	Sync Branch $branch: Pulling from GitHub:
	";
	passthru("cd $path ; git pull origin $branch", $ret);
	echo $ret;

//	echo "
//	Branch $branch: Setting permissions:
//	";
//  passthru("cd $path ; chown -R www-data . ; chgrp -R mini . ; chmod -R 770 .");
    
	echo "

	DONE!
	";

}


bv_git_sync("master", "../..");
