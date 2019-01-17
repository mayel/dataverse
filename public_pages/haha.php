<?php
$taxonomy_name = "HAHA Academy Taxonomy";
$taxonomy_default = 1;
$tag_default = 1;

if(isset($_GET['iframe'])) include_once("taxonomy_browser.php");
elseif(isset($_GET['embed'])) include_once("taxonomy_embed.php");
else include_once("taxonomies.php");
