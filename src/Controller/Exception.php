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
		} else $error = $exception->getMessage();
		if($error) echo 'Error: '.$error;
		exit();
	}
}
