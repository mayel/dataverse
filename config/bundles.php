<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\WebServerBundle\WebServerBundle::class => ['dev' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class => array(
        'router'      => array('annotations' => false),
        // 'request'     => array('converters' => true, 'auto_convert' => true),
        // 'view'        => array('annotations' => false),
        // 'cache'       => array('annotations' => false),
        // 'security'    => array('annotations' => false),
    ), //['all' => true], 
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Knp\Bundle\PaginatorBundle\KnpPaginatorBundle::class => ['all' => true],
];
