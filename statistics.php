<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require dirname(__FILE__) . '/index.local.php';
require dirname(__FILE__) . '/dibi.min.php';

dibi::connect($dbOptions);


//statistika podle tagu
$sumsCat = dibi::query("
	SELECT `tag`.`nazev`, SUM(`polozka`.`cena`) AS `suma`
	FROM `polozka`
	JOIN `polozka_tag` ON `polozka`.`id` = `polozka_tag`.`polozka`
	JOIN `tag` ON `polozka_tag`.`tag` = `tag`.`id`
	GROUP BY `polozka_tag`.`tag`
")->fetchPairs('nazev', 'suma');

//statistika podle polozek, ktere nas zajimaji
$items = file('vars/hp.txt');
$numitems = count($items);
$sums = array_combine($items, array_fill(0, $numitems, 0));
//resp. chci value ke klici, ktere uz tam mam.

$sumsItems = dibi::query("
	SELECT `polozka`.`nazev`, SUM(`polozka`.`cena`) AS `cena`
	FROM `polozka`
	GROUP BY `polozka`.`nazev`
")->fetchPairs('nazev', 'cena');

foreach($sumsItems as $item => $amount)
{
	foreach($items as $i)
	{
		if(false !== stripos($i, $item))
		{
			$sums[$i] += $amount; //mimi, proc mi to tam da posledni polozku misto souctu?
		}
	}
}
//okurky nestaly evidentne az tak moc :D
?>
<html>
<head>
	<title>Stats</title>
</head>
<body>
	<h1>By tag</h1>
	<?php
	foreach($sumsCat as $tag => $sum)
		echo $tag.": ".$sum."<br/>";
	?>
	
	<h1>By tag per week</h1>
	<?php
	foreach($sumsCat as $tag => $sum)
		echo $tag.": ".($sum/4)."<br/>";
	?>

	<h1>Custom items</h1>
	<?php
	foreach($sums as $key => $value)
		echo $key.": ".$value."<br/>";
	?>
	
	<h1>By name</h1>
	<?php
	foreach($sumsItems as $key => $value)
		echo $key.": ".$value."<br/>";
	?>
	
	<h1>Per week</h1>
	<?php
	foreach($sumsItems as $key => $value)
		echo $key.": ".($value/4)."<br/>";
	?>
</body>
</html>