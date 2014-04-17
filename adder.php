<?php

function set_date()
{
	if(isset($_SESSION['date']))
		return $_SESSION['date'];
	else
		return date('Y-m-d');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require dirname(__FILE__) . '/index.local.php';
require dirname(__FILE__) . '/dibi.min.php';

dibi::connect($dbOptions);

session_start();

$categories = dibi::query("SELECT `nazev`, `id_kategorie` FROM `tag`")->fetchAssoc('id_kategorie,#');

$submitted = isset($_POST['submit']);
if($submitted)
{
	//zdlouhave pridavani do db
	dibi::query("
		INSERT INTO `polozka` (`nazev`, `datum_zakoupeni`, `jednotkova_cena`, `pocet_kusu`, `jednotka`, `cena`, `zdroj`, `datum_vlozeni`)
		VALUES (%s, %d, %f, %f, %s, %f, %s, %d)
		", $_POST['name'], $_POST['datum'], $_POST['peritem'], $_POST['amount'], $_POST['unit'], $_POST['total'], $_POST['source'], time(), "
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
	
	/*foreach($_POST['tag'] as $tag)
	{
		$tagId = dibi::fetchSingle("SELECT `id` FROM `tag` WHERE `nazev` = %s", $tag);
		dibi::query("INSERT INTO `polozka_tag` VALUES (%i, %i)", $itemId, $tagId);
	}*/
	$_SESSION['date'] = $_POST['datum'];
}

//stat part
$firstDayThisMonth = date('Y-m-01');
$lastDayThisMonth  = date('Y-m-t');

$sumsRent = dibi::query("
	SELECT `polozka`.`zdroj`, SUM(`polozka`.`cena`) AS `suma`
	FROM `polozka`
	JOIN `polozka_tag` ON `polozka`.`id` = `polozka_tag`.`polozka`
	JOIN `tag` ON `polozka_tag`.`tag` = `tag`.`id`
	WHERE `tag`.`nazev` = 'ubytovani'
	GROUP BY `polozka`.`zdroj`
")->fetchPairs('zdroj', 'suma');
$sumsExtra = dibi::query("
	SELECT `polozka`.`zdroj`, SUM(`polozka`.`cena`) AS `suma`
	FROM `polozka`
	JOIN `polozka_tag` ON `polozka`.`id` = `polozka_tag`.`polozka`
	JOIN `tag` ON `polozka_tag`.`tag` = `tag`.`id`
	WHERE `tag`.`nazev` = 'mimoradne (rocni)'
	GROUP BY `polozka`.`zdroj`
")->fetchPairs('zdroj', 'suma');
$sums = dibi::query("
	SELECT `polozka`.`zdroj`, SUM(`polozka`.`cena`) AS `suma`
	FROM `polozka`
	GROUP BY `polozka`.`zdroj`
")->fetchPairs('zdroj', 'suma');


$limits = array('david' => 5500.00, 'lenka' => 5500.00);
$sources = array('david', 'lenka');

$totalDays = date('t');
$elapsedDays = date('j');

$totals = array();
$lefts  = array();
$sumsWoExtra    = array();
$averagesWoRent = array();
$limitsWoRent   = array();
$totalsWoRent   = array();
$expectedTotalsWoRent = array();
$expectedLeftsWoRent  = array();
foreach ($sources as $source)
{
	$sumsWoExtra[$source]    = (@$sums[$source] ?: 0) - (@$sumsExtra[$source] ?: 0);
	$totals[$source]         = (@$sumsWoExtra[$source] ?: 0) + (@$sums['spolecne'] ?: 0) / 2;
	$totalsWoRent[$source]   = $totals[$source] - (@$sumsRent[$source] ?: 0);
	$limitsWoRent[$source]   = $limits[$source] - (@$sumsRent[$source] ?: 0);
	$lefts[$source]          = $limits[$source] - $totals[$source];
	$averagesWoRent[$source] = $totalsWoRent[$source] / $elapsedDays;
	$expectedTotalsWoRent[$source] = $averagesWoRent[$source] * $totalDays;
	$expectedLeftsWoRent[$source]  = $expectedTotalsWoRent[$source] - $totalsWoRent[$source];
}

//last added items
$limit = 6;
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
	<link rel="stylesheet" href="style/style.css">
	<script type="text/javascript" src="script/cookies.js"></script>
	<script type="text/javascript">
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
	<div>
	<table>
		<thead>
			<tr>
				<th></th>
				<th>Lenička</th>
				<th>Davídek</th>
			</tr>
		</thead>
		<tbody>
			<tr class="ordinary">
				<th class="cell">Utraceno celkem</th>
				<td class="cell money"><?php echo number_format($totals['lenka'], 2, '.', ' ');?></td>
				<td class="cell money"><?php echo number_format($totals['david'], 2, '.', ' ');?></td>
			</tr>
			<tr class="spent">
				<th class="cell">Utraceno MU</th>
				<td class="cell money"><?php echo number_format($totalsWoRent['lenka'], 2, '.', ' ');?></td>
				<td class="cell money"><?php echo number_format($totalsWoRent['david'], 2, '.', ' ');?></td>
			</tr>
			<tr class="left">
				<th class="cell">Zbývá</th>
				<td class="cell money"><?php echo number_format($lefts['lenka'], 2, '.', ' ');?></td>
				<td class="cell money"><?php echo number_format($lefts['david'], 2, '.', ' ');?></td>
			</tr>
			<tr class="ordinary">
				<th class="cell">Útrata denně MU</th>
				<td class="cell money"><?php echo number_format($averagesWoRent['lenka'], 2, '.', ' ');?></td>
				<td class="cell money"><?php echo number_format($averagesWoRent['david'], 2, '.', ' ');?></td>
			</tr>
			<tr class="ordinary">
				<th class="cell">Očekávané výdaje za tento měsíc MU</th>
				<td class="cell money"><?php echo number_format($expectedTotalsWoRent['lenka'], 2, '.', ' ');?></td>
				<td class="cell money"><?php echo number_format($expectedTotalsWoRent['david'], 2, '.', ' ');?></td>
			</tr>
			<tr class="ordinary">
				<th class="cell">Očekávané výdaje do konce měsíce MU</th>
				<td class="cell money"><?php echo number_format($expectedLeftsWoRent['lenka'], 2, '.', ' ');?></td>
				<td class="cell money"><?php echo number_format($expectedLeftsWoRent['david'], 2, '.', ' ');?></td>
			</tr>
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
	
	<form action="adder.php" method="POST">
		Datum: <input type="date" id="datum" name="datum" value="<?php echo set_date(); ?>" placeholder="datum nákupu" /><br/>
		Položka: <input type="text" id="name" name="name" placeholder="řekni, co se pořídilo" /><br/>
		Cena:<br/>
		za kus * počet jednotek [jednotka] = celkově<br/>
		<input type="text" id="peritem" name="peritem" onchange="updateTotal();" placeholder="za kus" /> x
		<input type="text" id="amount" name="amount" value="1" onchange="updateTotal();" placeholder="počet jednotek" />
		[
		<select name="unit">
			<option value="ks">ks</option>
			<option value="kg">kg</option>
			<option value="l">l</option>
		</select>
		]
		=
		<input type="text" id="total" name="total" onchange="updatePerItem();" placeholder="celkem" />
		<select name="source">
			<option value="lenka">Uň (L)</option>
			<option value="david">Mimi (D)</option>
			<option value="spolecne">Koťátková rodina</option>
		</select><br/><br/>
		<div class="tagy">
		<?php
		foreach($categories as $categoryId => $tags)
		{
			foreach($tags as $tag)
			{
				$aux = $tag->nazev;
		?>
				<input type="checkbox" name="tag[]" value="<?php echo $aux;?>"/><?php echo $aux;?>
		<?php } ?>
			<br/><br/>
		<?php } ?>
		</div>
		<input type="submit" id="submit" name="submit" value="Add"/>
	</form>
</body>
</html>
