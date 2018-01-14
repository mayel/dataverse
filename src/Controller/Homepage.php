<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;

class Homepage extends App
{
    /**
    * @Route("/", name="homepage")
    */
    public function index()
    {
        return $this->render('general/home.html.twig', array(
            'name' => 'friend',
        ));
    }
}
