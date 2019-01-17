<?php
$taxonomy_name = "Needs & Resources Taxonomy";
// $taxonomy_default = 2;
$tag_default = 5;

if(isset($_GET['iframe'])) include_once("taxonomy_browser.php");
elseif(isset($_GET['embed'])) include_once("taxonomy_embed.php");
else include_once("taxonomies.php");
