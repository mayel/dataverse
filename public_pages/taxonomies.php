<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<title><?=($taxonomy_name ? $taxonomy_name : 'Commons Taxonomies')?></title>

</head>

<body> 

	<h3><?=($taxonomy_name ? $taxonomy_name : 'Commons Taxonomies')?> <?=($_GET['tag_label'] ? ' > '.$_GET['tag_label'] : '')?></h3>

	<?php
    include_once("taxonomy_browser.php");
    ?>

</body>
</html>
