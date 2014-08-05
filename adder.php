<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require dirname(__FILE__) . '/index.local.php';
require dirname(__FILE__) . '/dibi.min.php';

if(!isset($_SERVER['PATH_INFO']) || $_SERVER['PATH_INFO'] == '/')
{
	$year = date('Y');
	$month = date('m');
	header("Location: ".$baseurl."adder.php/".$year."/".$month);
	exit();
}

list($year, $month) = explode('/', ltrim($_SERVER['PATH_INFO'], '/'));
$firstDayThisMonth = $year."-".$month."-01";
$firstDayNextMonth  = date('Y-m-d', strtotime('+1 month', strtotime($firstDayThisMonth)));

$months = array("Leden", "Únor", "Březen", "Duben", "Květen", "Červen", "Červenec", "Srpen", "Září", "Říjen", "Listopad", "Prosinec");

function set_date()
{
	if(isset($_SESSION['date']))
		return $_SESSION['date'];
	else
		return date('Y-m-d');
}

dibi::connect($dbOptions);

session_start();

/************************************* ZPRACOVANI NOVE POLOZKY ****************************************/ 
$submitted = isset($_POST['submit']);
if($submitted)
{
	//zdlouhave pridavani do db
	dibi::query("
		INSERT INTO `polozka` (`nazev`, `datum_zakoupeni`, `jednotkova_cena`, `pocet_kusu`, `jednotka`, `cena`, `zdroj`,
							   `datum_vlozeni`, `ucel`, `misto_nakupu`, `je_rocni`)
		VALUES (%s, %d, %f, %f, %s, %f, %s, %d, %i, %i, %b)
		", $_POST['name'], $_POST['datum'], $_POST['peritem'], $_POST['amount'], $_POST['unit'], $_POST['total'], $_POST['source'], time(), 
		   $_POST['ucel'], $_POST['lokace'], isset($_POST['rocni']), "
	");
	$itemId = dibi::getInsertId();
	
	if (isset($_POST['tag']) && count($_POST['tag']) > 0)
	{
		dibi::query("
			INSERT INTO `polozka_tag` (`polozka`, `tag`)
			SELECT %i", $itemId, ", `id`
			FROM `tag`
			WHERE `nazev` IN %l", $_POST['tag'], "
		");
	}
	
	$_SESSION['date'] = $_POST['datum'];
}


/****************************************** STATISTIKA ***************************************************/

$categories = dibi::query("SELECT `nazev`, `id_kategorie` FROM `tag`")->fetchAssoc('id_kategorie,#');
$use = dibi::query("SELECT `id`, `nazev` FROM `ucel`")->fetchAssoc('id');
$location = dibi::query("SELECT `id`, `nazev` FROM `misto`")->fetchAssoc('id');

$sources = array('lenka', 'david', 'spolecne');
$categorizedExpencesMonthly = array();
$categorizedExpencesYearly = array();
foreach ($sources as $source)
{
	$categorizedExpencesMonthly[$source] = dibi::query("
		SELECT `ucel`.`nazev` as `nazev`, SUM(`polozka`.`cena`) as `suma` FROM `polozka`
		JOIN `ucel` ON `polozka`.`ucel` = `ucel`.`id`
		WHERE `polozka`.`zdroj` = %s", $source, " 
		AND %d", $firstDayThisMonth, " <= `polozka`.`datum_zakoupeni` AND  `polozka`.`datum_zakoupeni` < %d", $firstDayNextMonth, "
		AND `polozka`.`je_rocni` = 0
		GROUP BY `polozka`.`ucel`")->fetchPairs('nazev', 'suma');
	$categorizedExpencesYearly[$source] = dibi::query("
		SELECT `ucel`.`nazev` as `nazev`, SUM(`polozka`.`cena`) as `suma` FROM `polozka`
		JOIN `ucel` ON `polozka`.`ucel` = `ucel`.`id`
		WHERE `polozka`.`zdroj` = %s", $source, " 
		AND `polozka`.`je_rocni` = 1
		GROUP BY `polozka`.`ucel`")->fetchPairs('nazev', 'suma');
}

$limit = count($use);
$lastAddedItems = dibi::query("(
	SELECT *
	FROM `polozka`
	ORDER BY `id` DESC
	LIMIT %i", $limit ,"
) ORDER BY `id` ASC")->fetchAssoc('id');
?>

<html>
<head>
	<title>Pridat polozky nakupu</title>
	<meta charset="utf-8" />
	<link rel="stylesheet" href="<?php echo $baseurl; ?>style/style.css">
	<script type="text/javascript" src="<?php echo $baseurl; ?>script/cookies.js"></script>
	<script type="text/javascript">
	window.onload = function()
	{
		var element = document.getElementsByName("ucel")[0];
		var block = document.getElementById("category2");
		element.onchange = function(){
			if(element.selectedIndex == 1){
				block.style.display = "block";
			}
			else {
				block.style.display = "none";
			}
		};
		element.onchange();
	}
	function fillDate() {
		if(navigator.cookieEnabled) {
			var lastShoppingDate = readCookie("lastshoppingdate");
			document.getElementById("datum").value = lastShoppingDate;
		}
	}
	function updateTotal() {
		var perItem = parseFloat(document.getElementById("peritem").value);
		var amount = parseFloat(document.getElementById("amount").value);
		if(isNaN(perItem) || isNaN(amount))
			return;
		document.getElementById("total").value = (perItem * amount).toFixed(2);
	}
	function updatePerItem() {
		var amount = parseFloat(document.getElementById("amount").value);
		var total = parseFloat(document.getElementById("total").value);
		if(isNaN(amount) || isNaN(total))
			return;
		document.getElementById("peritem").value = (total / amount).toFixed(2);
	}
	</script>
</head>
<body onload="fillDate();">

	<div class="nav">
	<table>
		<tr>
			<td> <<< Předchozí měsíc </td>
			<td> Aktualně prohlížíte měsíc: <?php echo $months[$month - 1]; ?></td>
			<td> Další měsíc >>> </td>
		</tr>
	</table>
	</div>
	
	<div>
	<table>
		<thead>
				<th></th>
				<th>Lenička</th>
				<th>Davídek</th>
				<th>Společné</th>
		</thead>
		<tbody>
			<?php foreach($use as $u) { ?>
				<tr>
				<th><?php echo $u->nazev; ?></th>
				<?php foreach($sources as $source) { ?>
					<td><?php echo (@$categorizedExpencesMonthly[$source][$u->nazev] ?: 0); ?></td>
				<?php } ?>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	</div>
	
	
	<div>
	<table class="lastitems">
		<thead>
			<th colspan="3">Poslední přidané položky:</th>
		</thead>
		<tbody>
		<?php foreach($lastAddedItems as $id => $item) {?>
			<tr class="<?php echo $item->zdroj;?>">
			<!--
<?php foreach($item as $key => $value) {?>
				<tr>
					<?php echo $value; ?>
				</tr>
			<?php } ?>
 -->
 				<td class="cell"><?php echo $item->id;?></td>
 				<td class="cell"><?php echo date('d.m.Y', strtotime($item->datum_zakoupeni));?></td>
 				<td class="cell"><?php echo $item->nazev;?></td>
 				<td class="cell"><?php echo $item->pocet_kusu;?></td>
 				<td class="cell"><?php echo $item->jednotka;?></td>
 				<td class="cell"><?php echo $item->cena;?></td>
 				<td class="cell"><?php echo $item->zdroj;?></td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	<form action="" method="POST">
		Datum: <input type="date" id="datum" name="datum" value="<?php echo set_date(); ?>" placeholder="datum nákupu" required="required"/><br/>
		Položka: <input type="text" id="name" name="name" placeholder="řekni, co se pořídilo" required="required"/><br/>
		Cena:<br/>
		za kus * počet jednotek [jednotka] = celkově<br/>
		<input type="text" id="peritem" name="peritem" onchange="updateTotal();" placeholder="za kus" required="required" /> x
		<input type="text" id="amount" name="amount" value="1" onchange="updateTotal();" placeholder="počet jednotek" required="required" />
		[
		<select name="unit">
			<option value="ks">ks</option>
			<option value="kg">kg</option>
			<option value="l">l</option>
		</select>
		]
		=
		<input type="text" id="total" name="total" onchange="updatePerItem();" placeholder="celkem" />
		<select name="source" required="required">
			<option value=""></option>
			<option value="lenka">Uň (L)</option>
			<option value="david">Mimi (D)</option>
			<option value="spolecne">Koťátková rodina</option>
		</select><br/>
		
		<!--ucel-->
		<select name="ucel" required="required">
			<option value=""></option>
			<?php foreach($use as $u) { ?>
				<option value="<?php echo $u->id; ?>"><?php echo $u->nazev; ?></option>
			<?php } ?>
		</select>
		
		<!--lokace-->
		<select name="lokace" required="required">
			<option value=""></option>
			<?php foreach($location as $u) { ?>
				<option value="<?php echo $u->id; ?>"><?php echo $u->nazev; ?></option>
			<?php } ?>
		</select><br/>
		
		<input type="checkbox" name="rocni" value="1">Patri do rocniho vyuctovani<br/>
		
		<br/>
		<div class="tagy">
		<?php
		foreach($categories as $categoryId => $tags)
		{?>
			<div class="block" id="category<?php echo $categoryId; ?>" <?php if ($categoryId == 2) { echo 'style="display:none;"'; } ?>>
			<?php
			foreach($tags as $tag)
			{
				$aux = $tag->nazev;
		?>
				<div style="display: inline-block;"><input type="checkbox" name="tag[<?php echo $aux;?>]" value="<?php echo $aux;?>"/><?php echo $aux;?></div>
		<?php } ?>
			</div>
		<?php } ?>
		</div>
		<input type="submit" id="submit" name="submit" value="Add"/>
	</form>
	
	
	Here is gonna be reminder of our horrible spending habits.<br/>
	<div>
	<table>
		<thead>
				<th></th>
				<th>Lenička</th>
				<th>Davídek</th>
				<th>Společné</th>
		</thead>
		<tbody>
			<?php foreach($use as $u) { ?>
				<tr>
				<th><?php echo $u->nazev; ?></th>
				<?php foreach($sources as $source) { ?>
					<td><?php echo (@$categorizedExpencesYearly[$source][$u->nazev] ?: 0); ?></td>
				<?php } ?>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	</div>
	
	<div><a href="<?php echo $baseurl; ?>statistics.php">Statistika</a></div>
</body>
</html>
