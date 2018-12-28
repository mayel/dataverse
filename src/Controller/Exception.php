<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Debug\Exception\FlattenException;

class Exception extends Controller
{
	function showException(Request $request, FlattenException $exception) {

		if($exception->getStatusCode()==404){
			$inc = __DIR__.'/../../public_pages/'.trim($request->getPathInfo(),'/').'.php';
//			echo $inc;
			if(!file_exists($inc) || !require_once($inc)) $error = '404 Not Found';

		} 
		
		elseif($exception->getPrevious()->getCode()==23000) echo " Oops, it looks like that data was already saved! <p><small>".htmlentities($exception->getMessage());

		else $error = htmlentities($exception->getMessage())." / file: ".$exception->getFile() ." / line: ".$exception->getLine();
		
		if($error) echo 'Oops, there was an error. Please send these details to the site admin: '.$error;

		exit();
	}
}
